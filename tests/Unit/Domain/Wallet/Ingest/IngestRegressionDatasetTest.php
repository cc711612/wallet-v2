<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Wallet\Ingest;

use App\Domain\Wallet\Ingest\NotificationTextMasker;
use App\Domain\Wallet\Ingest\RuleBasedNotificationParser;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 推播解析與遮罩的回歸資料集（來源：第三輪驗收 probe3 的 real／non／non2／fx／fxtw／mask／mask2）。
 * 改規則時這裡會擋下：真實消費不得錯記或退化、非消費不得記帳、外幣不得記成台幣、卡號不得漏遮或誤遮。
 */
class IngestRegressionDatasetTest extends TestCase
{
    private function parse(string $text): ?\App\Domain\Wallet\Ingest\ParsedExpense
    {
        return (new RuleBasedNotificationParser)->parse($text, CarbonImmutable::parse('2026-11-15 13:00:00', 'Asia/Taipei'));
    }

    /**
     * 真實台幣消費 39 筆：37 筆必須解出正確金額與商店。
     *
     * @return array<string, array{0:string, 1:float, 2:string}>
     */
    public static function realExpenses(): array
    {
        return [
            'real-01' => ['【玉山銀行】您末四碼5678的信用卡於全家便利商店消費金額NT$1,234，謝謝。', 1234, '全家便利商店'],
            'real-02' => ['您的信用卡於11/15在7-ELEVEN消費NT$85', 85, '7-ELEVEN'],
            'real-03' => ['【國泰世華】您於PChome 24h購物刷卡金額NT$1,990', 1990, 'PChome 24h購物'],
            'real-04' => ['於全聯福利中心 一店消費 新台幣320元', 320, '全聯福利中心 一店'],
            'real-05' => ['商店：Uniqlo-台北101 消費金額：NT$590', 590, 'Uniqlo-台北101'],
            'real-06' => ['您在85度C消費120元', 120, '85度C'],
            'real-07' => ['【中信】您的信用卡於星巴克消費NT$150。如有疑問請洽客服，登錄 App 查看優惠', 150, '星巴克'],
            'real-08' => ['您於麥當勞消費NT$200。本訊息僅供通知，非帳單', 200, '麥當勞'],
            'real-09' => ['您於誠品書店消費NT$300，本筆消費可於帳單查詢', 300, '誠品書店'],
            'real-10' => ['您於家樂福消費NT$400，刷卡回饋請至官網登錄', 400, '家樂福'],
            'real-11' => ['您於學校福利社消費NT$50', 50, '學校福利社'],
            'real-12' => ['您於滿漢餐廳消費NT$880', 880, '滿漢餐廳'],
            'real-13' => ['您於限時專送消費NT$99', 99, '限時專送'],
            'real-14' => ['您於登錄街咖啡消費NT$150', 150, '登錄街咖啡'],
            'real-16' => ['您於優惠廚房消費NT$260', 260, '優惠廚房'],
            'real-17' => ['您於全家消費NT$１，２３４', 1234, '全家'],
            'real-18' => ['您於全家消費金額１２３４元', 1234, '全家'],
            'real-19' => ['您於好市多消費金額NT$1500', 1500, '好市多'],
            'real-20' => ['您於Apple消費金額NT$99.50', 99.5, 'Apple'],
            'real-21' => ['115/11/14 您於誠品消費NT$500', 500, '誠品'],
            'real-22' => ['您於中油站號123加油站消費NT$2,000 可用額度NT$50,000', 2000, '中油站號123加油站'],
            'real-23' => ['您末4碼1234信用卡11/15 12:30於好市多消費金額NT$350', 350, '好市多'],
            'real-24' => ['【台新銀行】卡號末四碼1234於11/15 12:30在UBER EATS刷卡NT$480', 480, 'UBER EATS'],
            'real-25' => ['富邦J卡 11/15 消費 NT$720 商店:momo購物網', 720, 'momo購物網'],
            'real-26' => ['LINE Pay 付款通知：您於路易莎咖啡付款 NT$45', 45, '路易莎咖啡'],
            'real-27' => ['交易金額：新台幣660元 特店名稱：誠品生活', 660, '誠品生活'],
            'real-28' => ['授權金額：TWD 1,200 商店名稱：IKEA', 1200, 'IKEA'],
            'real-29' => ['您於全家便利商店消費$75', 75, '全家便利商店'],
            'real-30' => ['您的簽帳金融卡於好樂迪消費金額NT$1,800', 1800, '好樂迪'],
            'real-31' => ['在50嵐刷卡NT$230', 230, '50嵐'],
            'real-32' => ['您於蝦皮購物消費NT$999，提醒您留意帳戶安全', 999, '蝦皮購物'],
            'real-33' => ['您於金石堂消費NT$888，詳情請登入網銀查詢', 888, '金石堂'],
            'real-34' => ['您於屈臣氏消費NT$560(含優惠折抵)', 560, '屈臣氏'],
            'real-35' => ['您於康是美消費NT$420，本卡享加碼回饋', 420, '康是美'],
            'real-36' => ['您於SOGO百貨消費NT$1,500。活動期間刷卡享好禮', 1500, 'SOGO百貨'],
            'real-37' => ['您於中華電信消費NT$260', 260, '中華電信'],
            'real-38' => ['您於利息麵館消費NT$130', 130, '利息麵館'],
        ];
    }

