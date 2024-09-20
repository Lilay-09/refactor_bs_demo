<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DailyStock;
use App\Models\MovementType;
use App\Models\PoPaymentSlip;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderExpense;
use App\Models\PurchaseOrderExpenseDetail;
use App\Models\PurchaseOrderItem;
use App\Models\ReceiptItem;
use App\Models\ReceivePo;
use App\Models\ReceivePoItem;
use App\Models\Stock;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentDetail;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Services\AuthService;
use App\Services\GeneralSettingService;
use App\Services\UserService;
use DataResponse;
use Exception;
use Helper;
use Illuminate\Http\Request;
use DB;
use Log;

class StockManagementController extends Controller
{

    protected $stockOperator = [
        'receive_qty' => '+',
        'transfer_in_qty' => '+',
        'transfer_out_qty' => '-',
        'sold_qty' => '-',
        'return_qty' => '+',
        'missing_qty' => '-',
        'take_out_qty' => '-',
        'donation_qty' => '-'
    ];

    protected $movementTypes = [
        'receive_qty' => '+',
        'transfer_in_qty' => '+',
        'transfer_out_qty' => '-',
        'sold_qty' => '-',
        'return_qty' => '+',
        'missing_qty' => '-',
        'take_out_qty' => '-',
        'donation_qty' => '-'
    ];


    function getStockMovementTypesArray(){
        return MovementType::all()->pluck('name')->toArray();
    }

    //** Purchase Order */
    private function purchaseOrderValidation(Request $req){
        return validator($req->all(),[
            'vendor_id' => 'required|int|exists:vendors,id',
            'issue_date' => 'required|date',
            'remarks' => 'nullable|string|max:250',
            'tax' => 'nullable|numeric',
            'cash' => 'nullable|numeric',
            'bank_id' => 'nullable|int|exists:banks,id',
            'cash_photo' => 'nullable|string',
            'bank_photo' => 'nullable|string',
            'bank_amount' => 'nullable|numeric',
            'bank_number' => 'nullable|string|max:30',
            'pmt_description' => 'nullable|string|max:250',
            'expect_arrival_date' => 'nullable|date',
            'discount_amount' => 'nullable|numeric',
            'discount_type' => 'nullable|in:$,%',
            'order_items' => 'required|array'
        ]);
    }
    /**
     * Summary of createPurchaseOrder
     * @return void
     *
     * create purchase order
     */
    public function createPurchaseOrder(Request $req){
        $imgDir = 'purchase_expense';
        $user = UserService::getAuthUser();
        if($user->error) return ApiResponse::flex($user);
        $userId = $user->id;
        $validate = $this->purchaseOrderValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first(),$validate->errors());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $userId;
        $inputs['update_uid'] = $userId;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        //** auto approve */
        $inputs['approve_uid'] = $userId;
        $inputs['approve_date'] = now();
        $inputs['status_id'] = 2;
        //------
        $cash = $inputs['cash'] ?? 0;
        $bankId = $inputs['bank_id'] ?? 0;
        $cashPhoto = $inputs['cash_photo'] ?? null;
        $bankPhoto = $inputs['bank_photo'] ?? null;
        $bankNumber = $inputs['bank_number'] ?? null;
        $pmtDescription = $inputs['pmt_description'] ?? null;
        $bankAmount = $inputs['bank_amount'] ?? null;
        $orderItems = $inputs['order_items'];

        $receiveAmount = $cash + $bankAmount;

        unset($inputs['order_items'],$inputs['pmt_description'],$inputs['bank_photo'],$inputs['cash_photo'],$inputs['bank_id'],$inputs['photo'],$inputs['bank_amount'],$inputs['bank_number']);


        $discount = $inputs['discount_amount'] ?? 0;
        $discountType = $inputs['discount_type'] ?? '%';
        $tax = $inputs['tax'] ?? 0;
        if($discount < 0) return ApiResponse::JsonResult(null,false,'Discount must be positive number');
        $discountInfo = (object)[
            'amount' => $discount,
            'type' => $discountType
        ];

        if($bankId && !$bankAmount){
            return ApiResponse::ValidateFail('Bank amount is required!');
        }

