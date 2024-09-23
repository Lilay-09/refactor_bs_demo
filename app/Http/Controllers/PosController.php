<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DailyStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\InvoiceSerivce;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\ReceiptPayment;
use App\Models\ReceiptService;
use App\Models\Service;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Services\UserService;
use DataResponse;
use Exception;
use Helper;
use Illuminate\Http\Request;
use Log;
use DB;

class PosController extends Controller
{
    //

    function validateOrder(Request $req){
        return validator($req->all(),[
            'customer_id' => 'nullable|int|exists:customers,id',
            'tax' => 'nullable|numeric',
            'discount_type' => 'nullable|in:$,%',
            'discount_amount' => 'nullable|numeric',
            'items' => 'nullable|array',
            'services' => 'nullable|array'
        ]);
    }

    function orderItemValidation(Request $req){
        return validator($req->all(),[
            'discount_amount' => 'nullable|numeric',
            'tax' => 'nullable|int',
            'discount_type' => 'nullable|in:$,%',
            'item_ref' => 'required',
            'qty' => 'required|int',
            'description' => 'nullable|string|max:250'
        ]);
    }

    function serviceValidation(Request $req){
        return validator($req->all(),[
            'discount_amount' => 'nullable|numeric',
            'tax' => 'nullable|int',
            'discount_type' => 'nullable|in:$,%',
            'service_id' => 'required',
            'qty' => 'required|int',
            'description' => 'nullable|string|max:250'
        ]);
    }

    public function AddItems(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->validateOrder($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $customer_id = isset($inputs['customer_id']) ? $inputs['customer_id'] : null;
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $items = $inputs['items'] ?? [];
        $services = $inputs['services'] ?? [];
        $customerId = $inputs['customer_id'] ?? null;
        unset($inputs['items'],$inputs['services']);
        if(!isset($services[0]) && !isset($items[0])) return ApiResponse::ValidateFail('There should be at least select one of service or product.');
        $customer = null;
        $defaultCustomerDisAmount = 0;
        if($customer_id){
            $customer = Customer::where('id',$customerId)->first();
            if($customer){
                $defaultCustomerDisAmount = $customer->discount_percent;
            }
        }

        $discountInfo = (object)[
            'amount' => $inputs['discount_amount'] ?? 0,
            'type' => $inputs['discount_type'] ?? '%',
            'default_discount' => $defaultCustomerDisAmount
        ];
        $tax = $inputs['tax'] ?? 0;

        $mergeItems = $this->mergeItem($items,$discountInfo,$tax,$defaultCustomerDisAmount);
        // return $mergeItems;
        $mergeService = $this->mergeServices($services,$discountInfo,$tax,$defaultCustomerDisAmount);
        if($mergeItems->status_code == 422) return ApiResponse::ValidateFail($mergeItems->message);
        if($mergeItems->status_code == 404) return ApiResponse::NotFound($mergeItems->message);
        if($mergeService->status_code == 422) return ApiResponse::ValidateFail($mergeService->message);
        if($mergeService->status_code == 404) return ApiResponse::NotFound($mergeService->message);
        DB::beginTransaction();
        try{
            $getInvoice = $this->generateInvoice($req,$mergeItems,$mergeService,$discountInfo,$tax,$user,$customer);
            if($getInvoice->status_code == 422) return ApiResponse::ValidateFail($getInvoice->message);
            if($getInvoice->status_code == 404) return ApiResponse::NotFound($getInvoice->message);
            if($getInvoice->status_code == 500) return ApiResponse::Error($getInvoice->message);
            DB::commit();
            // return Invoice::get();
            return ApiResponse::JsonResult(null,false,'Created');

        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getMessage());
            Log::error($e->getTraceAsString());
        }
    }

