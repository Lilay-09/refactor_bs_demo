<?php

namespace App\Services;

use App\DTO\PackageCommentDTO;
use App\Enums\CommentSource;
use App\Models\Comment;
use App\Models\CommentDescriptions;
use App\Models\CommentUser;
use DataResponse;
use DB;
use Illuminate\Http\Request;
use Log;
// use WebSocket\Client;

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
    public function addComment(Request $req,object $authUser): object
    {
        $validator = $this->commentValidator($req);
        if($validator->fails()){
            return DataResponse::ValidateFail($validator->errors()->first());
        }
        $inputs = $validator->validated();
        $threadId = $inputs['thread_id'];
        $comment = Comment::where('thread_id', $threadId)->first();
        $commentId = null;
        try{
            DB::beginTransaction();
            if(!$comment){
            $createComment = Comment::create([
                'thread_id' => $threadId,
                'source' => CommentSource::PACKAGE->value,
                'started_at' => now(),
                'starter_id' => $authUser->id,
                'company_id' => $authUser->company_id,
                'branch_id' => $authUser->branch_id,
            ]);
            $commentId = $createComment->id;
            }else {
                $commentId = $comment->id;
            }
            CommentUser::updateOrCreate([
                'comment_id' => $commentId,
                'user_id' => $authUser->id,
                'user_type' => $comment ? 'owner':'member',
            ]);

            $cmmDesId = CommentDescriptions::insertGetId([
                'parent_id' => $inputs['reply_to'] ?? null,
                'thread_id' => $threadId,
                'comment_id' => $commentId,
                'data' => $inputs['data'],
                'data_type' => $inputs['data_type'],
                'create_uid' => $authUser->id,
                'update_uid' => $authUser->id,
                'branch_id' => $authUser->branch_id,
                'company_id' => $authUser->company_id,
            ]);
            // DB::commit();
            return DataResponse::JsonResult([
                'id' => $cmmDesId
            ], false);
        }catch (\Exception $e) {
            DB::rollBack();
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
}
