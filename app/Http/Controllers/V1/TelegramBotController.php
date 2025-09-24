<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\TelegramBotService;
use Illuminate\Http\Request;

class TelegramBotController extends Controller
{
    //
    public function __construct(private TelegramBotService $telegramService)
    {
        
    }

    public function create(Request $req){
        return ApiResponse::flex($this->telegramService->create($req->all()));
    }

    public function updateById(Request $req){
        return ApiResponse::flex($this->telegramService->updateById($req->id,$req->all()));
    }

    public function getBots(Request $req){
        return ApiResponse::flex($this->telegramService->getBots($req->all()));
    }
    public function getBotById(Request $req){
        return ApiResponse::flex($this->telegramService->getBotById($req->id));
    }
}
