<?php

namespace App\Services;
use ApiResponse;
use App\Models\MovementType;
use App\Models\Stock;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentDetail;
use App\Models\StockMovement;
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
            return ApiResponse::JsonResult(null,false,'Created');
        }catch(Exception $e){
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
            DB::rollBack();
            return ApiResponse::Error('Fail to save missing item');
        }
    }

    private function getStockMovementItemDetails($rows,$sku){
        foreach($rows as $row){
            if($row->sku == $sku){
                return $row;
            }
        }
        return null;
    }

    public function getOneAdjustmentItem(Request $req,$type){
        $id = $req->id;
        $user = UserService::getAuthUser();
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


    public function updateAdjustmentStock(Request $req,$type,$targetCol){
        $id = $req->id;
        $user = UserService::getAuthUser();
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
}
