<?php

namespace App\Services;

use App\DTO\PackageCommentDTO;
use App\Enums\CommentSource;
use App\Jobs\SendCommentSocketJob;
use App\Models\Comment;
use App\Models\CommentDescriptions;
use App\Models\CommentUser;
use App\Models\MerchantOperator;
use App\Models\Package;
use DataResponse;
use DB;
use Exception;
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
        Log::info($req->all());

        $validator = $this->commentValidator($req);
        if ($validator->fails()) {
            return DataResponse::ValidateFail($validator->errors()->first());
        }

        $inputs = $validator->validated();
        $threadId = $inputs['thread_id'];
        $replyTo = $inputs['reply_to'] ?? null;

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
            $queueFCMName = config('queue_job_names.'.config('app.env').'.chat');
            Log::info($queueFCMName);
            SendCommentSocketJob::dispatch($threadId, $inputs['data'], $inputs['data_type'], $authUser->id, $replyTo, $commentId)
            ->onQueue('ng_chat_message_dev');
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

            // DB::commit();

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


    public function getPackageComments(int $packageId, object $authUser): object
    {
        $comment = Comment::where('thread_id', $packageId)
            ->where('source', CommentSource::PACKAGE->value)
            ->first();
        if ($comment) {
            $comment->load(['descriptions' => function($query) {
                $query->orderBy('created_at', 'asc');
            }]);
            $res = $comment->descriptions->map(function($description) use($authUser) {
                if($authUser->id == $description->create_uid){
                    $description->isSelf = true;
                }
                return PackageCommentDTO::fromModel($description)->toArray();
            });
            return DataResponse::JsonResult($res, false);
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
            // DB::commit();
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
            'package:id,merchant_id,receiver_phone,receiver_address,zone_code,zone_name,status_id,remarks,cod,price,price_khr,qr_code',
            'package.merchant:id,username,phone'
        ]);
        $callback = function($q){
            foreach($q->package->getAttributes() as $key=>$value){
                $q->{$key} = $value;
                $q->merchant_name = $q->package->merchant->username;
                $q->merchant_phone = $q->package->merchant->phone;
            }
            unset($q->package);
            return $q;
        };
        return DataResponse::PaginationV1($qC,$req,'',[],1000,$callback);
    }

    public function getPackageCommentDetailsById(int $id,object $authUser): object{
        $comment = Comment::find($id)
            ->where('source', CommentSource::PACKAGE->value)
            ->first();
        if ($comment) {
            $comment->load([
                'descriptions' => function ($query) {
                    $query->select('id', 'comment_id', 'parent_id', 'data', 'data_type')
                        ->orderBy('created_at', 'asc');
                },
                'descriptions.replyTo' => function ($query) {
                    $query->select('id', 'parent_id', 'comment_id', 'data', 'data_type');
                },
            ]);


            $res = $comment->descriptions->map(function($description) use($authUser) {
                if($authUser->id == $description->create_uid){
                    $description->isSelf = true;
                }
                $description->topic = "Package";
                return PackageCommentDTO::fromModel($description)->toArray();
            });
            return DataResponse::JsonResult($res, false);
        }

        return DataResponse::JsonResult([], false);
    }

    public function sendCommentSocket($pkgId,$msg,$dataType,$senderId,$replyTo,$refId){
        $uri = config('services.socket.chat_service_socket').'?key='.config('services.socket.chat_service_key');
        // Log::info($uri);
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
                    'data_type' => $dataType
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
}