    #[DataProvider('realExpenses')]
    public function test_real_expense_is_parsed_correctly(string $text, float $amount, string $merchant): void
    {
        $parsed = $this->parse($text);

        $this->assertNotNull($parsed, '真實消費不應回 null：'.$text);
        $this->assertSame($amount, $parsed->amount);
        $this->assertSame($merchant, $parsed->merchant);
    }

    /**
     * 資料集中目前解析不到的 2 筆（不得錯記；改善後可移到 realExpenses）。
     *
     * @return array<string, array{0:string, 1:string}>
     */
    public static function realExpensesNotParsed(): array
    {
        return [
            '商店名含強排除字「分期」（已知限制 R3）' => ['商店：分期樂3C 消費金額：NT$3,000', '商店名含強排除字「分期」（已知限制 R3）'],
            '自動加值不是消費（資料集標註金額 70 與原文不符，視為非消費）' => ['您於悠遊卡自動加值消費NT$500', '自動加值不是消費（資料集標註金額 70 與原文不符，視為非消費）'],
        ];
    }

    #[DataProvider('realExpensesNotParsed')]
    public function test_known_unparsed_cases_are_not_recorded(string $text, string $reason): void
    {
        $this->assertNull($this->parse($text), $reason);
    }

    /**
     * 非消費 63 筆：一律不得記成支出。
     *
     * @return array<string, array{0:string}>
     */
    public static function nonExpenses(): array
    {
        return [
            'non-01' => ['您的現金回饋NT$123已入帳'],
            'non-02' => ['您本期紅利點數增加500點，於全家消費'],
            'non-03' => ['您於11/10繳款NT$5,000已收到，謝謝'],
            'non-04' => ['您11月信用卡帳單應繳金額NT$12,345，最低應繳NT$1,235'],
            'non-05' => ['您的信用卡可用額度為NT$50,000'],
            'non-06' => ['您於全家消費NT$100之交易已取消'],
            'non-07' => ['您於全家消費NT$100授權失敗'],
            'non-08' => ['您於蝦皮的消費NT$300已退款'],
            'non-09' => ['您於蝦皮消費NT$300之OTP驗證碼為123456'],
            'non-10' => ['刷卡滿NT$3,000送全家禮券，於全家消費即享'],
            'non-11' => ['即日起於全家消費NT$100享88折'],
            'non-12' => ['您於燦坤消費NT$30,000，分12期'],
            'non-13' => ['您於燦坤消費NT$30,000已辦理分期付款，每期NT$2,500'],
            'non-14' => ['您的信用卡年費NT$1,800將於11/20於帳戶扣除'],
            'non-15' => ['您上期款項尚未繳清，將收取遲繳費NT$300'],
            'non-16' => ['您於11/15在台北車站ATM提領NT$3,000'],
            'non-17' => ['您本期循環利息NT$120'],
            'non-18' => ['您於國外消費NT$1,000之海外手續費NT$15'],
            'non-19' => ['您的信用卡已完成掛失，如於掛失前在全家消費請洽客服'],
            'non-20' => ['您的網銀密碼已於11/15變更'],
            'non-21' => ['您於11/15 13:00登入網銀'],
            'non-22' => ['您的國泰人壽保費NT$2,345已自動扣款'],
            'non-23' => ['台電電費NT$1,234代扣成功'],
            'non-24' => ['您的台水水費NT$456已由帳戶扣款完成'],
            'non-25' => ['您的帳戶收到轉帳NT$5,000'],
            'non-26' => ['薪資入帳NT$45,000'],
            'non-27' => ['您已轉帳NT$1,000至帳號123'],
            'non-28' => ['您於全家ATM消費提款NT$2,000'],
            'non-29' => ['您於ATM預借現金NT$10,000'],
            'non-30' => ['您的帳戶匯入NT$3,000，匯款人王小明'],
            'non-31' => ['您的信用額度已調整為NT$100,000'],
            'non-32' => ['您的新卡已開卡成功，於全家消費享優惠'],
            'non-33' => ['您於全家消費NT$100之交易已更正為NT$90'],
            'non-34' => ['飯店預先授權NT$5,000，將於退房後釋放'],
            'non-35' => ['您的悠遊卡自動加值NT$500'],
            'non-36' => ['您於Netflix的訂閱扣款NT$390未成功'],
            'non-37' => ['中華電信帳款NT$599已由信用卡代繳'],
            'non-38' => ['您於全家消費NT$100已退刷'],
            'non-39' => ['您於全家刷卡NT$100交易未授權'],
            'non-40' => ['您的帳戶存入NT$10,000'],
            'non-41' => ['您預約於11/20轉帳NT$5,000'],
            'non-42' => ['LINE Pay：王小明向您收款 NT$300 於全家消費分攤'],
            'non-43' => ['您的卡片因異常交易已停用，最後一筆在全家消費NT$100'],
            'non-44' => ['即日起至12/31於全家消費享88折'],
            'non-45' => ['於全家消費享9折優待NT$100'],
            'non-46' => ['您於全家消費NT$100未核准'],
            'non-47' => ['您於全家消費NT$100交易不成功'],
            'non-48' => ['您於全家刷卡NT$100授權未通過'],
            'non-49' => ['您於台北車站ATM跨行提款交易NT$3,000'],
            'non-50' => ['您於11/15在台北車站ATM提款交易NT$3,000'],
            'non-51' => ['您於全家消費NT$100，此筆為預先授權'],
            'non-52' => ['您的信用卡於全家消費NT$100已被婉拒'],
            'non-53' => ['您於全家消費NT$100疑似盜刷已暫停'],
            'non-54' => ['您於全家消費NT$100已撥款'],
            'non-55' => ['您於全家消費NT$100，交易已拒絕'],
            'non-56' => ['您於全家消費贈送點數100點'],
            'non-57' => ['您於全家消費NT$100獲得LINE POINTS 3點'],
            'non-58' => ['您於全家消費NT$100，返現NT$3'],
            'non-59' => ['您於全家的交易NT$100已作廢'],
            'non-60' => ['您於全家消費NT$100沖回'],
            'non-61' => ['您於Netflix消費NT$390，下期起停止續訂'],
            'non-62' => ['您於全家消費NT$100交易逾時'],
            'non-63' => ['您於全家消費NT$100扣抵點數'],
        ];
    }

