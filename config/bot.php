<?php

return [
    'line' => [
        'access_token' => env('LINE_BOT_TOKEN', ''),
        'channel_secret' => env('LINE_BOT_SECRET', ''),
        'admin_user_ids' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('LINE_ADMIN_USERID', '')),
        ))),
    ],
];
