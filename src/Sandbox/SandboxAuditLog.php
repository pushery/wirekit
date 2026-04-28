<?php

declare(strict_types=1);

namespace Pushery\WireKit\Sandbox;

/**
 * File-based rotating-daily audit log for sandbox
 * requests (per Open Question 4 default).
 *
 * Each request emits one line to `storage/logs/sandbox/YYYY-MM-DD.log`
 * with shape:
 *   {timestamp}\t{outcome}\t{component}\t{ip-hash}\t{violations-count}
 *
 * IP addresses are hashed (sha256, 8-byte truncation) so the log is
 * useful for rate-pattern auditing but not for tracking individuals.
 */
final class SandboxAuditLog
{
    public static function record(string $outcome, string $component, string $ipAddress, int $violationsCount = 0): void
    {
        $logDir = self::resolveLogDir();
        if ($logDir === null) {
            return;
        }

        if (! is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $file = $logDir.'/'.date('Y-m-d').'.log';
        $line = implode("\t", [
            date('c'),
            $outcome,
            $component,
            substr(hash('sha256', $ipAddress), 0, 16),
            (string) $violationsCount,
        ]).PHP_EOL;

        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    private static function resolveLogDir(): ?string
    {
        // Use Laravel's storage_path() if available (consumer app);
        // otherwise fall back to a sandbox-tests temp dir.
        if (function_exists('storage_path')) {
            return storage_path('logs/sandbox');
        }
        $tmp = sys_get_temp_dir().'/wirekit-sandbox-logs';

        return $tmp;
    }
}
