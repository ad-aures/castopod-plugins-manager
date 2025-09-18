<?php

declare(strict_types=1);

namespace Castopod\PluginsManager\Logger;

class BasicFormatter implements FormatterInterface
{
    public function format(LogLevel $level, array $log): void
    {
        echo strtoupper($level->name) . ' ' . json_encode($log, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    }
}
