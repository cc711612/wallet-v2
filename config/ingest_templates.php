<?php

/*
|--------------------------------------------------------------------------
| 推播自動記帳模板（POST /api/wallet/{wallet}/detail/ingest）
|--------------------------------------------------------------------------
|
| 模板白名單：只有「整段原文」完全符合某個模板的推播才會自動記帳，其餘一律回 400。
| 只放有真實推播樣本支撐的模板（不要憑空編造銀行格式）。
|
| 新增模板的步驟、欄位說明與樣本要求見 docs/api/wallet-detail-ingest.md「如何新增模板」。
| 每個模板都必須在 tests/Fixtures/ingest/<name>.json 附上 ≥2 則成功樣本與 ≥3 則負樣本，
| 否則 tests/Unit/Domain/Wallet/Ingest/TemplateFixtureCoverageTest 會失敗。
|
| 欄位：
| - name     必填，唯一，例如 'esun-credit-purchase'
| - pattern  必填，正規表示式本體（不含分隔符號與 ^ $），系統會自動加上首尾錨定（允許首尾空白）並以 /u 比對；
|            必須含具名群組 (?<amount>…) 與 (?<merchant>…)，可選 (?<date>…)、(?<time>…)。
|            比對對象是「已清理控制字元＋全形轉半形」後的原文。
| - currency 選填，預設 'TWD'；本版只允許 TWD。
| - source   選填，MacroDroid 送來的 source（App package）必須以此字串開頭（相等也算）才會嘗試此模板。
|
*/

return [
    'templates' => [
        // 國泰世華信用卡刷卡通知（App 推播）。來源：使用者 2026-11-15 的真實推播（樣本末四碼已換成 1234）。
        // 假設 MacroDroid 送出的 text 是「title + 換行 + text」，title「國泰世華銀行」可有可無。
        // 結尾行銷句是固定尾句，逐字比對；國泰改文案時需要更新此模板與樣本。
        // source 暫不限定；確認 MacroDroid 送出的 source 後可加上 'source' => '<App 名稱或套件名稱>'。
        [
            'name' => 'cathay-credit-purchase',
            'pattern' => '(?:國泰世華銀行\s+)?【刷卡通知】金額NT\$(?<amount>\d{1,3}(?:,\d{3})*(?:\.\d{1,2})?)元\s*'
                .'卡號末四碼\d{4}於\s?(?<date>\d{4}/\d{1,2}/\d{1,2})\s(?<time>\d{1,2}:\d{2})'
                .'在(?<merchant>[^\n，。：:;；$]{1,60}?)\s?刷卡。'
                .'立即以點數即時折抵，最高可折抵消費金額30%，點擊查看消費明細',
        ],

        // 玉山信用卡消費通知（玉山銀行 App 與玉山 Wallet 都會推，原文相同）。來源：使用者 2026-11-15 的真實推播（測試刷卡 600 元）。
        // 推播沒有商店名也沒有日期：商店名用 merchant_default（使用者可改名），日期用 received_at 的台北日期。
        // title「玉山銀行」可有可無；source 不限定。金額有無千分位皆可。
        [
            'name' => 'esun-credit-purchase',
            'pattern' => '(?:玉山銀行\s+)?信用卡消費(?<amount>(?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d{1,2})?)元通知',
            'merchant_default' => '玉山信用卡消費',
        ],
    ],
];
