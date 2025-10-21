<?php

namespace App\Services;

use App\Models\User;

interface TelegramBotService
{
    //
    public function create(array $data);
    public function updateById(int $id,array $data);
    public function getBotById(int $id);
    public function deleteBotById(int $id);
    public function getBots(array $filter);
    public function sendLog(User $senderId,array $data);
}
