<?php

declare(strict_types=1);

namespace Castopod\PluginsManager\Logger;

class PluginsManagerLogger
{
    public static LogLevel $LOG_LEVEL = LogLevel::Info;

    /**
     * @var class-string<FormatterInterface>
     */
    public static string $formatter = BasicFormatter::class;

    /**
     * @var array{message:string,context:array<string,string|null>}[]
     */
    private static array $error = [];

    /**
     * @var array{message:string,context:array<string,string|null>}[]
     */
    private static array $warning = [];

    /**
     * @var array{message:string,context:array<string,string|null>}[]
     */
    private static array $success = [];

    /**
     * @var array{message:string,context:array<string,string|null>}[]
     */
    private static array $info = [];

    /**
     * @param array<string,string|null> $context
     */
    public static function error(string $code, string $message, array $context = []): void
    {
        self::log($code, $message, $context, LogLevel::Error);
    }

    /**
     * @param array<string,string|null> $context
     */
    public static function warning(string $code, string $message, array $context = []): void
    {
        self::log($code, $message, $context, LogLevel::Warning);
    }

    /**
     * @param array<string,string|null> $context
     */
    public static function info(string $code, string $message, array $context = []): void
    {
        self::log($code, $message, $context, LogLevel::Info);
    }

    /**
     * @param array<string,string|null> $context
     */
    public static function success(string $code, string $message, array $context = []): void
    {
        self::log($code, $message, $context, LogLevel::Success);
    }

    /**
     * @param array<string,string|null> $context
     */
    public static function log(
        string $code,
        string $message,
        array $context = [],
        LogLevel $level = LogLevel::Info,
    ): void {
        $log = [
            'code'    => $code,
            'message' => $message,
            'context' => $context,
        ];

        self::${strtolower($level->name)}[] = $log;

        if ($level->value >= self::$LOG_LEVEL->value) {
            new self::$formatter()->format($level, $log);
        }
    }

    /**
     * @return array{message:string,context:array<string,string|null>}[]
     */
    public static function getErrors(): array
    {
        return self::$error;
    }

    /**
     * @return array{message:string,context:array<string,string|null>}[]
     */
    public static function getWarnings(): array
    {
        return self::$warning;
    }

    /**
     * @return array{message:string,context:array<string,string|null>}[]
     */
    public static function getSuccesses(): array
    {
        return self::$success;
    }

    /**
     * @return array{message:string,context:array<string,string|null>}[]
     */
    public static function getInfos(): array
    {
        return self::$info;
    }
}
