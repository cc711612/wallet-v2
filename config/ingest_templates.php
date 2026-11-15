<?php

/*
|--------------------------------------------------------------------------
| 推播自動記帳模板（POST /api/wallet/{wallet}/detail/ingest）
|--------------------------------------------------------------------------
|
| 模板白名單：只有「整段原文」完全符合某個模板的推播才會自動記帳，其餘一律回 400。
| 目前還沒有真實銀行推播樣本，因此預設不內建任何模板（不要憑空編造銀行格式）。
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
    'templates' => [],
];
