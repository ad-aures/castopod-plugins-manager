<?php

declare(strict_types=1);

namespace Castopod\PluginsManager\Logger;

interface FormatterInterface
{
    /**
     * @param array{code:string,message:string,context:array<string,string|int|null>} $log
     */
    public function format(LogLevel $level, array $log): void;
}
