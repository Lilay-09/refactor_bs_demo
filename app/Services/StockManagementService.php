<?php

namespace App\Services;
use ApiResponse;
use App\Models\DailyStock;
use App\Models\MovementType;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentDetail;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Carbon\Carbon;
use DataResponse;
use DB;
use Exception;
use Log;
use Illuminate\Http\Request;

class StockManagementService
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
    public function countStockAdjustment($type,$user,$approve=true){
        $query = StockAdjustment::where('void',0)->where('type',$type)->where('company_id',$user->company_id);
        if(!$approve) $query->whereIn('status',['pending','partially approved']);
        else $query->where('status','all approved');
        $count = $query->count();
        return ApiResponse::JsonResult($count,false,'Count unapprove');
    }
    public function createAdjustmentStock(Request $req,$prefixRefCode,$type,$targetCol){
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
            $inputs['type'] = $type;
            $warehouseId = $inputs['warehouse_id'];
            $create = StockAdjustment::create($inputs);
            if(!$create) return ApiResponse::Error('Fail to create!');
            $code = $prefixRefCode.'-'.str_pad($create->id, 8, '0', STR_PAD_LEFT);
            StockAdjustment::find($create->id)->update([
                'ref_code' => $code
            ]);
            $mergeItem = $this->mergeAdjustmentDetails($items,$create->id);
            if($mergeItem->error) return ApiResponse::flex($mergeItem);
            $items = $mergeItem->data;
            $createItems = $this->createOrUpdateAdjustmentDetails($items,$create->id,$warehouseId,$code,$user,$targetCol);
            if($createItems->error) return ApiResponse::flex($createItems);
            DB::commit();
            // return StockAdjustmentDetail::where('stock_adjustment_id',$create->id)->get();
            return ApiResponse::JsonResult(null,false,'Created');
        }catch(Exception $e){
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            return ApiResponse::Error('Fail to save missing item');
        }
    }

    public function voidAllAdjustment(Request $req,$type,$user){
        $items = $req->items ?? [];
        if(empty($items)) return ApiResponse::ValidateFail('Please provide delete list!');
        $skipApprovedItems = [];
        $skipPartiallyApprovedItems = [];
        foreach($items as $key=>$item){
            $rowId = $item['id'] ?? null;
            if(!$rowId) return ApiResponse::ValidateFail('Please item identity on row '.($key+1));
            $stockAdjustment = StockAdjustment::where('type',$type)->find($rowId);
            if(!$stockAdjustment) return ApiResponse::ValidateFail('Item not found on row '.($key+1));
            if($stockAdjustment->void) continue;
            if($stockAdjustment->status == 'all approved' && $user->system_admin){
                $skipApprovedItems[] = "$stockAdjustment->ref_code";
                continue;
            }else if($stockAdjustment->status == 'partially approved' && $user->system_admin){
                $skipPartiallyApprovedItems[] = "$stockAdjustment->ref_code";
                continue;
            }
            // if($stockAdjustment) return ApiResponse::NotFound('Missing Stock info not found by row '.($key+1));
            $stockAdjustment->update([
                'void' => 1,
                'void_uid' => $user->id
            ]);
            StockAdjustmentDetail::where('stock_adjustment_id',$rowId)->where('void',0)->update([
                'void' => 1,
                'void_uid' => $user->id
            ]);
        }


        return ApiResponse::JsonRaw((object)[
            'error' => false,
            'status' => 'OK',
            'message' => 'Voided'.((isset($skipApprovedItems[0]) || isset($skipPartiallyApprovedItems[0])) ? ', Please Contact Super Admin for the undeletable.' : ''),
            'skip' => (object)[
                'approved' => 'List of approved '. implode(',',$skipApprovedItems),
                'partially_approved' => 'List of partially approved '.implode(',',$skipPartiallyApprovedItems),
            ],
        ]);
        // return ApiResponse::JsonResult(null,false,$message);
    }

    public function getStockAdjustmentList(Request $req,$type,$user){
        $search = $req->search ?? null;
        $startDate = $req->startDate ?? null;
        $endDate = $req->endDate ?? null;
        $status = $req->status ?? null;
        $warehouse = $req->warehouse ?? null;
        $query = StockAdjustment::where('type',$type)->where('void',0)->where('company_id',$user->company_id)->with(['warehouse','createUser','updateUser','approveUser'])->selectRaw('id,ref_code,reason,type,status,approved_uid,approved_date,create_uid,update_uid,warehouse_id,created_at,updated_at');
        if($search){
            $query->where('ref_code','ilike','%'.$search.'%')
            ->orWhereHas('details',function($q) use($search){
                $q->where('item_ref','ilike','%'.$search.'%');
            });
        }

        if($status){
            $statusArr = explode(',',$status);
            $statusArr = array_map(function ($status) {
                return $status === 'partially approved' ? ($status == 'approved' ? 'all approved': 'pending') : $status;
            }, $statusArr);
            $query->whereIn('status', $statusArr);
        }

        if($warehouse){
            $warehouseArr = explode(',',$warehouse);
            $query->whereHas('warehouse', function($q) use($warehouseArr){
                $q->whereIn('id',$warehouseArr);
            });
        }
        if($startDate && $endDate){
            $startDate = Carbon::parse($startDate); // Ensures the format is correct
            $endDate = Carbon::parse($endDate);
            $startDate = date('Y-m-d',strtotime($startDate));
            $endDate = date('Y-m-d',strtotime($endDate));
            $query->whereBetween('approved_date',[$startDate,$endDate])->orWhereDate('approved_date',$endDate);
        }
        $missingItems = $query->orderByDesc('id')->get();
        foreach($missingItems as $item){
            $item->create_user_name = $item->createUser->user_name;
            $item->update_user_name = $item->updateUser->user_name;
            $item->approve_user_name = $item->approveUser ? $item->approveUser->user_name : null;
            $item->warehouse_name = $item->warehouse ? $item->warehouse->name : null;
            if($item->status == 'all approved') $item->status = 'approved';
            unset($item->createUser,$item->approveUser,$item->updateUser,$item->warehouse);
            $item->total_qty = 0;
            foreach($item->details as $detail){
                $item->total_qty += $detail->qty;
            }
            unset($item->details);
        }
        return ApiResponse::Pagination($missingItems,$req,'Get All Missing Items');
    }


    //** void */
    function approveByItemId($req,$type,$targetColOrKey,$user){
        $id = $req->id;
        $detail = StockAdjustmentDetail::where('void',0)->where('company_id',$user->company_id)->find($id);
        if(!$detail) return ApiResponse::NotFound('Item not found');
        $warehouseId = StockAdjustment::where('void',0)->where('id',$detail->stock_adjustment_id)->take(1)->value('warehouse_id');
        if(!$warehouseId) return ApiResponse::NotFound('Warehouse not found!');
        if($detail->status == 'approved') return ApiResponse::Duplicated('This item has already approved!');
        DB::beginTransaction();
        try{
            $detail->update([
                'status' => 'approved',
                'approved_uid' => $user->id,
                'approved_date' => now(),
            ]);
            $qty = $detail->qty;
            $prepareStock = $this->prepareStock($warehouseId,$detail->variant_id,'missing_qty',$qty,$user,$detail->cost,$detail->item_ref);
            if($prepareStock->error) return ApiResponse::flex($prepareStock);
            DB::commit();
            //** Update Parent Status */
            $this->updateAdjustmentStockStatus($detail->stock_adjustment_id,'Missing',$user);
            return ApiResponse::JsonResult(null,false,'Approved! Your stock has been reduced by '.$qty.' unit(s) out.');
        }catch(Exception $e){
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            return ApiResponse::Error('Fail to save missing item');
        }
    }

    public function approveAdjustmentByCheckList(Request $req,$type,$targetColOrKey,$user){
        $id = $req->id;
        $items = $req->items ?? [];
        $success = 0;
        $skipCount = 0;
        $skipRows = [];
        if(empty($items)) return ApiResponse::ValidateFail('Please provide list of approve items');
        $stockAdjustment = StockAdjustment::where('void',0)->where('type',$type)->where('company_id',$user->company_id)->find($id);
        if(!$stockAdjustment) return ApiResponse::NotFound('Missing stock not found');
        if($stockAdjustment->status === 'all approved') return ApiResponse::Duplicated('All items were approved, you cannot approve again!');
        DB::beginTransaction();
        try{
            foreach($items as $key=>$item){
                $rowId = $item['id'] ?? null;
                if(!$rowId) return ApiResponse::ValidateFail('Please provide item identity!');
                $detail = StockAdjustmentDetail::where('void',0)->find($rowId);
                if(!$detail) return ApiResponse::NotFound('Adjustment Item not found by row '.($key + 1).'.');
                if($detail->status == 'approved') {
                    $skipCount +=1;
                    $skipRows[] = $detail->item_ref;
                    continue;
                }
                $qty = $detail->qty;
                $detail->update([
                    'approved_date' => now(),
                    'approved_uid' => $user->id,
                    'status' => 'approved'
                ]);
                $prepareStock = $this->prepareStock($stockAdjustment->warehouse_id,$detail->variant_id,$targetColOrKey,$qty,$user,$detail->cost,$detail->item_ref);
                if($prepareStock->error) return ApiResponse::flex($prepareStock);
                $this->updateAdjustmentStockStatus($id,'Missing',$user);
                $success +=1;
            }
            DB::commit();
            // return ApiResponse::JsonResult(null,false,'Approved');
            return ApiResponse::JsonRaw((object)[
                'error' => false,
                'status' => 'OK',
                'message' => 'Approved!',
                'success_count' => $success,
                'skip' => implode(',',$skipRows),
                'skip_count' => $skipCount
            ]);
        }catch(Exception $e){
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            return ApiResponse::Error('Fail to approve missing item');
        }
    }

    function approveAllAjustment($req,$type,$targetColOrKey,$user){
        $items = $req->items ?? [];
        if(empty($items)) return ApiResponse::ValidateFail('Please provide delete list!');
        $success = 0;
        $skipCount = 0;
        $skipRows = [];
        DB::beginTransaction();
        try{
            foreach($items as $key=>$item){
                $rowId = $item['id'] ?? null;
                if(!$rowId) return ApiResponse::ValidateFail('Please item identity on row '.($key+1));
                $stockAdjustment = StockAdjustment::where('type',$type)->find($rowId);
                if(!$stockAdjustment) return ApiResponse::ValidateFail('Item not found on row '.($key+1));
                if($stockAdjustment->void) continue;
                if($stockAdjustment->status == 'all approved') {
                    $skipRows[] = "$stockAdjustment->ref_code";
                    $skipCount += 1;
                    continue;
                }

                foreach($stockAdjustment->details as $detail){
                    $qty = $detail->qty;
                    if($detail->status == 'approved') continue;
                    $prepareStock = $this->prepareStock($stockAdjustment->warehouse_id,$detail->variant_id,$targetColOrKey,$qty,$user,$detail->cost,$detail->item_ref);
                    if($prepareStock->error) return ApiResponse::flex($prepareStock);
                    $this->updateAdjustmentStockStatus($stockAdjustment->id,$type,$user);
                    StockAdjustmentDetail::find($detail->id)->update([
                        'status' => 'approved',
                        'approved_uid' => $user->id,
                        'approved_date' => now()
                    ]);
                }
                $stockAdjustment->update([
                    'status' => 'all approved',
                    'approved_uid' => $user->id,
                    'approved_date' => now()
                ]);
                $success ++;
            }

            DB::commit();

            return ApiResponse::JsonRaw((object)[
                'error' => false,
                'status' => 'OK',
                'message' => 'Approved!',
                'success_count' => $success,
                'skip' => implode(',',$skipRows),
                'skip_count' => $skipCount
            ]);
        }catch(Exception $e){
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            return ApiResponse::Error('Fail to approve!');
        }
    }


    public function approveAdjustmentAndRelatedItems(Request $req,$type,$targetColOrKey,$user){
        $id = $req->id;
        $stockAdjustment = StockAdjustment::where('void',0)->where('type',$type)->where('company_id',$user->company_id)->find($id);
        if(!$stockAdjustment) return ApiResponse::NotFound('Missing stock not found');
        if($stockAdjustment->status === 'all approved') return ApiResponse::Duplicated('All items were approved, you cannot approve again!');
        DB::beginTransaction();
        try{
            $stockAdjustment->update([
                'status' => 'all approved',
                'approved_date' => now(),
                'approved_uid' => $user->id
            ]);
            foreach($stockAdjustment->details as $detail){
                $qty = $detail->qty;
                if($detail->status == 'approved') continue;
                StockAdjustmentDetail::where('id',$detail->id)->update([
                    'status' => 'approved',
                    'approved_date' => now(),
                    'approved_uid' => $user->id
                ]);
                if($detail){
                    $prepareStock = $this->prepareStock($stockAdjustment->warehouse_id,$detail->variant_id,$targetColOrKey,$qty,$user,$detail->cost,$detail->item_ref);
                    if($prepareStock->error) return ApiResponse::flex($prepareStock);
                    $this->updateAdjustmentStockStatus($stockAdjustment->id,$type,$user);
                }
            }
            $stockAdjustment->update([
                'status' => 'all approved',
                'approved_uid' => $user->id,
                'approved_date' => now()
            ]);
            StockAdjustmentDetail::where('stock_adjustment_id',$id)->where('status','pending')->update([
                'status' => 'approved',
                'approved_date' => now(),
                'approved_uid' => $user->id
            ]);
            DB::commit();
            // return Stock::get();
            return ApiResponse::JsonResult(null,false,'All items has approved!');
        }catch(Exception $e){
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            return ApiResponse::Error('Fail to approve!');
        }


    }



    //** void adjustment */

    public function voidAdjustmentAndRelatedItems(Request $req,$type,$user){
        $id = $req->id;
        $StockAdjustment = StockAdjustment::where('company_id',$user->company_id)->where('type',$type)->find($id);
        if(!$StockAdjustment) return ApiResponse::NotFound('Missing Stock not found!');
        if($StockAdjustment->status == 'all approved') return ApiResponse::JsonResult('Missing Stock has already approved, You cannot void!');
        $void = $StockAdjustment->update([
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

    public function voidAdjustmentByItem(Request $req,$user){
        $id = $req->id;
        $detail = StockAdjustmentDetail::where('void',0)->find($id);
        if(!$detail) return ApiResponse::NotFound('Item not found');
        $detail->update([
            'void' => 1,
            'void_uid' => $user->id
        ]);
        return ApiResponse::JsonResult(null,false,'Voided');
    }
    public function voidAdjustmentByCheckItem(Request $req,$type,$user){
        $id = $req->id;
        $missingStock = StockAdjustment::where('company_id',$user->company_id)->where('void',0)->where('type',$type)->find($id);
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


    //** end void adjustment */


    private function updateAdjustmentStockStatus($id,$type,$user){
        $queryChildren = StockAdjustmentDetail::where('company_id',$user->company_id)->where('stock_adjustment_id',$id)->where('void',0);
        $childCount = $queryChildren->count();
        $children = $queryChildren->get();
        $approveCount = 0;
        foreach($children as $child){
            if($child->status == 'approved') $approveCount +=1;
        }
        if($approveCount > 0 || $approveCount > $childCount){
            StockAdjustment::where('company_id',$user->company_id)->find($id)->update([
                'status' => 'partially approved',
                'approved_uid' => $user->id,
                'approved_date' => now(),
            ]);
        }
        if($approveCount == $childCount) StockAdjustment::where('company_id',$user->company_id)->find($id)->update([
            'status' => 'all approved',
            'approved_uid' => $user->id,
            'approved_date' => now(),
        ]);
    }


    private function getStockMovementItemDetails($rows,$sku){
        foreach($rows as $row){
            if($row->sku == $sku){
                return $row;
            }
        }
        return null;
    }

    public function getOneAdjustmentItem(Request $req,$type,$user){
        $id = $req->id;
        $stockItems = GeneralSettingService::getStockItems($user);
        $adjustmentItem = StockAdjustment::with(['warehouse','createUser','updateUser','approveUser','details:id,stock_adjustment_id,item_ref as sku,qty,reason,status,approved_date,approved_uid,update_uid,create_uid'])->selectRaw('id,ref_code,reason,type,status,approved_uid,approved_date,create_uid,update_uid,warehouse_id')->where('void',0)->where('company_id',$user->company_id)->where('type',$type)->find($id);
        if(!$adjustmentItem) return ApiResponse::NotFound('Could not found the missing stock');
        $adjustmentItem->create_user_name = $adjustmentItem->createUser->user_name;
        $adjustmentItem->update_user_name = $adjustmentItem->updateUser->user_name;
        $adjustmentItem->approve_user_name = $adjustmentItem->approveUser ? $adjustmentItem->approveUser->user_name : null;
        $adjustmentItem->warehouse_name = $adjustmentItem->warehouse ? $adjustmentItem->warehouse->name : null;
        $adjustmentItem->created_at = date('Y-m-d',strtotime($adjustmentItem->created_at));
        $approvedCount = 0;
        $unapprovedCount = 0;
        foreach($adjustmentItem->details as $detail){
            $stockItemDetails = $this->getStockMovementItemDetails($stockItems,$detail->sku);
            $detail->create_user_name = $detail->createUser->user_name;
            $detail->update_user_name = $detail->updateUser->user_name;
            $detail->approve_user_name = $detail->approveUser ? $detail->approveUser->user_name : null;
            $detail->item_name = $stockItemDetails ? $stockItemDetails->product_name. ' |'.$stockItemDetails->product_details:'';
            if($detail->status === 'approved') $approvedCount +=1;
            else $unapprovedCount += 1;
            unset($detail->createUser,$detail->approveUser,$detail->updateUser);
            $adjustmentItem->total_qty += $detail->qty;
        }
        $adjustmentItem->approved_count = $approvedCount;
        $adjustmentItem->unapproved_count = $unapprovedCount;

        unset($adjustmentItem->approveUser,$adjustmentItem->createUser,$adjustmentItem->updateUser,$adjustmentItem->warehouse);
        return ApiResponse::JsonResult($adjustmentItem,false,'Get One '.$type.' Stock');
    }


    public function updateAdjustmentStock(Request $req,$type,$targetCol,$user){
        $id = $req->id;
        $validate = $this->stockAdjustmentValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $missingItem = StockAdjustment::where('company_id',$user->company_id)->where('type',$type)->find($id);
        if(!$missingItem) return ApiResponse::NotFound('Adjustment item not found');
        if($missingItem->status === 'all approved') return ApiResponse::Duplicated('You cannot modify approved!');
        $items = $inputs['items'];
        unset($inputs['items']);
        DB::beginTransaction();
        try{
            $inputs['update_uid'] = $user->id;
            $inputs['branch_id'] = $user->branch_id;
            $inputs['company_id'] = $user->company_id;
            $update = $missingItem->update($inputs);
            if(!$update) return ApiResponse::Error('Fail to update adjustment stock');
            $mergeItem = $this->mergeAdjustmentDetails($items,$id);
            if($mergeItem->error) return ApiResponse::flex($mergeItem);
            $items = $mergeItem->data;
            $updateItems = $this->createOrUpdateAdjustmentDetails($items,$id,$missingItem->warehouse_id,$missingItem->ref_code,$user,$targetCol);
            if($updateItems->error) return ApiResponse::flex($updateItems);
            DB::commit();
            return ApiResponse::JsonResult(null,false,'Updated');
        }catch(Exception $e){
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            return ApiResponse::Error('Fail to save adjustment item');
        }
    }


    function getStockMovementTypesArray(){
        return MovementType::all()->pluck('name')->toArray();
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

    public function createOrUpdateAdjustmentDetails($items,$adjustId,$warehouseId,$missingCode,$user,$targetCol){
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
            $existItem = Stock::where('company_id',$user->company_id)->where('sku',$sku)->where('stock_location_id',$warehouseId)->first();
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
                $keepItems[] = $id;
                $update = $adjustItem->update($item);
                if(!$update) return DataResponse::Error('Fail to update');
                $operator = $this->stockOperator['missing_qty'];
                StockMovement::where('reference_no',$id)->where('company_id',$user->company_id)->update([
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
                $keepItems[] = $createDetails->id;
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


    /**
     * Summary of mergeAdjustmentDetails
     * @param mixed $items
     * @param mixed $adjustId
     * @return object
     * if same sku sum QTY
     */
    public function mergeAdjustmentDetails($items,$adjustId){
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

            if($stock && $operator != '+') {
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

        $foundBySku = Stock::where('branch_id',$branchId)->where('stock_location_id',$stockLocationId)->where('variant_id',$variant_id)->where('sku',$sku)->first();
        if($itemRef){
            if(is_numeric($itemRef)) {
                $foundBySku = Stock::where('branch_id',$branchId)->where('id',$itemRef)->where('variant_id',$variant_id)->where('stock_location_id',$stockLocationId)->first();
            }
            else {
                $foundBySku = Stock::where('branch_id',$branchId)->where('sku',$itemRef)->where('variant_id',$variant_id)->where('stock_location_id',$stockLocationId)->first();
            }
        }
        $stockQty = 0;
        $retail_price = $variant->retail_price;
        $wholesale_price = $variant->wholesale_price;
        if($foundBySku){
            if($foundBySku->qty <=0 && $operator != '+') return DataResponse::ValidateFail('Stock quantity found '.$foundBySku->qty);
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
        }else if($operator != '-'){
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
        return DataResponse::JsonResult(null,false,'Stock prepared!');
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
}