    function generateReceipt(Request $req,$mergeItems,$mergeServices,$discountInfo,$tax,$user){
        $validate = validator($req->all(),[
            'customer_id' => 'nullable|int|exists:customers,id',
            'cash' => 'nullable|numeric',
            'bank_id' => 'nullable|exists:banks,id',
            'bank_number' => 'nullable|string|max:50',
            'bank_amount' => 'nullable|numeric',
        ]);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $isGeneral = !isset($inputs['customer_id']);
        $inputs['receipt_date'] = now();
        $items = $mergeItems->data->items ?? [];
        $services = $mergeServices->data->services ?? [];
        $total_amount = $mergeItems->data->total_amount + $mergeServices->data->total_amount;
        $total_due = $mergeItems->data->total_due + $mergeServices->data->total_due;
        $cash = $inputs['cash'] ?? 0;
        $bankNumber = $inputs['bank_number'] ?? null;
        $bankId = $inputs['bank_id'] ?? null;
        $bankAmt = $inputs['bank_amount'] ?? 0;
        if($bankAmt>0){
            if(!$bankId) return DataResponse::ValidateFail('Please select bank');
        }

        $paymentAmout = number_format($cash + $bankAmt,2);
        if($paymentAmout > $total_due) return DataResponse::ValidateFail('The payment amount is $'.$total_due.' only');
        if($paymentAmout !== $total_due)  return DataResponse::ValidateFail('Payment amount must be $'.$total_due.', but your input is $'.$paymentAmout.'. missing $'.abs($total_due - $paymentAmout).'!!!');

        $createReceipt = Receipt::create([
            'receipt_date' => now(),
            'total_amount' => $total_amount,
            'tax' => $tax,
            'due_amount' => $total_due,
            'paid_amount' => $paymentAmout,
            'general' => $isGeneral,
            'update_uid' => $user->id,
            'create_uid' => $user->id,
            'default_discount' => $discountInfo->default_discount,
            'discount_amount' => $discountInfo->amount,
            'discount_type' => $discountInfo->type,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id
        ]);
        if(!$createReceipt) return DataResponse::Error('Fail to generate receipt');
        $receiptId = $createReceipt->id;
        if(isset($items[0])){
            $generateReceiptItems = $this->generateReceiptItems($items,$receiptId,$user);
            if($generateReceiptItems->error) return $generateReceiptItems;
        }
        if(isset($services[0])){
            $generateReceiptServices = $this->generateReceiptSerivces($services,$receiptId);
            if($generateReceiptServices->error) return $generateReceiptServices;
        }

        //** add payment history */
        if($cash){
            ReceiptPayment::create([
                'receipt_id' => $receiptId,
                'method' => 'Cash',
                'amount' => $cash,
            ]);
        }
        if($bankAmt){
            ReceiptPayment::create([
                'receipt_id' => $receiptId,
                'method' => 'Bank',
                'amount' => $bankAmt,
                'bank_id' => $bankId,
                'bank_number' => $bankNumber
            ]);
        }

        $ref_code = Helper::setRefCode('receipt_code_controls','receipts','ref_code',$user->branch_id,$user->company_id,$receiptId,date('Y-m-d'),'R');
        if($ref_code->status !== 'OK') return DataResponse::Error('Fail to generate ref code');
        return DataResponse::JsonResult(null);
    }

    function generateReceiptItems($items,$receiptId,$user){
        // return $items;
        foreach($items as $item){
            $itemRef = $item['item_ref'];
            $qty = $item['qty'];
            $stockManagement = new StockManagementController();
            $prepareStock = $stockManagement->prepareStock(null,$item->variant_id,'sold_qty',$qty,$user,$item['cost'],$itemRef);
            // return $prepareStock;
            ReceiptItem::create([
                'receipt_id' => $receiptId,
                'unit_price' => $item['unit_price'],
                'cost' => $item['cost'] ?? 0,
                'tax'  => $item['tax'] ?? 0,
                'qty'  => $item['qty'],
                'discount_amount'  => $item['discount_amount'] ?? 0,
                'discount_type'  => $item['discount_type'] ?? '%',
                'description'  => $item['description'] ?? null,
            ]);
            if($prepareStock->error) return $prepareStock;
        }
    }

    function generateReceiptSerivces($services,$receiptId){
        foreach($services as $service){
            $service['receipt_id'] = $receiptId;
            $create = ReceiptService::create($service);
           if(!$create) return DataResponse::Error('Fail to save receipt service');
        }
        return DataResponse::JsonResult(null);
    }


    function generateInvoiceItems($items,$invoiceId,$user){
        // return $items;
        foreach($items as $item){
            $itemRef = $item['item_ref'];
            $qty = $item['qty'];
            $stockManagement = new StockManagementController();
            $prepareStock = $stockManagement->prepareStock(null,$item['variant_id'],'sold_qty',$qty,$user,$item['cost'],$itemRef);
            // return $prepareStock;
            $item['invoice_id'] = $invoiceId;
            InvoiceItem::create([
                'invoice_id' => $invoiceId,
                'variant_id' => $item['variant_id'],
                'net_amount' => $item['net_amount'],
                'cost' => $item['cost'],
                'qty' => $qty,
                'tax' => $item['tax'],
                'unit_price' => $item['unit_price'],
                'discount_amount' => $item['discount_amount'] ?? 0,
                'discount_type' => $item['discount_type'] ?? '%',
                'description' => $item['description'] ?? null
            ]);
            if($prepareStock->error) return $prepareStock;
        }
        return DataResponse::JsonResult(null);
    }

