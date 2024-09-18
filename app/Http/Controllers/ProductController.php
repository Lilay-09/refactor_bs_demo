<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantPhoto;
use App\Models\ProductVariantSpecification;
use App\Models\ProductVariantTag;
use App\Services\UserService;
use DataResponse;
use Exception;
use Helper;
use Illuminate\Http\Request;
use Log;
use DB;

class ProductController extends Controller
{
    //
    protected $limitImages = 3;

    public function productValidation(Request $req){
        return validator($req->all(),[
            'name' => 'required|string|max:50',
            'code' => 'nullable|string|max:30',
            'model_id' => 'required|exists:models,id',
            'description' => 'nullable|string|max:250',
            'category_id' => 'required|int|exists:categories,id',
            'group_id' => 'required|int|exists:product_groups,id',
            'country_id' => 'nullable|int|exists:countries,id',
            'cost' => 'nullable|numeric|between:0,999999.99',
            'retail_price' => 'nullable|numeric|between:0,999999.99',
            'wholesale_price' => 'nullable|numeric|between:0,999999.99',
            'tags' => 'nullable|array',
            'supplier_id' => 'nullable|int|exists:vendors,id',
            'specs' => 'nullable|array',
            'variants' => 'nullable|array',
            'photos' => 'nullable|array'
        ],[
            'model_id.required' => 'Please select model',
            'model_id.exists' => 'Please select model',
            'group_id.required' => 'Please select group',
            'group_id.exists' => 'Please select group',
            'category_id.required' => 'Please select category',
            'category_id.exists' => 'Please select category'
        ]);
    }