        DB::beginTransaction();
        try{
            $create = PurchaseOrder::create($inputs);
            if($create){
                //** add po code */
                $poCode = 'PO-'.str_pad($create->id, 8, '0', STR_PAD_LEFT);
                PurchaseOrder::find($create->id)->update([
                    'po_code' => $poCode
                ]);
                $purchaseId = $create->id;
                $mergedItems = $this->mergerOrderItems($orderItems,$purchaseId,$discountInfo);
                if($mergedItems->status_code == 422) return ApiResponse::ValidateFail($mergedItems->message);
                $totalAmt = $mergedItems->data->total + ($mergedItems->data->total * $tax / 100);
                $totalDue = $mergedItems->data->total_due + ($mergedItems->data->total_due * $tax / 100);
                $totalDue = round($totalDue,2);
                $items = $mergedItems->data->items;
                $createItems = $this->createOrUpdatePurchaseOrderItems($items,$purchaseId,$user);
                if($createItems->error){
                    if($createItems->status_code == 422) return ApiResponse::ValidateFail($createItems->message);
                    if($createItems->status_code == 409) return ApiResponse::Duplicated($createItems->message);
                    if($createItems->status_code == 500) return ApiResponse::Error($createItems->message);
                }
                $pmt_status_id = 1;
                if($receiveAmount > $totalDue) return ApiResponse::ValidateFail('Your input amount is exceeded the payable amount($'.$totalDue.')');
                if($receiveAmount == $totalDue) $pmt_status_id = 3;
                else if($receiveAmount < $totalDue && $receiveAmount > 0) $pmt_status_id = 2;
                $expense = PurchaseOrderExpense::create([
                    'purchase_order_id' => $purchaseId,
                    'amount' => $receiveAmount,
                    'pmt_status_id' => $pmt_status_id,
                    'create_uid' => $userId,
                    'update_uid' => $userId,
                    'branch_id' => $user->branch_id,
                    'company_id' => $user->company_id,
                    'description' => $pmtDescription
                ]);
                if(!$expense) return ApiResponse::ValidateFail('Fail to add payment');
                if($cash){
                    $photoFile = Helper::base64ToImageFile($cashPhoto,$user->company_id,$imgDir);
                    $cash = PurchaseOrderExpenseDetail::create([
                        'purchase_order_expense_id' => $expense->id,
                        'payment_method' => 'Cash',
                        'photo_file_name' => $photoFile,
                        'amount' => $cash
                    ]);
                    if(!$cash){
                        Helper::deleteImageFile($photoFile,$user->company_id,$imgDir);
                        return ApiResponse::Error('Fail to add cash');
                    }
                }

                if($bankId){
                    $photoFile = Helper::base64ToImageFile($bankPhoto,$user->company_id,$imgDir);
                    $bank = PurchaseOrderExpenseDetail::create([
                        'purchase_order_expense_id' => $expense->id,
                        'payment_method' => 'Bank',
                        'photo_file_name' => $photoFile,
                        'amount' => $bankAmount,
                        'bank_number' => $bankNumber
                    ]);
                    if(!$bank){
                        Helper::deleteImageFile($photoFile,$user->company_id,$imgDir);
                        return ApiResponse::Error('Fail to add cash');
                    }
                }
                // * Set total price of added items
                PurchaseOrder::find($purchaseId)->update([
                    'total_amount' => $totalAmt,
                    'paid_amount' => $receiveAmount,
                    'due_amount' => $totalDue
                ]);
            }

            DB::commit();
            return ApiResponse::JsonResult(null,false,'Created');
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getTraceAsString());
            Log::error($e->getMessage());
            return ApiResponse::Error('Fail to purchase');
        }
    }


    public function receivePurchaseOrder(Request $req,$id=null){
        $id = $id ? $id : $req->id;
        $imgDir = 'purchase_expense';
        $user = UserService::getAuthUser();
        if($user->error) return ApiResponse::flex($user);
        $userId = $user->id;
        $branchId = $user->branch_id;
        $companyId = $user->company_id;
        $purchaseOrder = PurchaseOrder::where('branch_id',$branchId)->find($id);
        if(!$purchaseOrder) return ApiResponse::NotFound('Purchase order not found');
        if($purchaseOrder->status_id == 6) return ApiResponse::Duplicated('All purchase order items have aready received.');
        if($purchaseOrder->status_id == 1) return ApiResponse::Duplicated('Please make sure your purchase is approved.');
        $validate = validator($req->all(),[
            'receive_date' => 'nullable|date',
            'stock_location_id' => 'required|int|exists:stock_locations,id',
            'order_items' => 'required|array',
            'tax' => 'nullable|numeric',
            'cash' => 'nullable|numeric',
            'bank_id' => 'nullable|int|exists:banks,id',
            'cash_photo' => 'nullable|string',
            'bank_photo' => 'nullable|string',
            'bank_amount' => 'nullable|numeric',
            'bank_number' => 'nullable|string|max:30',
            'pmt_description' => 'nullable|string|max:250',
        ],[
            'stock_location_id.required' => 'Please select warehouse',
            'stock_location_id.exists' => 'Please choose valid warehouse'
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $orderItems = $inputs['order_items'];
        $inputs['receive_date'] = isset($inputs['receive_date']) ? $inputs['receive_date'] : now();
        $inputs['update_uid'] = $userId;
        $inputs['branch_id'] = $branchId;
        $inputs['company_id'] = $companyId;
        $tax = $inputs['tax'] ?? 0;
        $cash = $inputs['cash'] ?? 0;
        $bankId = $inputs['bank_id'] ?? 0;
        $cashPhoto = $inputs['cash_photo'] ?? null;
        $bankPhoto = $inputs['bank_photo'] ?? null;
        $bankNumber = $inputs['bank_number'] ?? null;
        $pmtDescription = $inputs['pmt_description'] ?? null;
        $bankAmount = $inputs['bank_amount'] ?? null;
        $pmt_status_id = 1;
        // $payAmount = $inputs['pay_amount'] ?? null;

        if($bankId && !$bankAmount){
            return ApiResponse::ValidateFail('Bank amount is required!');
        }

        $discountInfo = (object)[
            'amount' => $purchaseOrder->discount_amount,
            'type' => $purchaseOrder->discount_type
        ];
        // return $inputs;
        DB::beginTransaction();
        try{
            $receive = $this->receiveOrderItems($orderItems,$id,$inputs['stock_location_id'],$user,$discountInfo,$tax);
            if($receive->status_code == 422) return ApiResponse::ValidateFail($receive->message);
            $lastReceive = $purchaseOrder->paid_amount;
            $receiveAmount = round($cash + $bankAmount,2);
            $openAmt = abs($lastReceive - $purchaseOrder->due_amount);
            $newPayment = round($receiveAmount + $lastReceive,2);

            if($newPayment > $purchaseOrder->due_amount){
                return ApiResponse::ValidateFail('You input amount is exceeded the payable.open amount ($'.$openAmt.')');
            }
            if($purchaseOrder->due_amount == $newPayment){
                $pmt_status_id = 3; //** fully paid */
            }else if($newPayment > 0){
                $pmt_status_id = 2; //** partially paid */
            }

            $expense = PurchaseOrderExpense::where('purchase_order_id',$id)->first();
            if($expense){
                $lastExpense = $expense->amount;
                $nexExpAmt = $lastExpense + $receiveAmount;
                $expense->update([
                    'amount' => $nexExpAmt,
                    'description' => $pmtDescription,
                    'update_uid' => $userId,
                    'pmt_status_id' => $pmt_status_id,
                    'branch_id' => $user->branch_id,
                    'company_id' => $user->company_id,
                ]);
            }else{
                $expense = PurchaseOrderExpense::create([
                    'purchase_order_id' => $id,
                    'amount' => $receiveAmount,
                    'pmt_status_id' => $pmt_status_id,
                    'create_uid' => $userId,
                    'update_uid' => $userId,
                    'branch_id' => $user->branch_id,
                    'company_id' => $user->company_id,
                    'description' => $pmtDescription
                ]);
            }

            if(!$expense) return ApiResponse::ValidateFail('Fail to add payment');
            if($cash){
                $photoFile = Helper::base64ToImageFile($cashPhoto,$user->company_id,$imgDir);
                $cash = PurchaseOrderExpenseDetail::create([
                    'purchase_order_expense_id' => $expense->id,
                    'payment_method' => 'Cash',
                    'photo_file_name' => $photoFile,
                    'amount' => $cash
                ]);
                if(!$cash){
                    Helper::deleteImageFile($photoFile,$user->company_id,$imgDir);
                    return ApiResponse::Error('Fail to add cash');
                }
            }

            if($bankId){
                $photoFile = Helper::base64ToImageFile($bankPhoto,$user->company_id,$imgDir);
                $bank = PurchaseOrderExpenseDetail::create([
                    'purchase_order_expense_id' => $expense->id,
                    'payment_method' => 'Bank',
                    'photo_file_name' => $photoFile,
                    'amount' => $bankAmount,
                    'bank_number' => $bankNumber
                ]);
                if(!$bank){
                    Helper::deleteImageFile($photoFile,$user->company_id,$imgDir);
                    return ApiResponse::Error('Fail to add cash');
                }
            }

            $purchaseOrder->update([
                'paid_amount' => $newPayment,
            ]);
            $this->updatePurchaseStatus($id);

            DB::commit();
            return ApiResponse::JsonResult(null,false,'Received!');
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            return ApiResponse::Error('Fail to receive');
        }
    }

    function updatePurchaseStatus($purchaseId){
        $query = PurchaseOrderItem::where('purchase_id',$purchaseId);
        $count = $query->count();
        $rows = $query->get();
        $success = 0;
        foreach($rows as $row){
            if($row->qty == $row->received_qty){
                $success += 1;
            }
        }
        if($count == $success){
            PurchaseOrder::find($purchaseId)->update([
                'status_id' => 6
            ]);
        }else {
            PurchaseOrder::find($purchaseId)->update([
                'status_id' => 5 //** partially received */
            ]);
        }
    }


    private function createSkuCode($modelId,$categoryId,$condition,$branch_id,$variantId,$expirationDate=null){
        /**
         * @var mixed
         * format SKUcategory_model_conditionExpiration_date example SKU1_1_NEW_120241108 or SKU1_1_NEW_1 , expiration_date can be null
         * note* => expiration_date is optional if item has add it.
         */
        // $model = ProductModel::where('branch_id',$branchId)->find($modelId)->take(1)->value('name');
        // $category = Category::where('branch_id',$branchId)->find($categoryId)->take(1)->value('name');
        // $model = strtoupper(substr($model,0,3));
        // $category = strtoupper(substr($category,0,3));
        $condition = strtoupper(substr($condition,0,3));
        $sku = 'SKU'.$categoryId.'-'.$modelId.'-'.$condition.$variantId.'-'.$branch_id;//.'-'.$cost;
        if($expirationDate) {
            $expirationDate = date('Ymd',strtotime($expirationDate));
            return $sku.$expirationDate;
        }
        return $sku;
    }

    public function createPurchaseOrderExpense(Request $req){
        $validate = validator($req->all(),[
            'purchase_order_id' => 'required|int|exists:purchase_orders,id',
            'amount' => 'required|numeric',
            'vendor_id' => 'nullable|int|exists:vendors,id',
            'expense_type' => 'required|in:Goods,Shipping,Handling,Tax,Discount,Other',
            'expense_date' => 'nullable|date',
            'description' => 'nullable|string|max:250'
        ],[
            'expense_type.in' => 'Expense Type must be one of (Goods,Shipping,Handling,Tax,Discount,Other)'
        ]);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        if(!isset($inputs['description']) && $inputs['expense_type'] == 'Goods'){
            $inputs['description'] = 'Purchase Goods';
        }
        return $inputs;
    }


    private function receiveOrderItems($orderItems,$purchaseId,$stockLocationId,$user,$discountInfo,$tax=0){
        $branchId = $user->branch_id;
        $companyId = $user->company_id;
        $userId = $user->id;
        $skip_message = null;
        $amountDue = 0;
        $newReceive = 0;
        $overAllQty = 0;
        $receivePo = ReceivePo::create([
            'receive_uid' => $userId,
            'purchase_id' => $purchaseId,
            'company_id' => $companyId,
            'create_uid' => $userId,
            'update_uid' => $userId,
            'branch_id' => $branchId
        ]);
        $receiveNumber = 'RO'.substr(date('Y'),-2) . Helper::formatNumber($receivePo->id, 5);
        ReceivePo::find($receivePo->id)->update([
            'receive_number' => $receiveNumber
        ]);
        foreach($orderItems as $key=>$item){
            $item['purchase_id'] = $purchaseId;
            $req = new Request($item);
            $validate = validator($req->all(),[
                'purchase_item_id' => 'required|int|exists:purchase_order_items,id',
                'expiration_date' => 'nullable|date',
                'receive_qty' => 'nullable|numeric',
                'remarks' => 'nullable|string|max:200'
            ]);
            if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
            $inputs = $validate->validated();
            $id = $inputs['purchase_item_id'];
            $purchaseOrderItem = PurchaseOrderItem::where('purchase_id',$purchaseId)->where('branch_id',$branchId)->find($id);
            if(!$purchaseOrderItem) return DataResponse::ValidateFail('Purchase item not found.');
            $unitPrice = $purchaseOrderItem->unit_price;
            $receiveQty = isset($inputs['receive_qty']) ? $inputs['receive_qty'] : 0;
            if($receiveQty < 0 ) return DataResponse::ValidateFail('receive quantity must be positive');
            $lastReceiveQty = $purchaseOrderItem->received_qty;
            $totalQty = $purchaseOrderItem->qty;
            $overAllQty += $totalQty;
            $openQty = $totalQty - $lastReceiveQty;
            $rowDiscount = $purchaseOrderItem->discount_amount;
            $rowDisType = $purchaseOrderItem->discount_type;
            $remarks = isset($inputs['remarks']) ? $inputs['remarks']:null;
            $expiration_date = isset($inputs['expiration_date']) ? $inputs['expiration_date'] : null;
            if($expiration_date) $inputs['expires_at'] = date('Y-m-d',strtotime($expiration_date));

            /**
             * allow partially receive
             */
            if($purchaseOrderItem->received_qty == $purchaseOrderItem->qty){
                $skip_message .= 'skip row['.($key+1).'], because it has already received all => quantity ='.$purchaseOrderItem->received_qty.',';
                continue;
            }

            $originalTotalRow = $unitPrice * $totalQty;

            if($discountInfo->type == '$'){
                $unitPrice = round(($originalTotalRow - $discountInfo->amount) / $totalQty,2);
            }else{
                $unitPrice = round(($originalTotalRow - $originalTotalRow * $discountInfo->amount / 100) / $totalQty,2);
            }
            $amountDue += $this->calculateTotalPriceForItem($unitPrice,$receiveQty,$rowDiscount,$rowDisType);//round($this->calculatePrice($unitPrice,$receiveQty,$discountAmt,$disType),2);
            if($openQty < $receiveQty){
                return DataResponse::ValidateFail('The receive quantity must be equal or lower than '.$openQty);
            }

            $newReceive += $receiveQty;

            $update = $purchaseOrderItem->update([
                'received_qty' => $lastReceiveQty + $receiveQty,
                'remarks' => $remarks,
                'update_uid' => $userId,
                'company_id' => $companyId,
                'branch_id' => $branchId
            ]);
            $createReceiveItem = ReceivePoItem::create([
                'received_qty' => $receiveQty,
                'receive_po_id' => $receivePo->id,
                'purchase_order_item_id' => $id
            ]);

            if(!$createReceiveItem) return DataResponse::Error('Fail to receive item row('.($key + 1).').');
            if(!$update) return DataResponse::Error('Fail to receive item row('.($key + 1).').');

            $item = ProductVariant::with('product')->find($purchaseOrderItem->variant_id);
            $modelId = $item->product->model_id;
            $categoryId = $item->product->category_id;
            $condition = $item->condition;
            $prepareStock = $this->prepareStock($stockLocationId,$item->id,'receive_qty',$receiveQty,$user,$purchaseOrderItem->unit_price,null,$modelId,$categoryId,$condition,$expiration_date);
            if($prepareStock->status_code == 422) return DataResponse::ValidateFail($prepareStock->message);
            if($prepareStock->status_code == 500) return DataResponse::Error($prepareStock->message);
            $stockMovement = $this->createStockMovementLog([
                'variant_id' => $item->id,
                'cost' => $purchaseOrderItem->unit_price,
                'retail_price' => $item->retail_price,
                'wholesale_price' => $item->wholesale_price,
            ],'receive_qty',$receiveQty,$user);
            if($stockMovement->status_code == 422) return DataResponse::ValidateFail($stockMovement->message);
            return DataResponse::JsonResult(null,false,'Stock prepared!');
            // return $prepareStock;

        }

        return DataResponse::JsonResult((object)[
            'amount_due' => round($amountDue,2),
            'new_receive_qty' => $newReceive,
            'total_qty' => $overAllQty
        ],false,$skip_message);

    }

    function calculateDiscountedUnitPrice($unitPrice, $rowDiscount, $discountType='%')
    {
        if ($discountType === '$') {
            $discountedPrice = $unitPrice - $rowDiscount;
        } elseif ($discountType === '%') {
            $discountedPrice = $unitPrice * (1 - ($rowDiscount / 100));
        }

        return round($discountedPrice, 2);
    }

    function calculateTotalPriceForItem($unitPrice, $quantity, $rowDiscount, $discountType = '%')
    {
        $discountedUnitPrice = $this->calculateDiscountedUnitPrice($unitPrice, $rowDiscount, $discountType);
        return $discountedUnitPrice * $quantity;
    }


    public function changePurchaseOrderStatus(){
        $user = UserService::getAuthUser();
    }

    public function approvePurchaseOrder(Request $req,$id=null){
        $id = $id ? $id :$req->id;
        $user = UserService::getAuthUser();
        if($user->error) return ApiResponse::flex($user);
        $userId = $user->id;
        $branchId = $user->branch_id;
        $purchaseOrder = PurchaseOrder::where('branch_id',$branchId)->find($id);
        if(!$purchaseOrder) return ApiResponse::NotFound('Purchase Order not found');
        $inputs = [];
        if($purchaseOrder->status_id == 2) return ApiResponse::Duplicated("The purchase order has already approved.");
        $inputs['status_id'] = 2; //* status id = 2, => Approved;
        $inputs['approve_uid'] = $userId;
        $inputs['approve_date'] = now();
        $update = $purchaseOrder->update($inputs);
        if($update) return ApiResponse::JsonResult(null,false,'Approved');
        return ApiResponse::Error('Fail to approve');
    }

    public function getPurchaseOrders(Request $req){
        $user = UserService::getAuthUser();
        if($user->error) return ApiResponse::flex($user);
        $rows = PurchaseOrder::with(['status','vendor'])->where('branch_id',$user->branch_id)->get();
        // $orderItems = PurchaseOrderItem::from('purchase_order_items as oi')->join('product_variants as pv','oi.variant_id','pv.id')->join('products as p','p.id','=','pv.product_id')->join('models as m','m.id','p.model_id')
        // ->selectRaw('oi.qty,oi.total_price,oi.unit_price,oi.discount_type,oi.discount_amount,pv.size,pv.color,pv.sku,pv.weight,pv.width,pv.length,pv.expires_at,pv.condition,condition_percentage,pv.material,m.name as model_name,p.name,p.code,description')
        // ->get();
        foreach($rows as $row){
            $row->status_text = $row->status->name;
            $row->vendor_name = $row->vendor->phone . $row->vendor->name ? ('('.$row->vendor->name.')'):'';
            unset($row->status,$row->vendor);
        }
        return ApiResponse::JsonResult($rows);
    }

    public function getPurchaseOrder(Request $req,$id=null){
        $id = $id ? $id : $req->id;
        $user = UserService::getAuthUser();
        if($user->error) return ApiResponse::flex($user);
        $row = PurchaseOrder::where('branch_id',$user->branch_id)->find($id);
        $orderItems = PurchaseOrderItem::from('purchase_order_items as oi')->where('oi.purchase_id',$id)->join('product_variants as pv','oi.variant_id','pv.id')->join('products as p','p.id','=','pv.product_id')->join('models as m','m.id','p.model_id')
        ->selectRaw('oi.id,oi.qty,oi.received_qty,oi.total_price,oi.unit_price,oi.discount_type,oi.discount_amount,pv.size,pv.color,pv.sku,pv.weight,pv.width,pv.length,pv.expires_at,pv.condition,condition_percentage,pv.material,m.name as model_name,p.name,p.code,description')
        ->get();
        if($row){
            if($row->due_amount == $row->paid_amount){
                $row->open_amount = 0;
            }else if($row->due_amount > $row->paid_amount){
                $row->open_amount = abs($row->paid_amount - $row->due_amount);
            }
            $row->order_items = $this->getPurchaseOrderItems($orderItems,$row->variant_id);
            $row->status_text = $row->status->name;
            $row->vendor_name = $row->vendor->phone . ($row->vendor->name ? ('('.$row->vendor->name.')'):'');
            unset($row->status,$row->vendor);
        }
        return ApiResponse::JsonResult($row);
    }

    private function getPurchaseOrderItems($orderItems,$variantId){
        $items = [];
        foreach($orderItems as $item){
            if($item->variant_id == $variantId){
                $item->product_name = $item->name .' |'.'Color: '.$item->color.', Size: '.$item->size.', Condition: '.$item->condition;
                $openQty = $item->qty - $item->received_qty;//$item->received_qty > 0 ? $item->qty - $item->received_qty:0;
                $item->open_qty = abs($openQty);
                $items[] = $item;
            }
        }
        return $items;
    }

    private function purchaseOrderItemValidation(Request $req){
        return validator($req->all(),[
            'purchase_id' => 'required|int|exists:purchase_orders,id',
            'variant_id' => 'required|int|exists:product_variants,id',
            'qty' => 'required|int',
            'unit_price' => 'required|numeric',
            'discount_amount' => 'nullable|numeric',
            'discount_type' => 'nullable|in:%,$',
            'received_qty' => 'nullable|int'
        ]);
    }

    private function createOrUpdatePurchaseOrderItems($orderItems,$purchaseId,$user){
        $userId = $user->id;
        $branchId = $user->branch_id;
        $companyId = $user->company_id;
        foreach($orderItems as $row){
            $id = isset($row['id']) ? $row['id']:null;
            $row['purchase_id'] = $purchaseId;

            $productId = ProductVariant::find($row['variant_id'])->take(1)->value('product_id');
            $row['update_uid'] = $userId;
            $row['branch_id'] = $branchId;
            $row['company_id'] = $companyId;
            $row['product_id'] = $productId;
            if($id){
                $poItem = PurchaseOrderItem::where('branch_id',$branchId)->find($id);
            }else{
                $row['create_uid'] = $userId;

                $row['total_price'] = $row['due_amount'];
                $create = PurchaseOrderItem::create($row);
                if(!$create) return DataResponse::Error('fail to save purchase items');
            }
        }
        return DataResponse::JsonResult(null,false,'Saved');
    }

    private function mergerOrderItems($items,$purchaseId,$discountInfo=[]){
        $merged = [];
        $total = 0;
        $totalQty = 0;
        $totalDue = 0;
        foreach ($items as $row) {
            $row['purchase_id'] = $purchaseId;
            $row['discount_amount'] = $row['discount_amount'] ?? 0;
            $validate = $this->purchaseOrderItemValidation(new Request($row));
            if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first(),$validate->errors());
            $key = $row['variant_id'].'-'.$row['discount_amount'].'-'.$row['discount_type'];
            $unitPrice = $row['unit_price'];
            $qty = $row['qty'];
            $discountAmount = $row['discount_amount'];
            $discountType = $row['discount_type'];
            if (isset($merged[$key])) {
                //* Merge quantities and prices, you can adjust this to fit your needs
                $merged[$key]['qty'] += $row['qty'];
                $totalQty += $merged[$key]['qty'];
                $totalRow = $merged[$key]['qty'] * $unitPrice;
                $total += $totalRow;
                $due_amount = $this->calculatePrice($unitPrice,$merged[$key]['qty'],$discountAmount,$discountType);
                if($due_amount < 0) return DataResponse::ValidateFail('The discount amount cannot exceed the payable price. Please enter a valid discount.');
                $totalDue += $due_amount;
                $merged[$key]['due_amount'] = $due_amount;
                $merged[$key]['total_amount'] = $totalRow;
            } else {
                $totalRow = $qty * $unitPrice;
                $total += $totalRow;
                $merged[$key] = $row;
                $due_amount = $this->calculatePrice($unitPrice,$qty,$discountAmount,$discountType);

                if($due_amount < 0) return DataResponse::ValidateFail('The discount amount cannot exceed the payable price. Please enter a valid discount.');
                $totalDue += $due_amount;
                $merged[$key]['due_amount'] = $due_amount;
                $merged[$key]['total_amount'] = $totalRow;
                $totalQty += $qty;
            }
        }
        if($discountInfo->type == '$'){
            $totalDue = $totalDue - $discountInfo->amount;
        }else{
            $totalDue = $totalDue - ($totalDue * $discountInfo->amount / 100);
        }
        return DataResponse::JsonResult((object)[
            'items' => array_values($merged),
            'total_due' => $totalDue,
            'total_qty' => $totalQty,
            'total' => $total
        ]);
    }

    //** End Purchase Order Process */

    //** Start Stockmovement */

    //** ----------------- */



    function calculatePrice($unitPrice,$qty,$discountAmount,$discountType="%"){
        $total = ($qty * $unitPrice);
        if($discountType == '$'){
            return $total - $discountAmount;
        }
        return  $total - ($total * $discountAmount) / 100;
    }


    /**
     * Summary of prepareStock
     * @param mixed $targetCol
     * @param mixed $targetValue
     * @param mixed $user
     * @param mixed $modelId
     * @param mixed $categoryId
     * @param mixed $condition
     * @param mixed $expirationDate
     * @return mixed
     * note* This prepareStock can be use to update stock by => [sold_qty,return_qty,adjustment_qty,transfer_in_qty,transfer_out_qty];
     */

     //* itemRef can be sku or id
    public function prepareStock($stockLocationId=null,$variant_id,$targetColOrKey,$targetValue,$user,$itemCost,$itemRef=null,$modelId=null,$categoryId=null,$condition=null,$expirationDate=null){
        $today = date('Y-m-d');
        $branchId = $user->branch_id;
        $userId = $user->id;
        $companyId = $user->company_id;
        $targetCol = $targetColOrKey;
        $operator = isset($this->stockOperator[$targetCol]) ? $this->stockOperator[$targetCol] : null;
        $variant = ProductVariant::where('company_id',$companyId)->find($variant_id);
        if(!$operator) return DataResponse::ValidateFail('You provided the wrong target.');
        if(!$stockLocationId) $stockLocationId = StockLocation::where('company_id',$companyId)->where('branch_id',$branchId)->take(1)->value('id');
        //** --- daily stock process -----------
        $todayStock = DailyStock::where('branch_id',$branchId)->where('variant_id',$variant_id)->where('stock_location_id',$stockLocationId)->whereDate('created_at',$today)->first();
        // return $todayStock;
        $beginQty = 0;
        $endingQty = 0;
        $targetColValue = 0;
        //** convert target col */
        if(in_array($targetCol,['take_out_qty','donation_qty'])){
            $targetCol = 'adjustment_qty';
            $operator = '-';
        }
        if($todayStock){
            $beginQty = $todayStock->begin_qty;
            $endingQty = $todayStock->ending_qty;
            $targetColValue = $todayStock->{$targetCol} + ($operator.$targetValue);
        }
        if(!in_array($targetCol,['return_qty','adjustment_qty']) && $targetValue < 0) return DataResponse::ValidateFail('Qty can not be negative');

        if(!$todayStock) {
            //** take one stock ending qty where created_at < today */
            $targetColValue += $operator.$targetValue;
            $stock = DailyStock::where('branch_id',$branchId)->where('variant_id',$variant_id)->where('stock_location_id',$stockLocationId)->orderBy('created_at','DESC')->where('created_at','<',$today)->first();

            if($stock) {
                if($stock->ending_qty  <=0) return DataResponse::ValidateFail('Stock qty found '.$stock->ending_qty);
                $endingQty += $stock->ending_qty + ($operator.$targetValue);
                $beginQty = $stock->ending_qty;
            }else{
                $beginQty = $targetValue;
                $endingQty = $targetValue;
            }
            if($beginQty < 0 || $endingQty < 0){
                return DataResponse::ValidateFail('The quantity is negative.,cannot set to daily stock');
            }

            $arr = [
                'stock_location_id' => $stockLocationId,
                'variant_id' => $variant_id,
                $targetCol => $targetColValue,
                'begin_qty' => $beginQty,
                'ending_qty' => $endingQty,
                'branch_id' => $branchId,
                'company_id' => $companyId,
                'update_uid' => $userId,
                'create_uid' => $userId
            ];
            $createDailyStock = DailyStock::create($arr);
            if(!$createDailyStock) return DataResponse::Error('Fail to save daily stock');
        }else{
            $endingQty += $operator.$targetValue;
            $arr = [
                'stock_location_id' => $stockLocationId,
                'variant_id' => $variant_id,
                $targetCol => $targetColValue,
                'begin_qty' => $beginQty,
                'ending_qty' => $endingQty,
                'branch_id' => $branchId,
                'company_id' => $companyId,
                'update_uid' => $userId
            ];

            $updateDailyStock = $todayStock->update($arr);
            if(!$updateDailyStock) return DataResponse::Error('Fail to update daily stock');
        }

        // return $operator;
        //* ---------- Stock Process Here ----------

        // if($targetCol == 'receive_qty'){
        $sku = $this->createSkuCode($modelId,$categoryId,$condition,$branchId,$variant_id,$expirationDate);

        $foundBySku = Stock::where('branch_id',$branchId)->where('stock_location_id',$stockLocationId)->where('sku',$sku)->first();
        if($itemRef){
            if(is_numeric($itemRef)) {
                $foundBySku = Stock::where('branch_id',$branchId)->where('id',$itemRef)->where('stock_location_id',$stockLocationId)->first();
            }
            else {
                $foundBySku = Stock::where('branch_id',$branchId)->where('sku',$itemRef)->where('stock_location_id',$stockLocationId)->first();
            }
        }
        $stockQty = 0;
        $retail_price = $variant->retail_price;
        $wholesale_price = $variant->wholesale_price;
        if($foundBySku){
            if($foundBySku->qty <=0) return DataResponse::ValidateFail('Stock quantity found '.$foundBySku->qty);
            $retail_price = $foundBySku->retail_price;
            $wholesale_price = $foundBySku->wholesale_price;
            $stockQty = $foundBySku->qty;
            $stockQty += $operator.$targetValue;
            $updateArr = [
                'stock_location_id' => $stockLocationId,
                'qty' => $stockQty,
                'variant_id' => $variant_id,
                'cost' => $itemCost,
                'retail_price' => $retail_price,
                'wholesale_price' => $wholesale_price,
                'update_uid' => $userId,
                'company_id' => $companyId,
                'branch_id' => $branchId
            ];
            if(!$foundBySku->barcode || !$foundBySku->barcode_file){
                $date = date('Ymd');
                $barNum = str_pad($date.$foundBySku->id, 14, '0', STR_PAD_RIGHT);
                $updateArr['barcode'] = $barNum;
            }
            $updateStock = $foundBySku->update($updateArr);
            if(!$updateStock) return DataResponse::Error('Fail to update stock.');
        }else{
            $stockQty += $operator.$targetValue;
            $addNewStock = Stock::create([
                'sku' => $sku,
                'stock_location_id' => $stockLocationId,
                'qty' => $stockQty,
                'variant_id' => $variant_id,
                'retail_price' => $variant->retail_price,
                'wholesale_price' => $variant->wholesale_price,
                'cost' => $itemCost,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'company_id' => $companyId,
                'branch_id' => $branchId
            ]);
            if(!$addNewStock) return DataResponse::Error('Fail to add new stock!');
            //** new barcode */
            $stockId = $addNewStock->id;
            $date = date('Ymd');
            $barNum = str_pad($date.$stockId, 14, '0', STR_PAD_RIGHT);
            Stock::find($stockId)->update([
                'barcode' => $barNum,
            ]);
        }
        // }

        // $stockMovement = $this->createStockMovementLog([
        //     'variant_id' => $variant_id,
        //     'cost' => $itemCost,
        //     'retail_price' => $retail_price,
        //     'wholesale_price' => $wholesale_price,
        // ],$targetCol,$targetValue,$user);
        // if($stockMovement->status_code == 422) return DataResponse::ValidateFail($stockMovement->message);
        return DataResponse::JsonResult(null,false,'Stock prepared!');
    }


    function stockMovementValidation(Request $req){
        $movementTypes = implode(',',$this->getStockMovementTypesArray());
        return validator($req->all(),[
            'movement_type' => 'required|in:'.$movementTypes,
            'variant_id' => 'required|int|exists:product_variants,id',
            'cost' => 'nullable|numeric',
            'retail_price' => 'nullable|numeric',
            'wholesale_price' => 'nullable|numeric',
            'adjustment_qty' => 'nullable|numeric',
            'transfer_in_qty' => 'nullable|numeric',
            'reference_no' => 'nullable|string|max:30',
            'transfer_out_qty' => 'nullable|numeric',
            'from_location_id' => 'nullable|int',
            'to_location_id' => 'nullable|int',
            'status' => 'nullable|in:pending,approved,transfered',
            'sold_qty' => 'nullable|numeric',
            'description' => 'nullable|string|max:250',
            'item_ref' => 'nullable|string|max:250',
            'receive_qty' => 'nullable|numeric'
        ],[
            'movement_type.in' => 'Movement type must be one of '.$movementTypes
        ]);
    }




    /**
     * Summary of createStockMovementLog
     * @param mixed $arr => require [variant_id,qty] ,
     * note* Qty can be one of (adjustment_qty,transfer_in_qty,transfer_out_qty,sold_qty,purchase_qty)
     * @param mixed $variant_id
     * @return void
     */
    function createStockMovementLog($arr,$targetCol,$targetValue,$user,$useAutoApprove=true,$alwaysCreate=false):object{
        $today = date('Y-m-d');
        // $arr[$targetCol] = $targetValue;
        $branch_id = $user->branch_id;
        $userId = $user->id;
        $companyId = $user->company_id;
        $operator = $this->stockOperator[$targetCol];
        $arr['movement_type'] = $arr['movement_type'] ?? GeneralSettingService::getMovementType($targetCol);

        $validate = $this->stockMovementValidation(new Request($arr));
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        // print_r($operator);
        $inputs = $validate->validated();
        $inputs['update_uid'] = $userId;
        $inputs['branch_id'] = $branch_id;
        $inputs['company_id'] = $companyId;
        $inputs['type'] = $inputs['movement_type'];
        //** convert target col */
        if(in_array($targetCol,['take_out_qty','donation_qty'])){
            $targetCol = 'adjustment_qty';
            if($targetValue > 0) $targetValue = '-'.$targetValue;
            $operator = null;
        }
        if($targetCol == 'tranfer_in_qty' || $targetCol == 'tranfer_out_qty') $inputs['transfer_uid'] = $userId;
        if($useAutoApprove){
            $inputs['approved_uid'] = $userId;
            $inputs['approved_date'] = now();
        }
        //** ------ daily stock movement log by user */
        $id = null;
        $todayMovement = StockMovement::where('branch_id',$branch_id)->where('type',$inputs['type'])->where('cost',$inputs['cost'])->where('variant_id',$inputs['variant_id'])->where('create_uid',$userId)->whereDate('created_at',$today)->first();
        if($todayMovement && $todayMovement->status !== 'approved' && !$alwaysCreate){
            $adjustStock = $todayMovement->{$targetCol};
            $adjustStock += $operator.$targetValue;
            $inputs[$targetCol] = $adjustStock;
            $id = $todayMovement->id;
            $update = $todayMovement->update($inputs);
            if(!$update) return DataResponse::Error('Fail to update stock movement log!');
        }else{
            $inputs['create_uid'] = $userId;
            $inputs[$targetCol] = $operator.$targetValue;
            $create = StockMovement::create($inputs);
            $id = $create->id;
            if(!$create) return DataResponse::Error('Fail to create stock movement log!');
        }
        return DataResponse::JsonResult((object)['id' => $id],false,'Stock log created');
    }



    //** Missing Item */
    public function stockAdjustmentValidation(Request $req){
        return validator($req->all(),[
            'reason' => 'nullable|max:300',
            'items' => 'required|array',
            'warehouse_id' => 'required|int|exists:stock_locations,id'
        ]);
    }

    public function stockAdjustItemValidation(Request $req){
        return validator($req->all(),[
            'sku' => 'required|string|exists:stocks,sku',
            'qty' => 'required|int|min:1',
            'stock_adjustment_id' => 'required|exists:stock_adjustments,id',
            'reason' => 'nullable|string|max:200'
        ]);
    }

    public function createStockMissingItem(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->stockAdjustmentValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $items = $inputs['items'];
        unset($inputs['items']);
        DB::beginTransaction();
        try{
            $inputs['create_uid'] = $user->id;
            $inputs['update_uid'] = $user->id;
            $inputs['branch_id'] = $user->branch_id;
            $inputs['company_id'] = $user->company_id;
            $inputs['type'] = 'Missing';
            $warehouseId = $inputs['warehouse_id'];
            $create = StockAdjustment::create($inputs);
            if(!$create) return ApiResponse::Error('Fail to create!');
            $missingCode = 'MISS-'.str_pad($create->id, 8, '0', STR_PAD_LEFT);
            StockAdjustment::find($create->id)->update([
                'ref_code' => $missingCode
            ]);
            $mergeItem = $this->mergeAdjustmentDetails($items,$create->id);
            if($mergeItem->error) return ApiResponse::flex($mergeItem);
            $items = $mergeItem->data;
            $createItems = $this->createOrUpdateAdjustmentDetails($items,$create->id,$warehouseId,$missingCode,$user,'missing_qty');
            if($createItems->error) return ApiResponse::flex($createItems);
            DB::commit();
            return ApiResponse::JsonResult(null,false,'Created');
        }catch(Exception $e){
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            return ApiResponse::Error('Fail to save missing item');
        }
    }

    public function voidAllMissingStock(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $missingStock = StockAdjustment::where('company_id',$user->company_id)->where('type','Missing')->find($id);
        if(!$missingStock) return ApiResponse::NotFound('Missing Stock not found!');
        $void = $missingStock->update([
            'void_uid' => $user->branch_id,
            'void' => 1,
        ]);
        if($void) {
            StockAdjustmentDetail::where('stock_adjustment_id',$id)->update([
                'void_uid' => $user->id,
                'void' => 1,
            ]);
            return ApiResponse::JsonResult(null,false,'All items has been voided!');
        }
        return ApiResponse::Error('Fail to void item');
    }

    /**
     * Summary of voidByCheckItem
     * @param \Illuminate\Http\Request $req
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function voidByCheckItem(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $missingStock = StockAdjustment::where('company_id',$user->company_id)->where('void',0)->where('type','Missing')->find($id);
        if(!$missingStock) return ApiResponse::NotFound('Missing Stock info not found!');
        $items = $req->items;
        $skipRows = 'no row skip!';
        $success = 0;
        if(!$items) return ApiResponse::ValidateFail('Please input valid items');
        foreach($items as $key=>$item){
            $rowid = $item['id'] ?? null;
            if(!$rowid) return ApiResponse::ValidateFail('Please provide item identity!');
            $detail = StockAdjustmentDetail::where('stock_adjustment_id',$id)->where('void',0)->find($rowid);
            if(!$detail) return ApiResponse::ValidateFail('Item not found!');
            if($detail->void) {
                $skipRows .= ($key+1).',';
            }else{
                $detail->update([
                    'void' => 1,
                    'void_uid' => $user->id
                ]);
                $success += 1;
            }
        }
        return ApiResponse::JsonResult((object)[
            'success' => $success,
            'skip_rows' => $skipRows
        ],false,'Voided '. $success. ' row(s).');
    }
    /**
     * void by item
     */

    public function voidByItem(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $detail = StockAdjustmentDetail::where('void',0)->find($id);
        if(!$detail) return ApiResponse::NotFound('Item not found');
        $detail->update([
            'void' => 1,
            'void_uid' => $user->id
        ]);
        return ApiResponse::JsonResult(null,false,'Voided');
    }


    /**
     * Summary of mergeAdjustmentDetails
     * @param mixed $items
     * @param mixed $adjustId
     * @return object
     * if same sku sum QTY
     */
    private function mergeAdjustmentDetails($items,$adjustId){
        $mergeArr = [];
        foreach($items as $item){
            $item['stock_adjustment_id'] = $adjustId;
            $id = $item['id'] ?? null;
            $reqItem = new Request($item);
            $validateItem = $this->stockAdjustItemValidation($reqItem);
            if($validateItem->fails()) return DataResponse::ValidateFail($validateItem->errors()->first());
            $inputs = $validateItem->validated();
            $reason = $inputs['reason'] ?? null;
            $key = $inputs['sku'].'-'.$reason;
            $inputs['id'] = $id;
            if(isset($mergeArr[$key])){
                $mergeArr[$key]['qty'] += $inputs['qty'];
            }else{
                $mergeArr[$key] = $inputs;
            }
        }
        return DataResponse::JsonResult(array_values($mergeArr));
    }

    private function createOrUpdateAdjustmentDetails($items,$adjustId,$warehouseId,$missingCode,$user,$targetCol){
        $keepItems = [];
        foreach($items as $key=>$item){
            $item['stock_adjustment_id'] = $adjustId;
            $id = $item['id'] ?? null;
            $reqItem = new Request($item);
            $validateItem = $this->stockAdjustItemValidation($reqItem);
            if($validateItem->fails()) return DataResponse::ValidateFail($validateItem->errors()->first());
            $item = $validateItem->validated();
            $qty = $item['qty'];
            $sku = $item['sku'];
            if($qty < 0) return DataResponse::ValidateFail('Invalid missing value!');
            $existItem = Stock::where('company_id',$user->company_id)->orWhere('sku',$sku)->where('stock_location_id',$warehouseId)->first();
            if(!$existItem) return DataResponse::ValidateFail('Item not found in warehouse');
            if($qty > $existItem->qty) return DataResponse::ValidateFail('It seems like your stock quantity is lower than missing quantity. Stock found '.$existItem->qty.' unit by sku('.$sku.')');
            $item['update_uid'] = $user->id;
            $item['company_id'] = $user->company_id;
            $item['branch_id'] = $user->branch_id;
            $item['status'] = 'pending';
            $item['warehouse_id'] = $warehouseId;
            $item['variant_id'] = $existItem->variant_id;
            $item['cost'] = $existItem->cost;
            $item['retail_price'] = $existItem->retail_price;
            $item['item_ref'] = $sku;
            unset($item['sku']);
            if($id){
                $adjustItem = StockAdjustmentDetail::where('void',0)->find($id);
                if(!$adjustItem) return DataResponse::NotFound('Adjustment details not found by row '.($key+1));
                if($adjustItem->status === 'approved') continue;
                $keepItems['id'] = $id;
                $update = $adjustItem->update($item);
                if(!$update) return DataResponse::Error('Fail to update');
                $operator = $this->stockOperator['missing_qty'];
                StockMovement::where('reference_no',$id)->update([
                    'item_ref' => $existItem->sku,
                    'variant_id' => $existItem->variant_id,
                    'missing_qty' => $operator.$qty,
                    'description'=> $adjustId.'|'.$id.'|'.$missingCode,
                    'cost' => $existItem->cost,
                    'retail_price' => $existItem->retail_price,
                ]);
            }else{
                $item['create_uid'] = $user->id;
                $createDetails = StockAdjustmentDetail::create($item);
                $keepItems['id'] = $createDetails->id;
                if(!$createDetails) return DataResponse::Error('Fail to create missing details.');
                $stockMovement = $this->createStockMovementLog([
                    'from_location_id' => $warehouseId,
                    'variant_id' => $existItem->variant_id,
                    'description'=> $adjustId.'|'.$createDetails->id.'|'.$missingCode,
                    'cost' => $existItem->cost,
                    'item_ref' => $existItem->sku,
                    'reference_no' => "$createDetails->id",
                    'retail_price' => $existItem->retail_price,
                    'wholesale_price' => $existItem->wholesale_price,
                ],$targetCol,$qty,$user,useAutoApprove: false,alwaysCreate: true);
                if($stockMovement->status_code == 422) return DataResponse::ValidateFail($stockMovement->message);
            }
        }
        //** if not provide delete */
        StockAdjustmentDetail::where('stock_adjustment_id',$adjustId)->where('void',0)->whereNotIn('id',$keepItems)->delete();
        return DataResponse::JsonResult(null,false,'Saved');
    }

    public function getStockMissingItem(Request $req){
        $user = UserService::getAuthUser();
        $query = StockAdjustment::where('type','Missing')->where('void',0)->with(['warehouse','createUser','updateUser','approveUser'])->selectRaw('id,ref_code,reason,type,status,approved_uid,approved_date,create_uid,update_uid,warehouse_id');
        $missingItems = $query->get();
        $stockItems = GeneralSettingService::getStockItems($user);
        foreach($missingItems as $item){
            $stockItemDetails = $this->getStockMovementItemDetails($stockItems,$item->item_ref);
            $item->create_user_name = $item->createUser->user_name;
            $item->update_user_name = $item->updateUser->user_name;
            $item->approve_user_name = $item->approveUser ? $item->approveUser->user_name : null;
            $item->warehouse_name = $item->warehouse ? $item->warehouse->name : null;
            // $item->item_name = $stockItemDetails ? $stockItemDetails->product_name. ' |'.$stockItemDetails->product_details:'';
            unset($item->createUser,$item->approveUser,$item->updateUser,$item->warehouse);
        }
        return ApiResponse::Pagination($missingItems,$req,'Get All Missing Items');
    }

    private function getStockMovementItemDetails($rows,$sku){
        foreach($rows as $row){
            if($row->sku == $sku){
                return $row;
            }
        }
        return null;
    }

    public function getOneStockMissingItem(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $stockItems = GeneralSettingService::getStockItems($user);
        $missintItem = StockAdjustment::with(['warehouse','createUser','updateUser','approveUser','details:id,stock_adjustment_id,item_ref as sku,qty,reason,status,approved_date,approved_uid,update_uid,create_uid'])->selectRaw('id,ref_code,reason,type,status,approved_uid,approved_date,create_uid,update_uid,warehouse_id')->where('void',0)->where('company_id',$user->company_id)->where('type','Missing')->find($id);
        if(!$missintItem) return ApiResponse::NotFound('Could not found the missing stock');
        $missintItem->missing_qty = abs($missintItem->missing_qty);
        $missintItem->create_user_name = $missintItem->createUser->user_name;
        $missintItem->update_user_name = $missintItem->updateUser->user_name;
        $missintItem->approve_user_name = $missintItem->approveUser ? $missintItem->approveUser->user_name : null;
        $missintItem->warehouse_name = $missintItem->warehouse ? $missintItem->warehouse->name : null;
        $missintItem->created_at = date('Y-m-d',strtotime($missintItem->created_at));
        $approvedCount = 0;
        $unapprovedCount = 0;
        foreach($missintItem->details as $detail){
            $stockItemDetails = $this->getStockMovementItemDetails($stockItems,$detail->sku);
            $detail->create_user_name = $detail->createUser->user_name;
            $detail->update_user_name = $detail->updateUser->user_name;
            $detail->approve_user_name = $detail->approveUser ? $detail->approveUser->user_name : null;
            $detail->item_name = $stockItemDetails ? $stockItemDetails->product_name. ' |'.$stockItemDetails->product_details:'';
            if($detail->status === 'approved') $approvedCount +=1;
            else $unapprovedCount += 1;
            unset($detail->createUser,$detail->approveUser,$detail->updateUser);
        }
        $missintItem->approved_count = $approvedCount;
        $missintItem->unapproved_count = $unapprovedCount;

        unset($missintItem->approveUser,$missintItem->createUser,$missintItem->updateUser,$missintItem->warehouse);
        return ApiResponse::JsonResult($missintItem,false,'Get One Missing Stock');
    }


    public function updateStockMissingItem(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $validate = $this->stockAdjustmentValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $missingItem = StockAdjustment::where('company_id',$user->company_id)->where('type','Missing')->find($id);
        if(!$missingItem) return ApiResponse::NotFound('Missing item not found');
        if($missingItem->status === 'all approved') return ApiResponse::Duplicated('You cannot modify approved!');
        $items = $inputs['items'];
        unset($inputs['items']);
        DB::beginTransaction();
        try{
            $inputs['update_uid'] = $user->id;
            $inputs['branch_id'] = $user->branch_id;
            $inputs['company_id'] = $user->company_id;
            $update = $missingItem->update($inputs);
            if(!$update) return ApiResponse::Error('Fail to update missing stock');
            $mergeItem = $this->mergeAdjustmentDetails($items,$id);
            if($mergeItem->error) return ApiResponse::flex($mergeItem);
            $items = $mergeItem->data;
            $updateItems = $this->createOrUpdateAdjustmentDetails($items,$id,$missingItem->warehouse_id,$missingItem->ref_code,$user,'missing_qty');
            if($updateItems->error) return ApiResponse::flex($updateItems);
            DB::commit();
            return ApiResponse::JsonResult(null,false,'Updated');
        }catch(Exception $e){
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            return ApiResponse::Error('Fail to save missing item');
        }
    }

    public function voidStockMissingItem(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $missingItem = StockMovement::where('company_id',$user->company_id)->where('type','Missing')->find($id);
        if(!$missingItem) return ApiResponse::NotFound('Missing item not found');
        if($missingItem->status === 'approved') return ApiResponse::ValidateFail('You cannot void approved record!');
        $void = $missingItem->update([
            'void' => 1,
            'void_uid' => $user->id
        ]);
        if($void) return ApiResponse::JsonResult(null,false,'Voided');
        return ApiResponse::Error('Fail to void');
    }

    public function approveListMissingItems(){

    }

    public function approveAllMissingItems(){

    }

    public function approveMissingItemById(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $detail = StockAdjustmentDetail::where('void',0)->find($id);
        if(!$detail) return ApiResponse::NotFound('Item not found');
        $warehouseId = StockAdjustment::where('void',0)->where('id',$detail->stock_adjustment_id)->take(1)->value('warehouse_id');
        if(!$warehouseId) return ApiResponse::NotFound('Warehouse not found!');
        DB::beginTransaction();
        try{
            $detail->update([
                'status' => 'approve',
                'approved_uid' => $user->id,
                'approved_date' => now(),
            ]);
            $missingQty = $detail->qty;
            $prepareStock = $this->prepareStock($warehouseId,$detail->variant_id,'missing_qty',$missingQty,$user,$detail->cost,$detail->item_ref);
            if($prepareStock->error) return ApiResponse::flex($prepareStock);
            DB::commit();
            // return Stock::where('sku',$detail->item_ref)->get();
            //** Update Parent Status */
            return ApiResponse::JsonResult(null,false,'Approved! Your stock has been reduced by 15 units out.');
        }catch(Exception $e){
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            return ApiResponse::Error('Fail to save missing item');
        }
    }

    private function udpateMissingStockStatus($id){
        $queryChildren = StockAdjustmentDetail::where('stock_adjustment_id')->where('void',0);
        $childCount = $queryChildren->count();
        $children = $queryChildren->get();
        foreach($children as $child){

        }
    }

    public function approveMissingItem(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $missingItem = StockMovement::where('company_id',$user->company_id)->where('type','Missing')->find($id);
        if(!$missingItem) return ApiResponse::NotFound('Missing Info not found by the provided reference');
        if($missingItem->status === 'approved') return ApiResponse::Duplicated('You cannot modify approved item');
        $missingQty = abs($missingItem->missing_qty);
        $variant = ProductVariant::with('product')->find($missingItem->variant_id);
        $modelId = $variant->product->model_id;
        $categoryId = $variant->product->category_id;
        $condition = $variant->condition;
        if(!$missingItem->item_ref) return ApiResponse::ValidateFail('Some fields contain invalid information, you cannot approve!');
        $approve = $missingItem->update([
            'approved_uid' => $user->id,
            'approved_date' => now(),
            'status' => 'approved'
        ]);
        if($approve){
            $prepareStock = $this->prepareStock($missingItem->from_location_id,$missingItem->variant_id,'missing_qty',$missingQty,$user,$missingItem->cost,$missingItem->item_ref,$modelId,$categoryId,$condition);
            if($prepareStock->error){
                $missingItem->update([
                    'approved_uid' => null,
                    'approved_date' => null,
                    'status' => 'pending'
                ]);
            }
            if($prepareStock->status_code == 422) return DataResponse::ValidateFail($prepareStock->message);
            if($prepareStock->status_code == 500) return DataResponse::Error($prepareStock->message);
            return ApiResponse::JsonResult(null,false,'Approved');
        }
        return ApiResponse::Error('Fail to approve');

    }


    //** end missing item */

    //** start adjustment item [Take out , donation, ..]*/
    public function createTakeOutStock(Request $req){
        $user = UserService::getAuthUser();
        $validate = validator($req->all(),[
            'items' => 'required|array',
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $items = $inputs['items'];
        DB::beginTransaction();
        try{
            foreach($items as $key=>$item){
                $takeOutQty = $item['qty'];
                $id = $item['id'] ?? null;
                $sku = $item['sku'] ?? null;
                $barcode = $item['barcode'] ?? null;
                if($takeOutQty < 0) return ApiResponse::ValidateFail('Invalid take out value!');
                $existItem = Stock::where('company_id',$user->company_id)->where('id',$id)->orWhere('sku',$sku)->orWhere('barcode',$barcode)->first();
                if(!$existItem) return DataResponse::ValidateFail('Item not found in warehouse');
                if($takeOutQty > $existItem->qty) return DataResponse::ValidateFail('It seems like your stock quantity is lower than take out quantity. Stock found '.$existItem->qty.' unit by sku('.$sku.')');
                $stockMovement = $this->createStockMovementLog([
                    'from_location_id' => $existItem->stock_location_id,
                    'variant_id' => $existItem->variant_id,
                    'description'=>$inputs['reason'] ?? null,
                    'cost' => $existItem->cost,
                    'item_ref' => $existItem->sku,
                    'retail_price' => $existItem->retail_price,
                    'wholesale_price' => $existItem->wholesale_price,
                ],'take_out_qty','-'.$takeOutQty,$user,useAutoApprove: false,alwaysCreate: true);
                if($stockMovement->status_code == 422) return DataResponse::ValidateFail($stockMovement->message);

                //** Set code */
                $takeOutCode = 'TO-'.str_pad($stockMovement->data->id, 8, '0', STR_PAD_LEFT);
                StockMovement::find($stockMovement->data->id)->update([
                    'reference_no' => $takeOutCode
                ]);
            }
            DB::commit();
            // return StockMovement::where('type','Take Out')->get();
            return ApiResponse::JsonResult(null,false,'Created');
        }catch(Exception $e){
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            return ApiResponse::Error('Fail to save missing item');
        }
    }


    public function getTakeOutStock(Request $req){
        $user = UserService::getAuthUser();
        $query = StockMovement::with(['createUser','updateUser','approveUser','transOutWarehouse'])->selectRaw('description as reason,approved_uid,type,updated_at,approved_date,created_at,from_location_id,status,id,reference_no,adjustment_qty as take_out_qty,variant_id,item_ref,create_uid,update_uid')->where('void',0)->where('company_id',$user->company_id)->where('type','Take Out');
        $takeOutStocks = $query->get();
        $stockItems = GeneralSettingService::getStockItems($user);
        foreach($takeOutStocks as $item){
            $stockItemDetails = $this->getStockMovementItemDetails($stockItems,$item->item_ref);
            $item->create_user_name = $item->createUser->user_name;
            $item->take_out_qty = abs($item->take_out_qty);
            $item->update_user_name = $item->updateUser->user_name;
            $item->approve_user_name = $item->approveUser ? $item->approveUser->user_name : null;
            $item->warehouse = $item->transOutWarehouse ? $item->transOutWarehouse->name : null;
            $item->item_name = $stockItemDetails ? $stockItemDetails->product_name. ' |'.$stockItemDetails->product_details:'';
            unset($item->createUser,$item->approveUser,$item->updateUser,$item->transOutWarehouse);
        }
        return ApiResponse::Pagination($takeOutStocks,$req,'Get All Take Out Items');
    }


    public function getOneTakeOutStock(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $stockItems = GeneralSettingService::getStockItems($user);
        $takeOutStock = StockMovement::with(['createUser','updateUser','approveUser','transOutWarehouse'])->selectRaw('description as reason,approved_uid,type,updated_at,approved_date,created_at,from_location_id,status,id,reference_no,adjustment_qty as take_out_qty,variant_id,item_ref,create_uid,update_uid')->where('void',0)->where('company_id',$user->company_id)->where('type','Take Out')->find($id);
        if(!$takeOutStock) return ApiResponse::NotFound('Could not found the take out item');
        $stockItemDetails = $this->getStockMovementItemDetails($stockItems,$takeOutStock->item_ref);
        $takeOutStock->take_out_qty = abs($takeOutStock->take_out_qty);
        $takeOutStock->create_user_name = $takeOutStock->createUser->user_name;
        $takeOutStock->update_user_name = $takeOutStock->updateUser->user_name;
        $takeOutStock->approve_user_name = $takeOutStock->approveUser ? $takeOutStock->approveUser->user_name : null;
        $takeOutStock->warehouse = $takeOutStock->transOutWarehouse ? $takeOutStock->transOutWarehouse->name : null;
        $takeOutStock->item_name = $stockItemDetails ? $stockItemDetails->product_name. ' |'.$stockItemDetails->product_details:'';
        $takeOutStock->created_at = date('Y-m-d',strtotime($takeOutStock->created_at));
        unset($takeOutStock->approveUser,$takeOutStock->createUser,$takeOutStock->updateUser,$takeOutStock->transOutWarehouse);
        return ApiResponse::JsonResult($takeOutStock,false,'Get One Take Out Stock');
    }

    public function updateTakeOutStock(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $validate = validator($req->all(),[
            'sku' => 'required|string|exists:stocks,sku',
            'qty' => 'required|int',
            'reason' => 'nullable|string|max:250'
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $qty = $inputs['qty'];
        $sku = $inputs['sku'];
        $takeOutStock = StockMovement::where('company_id',$user->company_id)->where('type','Take Out')->find($id);
        if(!$takeOutStock) return ApiResponse::NotFound('Take out item not found');
        if($takeOutStock->status === 'approved') return ApiResponse::Duplicated('This item has already been approved.');
        $existItem = Stock::where('company_id',$user->company_id)->orWhere('sku',$sku)->first();
        if(!$existItem) return DataResponse::ValidateFail('Item not found in warehouse');
        if($qty > $existItem->qty) return DataResponse::ValidateFail('It seems like your stock quantity is lower than take out quantity. Stock found '.$existItem->qty.' unit by sku('.$sku.')');
        $reason = $inputs['reason'];
        $operator = $this->stockOperator['take_out_qty'];
        $qty = $operator.$qty;
        $update = $takeOutStock->update([
            'adjustment_qty' => $qty,
            'item_ref' => $sku,
            'description' => $reason
        ]);
        if(!$update) return ApiResponse::Error('Fail to update');
        return ApiResponse::JsonResult(null,false,'Updated');
    }

    public function voidTakeOutStock(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser('void');
        if($user->error) return ApiResponse::flex($user);
        $takeOutStock = StockMovement::where('company_id',$user->company_id)->where('type','Take Out')->find($id);
        if(!$takeOutStock) return ApiResponse::NotFound('Take out item not found');
        if($takeOutStock->status === 'approved') return ApiResponse::ValidateFail('You cannot void approved record!');
        $void = $takeOutStock->update([
            'void' => 1,
            'void_uid' => $user->id
        ]);
        if($void) return ApiResponse::JsonResult(null,false,'Voided');
        return ApiResponse::Error('Fail to void');
    }

    public function approveTakeOutStock(Request $req){
        $user = UserService::getAuthUser();
        if($user->error) return ApiResponse::flex($user);
        $id = $req->id;
        $missingItem = StockMovement::where('company_id',$user->company_id)->where('type','Take Out')->find($id);
        if(!$missingItem) return ApiResponse::NotFound('Take out item not found.');
        // if($missingItem->status === 'approved') return ApiResponse::Duplicated('This item has already been approved.');
        $takeOutQty = abs($missingItem->adjustment_qty);
        $variant = ProductVariant::with('product')->find($missingItem->variant_id);
        $modelId = $variant->product->model_id;
        $categoryId = $variant->product->category_id;
        $condition = $variant->condition;
        if(!$missingItem->item_ref) return ApiResponse::ValidateFail('item reference contain invalid information, you cannot approve!');
        $approve = $missingItem->update([
            'approved_uid' => $user->id,
            'approved_date' => now(),
            'status' => 'approved'
        ]);
        if($approve){
            $prepareStock = $this->prepareStock($missingItem->from_location_id,$missingItem->variant_id,'take_out_qty',$takeOutQty,$user,$missingItem->cost,$missingItem->item_ref,$modelId,$categoryId,$condition);
            if($prepareStock->error){
                $missingItem->update([
                    'approved_uid' => null,
                    'approved_date' => null,
                    'status' => 'pending'
                ]);
            }
            if($prepareStock->status_code == 422) return DataResponse::ValidateFail($prepareStock->message);
            if($prepareStock->status_code == 500) return DataResponse::Error($prepareStock->message);

            return ApiResponse::JsonResult(null,false,'Approved');
        }
        return ApiResponse::Error('Fail to approve');

    }


    //** end adjustment */

    public function stockTransform(Request $req){
        $user = UserService::getAuthUser();
        if($user->error) return ApiResponse::flex($user);
        $validate = validator($req->all(),[
            'from_warehouse_id' => 'required|int|exists:stock_locations,id',
            'to_warehouse_id' => 'required|int|exists:stock_locations,id',
            'ref_number' => 'nullable|string|max:30',
            'items' => 'required|array',
        ],[
            'from_warehouse_id.required' => 'You must select stock warehouse',
            'to_warehouse_id.required' => 'You must select warehouse where you want to transfer in'
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $fromWarehouseId = $inputs['from_warehouse_id'];
        $ref_number = $inputs['ref_number'] ?? null;
        $toWarehouseId = $inputs['to_warehouse_id'];
        if($fromWarehouseId == $toWarehouseId) return ApiResponse::ValidateFail('You cannot transfer to the same warehouse');
        $items = $inputs['items'];
        DB::beginTransaction();
        try{
            $validateItems = $this->prepareTransferItems($items,$fromWarehouseId,$toWarehouseId,$user,$ref_number);
            // return $validateItems;
            if($validateItems->status_code == 422) return ApiResponse::Error($validateItems->message);
            DB::commit();
            return ApiResponse::JsonResult(null,false,'Stock transfered!');
        }catch(Exception $e){
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            return ApiResponse::Error('Fail to transfer');
        }
    }


    public function getTransferList(Request $req){
        $user = UserService::getAuthUser();
        if($user->error) return ApiResponse::flex($user);
        $transfers = StockMovement::where('company_id',$user->company_id)->where('branch_id',$user->branch_id)->whereIn('type',['Transfer Out'])->with(['transOutWarehouse','transInWarehouse'])->get();
        foreach($transfers as $tr){
            $tr->transfer_location = $tr->transOutWarehouse->name . ' To '. $tr->transInWarehouse->name;
            $tr->status = strtoupper($tr->status);
            unset($tr->transInWarehouse,$tr->transOutWarehouse);
        }
        return ApiResponse::Pagination($transfers,$req);
    }

    function prepareTransferStock($warehosueId,$variant_id,$targetCol,$targetQty,$user,$cost,$itemRef){
        $item = ProductVariant::with('product')->find($variant_id);
        $modelId = $item->product->model_id;
        $categoryId = $item->product->category_id;
        $condition = $item->condition;
        return $this->prepareStock($warehosueId,$variant_id,$targetCol,$targetQty,$user,$cost,$itemRef,$modelId,$categoryId,$condition);
    }

    function prepareTransferItems($items,$fromWarehouse_id,$toWarehouseId,$user,$reference_no){
        foreach($items as $key=>$item){
            $transferQty = $item['transfer_qty'];
            $existItem = Stock::where('company_id',$user->company_id)->where('id',$item['id'])->where('stock_location_id',$fromWarehouse_id)->first();
            if(!$existItem) return DataResponse::ValidateFail('Item not found in warehouse');
            if($transferQty > $existItem->qty) return DataResponse::ValidateFail('Your transfer qty is exceeded the existing');

            //** tranfer Out */
            $transferOut = $this->prepareTransferStock($fromWarehouse_id,$existItem->variant_id,'transfer_out_qty',$transferQty,$user,$existItem->cost,$item['id']);
            if($transferOut->status_code == 422) return DataResponse::ValidateFail($transferOut->message);
            if($transferOut->status_code == 500) return DataResponse::Error($transferOut->message);

            if(!$transferOut->error){
                $stockMovement = $this->createStockMovementLog([
                    'variant_id' => $existItem->variant_id,
                    'cost' => $existItem->cost,
                    'retail_price' => $existItem->retail_price,
                    'wholesale_price' => $existItem->wholesale_price,
                    'reference_no' => $reference_no,
                    'from_location_id' => $fromWarehouse_id,
                    'status' => 'transfered',
                    'transfer_date' => now(),
                    'to_location_id' => $toWarehouseId
                ],'transfer_out_qty',$transferQty,$user);
                if($stockMovement->status_code == 422) return DataResponse::ValidateFail($stockMovement->message);
            }

            //** transfer In */
            $transferIn = $this->prepareTransferStock($toWarehouseId,$existItem->variant_id,'transfer_in_qty',$transferQty,$user,$existItem->cost,$existItem->sku); //* note => last param use sku to find if item exists in stock
            if($transferIn->status_code == 422) return DataResponse::ValidateFail($transferIn->message);
            if($transferIn->status_code == 500) return DataResponse::Error($transferIn->message);

            if(!$transferIn->error){
                $stockMovement = $this->createStockMovementLog([
                    'variant_id' => $existItem->variant_id,
                    'cost' => $existItem->cost,
                    'retail_price' => $existItem->retail_price,
                    'wholesale_price' => $existItem->wholesale_price,
                    'reference_no' => $reference_no,
                    'from_location_id' => $fromWarehouse_id,
                    'status' => 'transfered',
                    'transfer_date' => now(),
                    'to_location_id' => $toWarehouseId
                ],'transfer_in_qty',$transferQty,$user);
                if($stockMovement->status_code == 422) return DataResponse::ValidateFail($stockMovement->message);
            }

        }
        return DataResponse::JsonResult(null);
    }

    function savePaymentSlip($purchaseId,$photo,$user,$amount=0){
        $image = Helper::base64ToImageFile($photo,$user->company_id,'purchase_payment_slip');
        $create = PoPaymentSlip::create([
            'purchase_id' => $purchaseId,
            'photo_file_name' => $image,
            'amount' => $amount
        ]);
        if(!$create) return DataResponse::Error('fail to add payment slip');
        return DataResponse::JsonResult(null,false);
    }
}