    function generateInvoiceSerivces($services,$invoice_id){
        foreach($services as $service){
            $service['invoice_id'] = $invoice_id;
            $create = InvoiceSerivce::create($service);
           if(!$create) return DataResponse::Error('Fail to save receipt service');
        }
        return DataResponse::JsonResult(null);
    }

    function getStockItem($ref){
        $item = Stock::with('variant')->where('sku',$ref)->first();
        if(!$item) if(is_numeric($ref)) $item = Stock::with('variant')->find($ref);
        if($item){
            $item->retail_price = $item->variant->retail_price;
        }

        return $item;
    }

    function mergeItem($items,$discount,$tax=0,$defaultCustomerDisAmount=0){
        if(!isset($items[0])) return DataResponse::JsonResult((object)[
            'total_amount' => 0,
            'total_due' => 0,
            'items' => []
        ]);
        $merged = [];
        $stckMng = new StockManagementController();
        $total_amount = 0;
        $total_due = 0;
        foreach ($items as $row) {
            $req = new Request($row);
            $validate = $this->orderItemValidation($req);
            if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
            $inputs = $validate->validated();
            $stock = $this->getStockItem($inputs['item_ref']);
            if(!$stock) return DataResponse::NotFound('Item not found in stock.');
            $unitPrice = $stock->retail_price;
            if(!$unitPrice || $unitPrice <=0) return DataResponse::NotFound('Please check your product price, it seems like there is no price!');
            $variantId = $stock->variant_id;
            $discountAmount = $inputs['discount_amount'] ?? 0;
            $discountType = $inputs['discount_type'] ?? null;
            $key = $variantId.'-'.$discountAmount.'-'.$discountType;
            $discountedAmount = 0;
            $qty = $inputs['qty'];

            if (isset($merged[$key])) {
                //* Merge quantities and prices, you can adjust this to fit your needs
                $merged[$key]['qty'] += $inputs['qty'];
                //-- overall discount = %  => item discount + overall disAmount
                $totalPrice = $unitPrice * $merged[$key]['qty'];
                $due_amount = $stckMng->calculatePrice($unitPrice,$merged[$key]['qty'],$discountAmount,$discountType);
                // if($discount->type == '%') {
                //     $due_amount = $due_amount - ($totalPrice * ($discount->amount + $defaultCustomerDisAmount) / 100);
                // }

                // if($discount->type == '$') {
                //     $due_amount = $due_amount - $discount->amount - ($totalPrice * $defaultCustomerDisAmount / 100);
                // };
                $due_amount = $due_amount + ($due_amount * $tax / 100);
                $due_amount = number_format($due_amount,2);
                $total_due += $due_amount;
                $merged[$key]['due_amount'] = $due_amount;
                $merged[$key]['net_amount'] = $due_amount;
                $total_row = $merged[$key]['qty'] * $unitPrice;
                $total_amount += $total_row;
                $merged[$key]['total_amount'] = $total_row;
            } else {
                $merged[$key] = $row;
                $totalPrice = $unitPrice * $qty;
                $due_amount = $stckMng->calculatePrice($unitPrice,$qty,$discountAmount,$discountType);

                // if($discount->type == '%') {
                //     $due_amount = $due_amount - ($totalPrice * ($discount->amount + $defaultCustomerDisAmount) / 100);
                // }
                // if($discount->type == '$') {
                //     $due_amount = $due_amount - $discount->amount - ($totalPrice * $defaultCustomerDisAmount / 100);
                // };
                // var_dump($due_amount);
                // $discountedAmount = $due_amount - ($due_amount * $defaultCustomerDisAmount / 100); // Apply discount
                $due_amount = $due_amount + ($due_amount * $tax / 100);
                $due_amount = number_format($due_amount,2);
                $total_due += $due_amount;
                $total_row = $qty * $unitPrice;
                $total_amount += $total_row;
                $merged[$key]['due_amount'] = $due_amount;
                $merged[$key]['net_amount'] = $due_amount;
                $merged[$key]['total_amount'] = $total_row;
            }
            $merged[$key]['unit_price'] = $unitPrice;
            $merged[$key]['tax'] = $tax;
            $merged[$key]['variant_id'] = $variantId;
            $merged[$key]['cost'] = $stock->cost;
        }
        if($discountType == '$'){
            $totalDis = $defaultCustomerDisAmount + $discount->amount;
            $total_due = $total_due - ($total_due * $totalDis / 100);
        }else{
            $afterDisAmount = $total_due - $discount->amount;
            $total_due = $afterDisAmount - ($total_due * $defaultCustomerDisAmount / 100);
        }
        return DataResponse::JsonResult((object)[
            'total_amount' => $total_amount,
            'total_due' => $total_due,
            'items' => array_values($merged)
        ]);
    }

