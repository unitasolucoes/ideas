<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginIdeasLogger {
    private const LOG_FILENAME = 'plugin_ideas.log';
    private const MAX_STRING_LENGTH = 2000;

    /**
     * Returns the absolute path for the plugin log file when it can be written.
     */
    public static function getLogFilePath(): ?string {
        if (!defined('GLPI_LOG_DIR')) {
            return null;
        }

        $logDir = GLPI_LOG_DIR;
        if (!is_dir($logDir) || !is_writable($logDir)) {
            return null;
        }

        return $logDir . '/' . self::LOG_FILENAME;
    }

    public static function info(string $event, string $message, array $context = []): void {
        self::write('INFO', $event, $message, $context);
    }

    public static function error(string $event, string $message, array $context = [], ?Throwable $exception = null): void {
        if ($exception !== null) {
            $context['exception'] = [
                'class'   => get_class($exception),
                'message' => $exception->getMessage(),
                'code'    => $exception->getCode(),
                'file'    => $exception->getFile(),
                'line'    => $exception->getLine(),
            ];
        }

        self::write('ERROR', $event, $message, $context, $exception);
    }

    public static function sanitizeArray(array $input, array $hiddenKeys = []): array {
        $sanitized = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $hiddenKeys, true)) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $sanitized[$key] = self::truncate((string) $value);
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeArray($value, $hiddenKeys);
                continue;
            }

            $sanitized[$key] = gettype($value);
        }

        return $sanitized;
    }

    private static function write(string $level, string $event, string $message, array $context = [], ?Throwable $exception = null): void {
        try {
            $logFile = self::getLogFilePath();
            if ($logFile === null) {
                error_log(sprintf('[PluginIdeasLogger] %s - %s: %s', $level, $event, $message));
                return;
            }

            $record = [
                'timestamp' => date('c'),
                'level'     => $level,
                'event'     => $event,
                'user_id'   => Session::getLoginUserID(),
                'message'   => self::truncate($message),
            ];

            if (!empty($context)) {
                $record['context'] = self::sanitizeArray($context, ['_glpi_csrf_token']);
            }

            if ($exception !== null) {
                $record['context']['exception']['trace'] = self::truncate($exception->getTraceAsString());
            }

            $logLine = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
            file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
        } catch (Throwable $logException) {
            error_log('[PluginIdeasLogger] Falha ao registrar log: ' . $logException->getMessage());
        }
    }

    private static function truncate(string $value): string {
        $lengthFn = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
        $substrFn = function_exists('mb_substr') ? 'mb_substr' : 'substr';

        if ($lengthFn($value) <= self::MAX_STRING_LENGTH) {
            return $value;
        }

        return $substrFn($value, 0, self::MAX_STRING_LENGTH) . '…';
    }
}