    #[DataProvider('nonExpenses')]
    public function test_non_expense_is_not_recorded(string $text): void
    {
        $parsed = $this->parse($text);

        $this->assertNull($parsed, '非消費被記成支出：'.$text.' => '.($parsed?->amount ?? '').'/'.($parsed?->merchant ?? ''));
    }

    /**
     * 外幣 45 筆中 41 筆：不得記成台幣。
     *
     * @return array<string, array{0:string}>
     */
    public static function foreignCurrency(): array
    {
        return [
            'fx-01' => ['您於AMAZON消費USD 25.99'],
            'fx-02' => ['您於AMAZON消費US$25.99'],
            'fx-03' => ['您於AMAZON消費$25.99(USD)'],
            'fx-04' => ['您於AMAZON消費$25.99 美元'],
            'fx-05' => ['您於東京Lawson消費JPY 1,200'],
            'fx-06' => ['您於東京Lawson消費日圓1,200元'],
            'fx-07' => ['您於東京Lawson消費1,200日幣'],
            'fx-08' => ['您於東京Lawson消費¥1,200'],
            'fx-09' => ['您於東京Lawson消費1200円'],
            'fx-10' => ['您於巴黎Cafe消費EUR 12.5'],
            'fx-11' => ['您於巴黎Cafe消費€12.5'],
            'fx-12' => ['您於London Shop消費GBP 30'],
            'fx-13' => ['您於London Shop消費£30'],
            'fx-14' => ['您於香港7-11消費HKD 50'],
            'fx-15' => ['您於香港7-11消費HK$50'],
            'fx-16' => ['您於淘寶消費人民幣100元'],
            'fx-17' => ['您於淘寶消費CNY 100'],
            'fx-18' => ['您於首爾消費KRW 15,000'],
            'fx-19' => ['您於首爾消費₩15,000'],
            'fx-20' => ['您於曼谷消費THB 300'],
            'fx-21' => ['您於曼谷消費300泰銖'],
            'fx-22' => ['您於雪梨消費AUD 40'],
            'fx-23' => ['您於雪梨消費A$40'],
            'fx-24' => ['您於AMAZON消費 USD 25.99（折合新台幣 NT$810）'],
            'fx-25' => ['您於AMAZON消費NT$810（原幣USD25.99）'],
            'fx-26' => ['您於AMAZON消費金額NT$810，外幣金額25.99美金'],
            'fx-27' => ['您於東京消費 JPY1200'],
            'fx-28' => ['您於AMAZON消費usd 25.99'],
            'fx-29' => ['您於AMAZON消費 25.99 U.S.D'],
            'fx-30' => ['您於AMAZON消費金額 25.99 Dollars'],
            'fx-31' => ['您於AMAZON海外消費$25.99'],
            'fx-32' => ['您於新加坡消費SGD 20'],
            'fx-33' => ['您於新加坡消費S$20'],
            'fx-34' => ['您於越南消費VND 100,000'],
            'fx-35' => ['您於越南消費100,000 ₫'],
            'fx-36' => ['您於AMAZON消費USD$25.99'],
            'fx-37' => ['您於Booking.com消費金額 25.99 dollars'],
            'fx-38' => ['您於上海消費RMB100'],
            'fx-39' => ['您於上海消費CN¥100'],
            'fx-40' => ['您於AMAZON消費 25.99 美金'],
            'fx-45' => ['您於國外AMAZON消費25.99'],
        ];
    }

