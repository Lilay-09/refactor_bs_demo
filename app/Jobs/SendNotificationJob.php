<?php

namespace App\Jobs;

use App\Services\CloudMessagingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\Request;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */protected $clmsgReq;
    protected $user;

    public function __construct(Request $clmsgReq, $user)
    {
        $this->clmsgReq = $clmsgReq;
        $this->user = $user;
    }

    public function handle()
    {
        $clmsg = new CloudMessagingService();
        $clmsg->sendNotificationByTopic($this->clmsgReq, $this->user);
    }
}
