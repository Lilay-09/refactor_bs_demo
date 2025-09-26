<?php

namespace App\Services;

interface TelegramBotService
{
    //
    public function create(array $data);
    public function updateById(int $id,array $data);
    public function getBotById(int $id);
    public function deleteBotById(int $id);
    public function getBots(array $filter);
}