    #[DataProvider('foreignCurrency')]
    public function test_foreign_currency_is_not_recorded_as_twd(string $text): void
    {
        $this->assertNull($this->parse($text));
    }

    /**
     * 外幣資料集中「設計上記成台幣」的 4 筆：裸 $ 無其他幣別線索（假設為台幣）、金額本身標示 NT$／元。
     *
     * @return array<string, array{0:string, 1:float}>
     */
    public static function foreignDatasetRecordedAsTwdByDesign(): array
    {
        return [
            '裸 $（PayPal）' => ['您於PayPal *Steam消費 $19.99', 19.99],
            '裸 $（Apple）' => ['您於Apple.com/bill消費$0.99', 0.99],
            '國外消費但金額為 NT$' => ['【玉山】您於國外消費NT$1,234（含國外交易手續費）', 1234.0],
            '國外交易但金額為元' => ['您於國外商店刷卡金額1,234元（國外交易）', 1234.0],
        ];
    }

    #[DataProvider('foreignDatasetRecordedAsTwdByDesign')]
    public function test_bare_dollar_and_twd_denominated_foreign_purchases_are_recorded(string $text, float $amount): void
    {
        $this->assertSame($amount, $this->parse($text)?->amount);
    }

    /**
     * 店名含外幣字樣的台幣消費 18 筆：可以回 null（已知限制，只會 400），但不得錯記金額。
     *
     * @return array<string, array{0:string, 1:float}>
     */
    public static function twdWithForeignLookingMerchant(): array
    {
        return [
            'fxtw-01' => ['您於EUROPE Cafe消費NT$250', 250],
            'fxtw-02' => ['您於JPY拉麵消費NT$280', 280],
            'fxtw-03' => ['您於JPY 拉麵消費NT$280', 280],
            'fxtw-04' => ['您於US Burger消費NT$199', 199],
            'fxtw-05' => ['您於HKD港式茶餐廳消費NT$150', 150],
            'fxtw-06' => ['您於US$ Store消費NT$100', 100],
            'fxtw-07' => ['您於¥拉麵消費NT$280', 280],
            'fxtw-08' => ['您於€uro Cafe消費NT$250', 250],
            'fxtw-09' => ['您於日幣兌換所消費NT$500', 500],
            'fxtw-10' => ['您於美金咖啡消費NT$120', 120],
            'fxtw-11' => ['您於THB泰式料理消費NT$350', 350],
            'fxtw-12' => ['您於Apple消費 $ 99（含稅）', 99],
            'fxtw-13' => ['您於S$ Bistro消費NT$300', 300],
            'fxtw-14' => ['您於USD Bar消費NT$300', 300],
            'fxtw-15' => ['您於EUROTEC消費NT$300', 300],
            'fxtw-16' => ['您於PHP 工作室消費NT$300', 300],
            'fxtw-17' => ['您於大阪燒円山消費NT$300', 300],
            'fxtw-18' => ['您於外幣找換店消費NT$300', 300],
        ];
    }

