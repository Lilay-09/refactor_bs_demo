<?php

namespace App\Services;

use Http;



class PlasgateService
{
    // Your service methods go here
    protected $baseUrl;
    protected $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.plasgate.base_url');
        $this->apiKey = config('services.plasgate.api_key');
    }

    public function sendSMS($to, $message)
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])->post("{$this->baseUrl}/send-sms", [
            'to' => $to,
            'message' => $message,
        ]);
    }
}
