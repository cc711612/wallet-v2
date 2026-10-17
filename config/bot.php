<?php

return [
    'line' => [
        'access_token' => env('LINE_BOT_TOKEN', ''),
        'channel_secret' => env('LINE_BOT_SECRET', ''),
        // /api/webhook/line/notify 內部轉發用的共享密鑰（X-Notify-Relay-Key），未設定時一律拒絕
        'notify_relay_key' => env('LINE_NOTIFY_RELAY_KEY', ''),
        'admin_user_ids' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('LINE_ADMIN_USERID', '')),
        ))),
    ],
];
