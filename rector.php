<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php82\Rector\Encapsed\VariableInStringInterpolationFixerRector;
use Rector\Php84\Rector\Param\ExplicitNullableParamTypeRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([__DIR__ . DIRECTORY_SEPARATOR . 'src', __DIR__ . DIRECTORY_SEPARATOR . 'tests'])
    ->withPhpSets(php81: true)
    ->withPhpVersion(PhpVersion::PHP_84)
    ->withPreparedSets(deadCode: true, codeQuality: true)
    ->withRules([ExplicitNullableParamTypeRector::class, VariableInStringInterpolationFixerRector::class]);