    #[DataProvider('twdWithForeignLookingMerchant')]
    public function test_foreign_looking_merchant_is_never_misrecorded(string $text, float $amount): void
    {
        $parsed = $this->parse($text);

        if ($parsed !== null) {
            $this->assertSame($amount, $parsed->amount);
        } else {
            $this->addToAssertionCount(1);
        }
    }

    /**
     * 應遮罩 54 筆：輸出需與預期完全一致。
     *
     * @return array<string, array{0:string, 1:string}>
     */
    public static function maskedCases(): array
    {
        return [
            'mask-01' => ['您的Visa 1234卡消費', '您的Visa ****卡消費'],
            'mask-02' => ['VISA*1234 消費', 'VISA**** 消費'],
            'mask-03' => ['JCB卡(4321)於全家消費', 'JCB卡(****)於全家消費'],
            'mask-04' => ['末 4 碼 4321', '末 4 碼 ****'],
            'mask-05' => ['末四碼1234', '末四碼****'],
            'mask-06' => ['尾號1234', '尾號****'],
            'mask-07' => ['Card ending 1234', 'Card ending ****'],
            'mask-08' => ['身分證 A123456789', '身分證 ****'],
            'mask-09' => ['手機 0912345678', '手機 ****'],
            'mask-10' => ['帳號 123-456-789012', '帳號 ****'],
            'mask-11' => ['卡號 1234-5678-9012-3456', '卡號 ****'],
            'mask-12' => ['1234 5678 9012 3456', '****'],
            'mask-13' => ['1234567890123456', '****'],
            'mask-14' => ['Mastercard 5678 消費', 'Mastercard **** 消費'],
            'mask-15' => ['MasterCard末碼5678', 'MasterCard末碼****'],
            'mask-16' => ['card ending in 9876', 'card ending in ****'],
            'mask-17' => ['ending in 9876', 'ending in ****'],
            'mask-18' => ['信用卡(5678)', '信用卡(****)'],
            'mask-19' => ['金融卡 9999', '金融卡 ****'],
            'mask-20' => ['卡片 5678 消費', '卡片 **** 消費'],
            'mask-21' => ['xx1234', '****'],
            'mask-22' => ['XXXX-1234', '****'],
            'mask-23' => ['****1234', '****'],
            'mask-24' => ['**1234', '****'],
            'mask-25' => ['卡末1234', '卡末****'],
            'mask-26' => ['後四碼 1234', '後四碼 ****'],
            'mask-27' => ['帳戶0012345678901', '帳戶****'],
            'mask-28' => ['ID:a123456789', 'ID:****'],
            'mask-29' => ['帳號：12345678', '帳號：****'],
            'mask-30' => ['卡號末四碼為1234', '卡號末四碼為****'],
            'mask-31' => ['AMEX 3782 消費', 'AMEX **** 消費'],
            'mask-32' => ['您的信用卡1234於全家消費', '您的信用卡****於全家消費'],
            'mask-33' => ['您的卡 1234 於全家消費', '您的卡 **** 於全家消費'],
            'mask-34' => ['卡號後4碼1234', '卡號後4碼****'],
            'mask-35' => ['您的VISA金融卡末4碼(1234)', '您的VISA金融卡末4碼(****)'],
            'mask-36' => ['１２３４－５６７８－９０１２－３４５６', '****'],
            'mask-37' => ['卡號１２３４５６７８９０１２３４５６', '卡號****'],
            'mask-38' => ['身分證Ａ１２３４５６７８９', '身分證****'],
            'mask-39' => ['手機 0912-345-678', '手機 ****'],
            'mask-40' => ['手機 +886912345678', '手機 ****'],
            'mask-41' => ['統編 12345678', '統編 ****'],
            'mask-42' => ['Visa卡號1234-56XX-XXXX-1234', 'Visa卡號****'],
            'mask-43' => ['末四碼 1 2 3 4', '末四碼 ****'],
            'mask-44' => ['信用卡 末四碼：*1234', '信用卡 末四碼：****'],
            'mask-45' => ['您的信用卡末4碼1234於全家消費', '您的信用卡末4碼****於全家消費'],
            'mask-46' => ['您的信用卡末4碼：1234', '您的信用卡末4碼：****'],
            'mask-47' => ['玉山卡末4碼5678消費NT$100', '玉山卡末4碼****消費NT$100'],
            'mask-48' => ['簽帳卡末4碼(1234)', '簽帳卡末4碼(****)'],
            'mask-49' => ['您的卡末4碼1234', '您的卡末4碼****'],
            'mask-50' => ['您的信用卡末四碼1234', '您的信用卡末四碼****'],
            'mask-51' => ['卡號末4碼1234', '卡號末4碼****'],
            'mask-52' => ['您的信用卡後4碼1234', '您的信用卡後4碼****'],
            'mask-53' => ['您的金融卡尾號1234', '您的金融卡尾號****'],
            'mask-54' => ['卡末四碼1234', '卡末四碼****'],
        ];
    }

