<?php

namespace App\Services;

use App\DTO\PackageCommentDTO;
use App\Enums\CommentSource;
use App\Enums\ImageDirectory;
use App\Jobs\SendCommentSocketJob;
use App\Models\Comment;
use App\Models\CommentDescriptions;
use App\Models\CommentUser;
use App\Models\MerchantOperator;
use App\Models\Package;
use App\Models\User;
use DataResponse;
use DB;
use Exception;
use Helper;
use Illuminate\Http\Request;
use Log;
use WebSocket\Client;

class CommentServiceImpl implements CommentService
{
    // Your service methods go here
    private function commentValidator(Request $req){
        return validator($req->all(),[
            'thread_id' => 'required|integer',
            'data' => 'required',
            'data_type' => 'required|string|in:text,photo',
            'reply_to' => 'nullable',
        ]);
    }
    // public function addComment(Request $req,object $authUser): object
    // {
    //     Log::info($req->all());
    //     $validator = $this->commentValidator($req);
    //     if($validator->fails()){
    //         return DataResponse::ValidateFail($validator->errors()->first());
    //     }
    //     $inputs = $validator->validated();
    //     $threadId = $inputs['thread_id'];
    //     $comment = Comment::where('thread_id', $threadId)->first();
    //     $commentId = null;
    //     $replyTo = $inputs['reply_to'] ?? null;
    //     try{
    //         DB::beginTransaction();
    //         if(!$comment){
    //         $createComment = Comment::create([
    //             'thread_id' => $threadId,
    //             'source' => CommentSource::PACKAGE->value,
    //             'started_at' => now(),
    //             'starter_id' => $authUser->id,
    //             'company_id' => $authUser->company_id,
    //             'branch_id' => $authUser->branch_id,
    //         ]);
    //         $commentId = $createComment->id;
    //         }else {
    //             $commentId = $comment->id;
    //         }
    //         CommentUser::updateOrCreate([
    //             'comment_id' => $commentId,
    //             'user_id' => $authUser->id,
    //             'user_type' => $comment ? 'owner':'member',
    //         ]);

    //         $this->sendCommentSocket($threadId,$inputs['data'],$inputs['data_type'],$authUser->id,$replyTo,$commentId);
    //         $cmmDesId = CommentDescriptions::insertGetId([
    //             'parent_id' => $replyTo,
    //             'thread_id' => $threadId,
    //             'comment_id' => $commentId,
    //             'data' => $inputs['data'],
    //             'data_type' => $inputs['data_type'],
    //             'create_uid' => $authUser->id,
    //             'update_uid' => $authUser->id,
    //             'branch_id' => $authUser->branch_id,
    //             'company_id' => $authUser->company_id,
    //         ]);

    //         // DB::commit();
    //         return DataResponse::JsonResult([
    //             'id' => $cmmDesId
    //         ], false);
    //     }catch (Exception $e) {
    //         DB::rollBack();
    //         Log::error($e->getMessage());
    //         return DataResponse::JsonResult([], true, $e->getMessage());
    //     }
    // }


