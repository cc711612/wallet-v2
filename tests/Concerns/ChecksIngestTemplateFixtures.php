<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Domain\Wallet\Ingest\NotificationTemplateRegistry;
use App\Domain\Wallet\Ingest\NotificationTextMasker;
use Carbon\CarbonImmutable;

/**
 * 模板樣本檢查：每個模板都必須有 tests/Fixtures/ingest/<name>.json，
 * 內含 ≥2 則會命中「該模板」且欄位正確的成功樣本，以及 ≥3 則不得命中任何模板的負樣本。
 */
trait ChecksIngestTemplateFixtures
{
    /**
     * 範例（example）模板：虛構的「範例銀行」，只用於測試模板機制，不是真實銀行格式。
     * 樣本在 tests/Fixtures/ingest/examples/example-bank-purchase.json。
     *
     * @return array<string, mixed>
     */
    protected static function exampleTemplate(): array
    {
        return [
            'name' => 'example-bank-purchase',
            'source' => 'com.example.bank',
            'pattern' => '【範例銀行】您的信用卡於(?<date>\d{1,2}/\d{1,2}) (?<time>\d{1,2}:\d{2})在(?<merchant>[^，。\n$]{1,60}?)消費NT\$(?<amount>\d{1,3}(?:,\d{3})*(?:\.\d{1,2})?)。',
        ];
    }

    /** 樣本統一以這個時間當作 received_at */
    protected static string $fixtureReceivedAt = '2026-11-15 13:00:00';

    /**
     * @param  array<int, array<string, mixed>>  $templates
     * @return array<int, string> 錯誤清單；空陣列代表全部通過
     */
    protected function templateFixtureErrors(array $templates, string $fixtureDir): array
    {
        $registry = NotificationTemplateRegistry::fromArray($templates);
        $receivedAt = CarbonImmutable::parse(self::$fixtureReceivedAt, 'Asia/Taipei');
        $errors = [];

        foreach ($registry->templates() as $template) {
            $name = $template['name'];
            $path = rtrim($fixtureDir, '/').'/'.$name.'.json';
            if (! is_file($path)) {
                $errors[] = "{$name}: 缺少樣本檔 {$path}";

                continue;
            }

            $fixture = json_decode((string) file_get_contents($path), true);
            if (! is_array($fixture)) {
                $errors[] = "{$name}: 樣本檔不是合法 JSON";

                continue;
            }

            $positives = (array) ($fixture['positive'] ?? []);
            $negatives = (array) ($fixture['negative'] ?? []);
            if (count($positives) < 2) {
                $errors[] = "{$name}: 成功樣本少於 2 則";
            }
            if (count($negatives) < 3) {
                $errors[] = "{$name}: 負樣本少於 3 則";
            }

            foreach ($positives as $i => $sample) {
                $text = $this->fixtureText($sample);
                $parsed = $registry->match($text, $sample['source'] ?? $template['source'], $receivedAt);
                if ($parsed === null || $parsed->parser !== NotificationTemplateRegistry::PARSER_PREFIX.$name) {
                    $errors[] = "{$name}: 成功樣本 #{$i} 沒有命中此模板";

                    continue;
                }
                if (abs($parsed->amount - (float) ($sample['amount'] ?? -1)) > 0.001
                    || $parsed->merchant !== ($sample['merchant'] ?? null)
                    || (array_key_exists('date', $sample) && $parsed->date !== $sample['date'])) {
                    $errors[] = "{$name}: 成功樣本 #{$i} 解析結果與預期不符";
                }
            }

            foreach ($negatives as $i => $sample) {
                $source = array_key_exists('source', $sample) ? $sample['source'] : $template['source'];
                if ($registry->match($this->fixtureText($sample), $source, $receivedAt) !== null) {
                    $errors[] = "{$name}: 負樣本 #{$i}（".($sample['reason'] ?? '').'）不應命中任何模板';
                }
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $sample
     */
    private function fixtureText(array $sample): string
    {
        // 與正式流程相同：先清理再全形轉半形
        return trim(NotificationTextMasker::normalizeWidth(NotificationTextMasker::sanitize((string) ($sample['text'] ?? ''))));
    }
}