    function mergeServices($services,$discount,$tax=0,$defaultCustomerDisAmount=0){
        if(!isset($services[0])) return DataResponse::JsonResult((object)[
            'total_amount' => 0,
            'total_due' => 0,
            'items' => []
        ]);
        $total_amount = 0;
        $total_due = 0;
        $merged = [];
        $stckMng = new StockManagementController();
        foreach ($services as $row) {
            $req = new Request($row);
            $validate = $this->serviceValidation($req);
            if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
            $inputs = $validate->validated();
            $serviceId = $inputs['service_id'];
            $service = Service::find($serviceId);
            if(!$service) return DataResponse::NotFound('Service not found.');
            $unitPrice = $service->price;
            $discountAmount = $inputs['discount_amount'] ?? 0; //* add overall tax
            $discountType = $inputs['discount_type'] ?? null;
            $key = $serviceId.'-'.$discountAmount.'-'.$discountType;
            $qty = $inputs['qty'];
            if (isset($merged[$key])) {
                //* Merge quantities and prices, you can adjust this to fit your needs
                $merged[$key]['qty'] += $inputs['qty'];
                $totalPrice = $unitPrice * $merged[$key]['qty'];
                $due_amount = $stckMng->calculatePrice($unitPrice,$merged[$key]['qty'],$discountAmount,$discountType);
                // if($discount->type == '%') {
                //     $due_amount = $due_amount - ($totalPrice * ($discount->amount + $defaultCustomerDisAmount) / 100);
                // }

                // if($discount->type == '$') {
                //     $due_amount = $due_amount - $discount->amount - ($totalPrice * $defaultCustomerDisAmount / 100);
                // };
                $due_amount = $due_amount + ($due_amount * $tax / 100);
                $due_amount = number_format($due_amount,2);
                $total_due += $due_amount;
                $total_row = $merged[$key]['qty'] * $unitPrice;
                $total_amount += $total_row;
                $merged[$key]['due_amount'] = $due_amount;
                $merged[$key]['net_amount'] = $due_amount;
                $merged[$key]['total_amount'] = $total_row;
            } else {
                $merged[$key] = $row;
                $totalPrice = $unitPrice * $qty;
                $due_amount = $stckMng->calculatePrice($unitPrice,$qty,$discountAmount,$discountType);

                // if($discount->type == '%') {
                //     $due_amount = $due_amount - ($totalPrice * ($discount->amount + $defaultCustomerDisAmount) / 100);
                // }
                // if($discount->type == '$') {
                //     $due_amount = $due_amount - $discount->amount - ($totalPrice * $defaultCustomerDisAmount / 100);
                // };
                $due_amount = $due_amount + ($due_amount * $tax / 100);
                $due_amount = number_format($due_amount,2);
                $total_due += $due_amount;
                $total_row = $qty * $unitPrice;
                $total_amount += $total_row;
                $merged[$key]['due_amount'] = $due_amount;
                $merged[$key]['net_amount'] = $due_amount;
                $merged[$key]['total_amount'] = $total_row;
            }
            $merged[$key]['unit_price'] = $unitPrice;
            $merged[$key]['tax'] = $tax;
        }
        if($total_due < 0) return DataResponse::ValidateFail('It seems like your discount is grather than service cost');
        if($discountType == '$'){
            $totalDis = $defaultCustomerDisAmount + $discount->amount;
            $total_due = $total_due - ($total_due * $totalDis / 100);
        }else{
            $afterDisAmount = $total_due - $discount->amount;
            $total_due = $afterDisAmount - ($total_due * $defaultCustomerDisAmount / 100);
        }
        return DataResponse::JsonResult((object)[
            'total_amount' => $total_amount,
            'total_due' => $total_due,
            'services' => array_values($merged)
        ]);
    }


