<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantPhoto;
use App\Models\PurchaseOrderItem;
use App\Models\Stock;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;

class ProductVariantController extends Controller
{

    protected $productCon;
    public function __construct(ProductController $pc){
        $this->productCon = $pc;
    }
    //
    /**
     * Summary of createVariant
     * @param \Illuminate\Http\Request $req
     * @return mixed|\Illuminate\Http\JsonResponse
     *
     * create product variant
     */
    public function createVariant(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->productCon->productVariantValidator($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $inputs['update_uid'] = $user->id;
        $inputs['create_uid'] = $user->id;
        $photos = isset($inputs['photos']) ? $inputs['photos'] : [];
        unset($inputs['photos']);
        $productInfo = Product::find($inputs['product_id']);
        $create = ProductVariant::create($inputs);
        $product = new ProductController();
        $inputs['cost'] = $inputs['cost'] ?? $productInfo->cost;
        $inputs['retail_price'] = $inputs['retail_price']?? $productInfo->retail_price;
        $inputs['wholesale_price'] = $inputs['wholesale_price'] ?? $productInfo->wholesale_price;
        if(isset($photos[0])){
            $savePhoto = $product->updateOrCreateVariantPhotos($photos,$user->company_id,$create->id);
            if($savePhoto->status_code == 422) return ApiResponse::ValidateFail($savePhoto->message);
        }
        if(!$create) return ApiResponse::Error('Fail to create variant');
        return ApiResponse::JsonResult(null,false,'Created');
    }

    /**
     * Summary of updateVariant
     * @param \Illuminate\Http\Request $req
     * @param mixed $id
     * @return mixed|\Illuminate\Http\JsonResponse
     *
     * update product variant
     */
    public function updateVariant(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $validate = $this->productCon->productVariantValidator($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $inputs['update_uid'] = $user->id;
        $photos = $inputs['photos'];
        unset($inputs['photos']);
        $variant = ProductVariant::where('branch_id',$user->branch_id)->find($id);
        $product = new ProductController();
        $savePhoto = $product->updateOrCreateVariantPhotos($photos,$user->company_id,$id);
        if($savePhoto->status_code == 422) return ApiResponse::ValidateFail($savePhoto->message);
        $productInfo = Product::find($inputs['product_id']);
        $inputs['cost'] = $inputs['cost'] ?? $productInfo->cost;
        $inputs['retail_price'] = $inputs['retail_price']?? $productInfo->retail_price;
        $inputs['wholesale_price'] = $inputs['wholesale_price'] ?? $productInfo->wholesale_price;
        $update = $variant->update($inputs);
        if(!$update) return ApiResponse::Error('Fail to update variant');
        return ApiResponse::JsonResult(null,false,'Updated');
    }

    public function getVariantById(Request $req,$id=null){
        $user = UserService::getAuthUser();
        $id = $id ? $id : $req->id;
        $variant = ProductVariant::with(['photos:id,variant_id,photo_file_name,directory,is_thumbnail'])->where('branch_id',$user->branch_id)->where('id',$id)->selectRaw('id,size,color,cost,retail_price,sku,weight,width,length,expires_at,condition,condition_percentage,material,product_id,company_id')->first();
        if($variant){
            foreach($variant->photos as $photo){
                $photo->image_url = Helper::getImageUrl($photo->photo_file_name,$variant->company_id,$photo->directory);
                unset($photo->directory);
            }
        }
        return ApiResponse::JsonResult($variant);

    }
    public function getVariantByProductId(Request $req,$product_id=null){
        $user = UserService::getAuthUser();
        $product_id = $product_id ? $product_id : $req->product_id;
        $variants = ProductVariant::where('branch_id',$user->branch_id)->where('product_id',$product_id)->selectRaw('id,size,color,sku,weight,width,length,expires_at,condition,condition_percentage,material,product_id,company_id')->get();
        return ApiResponse::JsonResult($variants);
    }

    public function getVariants(Request $req){
        $user = UserService::getAuthUser();
        $query = ProductVariant::with(['product:id,name,code','photos:id,variant_id,photo_file_name,directory,is_thumbnail'])->where('branch_id',$user->branch_id);
        $variants = $query->get();
        foreach($variants as $vr){
            $vr->product_name = $vr->product->name;
            $vr->code = $vr->product->code;
            foreach($vr->photos as $photo){
                 if($photo->is_thumbnail){
                    $vr->image_url = Helper::getImageUrl($photo->photo_file_name,$vr->company_id,$photo->directory);
                 }
                 if(!$vr->image_url) $vr->image_url = Helper::getImageUrl($photo->photo_file_name,$vr->company_id,$photo->directory);
                // unset($vr->photos);
            }
            unset($vr->product);
        }
        return ApiResponse::Pagination($variants);
    }

    public function deleteVariantPhoto(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $photo = ProductVariantPhoto::find($id);
        if(!$photo) return ApiResponse::NotFound('The photo not found');
        if($photo){
            Helper::deleteImageFile($photo->photo_file_name,$user->company_id,$photo->directory);
        }
        $photo->delete();
        return ApiResponse::JsonResult(null,false,'Photo deleted');
    }

    public function deleteVariant(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $variant = ProductVariant::where('branch_id',$user->branch_id)->find($id);
        if(!$variant) return ApiResponse::NotFound('Variant not found');

        // clear variant photos
        $photos = ProductVariantPhoto::where('variant_id',$id)->get();
        foreach($photos as $photo){
            $inPurhcase = PurchaseOrderItem::where('variant_id',$id)->first();
            $inStock = Stock::where('variant_id',$id)->first();
            if($inStock || $inPurhcase) return ApiResponse::ValidateFail('This variant is running in stock, you cannot delete it.');
            Helper::deleteImageFile($photo->photo_file_name,$user->company_id,$photo->directory);
            ProductVariantPhoto::find($photo->id)->delete();
        }
        $variant->delete();
        return ApiResponse::JsonResult(null,false,'Variant deleted');
    }
}
