<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DailyStock;
use App\Models\PoPaymentSlip;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Stock;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\StockMovementType;
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
    ];

    protected $movementTypes = [
        'receive_qty' => '+',
        'transfer_in_qty' => '+',
        'transfer_out_qty' => '-',
        'sold_qty' => '-',
        'return_qty' => '+',
    ];


    function getStockMovementTypesArray(){
        return StockMovementType::all()->pluck('name')->toArray();
    }

    //** Purchase Order */
    private function purchaseOrderValidation(Request $req){
        return validator($req->all(),[
            'vendor_id' => 'required|int|exists:vendors,id',
            'issue_date' => 'required|date',
            'remarks' => 'nullable|string|max:250',
            'expect_arrival_date' => 'nullable|date',
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
        $user = UserService::getAuthUser();
        $userId = $user->id;
        $validate = $this->purchaseOrderValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
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
        $orderItems = $inputs['order_items'];
        unset($inputs['order_items']);

        $poCode = 'PO'.date('dMYhis');
        $inputs['po_code'] = $poCode;
        DB::beginTransaction();
        try{
            $create = PurchaseOrder::create($inputs);
            if($create){
                $purchaseId = $create->id;
                $createItems = $this->createOrUpdatePurchaseOrderItems($orderItems,$purchaseId,$user);
                if($createItems->error){
                    if($createItems->status_code == 422) return ApiResponse::ValidateFail($createItems->message);
                    if($createItems->status_code == 409) return ApiResponse::Duplicated($createItems->message);
                    if($createItems->status_code == 500) return ApiResponse::Error($createItems->message);
                }
                // * Set total price of added items
                PurchaseOrder::find($purchaseId)->update([
                    'total_amount' => $createItems->data->total_amount,
                    'due_amount' => $createItems->data->due_amount
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
        $user = UserService::getAuthUser();
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
            'pay_amount' => 'nullable|numeric',
            'tax' => 'nullable|numeric'
            // 'payment_slip' => 'nullable|string',
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $orderItems = $inputs['order_items'];
        $inputs['receive_date'] = isset($inputs['receive_date']) ? $inputs['receive_date'] : now();
        $inputs['update_uid'] = $userId;
        $inputs['branch_id'] = $branchId;
        $inputs['company_id'] = $companyId;
        $tax = $inputs['tax'] ?? 0;
        $payAmount = $inputs['pay_amount'] ?? null;
        $paymentSlip = $req->payment_slip ? $req->payment_slip:null;
        unset($inputs['order_items'],$inputs['payment_slip']);
        // return $inputs;
        DB::beginTransaction();
        try{
            $receive = $this->receiveOrderItems($orderItems,$id,$inputs['stock_location_id'],$user,$tax);
            if($receive->status_code == 422) return ApiResponse::ValidateFail($receive->message);
            if($paymentSlip){
                $addPaymentSlip = $this->savePaymentSlip($id,$paymentSlip,$user);
                if($addPaymentSlip->status_code == 500) return ApiResponse::Error($addPaymentSlip->message);
            }
            $status_id = 5; //** partially received */
            $allReceive = ($purchaseOrder->paid_amount == $receive->data->amount_due);
            if($allReceive) $status_id = 6;
            // return $receive;
            // if($payAmount !== $receive->data->amount_due) return ApiResponse::ValidateFail('Pay amount must be '.$receive->data->amount_due);
            $purchaseOrder->update([
                'paid_amount' => $purchaseOrder->paid_amount + $receive->data->amount_due,
                'status_id' => $status_id,
            ]);
            DB::commit();
            // return DailyStock::get();
            return ApiResponse::JsonResult(null,false,'Received!');
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            return ApiResponse::Error('Fail to receive');
        }
    }



    private function createSkuCode($modelId,$categoryId,$condition,$branchId,$cost,$variantId,$expirationDate=null){
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
        $sku = 'SKU'.$categoryId.'_'.$modelId.'_'.$condition.$variantId.'_'.$branchId.'_'.$cost;
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

    private function prepareDailyStock($target){

    }

    private function receiveOrderItems($orderItems,$purchaseId,$stockLocationId,$user,$tax=0){
        $branchId = $user->branch_id;
        $companyId = $user->company_id;
        $userId = $user->id;
        $skip_message = null;
        $amountDue = 0;
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
            $openQty = $totalQty - $lastReceiveQty;
            $discountAmt = $purchaseOrderItem->discount_amount;
            $disType = $purchaseOrderItem->discount_type;
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
            $amountDue += $this->calculatePrice($unitPrice,$receiveQty,$discountAmt,$disType);

            if($openQty < $receiveQty){
                return DataResponse::ValidateFail('The receive quantity must be equal or lower than open quantity. the open amount = '.$openQty);
            }

            $update = $purchaseOrderItem->update([
                'received_qty' => $lastReceiveQty + $receiveQty,
                'remarks' => $remarks,
                'update_uid' => $userId,
                'company_id' => $companyId,
                'branch_id' => $branchId
            ]);
            if(!$update) return DataResponse::Error('Fail to receive item row('.($key + 1).').');

            $item = ProductVariant::with('product')->find($purchaseOrderItem->variant_id);
            $modelId = $item->product->model_id;
            $categoryId = $item->product->category_id;
            $condition = $item->condition;
            $prepareStock = $this->prepareStock($stockLocationId,$item->id,'receive_qty',$receiveQty,$user,$purchaseOrderItem->unit_price,null,$modelId,$categoryId,$condition,$expiration_date);
            // return $prepareStock;
            if($prepareStock->status_code == 422) return DataResponse::ValidateFail($prepareStock->message);
            if($prepareStock->status_code == 500) return DataResponse::Error($prepareStock->message);
        }
        return DataResponse::JsonResult((object)[
            'amount_due' => $amountDue - ($amountDue * $tax) / 100
        ],false,$skip_message);
    }

    public function changePurchaseOrderStatus(){
        $user = UserService::getAuthUser();
    }

    public function approvePurchaseOrder(Request $req,$id=null){
        $id = $id ? $id :$req->id;
        $user = UserService::getAuthUser();
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
        $rows = PurchaseOrder::where('branch_id',$user->branch_id)->get();
        $orderItems = PurchaseOrderItem::from('purchase_order_items as oi')->join('product_variants as pv','oi.variant_id','pv.id')->join('products as p','p.id','=','pv.product_id')->join('models as m','m.id','p.model_id')
        ->selectRaw('oi.qty,oi.total_price,oi.unit_price,oi.discount_type,oi.discount_amount,pv.size,pv.color,pv.sku,pv.weight,pv.width,pv.length,pv.expires_at,pv.condition,condition_percentage,pv.material,m.name as model_name,p.name,p.code,description')
        ->get();
        foreach($rows as $row){
            $row->order_items = $this->getPurchaseOrderItems($orderItems,$row->variant_id);
        }
        return ApiResponse::JsonResult($rows);
    }

    public function getPurchaseOrder(Request $req,$id=null){
        $id = $id ? $id : $req->id;
        $user = UserService::getAuthUser();
        $row = PurchaseOrder::where('branch_id',$user->branch_id)->find($id);
        $orderItems = PurchaseOrderItem::from('purchase_order_items as oi')->join('product_variants as pv','oi.variant_id','pv.id')->join('products as p','p.id','=','pv.product_id')->join('models as m','m.id','p.model_id')
        ->selectRaw('oi.id,oi.qty,oi.total_price,oi.unit_price,oi.discount_type,oi.discount_amount,pv.size,pv.color,pv.sku,pv.weight,pv.width,pv.length,pv.expires_at,pv.condition,condition_percentage,pv.material,m.name as model_name,p.name,p.code,description')
        ->get();
        if($row){
            $row->order_items = $this->getPurchaseOrderItems($orderItems,$row->variant_id);
        }
        return ApiResponse::JsonResult($row);
    }

    private function getPurchaseOrderItems($orderItems,$variantId){
        $items = [];
        foreach($orderItems as $item){
            if($item->variant_id == $variantId){
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
        //* merge item by same unit_price && discount_amount && discount_type
        $mergeItems = $this->mergerOrderItems($orderItems);
        // return array_values($mergeItems);
        $due_amount = 0;
        $total_amount = 0;
        foreach($mergeItems as $row){
            $id = isset($row['id']) ? $row['id']:null;
            $row['purchase_id'] = $purchaseId;
            $validate = $this->purchaseOrderItemValidation(new Request($row));
            if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
            $inputs = $validate->validated();
            $productId = ProductVariant::find($inputs['variant_id'])->take(1)->value('product_id');
            $inputs['update_uid'] = $userId;
            $inputs['branch_id'] = $branchId;
            $inputs['company_id'] = $companyId;
            $inputs['product_id'] = $productId;
            if($id){
                $poItem = PurchaseOrderItem::where('branch_id',$branchId)->find($id);
            }else{
                $inputs['create_uid'] = $userId;
                //* calculate order item
                // $total_price = $this->calculatePrice($unitPrice,$qty,$discountAmount,$discountType);
                $inputs['total_price'] = $row['due_amount'];
                $total_amount += $row['total_amount'];
                $due_amount += $row['due_amount'];
                $create = PurchaseOrderItem::create($inputs);
                if(!$create) return DataResponse::Error('fail to save purchase items');
            }
        }
        return DataResponse::JsonResult((object)[
            'due_amount' => $due_amount,
            'total_amount' => $total_amount
        ],false,'Saved');
    }


    private function mergerOrderItems($items){
        $merged = [];
        foreach ($items as $row) {
            $key = $row['variant_id'].'-'.$row['discount_amount'].'-'.$row['discount_type'];
            $unitPrice = $row['unit_price'];
            $qty = $row['qty'];
            $discountAmount = $row['discount_amount'];
            $discountType = $row['discount_type'];
            if (isset($merged[$key])) {
                //* Merge quantities and prices, you can adjust this to fit your needs
                $merged[$key]['qty'] += $row['qty'];
                $merged[$key]['due_amount'] = $this->calculatePrice($unitPrice,$merged[$key]['qty'],$discountAmount,$discountType);
                $merged[$key]['total_amount'] = $merged[$key]['qty'] * $unitPrice;
            } else {
                $merged[$key] = $row;
                $merged[$key]['due_amount'] = $this->calculatePrice($unitPrice,$qty,$discountAmount,$discountType);
                $merged[$key]['total_amount'] = $qty * $unitPrice;
            }
        }
        return array_values($merged);
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
    public function prepareStock($stockLocationId=null,$variant_id,$targetCol,$targetValue,$user,$itemCost,$itemRef=null,$modelId=null,$categoryId=null,$condition=null,$expirationDate=null){
        $today = date('Y-m-d');
        $branchId = $user->branch_id;
        $userId = $user->id;
        $companyId = $user->company_id;
        $operator = isset($this->stockOperator[$targetCol]) ? $this->stockOperator[$targetCol] : null;
        if(!$operator) return DataResponse::ValidateFail('You provided the wrong target.');
        if(!$stockLocationId) $stockLocationId = StockLocation::where('company_id',$companyId)->where('branch_id',$branchId)->take(1)->value('id');
        //** --- daily stock process -----------
        $todayStock = DailyStock::where('branch_id',$branchId)->where('variant_id',$variant_id)->where('stock_location_id',$stockLocationId)->whereDate('created_at',$today)->first();
        // return $todayStock;
        $beginQty = 0;
        $endingQty = 0;
        $targetColValue = 0;

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
            // $beginQty = $targetValue;
            // $endingQty = $targetValue;
            // $targetColValue += $operator.$targetValue;
            // var_dump($beginQty);
            // var_dump($st)
            if($stock) {
                $beginQty += $stock->ending_qty + ($operator.$targetValue);
                $endingQty += $stock->ending_qty + ($operator.$targetValue);
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
            // var_dump($arr);
            $createDailyStock = DailyStock::create($arr);
            if(!$createDailyStock) return DataResponse::Error('Fail to save daily stock');
        }else{
            $beginQty += $operator.$targetValue;
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
        $sku = $this->createSkuCode($modelId,$categoryId,$condition,$branchId,$itemCost,$variant_id,$expirationDate);

        $foundBySku = Stock::where('branch_id',$branchId)->where('stock_location_id',$stockLocationId)->where('sku',$sku)->where('cost',$itemCost)->first();
        if($itemRef){
            if(is_numeric($itemRef)) {
                $foundBySku = Stock::where('id',$itemRef)->first();
            }
            else {
                $foundBySku = Stock::where('sku',$itemRef)->first();
            }
        }

        $stockQty = 0;
        if($foundBySku){
            $stockQty = $foundBySku->qty;
            $stockQty += $operator.$targetValue;
            $updateStock = $foundBySku->update([
                'stock_location_id' => $stockLocationId,
                'qty' => $stockQty,
                'variant_id' => $variant_id,
                'cost' => $itemCost,
                'update_uid' => $userId,
                'company_id' => $companyId,
                'branch_id' => $branchId
            ]);
            if(!$updateStock) return DataResponse::Error('Fail to update stock.');
        }else{
            $stockQty += $operator.$targetValue;
            $addNewStock = Stock::insert([
                'sku' => $sku,
                'stock_location_id' => $stockLocationId,
                'qty' => $stockQty,
                // 'batch_number'=> date('YMdhis').mt_rand(1000, 9999),
                'variant_id' => $variant_id,
                'cost' => $itemCost,
                'create_uid' => $userId,
                'update_uid' => $userId,
                'company_id' => $companyId,
                'branch_id' => $branchId
            ]);
            if(!$addNewStock) return DataResponse::Error('Fail to add new stock!');
        }
        // }

        $stockMovement = $this->createStockMovementLog([
            'variant_id' => $variant_id,
            'cost' => $itemCost
        ],$targetCol,$targetValue,$user);
        if($stockMovement->status_code == 422) return DataResponse::ValidateFail($stockMovement->message);
        return DataResponse::JsonResult(null,false,'Stock prepared!');
    }


    function stockMovementValidation(Request $req){
        // $movementTypes = implode(',',$this->getStockMovementTypesArray());
        return validator($req->all(),[
            // 'movement_type' => 'required|in:'.$movementTypes,
            'variant_id' => 'required|int|exists:product_variants,id',
            'cost' => 'nullable|numeric',
            'adjustment_qty' => 'nullable|numeric',
            'transfer_in_qty' => 'nullable|numeric',
            'transfer_out_qty' => 'nullable|numeric',
            'sold_qty' => 'nullable|numeric',
            'receive_qty' => 'nullable|numeric'
        ]);
    }


    /**
     * Summary of createStockMovementLog
     * @param mixed $arr => require [variant_id,qty] ,
     * note* Qty can be one of (adjustment_qty,transfer_in_qty,transfer_out_qty,sold_qty,purchase_qty)
     * @param mixed $variant_id
     * @return void
     */
    function createStockMovementLog($arr,$targetCol,$targetValue,$user):object{
        $today = date('Y-m-d');
        $arr[$targetCol] = $targetValue;
        $branch_id = $user->branch_id;
        $userId = $user->id;
        $companyId = $user->company_id;
        $operator = $this->stockOperator[$targetCol];
        $validate = $this->stockMovementValidation(new Request($arr));
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        // print_r($operator);
        $inputs = $validate->validated();
        $inputs['update_uid'] = $userId;
        $inputs['branch_id'] = $branch_id;
        $inputs['company_id'] = $companyId;
        //** ------ daily stock movement log by user */
        $todayMovement = StockMovement::where('branch_id',$branch_id)->where('cost',$inputs['cost'])->where('variant_id',$inputs['variant_id'])->where('create_uid',$userId)->whereDate('created_at',$today)->first();
        if($todayMovement){
            // print_r('asdfs');
            $adjustStock = $todayMovement->{$targetCol};
            $adjustStock += $operator.$targetValue;
            $inputs[$targetCol] = $adjustStock;
            $update = $todayMovement->update($inputs);
            if(!$update) return DataResponse::Error('Fail to update stock movement log!');
        }else{
            $inputs['create_uid'] = $userId;
            $inputs[$targetCol] = $operator.$targetValue;
            $create = StockMovement::create($inputs);
            if(!$create) return DataResponse::Error('Fail to create stock movement log!');
        }


        return DataResponse::JsonResult(null,false,'Stock log created');
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