    public function createProduct(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->productValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();

        //* add user info
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $variants =  isset($inputs['variants']) ? $inputs['variants'] : [];
        $specs = isset($inputs['specs']) ? $inputs['specs'] : [];
        $tags = isset($inputs['tags']) ? $inputs['tags'] : [];
        $cost = isset($inputs['cost']) ? $inputs['cost'] : 0;
        $retail_price = isset($inputs['retail_price']) ? $inputs['retail_price'] : 0;
        $wholesale_price = isset($inputs['wholesale_price']) ? $inputs['wholesale_price'] : 0;
        $inputs['cost'] = $cost;
        $inputs['retail_price'] = $retail_price;
        $inputs['wholesale_price'] = $wholesale_price;
        unset($inputs['variants'],$inputs['specs'],$inputs['tags']);

        DB::beginTransaction();
        try{
            $create = Product::create($inputs);
            if($create){
                $productId = $create->id;
                if(isset($variants[0])){
                    $updateVariant = $this->updateOrCreateProductVaraints($variants,$productId,$user,$cost,$retail_price,$wholesale_price);
                    // return $updateVariant;
                    if($updateVariant->error){
                        if($updateVariant->status_code == 422) return ApiResponse::ValidateFail($updateVariant->message);
                        if($updateVariant->status_code == 500) return ApiResponse::Error($updateVariant->message);
                    }
                }

                if(isset($specs[0])){
                    $createSpec = $this->updateOrCreateProductSpec($specs,$productId,$user);
                    if($createSpec->error){
                        return $createSpec->status_code == 422 ? ApiResponse::ValidateFail($createSpec->error):ApiResponse::Error($createSpec->message);
                    }
                }
                if(isset($tags[0])){
                    $createTags = $this->updateOrCreateProductTags($tags,$productId,$user);
                    if($createTags->error){
                        if($createTags->status_code == 422) return ApiResponse::ValidateFail($createTags->message);
                        if($createTags->status_code == 500) return ApiResponse::Error($createTags->message);
                        if($createTags->status_code == 409) return ApiResponse::Duplicated($createTags->message);
                    }
                }

                //** add images */

            }
            DB::commit();
            return ApiResponse::JsonResult(null,false,'Product has been created!');
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getTraceAsString());
            Log::error($e->getMessage());
            return ApiResponse::Error('Fail to create 1');
        }
    }

    function variantPhotoValidation(Request $req){
        return validator($req->all(),[
                'variant_id' => 'required|int|exists:product_variants,id',
                'photo' => 'required|string',
                'is_thumbnail' => 'nullable|boolean'
            ],[
                'photo.required' => 'Image is empty, Please add the image'
            ]);
    }
    function updateOrCreateVariantPhotos($photos,$company_id,$variantId){
        foreach($photos as $photo){
            $photo['variant_id'] = $variantId;
            $request = new Request($photo);
            $id = $photo['id'] ?? null;
            $validate = $this->variantPhotoValidation($request);
            if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
            $inputs = $validate->validated();
            $file_name =  Helper::base64ToImageFile($inputs['photo'],$company_id,'product_variant');
            // if(!$file_name) return DataResponse::ValidateFail('It seems like the photo is empty!');
            $inputs['photo_file_name'] = $file_name;
            unset($inputs['photo']);
            if($id){

                // $photoCount = ProductVariantPhoto::where('id','!=',$id)->where('variant_id',$inputs['variant_id'])->count();
                // if($photoCount == $this->limitImages) {
                //     Helper::deleteImageFile($file_name,$company_id,'product_variant');
                //     return DataResponse::ValidateFail('Each variant can only store up to '.$this->limitImages.' photos');
                // }
                $productVariantPhoto = ProductVariantPhoto::find($id);
                $inputs['photo_file_name'] = $file_name ?? $productVariantPhoto->photo_file_name;
                if(!$productVariantPhoto) {
                    Helper::deleteImageFile($file_name,$company_id,'product_variant');
                    return DataResponse::ValidateFail('photo not found');
                }
                //* delete exists img
                if($file_name) Helper::deleteImageFile($productVariantPhoto->photo_file_name,$company_id,'product_variant');

                $update = $productVariantPhoto->update($inputs);
                if(!$update) {
                    Helper::deleteImageFile($file_name,$company_id,'product_variant');
                    return DataResponse::Error('Fail to update photo');
                }
                //* delete old image
            }else{
                // $photoCount = ProductVariantPhoto::where('variant_id',$inputs['variant_id'])->count();
                // if($photoCount == $this->limitImages){
                //     Helper::deleteImageFile($file_name,$company_id,'product_variant');
                //     return DataResponse::ValidateFail('Each variant can only store up to '.$this->limitImages.' photos');
                // }
                $photo = ProductVariantPhoto::create($inputs);
                if(!$photo){
                    Helper::deleteImageFile($file_name,$company_id,'product_variant');
                    return DataResponse::Error('Fail to add photo');
                }
                //** delete insert file in local storage if commit to db fail  */
            }
        }
        return DataResponse::JsonResult(null,false,'Photo added');
    }


    public function getProducts(Request $req){
        $user = UserService::getAuthUser();
        $tags = $req->tag;
        $inactive = $req->inactive ?? 0;
        $search = $req->search;
        $categories = $req->categories ?? [];
        $groups = $req->groups ?? [];
        $brands = $req->brands ?? [];
        if (is_string($tags)) {
            $tags = explode(',', strtolower($tags));
        }
        $query = Product::where('void',0)->with(['specifications:id,name,value,product_id','tags:id,tag,product_id','variants.photos','variants.stocks','getModel:id,name,brand_id','getModel.brand:id,name'])->where('branch_id',$user->branch_id)->orderByRaw('DATE(created_at) DESC')->selectRaw('id,name,code,description,model_id,category_id,group_id,supplier_id');
        if (!empty($tags)){
            $query->whereHas('tags', function($query) use ($tags) {
                $query->whereRaw('LOWER(tag) IN (?)', [$tags]);
            });
        }

        if(!empty($groups)){
            $query->whereIn('category_id', $groups);
        }

        if(!empty($categories)){
            $query->whereIn('category_id', $categories);
        }

        if(!empty($brands)){
            $query->whereHas('getModel.brand',function($query) use ($brands){
                $query->whereIn('id', $brands);
            });
        }

        if ($search) {
            $query->whereRaw('name ilike \'%'.$search.'%\' or code = \''.$search.'\'')
                ->orWhereHas('variants', function($query) use ($search) {
                    $query->where('color', 'ilike', '%' . $search . '%');
                });
        }

        $products = $query->get();
        foreach($products as $product){
            $product->brand_name = $product->getModel->brand->name;
            $product->model_name = $product->getModel->name;
            foreach ($product->variants as $vr) {
                // Initialize stock quantity to 0
                $vr->stock_qty = 0;
                // Check if the product has associated stocks
                if (isset($vr->stocks[0])) {
                    // Sum up the stock quantities for each stock entry related to the variant
                    foreach ($vr->stocks as $stock) {
                        $vr->stock_qty += $stock->qty;
                    }
                }
            }
            unset($product->getModel);
        }
        return ApiResponse::Pagination($products,$req,'get product list');
    }

    public function getProductById(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id ? $req->id : $req->query('id');
        $product = Product::where('void',0)->with(['getModel:id,brand_id','variants:id,product_id,size,color,weight,length,expires_at,condition,material,cost,retail_price','tags:id,tag,product_id'])->where('branch_id',$user->branch_id)->find($id);
        if($product){
            $product->brand_id = $product->getModel->brand_id;
            foreach ($product->variants as $vr) {
                // Initialize stock quantity to 0
                $vr->stock_qty = 0;
                // Check if the product has associated stocks
                if (isset($vr->stocks[0])) {
                    // Sum up the stock quantities for each stock entry related to the variant
                    foreach ($vr->stocks as $stock) {
                        $vr->stock_qty += $stock->qty;
                    }
                }
            }
            unset($product->getModel);

        }
        return ApiResponse::JsonResult($product,false,'get product');
    }

    function createProductVariant(Request $req){
        $validate = $this->productVariantValidator($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
    }

    function productVariantValidator(Request $req){
        return validator($req->all(),[
            'product_id' => 'required|int|exists:products,id',
            'size' => 'nullable|string|max:30',
            'color' => 'nullable|string|max:30',
            'weight' => 'nullable|string|max:30',
            'width' => 'nullable|string|max:30',
            'material' => 'nullable|string|max:30',
            'length' => 'nullable|string|max:30',
            'expires_at' => 'nullable|date',
            'condition' => 'required|in:new,second hand',
            'condition_percentage' => 'nullable|string',
            'cost' => 'nullable|numeric',
            'retail_price' => 'required|numeric',
            'wholesale_price' => 'nullable|numeric',
            'tags' => 'nullable|array',
            'photos' => 'nullable|array'
        ],[
            'condition.required' => 'Condition must be one of (new,second hand)',
            'condition.in' => 'Condition must be one of (new,second hand)'
        ]);
    }

    private function productSpecValidation(Request $req){
        return validator($req->all(),[
            'product_id' => 'nullable|int',
            'variant_id' => 'nullable|int',
            'name' => 'required|string|max:30',
            'value' => 'required|string|max:30',
        ]);
    }
    private function updateOrCreateSpec($specs,$fk=[],$user){
        $fkId = isset($fk['product_id']) ? $fk['product_id']:null;
        if(!$fkId) $fkId = isset($fk['variant_id']) ? $fk['variant_id']:null;
        $key = array_keys($fk);
        if(!$fkId) return DataResponse::ValidateFail([$key[0].' is required']);

        $branch_id = $user->branch_id;
        $company_id = $user->company_id;
        $userId = $user->id;
        foreach($specs as $vr){
            $id = isset($vr['id']) ? $vr['id'] : null;
            $vr[$key[0]] = $fkId;
            $validate = $this->productSpecValidation(new Request($vr));
            if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
            $inputs = $validate->validated();
            $inputs['branch_id'] = $branch_id;
            $inputs['company_id'] = $company_id;
            $inputs['update_uid'] = $userId;
            if($id){
                $specification = ProductVariantSpecification::where('branch_id',$branch_id)->find($id);
                if(!$specification) continue;
                $update = $specification->update($inputs);
                if(!$update) return DataResponse::Error('Fail to update specification');
            }else{
                $inputs['create_uid'] = $user->id;
                $create = ProductVariantSpecification::create($inputs);
                if(!$create) return DataResponse::Error('Fail to create specification');
            }
        }

        return DataResponse::JsonResult(null,false,'Saved');
    }

    private function updateOrCreateProductSpec($specs,$product_id,$user){
        return $this->updateOrCreateSpec($specs,['product_id' => $product_id],$user);
    }

    private function updateOrCreateVariantSpec($specs,$variant_id,$user){
        return $this->updateOrCreateSpec($specs,['variant_id' => $variant_id],$user);
    }


    /**
     * Summary of updateProduct
     * @param \Illuminate\Http\Request $req
     * @param mixed $id
     * @return mixed|\Illuminate\Http\JsonResponse
     *
     * update product
     * variants,specifications, product tags can be update or create here too
     *
     */


    public function updateProduct(Request $req,$id=null){
        $user = UserService::getAuthUser();
        $productId = $id ? $id : $req->id;
        $product = Product::where('branch_id',$user->branch_id)->find($productId);
        if(!$product) return ApiResponse::NotFound('Product not found');
        $validate = $this->productValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $inputs['update_uid'] = $user->id;
        $variants = $inputs['variants'];
        $specs = isset($inputs['specs']) ? $inputs['specs'] : [];
        $tags = isset($inputs['tags']) ? $inputs['tags'] : [];
        $cost = isset($inputs['cost']) ? $inputs['cost'] : null;
        $retail_price = $inputs['retail_price'] ?? 0;
        $wholesale_price = $inputs['wholesale_price'] ?? 0;
        unset($inputs['variants'],$inputs['specs'],$inputs['tags']);
        DB::beginTransaction();
        try{
            $update = $product->update($inputs);
            if($update){
            $updateVariant = $this->updateOrCreateProductVaraints($variants,$productId,$user,$cost,$retail_price,$wholesale_price);
                if($updateVariant->error){
                    if($updateVariant->status_code == 422) return ApiResponse::ValidateFail($updateVariant->message);
                    if($updateVariant->status_code == 500) return ApiResponse::Error($updateVariant->message);
                }
                if(isset($specs[0])){
                    $updateSpec = $this->updateOrCreateProductSpec($specs,$productId,$user);
                    if($updateSpec->error){
                        return $updateSpec->status_code == 422 ? ApiResponse::ValidateFail($updateSpec->error):ApiResponse::Error($updateSpec->message);
                    }
                }
                if(isset($tags[0])){
                    $updateTag = $this->updateOrCreateProductTags($tags,$productId,$user);
                    if($updateTag->error){
                        return $updateTag->status_code == 422 ? ApiResponse::ValidateFail($updateTag->message) : ApiResponse::Error($updateTag->message);
                    }
                }
            }

            DB::commit();
            return ApiResponse::JsonResult(null,false,'Product has been saved!');
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getTraceAsString());
            Log::error($e->getMessage());
            return ApiResponse::Error('Fail to save');
        }
    }

    private function upodateProductVariantPrice(Request $req){
        $user = UserService::getAuthUser();
        $validate = validator($req->all(),[
            'cost' => 'required|numeric',
            'retail_price' => 'required|numric',
            'wholesale_price' => 'required|numeric'
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['update_uid'] = $user->id;


    }


    /**
     * Summary of updateOrCreateProductVaraints
     * @param mixed $variants
     * @param mixed $productId
     * @param mixed $user
     * @return object
     *
     * create or update variants on update Product function
     */
    private function updateOrCreateProductVaraints($variants,$productId,$user,$cost=0,$retail_price=0,$wholesale_price=0){
        $branch_id = $user->branch_id;
        $userId = $user->id;
        $company_id = $user->company_id;
        if(!$productId) return DataResponse::ValidateFail('Product ID is required');
        foreach($variants as $idx=>$vr){
            $vr['product_id'] = $productId;
            $id = isset($vr['id']) ? $vr['id'] : null;
            $vr['retail_price'] = isset($vr['retail_price']) ? $vr['retail_price'] : $retail_price;
            $vr['cost'] = isset($vr['cost']) ? $vr['cost'] : $cost;
            $vr['wholesale_price'] = isset($vr['wholesale_price']) ? $vr['wholesale_price'] : $wholesale_price;
            $validate = $this->productVariantValidator(new Request($vr));
            if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
            $inputs = $validate->validated();
            //** add user info */
            $inputs['branch_id'] = $branch_id;
            $inputs['company_id'] = $company_id;
            $inputs['update_uid'] = $userId;
            $photos = isset($inputs['photos']) ? $inputs['photos']:[];
            unset($inputs['photos']);
            // $inputs['retail_price'] = isset($inputs['retail_price']) ? $inputs['retail_price'] : $retail_price;
            // $inputs['cost'] = isset($inputs['cost']) ? $inputs['cost'] : $cost;
            // $inputs['wholesale_price'] = isset($inputs['wholesale_price']) ? $inputs['wholesale_price'] : $wholesale_price;
            if($id){
                $variant = ProductVariant::where('branch_id',$branch_id)->where('product_id',$productId)->find($id);
                if(!$variant) return DataResponse::Error('Variant not found on row('.($idx + 1).'), while updating.' );
                $update = $variant->update($inputs);
                if(!$update) return DataResponse::Error('Fail to update variant');
                if(isset($photos[0])){
                    $savePhoto = $this->updateOrCreateVariantPhotos($photos,$company_id,$id);
                    if($savePhoto->status_code == 422) return DataResponse::ValidateFail($savePhoto->message);
                }
            }else{
                $inputs['create_uid'] = $userId;
                $create = ProductVariant::create($inputs);
                if(!$create) return DataResponse::Error('Fail to create variant');
                if(isset($photos[0])){
                    $savePhoto = $this->updateOrCreateVariantPhotos($photos,$company_id,$create->id);
                    if($savePhoto->status_code == 422) return DataResponse::ValidateFail($savePhoto->message);
                }
            }

        }
        return DataResponse::JsonResult(null,false,'Saved');
    }

    private function TagValidation(Request $req){
        return validator($req->all(),[
            'product_id' => 'nullable|int',
            'variant_id' => 'nullable|int',
            'tag' => 'required|string'
        ]);
    }

    private function updateOrCreateTags($tags,$fk=[],$user){
        $branch_id = $user->branch_id;
        $userId = $user->id;
        $company_id = $user->company_id;
        $fkId = isset($fk['product_id']) ? $fk['product_id']:null;
        if(!$fkId) $fkId = isset($fk['variant_id']) ? $fk['variant_id'] : null;
        if(!$fkId) return DataResponse::ValidateFail('Please check your tags form');
        $keys = array_keys($fk);
        if(!$fk) return DataResponse::ValidateFail($keys[0].' is required');
        $ids = [];
        foreach($tags as $tag){
            // return $tag;
            $id = isset($tag['id']) ? $tag['id']:null;
            if(!isset($tag['tag'])) return DataResponse::ValidateFail('Please check your tags form');
            $tag[$keys[0]] = $fkId;
            $validate = $this->TagValidation(new Request($tag));
            if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
            $inputs = $validate->validated();
            $inputs['update_uid'] = $userId;
            $inputs['branch_id'] = $branch_id;
            $inputs['company_id'] = $company_id;
            if($id){
                $duplicateName = ProductVariantTag::where('tag',$inputs['tag'])->where('id','!=',$tag['id'])->where($fk)->where('branch_id',$branch_id)->first();
                if($duplicateName) return DataResponse::Duplicated('It seems like you try to add tag but got duplicated by tag name = ('.$tag['tag'].')');
                $tag = ProductVariantTag::where('branch_id',$branch_id)->find($id);
                $update = $tag->update($inputs);
                if(!$update) return DataResponse::Error('Fail to update tag');
                $ids[] = $id;
            }else{
                $duplicateName = ProductVariantTag::where('tag',$inputs['tag'])->where($fk)->where('branch_id',$branch_id)->first();
                if($duplicateName) return DataResponse::Duplicated('It seems like you try to add tag but got duplicated by tag ('.$tag['tag'].')');
                $inputs['create_uid'] = $userId;
                $create = ProductVariantTag::create($inputs);
                if(!$create) return DataResponse::Error('Fail to create');
                $ids[] = $create->id;
            }


        }
        //** DELETE where if not provided */
        ProductVariantTag::where($keys[0],$fkId)->whereNotIn('id',$ids)->delete();
        return DataResponse::JsonResult(null,false,'Saved');
    }

    private function updateOrCreateProductTags($tags,$product_id,$user){
        return $this->updateOrcreateTags($tags,['product_id' => $product_id],$user);
    }

    public function deleteProduct(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $branch_id = $user->branch_id;
        $product = Product::where('branch_id',$branch_id)->find($id);
        if(!$product) return ApiResponse::NotFound('Product not found');
        $useInVariant = ProductVariant::where('product_id',$id)->first();
        if($useInVariant) return ApiResponse::ValidateFail('Product is used by variant');
        $product->delete();
        return ApiResponse::JsonResult(null,false,'Deleted');

    }

    public function voidProduct(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $branch_id = $user->branch_id;
        $product = Product::where('branch_id',$branch_id)->where('void',0)->find($id);
        if(!$product) return ApiResponse::NotFound('Product not found');
        $product->update([
            'void' => 1,
            'void_ui' => $user->id
        ]);
        ProductVariant::where('product_id',$id)->update([
            'void' => 1,
            'void_uid' => $user->id
        ]);
        return ApiResponse::JsonResult(null,false,'Voided');
    }
    public function unVoidProduct(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $branch_id = $user->branch_id;
        $product = Product::where('branch_id',$branch_id)->find($id);
        if(!$product) return ApiResponse::NotFound('Product not found');
        $product->update([
            'void' => 0,
            'void_uid' => $user->id
        ]);
        ProductVariant::where('product_id',$id)->update([
            'void' => 0,
            'void_uid' => $user->id
        ]);
        return ApiResponse::JsonResult(null,false,'Voided');
    }
}
