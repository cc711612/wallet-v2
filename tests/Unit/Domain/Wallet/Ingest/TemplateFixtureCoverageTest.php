<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Wallet\Ingest;

use Illuminate\Support\Facades\File;
use Tests\Concerns\ChecksIngestTemplateFixtures;
use Tests\TestCase;

/**
 * 資料驅動：config/ingest_templates.php 裡的每個模板都必須有成功樣本與負樣本（tests/Fixtures/ingest/<name>.json）。
 */
class TemplateFixtureCoverageTest extends TestCase
{
    use ChecksIngestTemplateFixtures;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/ingest-fixture-test-'.bin2hex(random_bytes(4));
        File::makeDirectory($this->tempDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    public function test_every_configured_template_has_fixture_samples(): void
    {
        /** @var array<int, array<string, mixed>> $templates */
        $templates = (array) (require base_path('config/ingest_templates.php'))['templates'];

        $this->assertSame([], $this->templateFixtureErrors($templates, base_path('tests/Fixtures/ingest')));
    }

    public function test_example_template_with_complete_fixture_passes(): void
    {
        $this->assertSame([], $this->templateFixtureErrors([self::exampleTemplate()], base_path('tests/Fixtures/ingest/examples'), false));
    }

    public function test_missing_fixture_file_fails(): void
    {
        $errors = $this->templateFixtureErrors([self::exampleTemplate()], $this->tempDir, false);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('缺少樣本檔', $errors[0]);
    }

    public function test_too_few_samples_fail(): void
    {
        $this->writeFixture([
            'positive' => [['text' => '【範例銀行】您的信用卡於11/14 19:20在全家消費NT$85。', 'amount' => 85, 'merchant' => '全家']],
            'negative' => [['text' => '隨便一句話', 'reason' => 'x']],
        ]);

        $errors = $this->templateFixtureErrors([self::exampleTemplate()], $this->tempDir, false);

        $this->assertContains('example-bank-purchase: 成功樣本少於 2 則', $errors);
        $this->assertContains('example-bank-purchase: 負樣本少於 3 則', $errors);
    }

    public function test_negative_sample_that_matches_fails(): void
    {
        $sample = '【範例銀行】您的信用卡於11/14 19:20在全家消費NT$85。';
        $this->writeFixture([
            'positive' => [
                ['text' => $sample, 'amount' => 85, 'merchant' => '全家'],
                ['text' => '【範例銀行】您的信用卡於11/14 19:20在萊爾富消費NT$40。', 'amount' => 40, 'merchant' => '萊爾富'],
            ],
            'negative' => [
                ['text' => $sample, 'reason' => '其實會命中'],
                ['text' => '取消', 'reason' => 'x'],
                ['text' => '退款', 'reason' => 'y'],
            ],
        ]);

        $errors = $this->templateFixtureErrors([self::exampleTemplate()], $this->tempDir, false);

        $this->assertSame(['example-bank-purchase: 負樣本 #0（其實會命中）不應命中任何模板'], $errors);
    }

    public function test_positive_sample_with_wrong_expectation_fails(): void
    {
        $this->writeFixture([
            'positive' => [
                ['text' => '【範例銀行】您的信用卡於11/14 19:20在全家消費NT$85。', 'amount' => 86, 'merchant' => '全家'],
                ['text' => '【範例銀行】您的信用卡於11/14 19:20在萊爾富消費NT$40。已入帳', 'amount' => 40, 'merchant' => '萊爾富'],
            ],
            'negative' => [['text' => 'a', 'reason' => 'a'], ['text' => 'b', 'reason' => 'b'], ['text' => 'c', 'reason' => 'c']],
        ]);

        $errors = $this->templateFixtureErrors([self::exampleTemplate()], $this->tempDir, false);

        $this->assertSame([
            'example-bank-purchase: 成功樣本 #0 解析結果與預期不符',
            'example-bank-purchase: 成功樣本 #1 沒有命中此模板',
        ], $errors);
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function writeFixture(array $fixture): void
    {
        file_put_contents($this->tempDir.'/example-bank-purchase.json', (string) json_encode($fixture, JSON_UNESCAPED_UNICODE));
    }

    public function test_configured_templates_are_not_empty(): void
    {
        $this->assertContains('cathay-credit-purchase', array_column((array) config('ingest_templates.templates'), 'name'));
    }

    public function test_fixture_without_real_positive_sample_fails(): void
    {
        $this->writeFixture([
            'positive' => [
                ['text' => '【範例銀行】您的信用卡於11/14 19:20在全家消費NT$85。', 'amount' => 85, 'merchant' => '全家', 'synthetic' => true],
                ['text' => '【範例銀行】您的信用卡於11/14 19:20在萊爾富消費NT$40。', 'amount' => 40, 'merchant' => '萊爾富'],
            ],
            'negative' => [['text' => 'a', 'reason' => 'a'], ['text' => 'b', 'reason' => 'b'], ['text' => 'c', 'reason' => 'c']],
        ]);

        $this->assertSame(
            ['example-bank-purchase: 沒有真實的成功樣本（至少 1 則要標記 "synthetic": false）'],
            $this->templateFixtureErrors([self::exampleTemplate()], $this->tempDir)
        );

        $this->writeFixture([
            'positive' => [
                ['text' => '【範例銀行】您的信用卡於11/14 19:20在全家消費NT$85。', 'amount' => 85, 'merchant' => '全家', 'synthetic' => false],
                ['text' => '【範例銀行】您的信用卡於11/14 19:20在萊爾富消費NT$40。', 'amount' => 40, 'merchant' => '萊爾富', 'synthetic' => true],
            ],
            'negative' => [['text' => 'a', 'reason' => 'a'], ['text' => 'b', 'reason' => 'b'], ['text' => 'c', 'reason' => 'c']],
        ]);

        $this->assertSame([], $this->templateFixtureErrors([self::exampleTemplate()], $this->tempDir));
    }
}
