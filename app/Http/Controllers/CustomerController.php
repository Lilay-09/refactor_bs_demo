<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\Invoice;
use App\Services\UserService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    //
    private function customerValidation(Request $req){
        return validator($req->all(),[
            'name' => 'nullable|string|max:50',
            'name_kh' => 'nullable|string|max:150',
            'phone' => 'required|string|min:8|max:25',
            'email' => 'nullable|email',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'address' => 'nullable|string|max:250',
            'address_kh' => 'nullable|string|max:250',
            'customer_type_id' => 'required|int|exists:customer_types,id'
        ]);
    }
    public function createCustomer(Request $req){
        $validate = $this->customerValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $user = UserService::getAuthUser();
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $customerId = $inputs['customer_type_id'];
        $defaultDiscount = CustomerType::where('company_id',$user->company_id)->where('id',$customerId)->take(1)->value('discount_percent');
        $inputs['discount_percent'] = isset($inputs['discount_percent']) ? $inputs['discount_percent'] : $defaultDiscount;
        $duplicatedPhone = Customer::where('company_id',$user->company_id)->where('phone',$inputs['phone'])->first();
        if($duplicatedPhone) {
            $isDiffBranch = $duplicatedPhone->branch_id !== $user->branch_id;
            $diffBranchText = null;
            if($isDiffBranch) $diffBranchText = ' but in another branch';
            return ApiResponse::Duplicated('Phone number '.$inputs['phone'].' is already taken.'.$diffBranchText);
        }
        $create = Customer::create($inputs);
        if($create) return ApiResponse::JsonResult(null,false,'Created');
        return ApiResponse::Error('Fail to saved');
    }

    public function getCustomers(Request $req){
        $user = UserService::getAuthUser();
        $query = Customer::with('type:id,name')->where('company_id',$user->company_id);
        if($req->search){
            $query->where('name','ilike','%'.$req->search.'%')->orWhere('name_kh','ilike','%'.$req->search.'%')->orWhere('phone',$req->search);
        }
        $rows = $query->get();
        foreach($rows as $row){
            $row->customer_type = $row->type->name;
            unset($row->type);
        }
        return ApiResponse::Pagination($rows,$req);
    }

    public function getCustomer(Request $req,$id=null){
        $id = $id ? $id : $req->id;
        $user = UserService::getAuthUser();
        $row = Customer::where('company_id',$user->company_id)->find($id);
        return ApiResponse::JsonResult($row);
    }

    public function updateCustomer(Request $req,$id=null){
        $id = $id ? $id : $req->id;
        $validate = $this->customerValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $user = UserService::getAuthUser();
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $duplicatedPhone = Customer::where('company_id',$user->company_id)->where('id','!=',$id)->where('company_id',$user->company_id)->where('phone',$inputs['phone'])->first();
        if($duplicatedPhone) {
            $isDiffBranch = $duplicatedPhone->branch_id !== $user->branch_id;
            $diffBranchText = null;
            if($isDiffBranch) $diffBranchText = ' but in another branch';
            return ApiResponse::Duplicated('Phone number '.$inputs['phone'].' is already taken.'.$diffBranchText);
        }
        $customer = Customer::where('company_id',$user->company_id)->find($id);
        if(!$customer) return ApiResponse::NotFound('Customer not found');
        $defaultDiscount = $customer->discount_percent > 0 ? $customer->discount_percent :  CustomerType::where('company_id',$user->company_id)->where('id',$id)->take(1)->value('discount_percent');
        $inputs['discount_percent'] = isset($inputs['discount_percent']) ? $inputs['discount_percent'] : $defaultDiscount;
        $update = $customer->update($inputs);
        if($update) return ApiResponse::JsonResult(null,false,'Updated');
        return ApiResponse::Error('Fail to update');
    }

    public function deleteCustomer(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $customer = Customer::where('company_id',$user->company_id)->where('void',0)->find($id);
        if($customer){
            // $recordedInInvoice = Invoice::where('customer_id',$id)->first();
            // if($recordedInInvoice) return ApiResponse::ValidateFail('To keep customer history, you cannot delete!');
            // $customer->delete();
            $customer->update([
                'void' => 1,
                'void_uid' => $user->id
            ]);
            return ApiResponse::JsonResult(null);
        }
        return ApiResponse::NotFound('Customer not found');
    }
}
