<?php

namespace App\Jobs;

use App\Services\CommentServiceImpl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCommentSocketJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $pkgId, $msg, $dataType, $senderId, $replyTo, $refId;

    public function __construct($pkgId, $msg, $dataType, $senderId, $replyTo, $refId)
    {
        $this->pkgId = $pkgId;
        $this->msg = $msg;
        $this->dataType = $dataType;
        $this->senderId = $senderId;
        $this->replyTo = $replyTo;
        $this->refId = $refId;
    }

    public function handle()
    {
        // Put your sendCommentSocket logic here or call it
        (new CommentServiceImpl)->sendCommentSocket(
            $this->pkgId,
            $this->msg,
            $this->dataType,
            $this->senderId,
            $this->replyTo,
            $this->refId,
        );
        // \Log::info('Sent');
    }
}
