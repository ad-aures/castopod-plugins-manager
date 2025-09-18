<?php

declare(strict_types=1);

namespace Castopod\PluginsManager\Logger;

enum LogLevel: int
{
    case Info = 1;
    case Success = 2;
    case Warning = 3;
    case Error = 4;
}