    public function addComment(Request $req, object $authUser): object
    {
        $validator = $this->commentValidator($req);
        if ($validator->fails()) {
            return DataResponse::ValidateFail($validator->errors()->first());
        }

        // Log::info($req->all());
        $inputs = $validator->validated();
        $threadId = $inputs['thread_id'];
        $replyTo = $inputs['reply_to'] ?? null;
        $dataType = $inputs['data_type'];
        if($dataType == 'photo'){
            $photo = $inputs['data'] ?? null;
            // Log::info('photp data'.$photo);
            $isValidUpload = Helper::isValidUploadImage($photo,3);
            if($isValidUpload->error) return DataResponse::ValidateFail($isValidUpload->message);
            $date = date(format: 'Y-m-d');
            $inputs['file_name'] = Helper::saveImageFileOrBase64($photo,$authUser->company_id,ImageDirectory::COMMENT->value,$date)->filename;
            $inputs['data'] = Helper::getImageUrl($inputs['file_name'], $authUser->company_id, ImageDirectory::COMMENT->value,$date);
        }else{
            if(strlen($inputs['data']) > 1000){
                return DataResponse::ValidateFail(__('messages.info',[
                    'info' => 'Comment text is too long. Please limit it to 1000 characters.',
                    'khInfo' => 'មតិយោបល់មានអត្ថបទវែងពេក។ សូមកំណត់វាទៅ 1000 តួអក្សរទេ។'
                ]));
            }
        }

        try {
            DB::beginTransaction();

            // Use firstOrCreate instead of manual check + create for better atomicity
            $comment = Comment::firstOrCreate(
                ['thread_id' => $threadId],
                [
                    'source'     => CommentSource::PACKAGE->value,
                    'started_at' => now(),
                    'starter_id' => $authUser->id,
                    'company_id' => $authUser->company_id,
                    'branch_id'  => $authUser->branch_id,
                ]
            );

            $commentId = $comment->id;

            // Determine user_type based on whether comment was just created or existed
            $userType = $comment->wasRecentlyCreated ? 'owner' : 'member';

            CommentUser::updateOrCreate(
                [
                    'comment_id' => $commentId,
                    'user_id'    => $authUser->id,
                ],
                [
                    'user_type' => $userType,
                ]
            );

            // $this->sendCommentSocket($threadId, $inputs['data'], $inputs['data_type'], $authUser->id, $replyTo, $commentId);

            // ->onQueue($queueFCMName);



            $cmmDesId = CommentDescriptions::insertGetId([
                'parent_id'  => $replyTo,
                'thread_id'  => $threadId,
                'comment_id' => $commentId,
                'data'       => $inputs['data'],
                'data_type'  => $inputs['data_type'],
                'create_uid' => $authUser->id,
                'update_uid' => $authUser->id,
                'branch_id'  => $authUser->branch_id,
                'company_id' => $authUser->company_id,
            ]);

            $queueFCMName = config('queue_job_names.'.config('app.env').'.chat');
            // Log::info($queueFCMName);
            SendCommentSocketJob::dispatch($threadId, $imgUrl ?? $inputs['data'], $inputs['data_type'], $authUser->id, $replyTo, $cmmDesId)
            ->onQueue($queueFCMName)->afterCommit();
            DB::commit();
            return DataResponse::JsonResult([
                'id' => $cmmDesId,
            ], false);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getTraceAsString());
            Log::error($e->getMessage());
            return DataResponse::JsonResult([], true, $e->getMessage());
        }
    }


    public function getPackageCommentDetailsByPackageId(Request $req,int $packageId,object $authUser): object{
        $comment = Comment::where('is_deleted', false)
            ->where('thread_id', $packageId)
            ->where('source', CommentSource::PACKAGE->value)
            ->first();
        if ($comment) {
            $userIds = CommentUser::where('comment_id',$comment->id)->pluck('user_id')->toArray();
            $users = User::whereIn('id', $userIds)
            ->select('id', 'photo_file_name','account_type as user_type','username')
            ->get()
            ->each(function ($u) {
                $u->image_url = Helper::getImageUrl(
                        $u->photo_file_name,
                        1,
                        ImageDirectory::USER_PROFILE->value
                );
            })
            ->toArray();

            $descriptions = CommentDescriptions::query()
            ->where('comment_id',$comment->id)
            ->where('is_deleted',false)
            ->orderByDesc('id');//$comment->descriptions->query();
            $selectDes = ['id', 'comment_id', 'parent_id', 'data', 'data_type','create_uid','create_uid as user_id','created_at'];


            $callbackDesc = function($description) use($authUser) {
                if($authUser->id == $description->create_uid){
                    $description->isSelf = true;
                }
                $description->sender_id = $description->user_id;
                $description->topic = "Package";
                $description->date = Helper::formatCustomDateTime($description->created_at,'d-M-Y') ?? null;
                $description->time = Helper::formatCustomDateTime($description->created_at,'H:i A') ?? null;
                return PackageCommentDTO::fromModel($description)->toArray();
            };

            $cacheTags = ['comment_details'.date('Y-m-d')];
            return DataResponse::PaginationV1($descriptions,$req,'',[
                'profiles' => $users,
            ],100,$callbackDesc,$selectDes,true,300,$cacheTags);
        }

        return DataResponse::JsonResult([], false);
    }

    public function createPackageCommentSection(Request $req, object $authUser): object
    {
        $validator = validator($req->all(), [
            'package_id' => 'nullable|exists:packages,id,is_deleted,0', // validate package exists and not deleted
            'operators' => 'nullable|array',
            'operators.*' => 'integer', // each operator id should be integer
            'include_related' => 'nullable|in:no,yes'
        ]);

        if ($validator->fails()) {
            return DataResponse::ValidateFail($validator->errors()->first());
        }

        $inputs = $validator->validated();

        $members = $inputs['operators'] ?? [];
        $includeRelated = $inputs['include_related'] ?? 'no';

        // Initialize $pkg only if package_id is provided
        $pkg = null;
        if (!empty($inputs['package_id'])) {
            $pkg = Package::where('is_deleted', false)->find($inputs['package_id']);
            if (!$pkg) {
                return DataResponse::ValidateFail('Invalid package_id or package is deleted.');
            }
        }

        if ($includeRelated === 'yes' && $pkg) {
            $operatorIds = MerchantOperator::where('merchant_id', $pkg->merchant_id)
                ->pluck('operator_id')
                ->toArray();

            // Merge and remove duplicate operator IDs
            $members = array_unique(array_merge($members, $operatorIds));
        }

        // Create the comment thread
        try{
            DB::beginTransaction();
            $createComment = Comment::create([
                'thread_id'  => $inputs['package_id'] ?? null,
                'source'     => CommentSource::PACKAGE->value,
                'started_at' => now(),
                'starter_id' => $authUser->id,
                'company_id' => $authUser->company_id,
                'branch_id'  => $authUser->branch_id,
            ]);

            $commentId = $createComment->id;

            // Add the owner as a comment user
            CommentUser::create([
                'comment_id' => $commentId,
                'user_id'    => $authUser->id,
                'user_type'  => 'owner',
            ]);

            // Add members if any
            if (!empty($members)) {
                $insertMembers = [];
                foreach ($members as $memberId) {
                    // Skip if member is the starter (optional)
                    if ($memberId == $authUser->id) continue;
                    $insertMembers[] = [
                        'comment_id' => $commentId,
                        'user_id'    => $memberId,
                        'user_type'  => 'member',
                    ];
                }

                if (!empty($insertMembers)) {
                    CommentUser::insert($insertMembers);
                }
            }
            DB::commit();
            return DataResponse::JsonResult(['comment_id' => $commentId],false,'Comment section created successfully',);
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getMessage());
            return DataResponse::Error('Failed');
        }
    }


    public function getPackageCommentSections(Request $req,object $authUser):object{
        $select = ['id as comment_id','thread_id','starter_id','started_at'];
        $qC = Comment::query()
        ->select($select)
        ->where('source',CommentSource::PACKAGE->value)
        ->with([
            'package:id,order_id,merchant_id,driver_total as total,delivery_fee,extra_charge,main_zone_name,main_zone_code,delivery_type,receiver_phone,receiver_address,zone_code,zone_name,status_id,remarks,cod,price,price_khr,qr_code',
            'package.merchant:id,username,phone',
            'package.order:id,order_datetime'
        ]);
        $callback = function($q){
            if(!empty($q->package)){
                foreach($q->package->getAttributes() as $key=>$value){
                    $q->{$key} = $value;
                    $q->image_url = null;
                    $q->merchant_name = $q->package->merchant->username;
                    $q->merchant_phone = $q->package->merchant->phone;
                    $q->order_date = Helper::formatCustomDateTime($q->package->order->order_datetime,'d-M-Y H:i A') ?? null;
                }
            }

            unset($q->package);
            return $q;
        };
        return DataResponse::PaginationV1($qC,$req,'',[],1000,$callback);
    }

    public function getPackageCommentDetailsById(Request $req, $id,object $authUser): object{
        $comment = Comment::find($id)
            ->where('source', CommentSource::PACKAGE->value)
            ->first();
        if ($comment) {
            $userIds = CommentUser::where('comment_id',$id)->pluck('user_id')->toArray();
            $users = User::whereIn('id', $userIds)
            ->select('id', 'photo_file_name','account_type as user_type','username')
            ->get()
            ->each(function ($u) {
                $u->image_url = Helper::getImageUrl(
                        $u->photo_file_name,
                        1,
                        ImageDirectory::USER_PROFILE->value
                );
            })
            ->toArray();

            $descriptions = CommentDescriptions::query()
            ->where('comment_id',$id)
            ->where('is_deleted',false)
            ->orderByDesc('id');//$comment->descriptions->query();
            $selectDes = ['id', 'comment_id', 'parent_id', 'data', 'data_type','create_uid','create_uid as user_id','created_at'];


            $callbackDesc = function($description) use($authUser) {
                if($authUser->id == $description->create_uid){
                    $description->isSelf = true;
                }
                $description->sender_id = $description->user_id;
                $description->topic = "Package";
                $description->date = Helper::formatCustomDateTime($description->created_at,'d-M-Y') ?? null;
                $description->time = Helper::formatCustomDateTime($description->created_at,'H:i A') ?? null;
                return PackageCommentDTO::fromModel($description)->toArray();
            };

            $cacheTags = ['comment_details'.date('Y-m-d')];
            return DataResponse::PaginationV1($descriptions,$req,'',[
                'profiles' => $users,
            ],100,$callbackDesc,$selectDes,true,0,$cacheTags);
        }
        return DataResponse::JsonResult([], false);
    }

    public function sendCommentSocket($pkgId,$msg,$dataType,$senderId,$replyTo,$refId,$isSelf = false){
        $uri = config('services.socket.chat_service_socket').'?key='.config('services.socket.chat_service_key');
        try {
            $client = new Client($uri); // WebSocket server
            $client->send(json_encode([
                'action' => 'comment-group',
                'topic' => (string)$pkgId
            ]));
            $client->send(json_encode([
                'action' => 'comment',
                'sender_id' => $senderId,
                'topic' => (string)$pkgId, // e.g., '171'
                'payload' => [
                    'id' => $refId,
                    'data' => $msg,
                    'data_type' => $dataType,
                    'reply_to' => $replyTo,
                    'user_id' => $senderId,
                    'sender_id' => $senderId,
                    'date' => Helper::formatCustomDateTime(now(),'d-M-Y') ?? null,
                    'time' => Helper::formatCustomDateTime(now(),'H:i A') ?? null,
                    'isSelf' => $isSelf, // Assuming this is always true for the sender
                    // 'user_id' => $comment->user_id
                ]
            ]));
            $client->close();
        } catch (Exception $e) {
            error_log("WS failed: " . $e->getMessage());
        }
        // exit;
        // $client->close();
    }

    public function deleteCommentDescriptionById(int $commentId,string|int $treadId,int $detailId, object $authUser): object{
        $commentDescription = CommentDescriptions::where('is_deleted',false)
        ->where('thread_id', $treadId)
        ->where('comment_id', $commentId)
        ->find($detailId);
        if(!$commentDescription){
            return DataResponse::ValidateFail('Comment description not found');
        }
        if($commentDescription->create_uid != $authUser->id){
            return DataResponse::ValidateFail('You are not authorized to delete this comment');
        }
        $commentDescription->is_deleted = true;
        $commentDescription->deleted_uid = $authUser->id;
        $commentDescription->deleted_datetime = now();
        $commentDescription->save();

        return DataResponse::JsonResult([], false, __('messages.deleted'));
    }

    public function deleteMobileCommentDescriptionById(string|int $treadId,int $detailId, object $authUser): object{
        $commentDescription = CommentDescriptions::where('is_deleted',false)
        ->where('thread_id', $treadId)
        ->find($detailId);
        if(!$commentDescription){
            return DataResponse::ValidateFail('Comment description not found');
        }
        if($commentDescription->create_uid != $authUser->id){
            return DataResponse::ValidateFail('You are not authorized to delete this comment');
        }
        $commentDescription->is_deleted = true;
        $commentDescription->deleted_uid = $authUser->id;
        $commentDescription->deleted_datetime = now();
        $commentDescription->save();

        return DataResponse::JsonResult([], false, __('messages.deleted'));
    }

    public function editCommentDescriptionById(int $commentId,string|int $treadId,int $detailId, object $authUser): object{
        $commentDescription = CommentDescriptions::where('is_deleted',false)
        ->where('thread_id', $treadId)
        ->where('comment_id', $commentId)
        ->find($detailId);
        if(!$commentDescription){
            return DataResponse::ValidateFail('Comment description not found');
        }
        if($commentDescription->create_uid != $authUser->id){
            return DataResponse::ValidateFail('You are not authorized to edit this comment');
        }
        $commentDescription->data = request()->input('data');
        $commentDescription->data_type = request()->input('data_type');
        $commentDescription->update_uid = $authUser->id;
        $commentDescription->save();

        return DataResponse::JsonResult([], false, __('messages.updated'));
    }

}