    function generateInvoice(Request $req,$mergeItems,$mergeServices,$discountInfo,$tax,$user,$customer){
        $validate = validator($req->all(),[
            // 'customer_id' => 'nullable|int|exists:customers,id',
            'cash' => 'nullable|numeric',
            'bank_id' => 'nullable|exists:banks,id',
            'bank_number' => 'nullable|string|max:50',
            'bank_amount' => 'nullable|numeric',
            'bank_amount_kh' => 'nullable|numeric',
            'cash_kh' => 'nullable|numeric',
            'change' => 'nullable|numeric',
            'change_kh' => 'nullable|numeric',
            'exchange_rate' => 'nullable|numeric',
            'tax' => 'nullable|numeric',
            'issue_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            "remarks" => 'nullable|string|250'
        ]);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $isGeneral = !$req->customer_id;
        $inputs['receipt_date'] = now();
        $items = $mergeItems->data->items ?? [];
        $services = $mergeServices->data->services ?? [];
        $total_amount = $mergeItems->data->total_amount + $mergeServices->data->total_amount;
        $total_due = $mergeItems->data->total_due + $mergeServices->data->total_due;
        $cash = $inputs['cash'] ?? 0;
        $bankNumber = $inputs['bank_number'] ?? null;
        $bankId = $inputs['bank_id'] ?? null;
        $bankAmt = $inputs['bank_amount'] ?? 0;
        $dueDate = $inputs['due_date'] ?? now();
        $issueDate = $inputs['issue_date'] ?? now();

        if($bankAmt>0){
            if(!$bankId) return DataResponse::ValidateFail('Please select bank');
        }
        $customerId = $customer->id ?? null;
        $customerPhone = $customer->phone ?? null;

        $paymentAmout = number_format($cash + $bankAmt,2);
        if($paymentAmout > $total_due) return DataResponse::ValidateFail('The payment amount is $'.$total_due.' only');
        if($paymentAmout != $total_due)  return DataResponse::ValidateFail('Payment amount must be $'.$total_due.', but your input is only $'.$paymentAmout.'. missing $'.abs($total_due - $paymentAmout).'!!!');
        $walkIn = 1;
        if(!$customerId) $walkIn = 1;
        $status_id = 3;/// payment status 3 = fully paid
        if($paymentAmout && $paymentAmout < $total_due) $status_id = 2; // *partially paid
        $createInvoice = Invoice::create([
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'customer_id' => $customerId,
            'customer_phone' => $customerPhone,
            'default_discount' => $discountInfo->default_discount,
            'change_kh' => $inputs['change_kh'],
            'change' => $inputs['change'],
            'exchange_rate' => $inputs['exchange_rate'],
            'total_amount' => $total_amount,
            'tax' => $tax,
            'walkin' => $walkIn,
            'due_amount' => $total_due,
            'paid_amount' => $paymentAmout,
            'update_uid' => $user->id,
            'create_uid' => $user->id,
            'status_id' => $status_id,
            'discount_amount' => $discountInfo->amount,
            'discount_type' => $discountInfo->type,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id
        ]);

        $invoiceId = $createInvoice->id;
        if(isset($items[0])){
            $generateReceiptItems = $this->generateInvoiceItems($items,$invoiceId,$user);
            if($generateReceiptItems->error) return $generateReceiptItems;
        }
        if(isset($services[0])){
            $generateReceiptServices = $this->generateInvoiceSerivces($services,$invoiceId);
            if($generateReceiptServices->error) return $generateReceiptServices;
        }

        //** add payment history */
        if($cash){
            InvoicePayment::create([
                'invoice_id' => $invoiceId,
                'method' => 'Cash',
                'amount' => $cash,
            ]);
        }
        if($bankAmt){
            InvoicePayment::create([
                'invoice_id' => $invoiceId,
                'method' => 'Bank',
                'amount' => $bankAmt,
                'bank_id' => $bankId,
                'bank_number' => $bankNumber
            ]);
        }

        $ref_code = Helper::setRefCode('invoice_code_controls','invoices','ref_code',$user->branch_id,$user->company_id,$invoiceId,date('Y-m-d'),'INV');
        if($ref_code->status !== 'OK') return DataResponse::Error('Fail to generate ref code');
        return DataResponse::JsonResult(null);
    }

}
