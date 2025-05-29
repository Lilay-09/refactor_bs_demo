<?php

namespace App\Services;

use Illuminate\Http\Request;

interface UserNotificationService
{
    //
    public function getUserNotificationSubscriptions(Request $req,object $authUser):object;
}
