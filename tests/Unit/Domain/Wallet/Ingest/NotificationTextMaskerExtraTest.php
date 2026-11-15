<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Wallet\Ingest;

use App\Domain\Wallet\Ingest\NotificationTextMasker;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 第四輪驗收補的遮罩案例、preg 錯誤記錄、ZWJ 處理。
 */
class NotificationTextMaskerExtraTest extends TestCase
{
    /**
     * @return array<string, array{0:string, 1:string}>
     */
    public static function masked(): array
    {
        return [
            'AMEX 4-6-5' => ['AMEX 3782 822463 10005 消費', 'AMEX **** 消費'],
            'AMEX 4-6-5 連字號' => ['卡號 3782-822463-10005', '卡號 ****'],
            'AMEX 15 碼連號' => ['378282246310005', '****'],
            '護照號碼' => ['護照號碼 312345678', '護照號碼 ****'],
            'Passport No.' => ['Passport No. 312345678', 'Passport No. ****'],
            '護照帶英文字母' => ['護照：A12345678', '護照：****'],
            '舊式居留證' => ['居留證 AB12345678', '居留證 ****'],
            '手機 09-1234-5678' => ['手機 09-1234-5678', '手機 ****'],
            '末四碼中文數字' => ['卡號末四碼一二三四', '卡號末四碼****'],
            '末四碼中文數字含零' => ['末四碼〇一二九', '末四碼****'],
        ];
    }

    #[DataProvider('masked')]
    public function test_masks(string $input, string $expected): void
    {
        $this->assertSame($expected, NotificationTextMasker::mask($input));
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function kept(): array
    {
        return [
            '金額' => ['消費 NT$12,345'],
            '日期' => ['2026/11/15 13:15'],
            '訂單編號（英數）' => ['訂單編號 AB12345678'],
            '訂單編號（長數字）' => ['訂單編號：1234567890123'],
            '訂單單號（卡號格式）' => ['單號 1234-5678-9012'],
            '優惠碼（身分證格式）' => ['優惠碼 A123456789'],
            'order no' => ['Order #378282246310005'],
            '一般 9 碼數字（非護照語境）' => ['會員編號 312345678'],
            '金額中文' => ['二百五十元'],
        ];
    }

    #[DataProvider('kept')]
    public function test_does_not_mask(string $input): void
    {
        $this->assertSame($input, NotificationTextMasker::mask($input));
    }

    public function test_preg_error_returns_empty_string_and_logs_without_original_text(): void
    {
        Log::spy();
        $secret = '卡號 1234-5678-9012-3456 '.str_repeat('X', 300).'1';
        $limit = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', '5');

        try {
            $masked = NotificationTextMasker::mask($secret);
        } finally {
            ini_set('pcre.backtrack_limit', (string) $limit);
        }

        $this->assertSame('', $masked);
        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) use ($secret): bool {
            $dump = $message.json_encode($context, JSON_UNESCAPED_UNICODE);

            return $message === 'NotificationTextMasker preg error'
                && $context['error'] === 'Backtrack limit exhausted'
                && $context['length'] === mb_strlen($secret)
                && ! str_contains($dump, '1234')
                && ! str_contains($dump, '卡號')
                && ! str_contains($dump, 'XXX');
        });
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function emojiSequences(): array
    {
        return [
            '家庭' => ["\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}"],
            '彩虹旗' => ["\u{1F3F3}\u{FE0F}\u{200D}\u{1F308}"],
            '女性科學家（膚色）' => ["\u{1F469}\u{1F3FD}\u{200D}\u{1F52C}"],
        ];
    }

    #[DataProvider('emojiSequences')]
    public function test_zwj_inside_emoji_sequence_is_kept(string $emoji): void
    {
        $text = '在'.$emoji.'壽司郎消費';

        $this->assertSame($text, NotificationTextMasker::sanitize($text));
        $this->assertSame($text, NotificationTextMasker::singleLine($text));
        $this->assertSame($text, NotificationTextMasker::mask($text));
    }

    public function test_zwj_outside_emoji_is_removed_and_cannot_bypass_masking(): void
    {
        $this->assertSame('全家', NotificationTextMasker::sanitize("全\u{200D}家"));
        $this->assertSame('卡號****', NotificationTextMasker::mask("卡號1234\u{200D}5678\u{200D}9012\u{200D}3456"));
        $this->assertSame('末四碼****', NotificationTextMasker::mask("末四碼1\u{200D}2\u{200D}3\u{200D}4"));
        // ZWJ 緊貼 emoji 但另一側是數字：仍移除
        $this->assertSame("\u{1F600}卡號****", NotificationTextMasker::mask("\u{1F600}\u{200D}卡號1234\u{200D}5678\u{200D}9012\u{200D}3456"));
    }
}