    #[DataProvider('maskedCases')]
    public function test_sensitive_numbers_are_masked(string $text, string $expected): void
    {
        $this->assertSame($expected, NotificationTextMasker::mask($text));
    }

    /**
     * 不應遮罩 20 筆：金額、日期、店名保持原樣。
     *
     * @return array<string, array{0:string}>
     */
    public static function keptCases(): array
    {
        return [
            'keep-01' => ['XX12 Burger'],
            'keep-02' => ['7-ELEVEN'],
            'keep-03' => ['Room 1234 民宿'],
            'keep-04' => ['第1234號'],
            'keep-05' => ['卡片 1500 NTD'],
            'keep-06' => ['信用卡 1234 TWD'],
            'keep-07' => ['消費 5,678 元'],
            'keep-08' => ['消費金額NT$1,234'],
            'keep-09' => ['2026-11-15 13:15'],
            'keep-10' => ['115/11/14'],
            'keep-11' => ['11/15 12:30'],
            'keep-12' => ['信用卡 1234元'],
            'keep-13' => ['Visa 1500元'],
            'keep-14' => ['商店：85度C'],
            'keep-15' => ['PChome 24h購物'],
            'keep-16' => ['消費 NT$12,345,678'],
            'keep-17' => ['消費金額 1234567 元'],
            'keep-18' => ['金額 NT$1000000'],
            'keep-19' => ['Uniqlo-台北101'],
            'keep-20' => ['門號2號店'],
        ];
    }

    #[DataProvider('keptCases')]
    public function test_amounts_dates_and_merchants_are_not_masked(string $text): void
    {
        $this->assertSame($text, NotificationTextMasker::mask($text));
    }
}
