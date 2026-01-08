<?php
return [
    'production' => [
        'notification' => 'ng_fcm_notification',
        'registered_telegram_message' => 'ng_registered_telegram_message',
        'chat' => 'ng_chat_message',
        'payment' => 'ng_payment',
        'exports' => 'ng_export_report'
    ],
    'development' => [
        'notification' => 'ng_fcm_notification_dev',
        'registered_telegram_message' => 'ng_registered_telegram_message_dev',
        'chat' => 'ng_chat_message_dev',
        'payment' => 'ng_payment_dev',
        'exports' => 'ng_export_report_dev'
    ],
];
