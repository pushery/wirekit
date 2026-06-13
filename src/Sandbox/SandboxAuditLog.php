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
        // Log-injection (CWE-117) defense: the log is tab-delimited, line-based,
        // and `$component` arrives UNSANITIZED on the `rejected:component` path
        // (it is logged BEFORE the allowlist regex validates it — see
        // SandboxRenderer::render). A `$component` carrying a tab or newline
        // could otherwise forge a fake log record. Collapse every control
        // character (tabs, CR, LF, and the rest of the C0/C1 range) to a single
        // U+FFFD and cap the length so a hostile field cannot break the record
        // shape. `$outcome` is always an internal literal but is sanitized too
        // for defense in depth.
        $line = implode("\t", [
            date('c'),
            self::sanitizeField($outcome),
            self::sanitizeField($component),
            substr(hash('sha256', $ipAddress), 0, 16),
            (string) $violationsCount,
        ]).PHP_EOL;

        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Neutralize a value for inclusion in a tab-delimited, line-based log
     * record: replace every control character (C0 + DEL + C1, which includes
     * tab / CR / LF — the field and record separators) with U+FFFD, and cap
     * the length. Prevents a hostile field from forging extra columns or rows.
     */
    private static function sanitizeField(string $value): string
    {
        // `\pC` = any Unicode "Other" (control/format/surrogate/unassigned).
        // The `/u` flag is required so the regex walks UTF-8 codepoints, not
        // bytes (matches the project's multi-byte-safe regex rule).
        $clean = preg_replace('/\pC/u', "\u{FFFD}", $value);
        // preg_replace returns null on a malformed-UTF-8 subject; fall back to
        // a byte-level control-char strip so a bad input still can't inject.
        if ($clean === null) {
            $clean = preg_replace('/[\x00-\x1F\x7F]/', "\u{FFFD}", $value) ?? '';
        }

        return mb_substr($clean, 0, 200);
    }

    private static function resolveLogDir(): ?string
    {
        // Use Laravel's storage_path() if available (developer app);
        // otherwise fall back to a sandbox-tests temp dir.
        if (function_exists('storage_path')) {
            return storage_path('logs/sandbox');
        }
        $tmp = sys_get_temp_dir().'/wirekit-sandbox-logs';

        return $tmp;
    }
}
