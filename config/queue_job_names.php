<?php
return [
    'production' => [
        'notification' => 'ng_fcm_notification',
        'registered_telegram_message' => 'ng_registered_telegram_message',
        'chat' => 'ng_chat_message'
    ],
    'development' => [
        'notification' => 'ng_fcm_notification_dev',
        'registered_telegram_message' => 'ng_registered_telegram_message_dev',
        'chat' => 'ng_chat_message_dev'
    ],
];
