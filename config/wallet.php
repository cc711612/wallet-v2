<?php

return [
    /*
    | 每月自動建立／結算帳本的指令設定（command:auto_create_wallet、command:auto_calculate_wallet）。
    | 指令內不可直接呼叫 env()：部署時會 config:cache，cache 後 env() 一律回 null。
    */
    'auto_wallet' => [
        'user_id' => (int) env('AUTO_WALLET_USER_ID', 58),
        'seed_title' => (string) env('AUTO_WALLET_SEED_TITLE', '多多健保'),
        'seed_amount' => (int) env('AUTO_WALLET_SEED_AMOUNT', 943),
        'seed_category_id' => (int) env('AUTO_WALLET_SEED_CATEGORY_ID', 13),
    ],
];
