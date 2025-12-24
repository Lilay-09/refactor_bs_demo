<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'socket' => [
        'cl_socket' => env('CL_SOCKET','ws://192.168.0.236:3000/api/ws'),
        'chat_service_socket' => env('CHAT_SERVICE_SOCKET','ws://localhost:3000/_ws'),
        'chat_service_key' => env('CHAT_API_KEY'),
        'client_origin' => env('CL_ORIGIN')
    ],
    'package' => [
        'info_key' => env('INFO_KEY','asdf354feff'),
    ],

    'plasgate' => [
        'base_url' => env('PLASGATE_BASE_URL'),
        'private_key' => env('PLASGATE_PRIVATE_KEY'),
        'secret' => env('PLASGATE_SECRET'),
    ],

    'aba' => [
        'apikey' => env('ABAPAYWAYKEY',''),
        'merchantid' => env('ABAMID',''),
        'baseURL' => env('ABA_BASE_URL'),
        'checkTransactionEndpoint' => env('ABA_CHECK_TRAN_ENDPOINT','/api/payment-gateway/v1/payments/check-transaction-2'),
        'checkTransactionDetailsEndpoint' => env('ABA_CHECK_TRAN_DETAILS_ENDPOINT','/api/payment-gateway/v1/payments/transaction-detail'),
    ],
    'payway' => [
        'key' => env('PAYWAY_KEY'),
        'callback' => env('APP_URL').'/api/driver/v1/'.app()->getLocale().env('PW_CALLBACK_ENDPOINT'),
        'receiver-callback' => env('APP_URL').'/api/driver/v1/'.app()->getLocale().env('PW_RECEIVER_CALLBACK_ENDPOINT'),
        'driver-callback' => env('APP_URL').'/api/driver/v1/'.app()->getLocale().env('PW_DRIVER_CALLBACK_ENDPOINT'),
        'max_stream_duration' => env('PAYWAY_MAX_STREAM_DURATION', 60), // Default to 60 seconds if not set
        'sleep_duration' => env('PAYWAY_SLEEP_DURATION', 1.5), // Default to 2 seconds if not set
    ],

];
