<?php

declare(strict_types=1);

namespace App\Models\Logging\Formatter;

use App\Models\Logging\Services\LoggingService;
use Monolog\Formatter\LineFormatter;
use Monolog\LogRecord;

class CustomFormatter extends LineFormatter
{
    /**
     * @param  string|null  $format
     */
    public function __construct(?string $format = null)
    {
        $this->format = $format
            ?: '[%datetime%] ['.getmypid()."] [%prefix%] (%index%) %channel%.%level_name%: %message% %context% %extra%\n";

        parent::__construct($this->format, null, true, true);
        $this->includeStacktraces(true);
    }

    /**
     * @param  LogRecord  $record
     * @return string
     */
    public function format(LogRecord $record): string
    {
        $output = parent::format($record);

        static $index = 0;

        $vars = array_merge(
            ['index' => ++$index],
            LoggingService::getLoggerExtend()
        );

        foreach ($vars as $key => $var) {
            $output = str_replace('%'.$key.'%', (string) ($var ?? ''), $output);
        }

        return $output;
    }
}
