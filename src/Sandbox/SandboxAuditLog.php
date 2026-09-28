<?php

declare(strict_types=1);

namespace Pushery\WireKit\Sandbox;

/**
 * File-based rotating-daily audit log for sandbox requests.
 *
 * Each request emits one line to `storage/logs/sandbox/YYYY-MM-DD.log`
 * with shape:
 *   {timestamp}\t{outcome}\t{component}\t{network-digest}\t{violations-count}
 *
 * The address is written as a digest of its NETWORK, keyed with the application key: an IPv4
 * address cut to its /24, an IPv6 address to its /48, then HMAC-SHA256 with 8 bytes kept. That
 * is enough to see a burst of requests from one place, and not enough to name a person. An
 * unkeyed digest of the whole address was a lookup table: IPv4 has 2^32 values, and hashing all
 * of them takes minutes. Without an application key there is no secret to key with, and the
 * field is written as `-`.
 *
 * Daily files older than `wirekit.sandbox.audit_log_retention_days` (14 by default) are deleted
 * when the first record of a day opens that day's file; `null` keeps them all.
 */
final class SandboxAuditLog
{
    public static function record(string $outcome, string $component, string $ipAddress, int $violationsCount = 0): void
    {
        // resolveLogDir() always produces a path; the early return guarded a state it
        // cannot reach, and PHPStan named it the moment the return type stopped lying.
        $logDir = self::resolveLogDir();

        if (! is_dir($logDir) && ! @mkdir($logDir, 0755, true) && ! is_dir($logDir)) {
            self::reportWriteFailure(sprintf('the log directory %s could not be created', $logDir));

            return;
        }

        $file = $logDir.'/'.date('Y-m-d').'.log';
        $opensTheDay = ! is_file($file);
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
            self::networkDigest($ipAddress),
            (string) $violationsCount,
        ]).PHP_EOL;

        $written = @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);

        if ($written === false || $written !== strlen($line)) {
            self::reportWriteFailure(sprintf('%s could not be appended to', $file));
        }

        // Once a day, on the record that opens the day's file, rather than on every request:
        // the directory walk is then paid once however busy the endpoint is.
        if ($opensTheDay) {
            self::prune($logDir);
        }
    }

    /**
     * The keyed digest of the address's network, 16 hex characters, or `-` with no key to use.
     */
    private static function networkDigest(string $ipAddress): string
    {
        $key = self::applicationKey();

        if ($key === '') {
            return '-';
        }

        return substr(hash_hmac('sha256', self::network($ipAddress), $key), 0, 16);
    }

    /**
     * An IPv4 address cut to its /24 and an IPv6 address to its /48. An IPv4 address written in
     * IPv6 form (`::ffff:203.0.113.4`) counts as the IPv4 address it is, or every such client
     * would share one /48. A value that is no address at all is used as given.
     */
    private static function network(string $ipAddress): string
    {
        $packed = @inet_pton($ipAddress);

        if ($packed === false) {
            return $ipAddress;
        }

        if (strlen($packed) === 16 && str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
            $packed = substr($packed, 12);
        }

        $kept = strlen($packed) === 4 ? 3 : 6;

        return (string) inet_ntop(substr($packed, 0, $kept).str_repeat("\0", strlen($packed) - $kept));
    }

    /**
     * Delete this log's own daily files once they are older than the retention, judged by the
     * date in the name. Anything else in the directory is left alone.
     */
    private static function prune(string $logDir): void
    {
        $days = self::retentionDays();

        if ($days === null) {
            return;
        }

        $cutoff = date('Y-m-d', (int) strtotime('-'.$days.' days'));

        foreach (glob($logDir.'/*.log') ?: [] as $path) {
            $day = basename($path, '.log');

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1 && $day < $cutoff) {
                @unlink($path);
            }
        }
    }

    /**
     * The application key, or an empty string outside a booted application. The log also runs
     * without a container, which is why every Laravel helper here is asked for first.
     */
    private static function applicationKey(): string
    {
        $key = function_exists('app') && app()->bound('config') ? app('config')->get('app.key') : null;

        return is_string($key) ? $key : '';
    }

    /**
     * How many days of files to keep, at least one, or null to keep them all. 14 without a
     * booted application, the same as the published default.
     */
    private static function retentionDays(): ?int
    {
        if (! function_exists('app') || ! app()->bound('config')) {
            return 14;
        }

        $days = app('config')->get('wirekit.sandbox.audit_log_retention_days', 14);

        // An environment variable arrives as a string, so the count is read as a number here.
        return $days === null ? null : max(1, (int) $days);
    }

    /**
     * Say so when a security record could not be written — WITHOUT throwing.
     *
     * The failure mode this closes is the quiet one: with an unwritable log directory every
     * rejected render would go unrecorded, and the only evidence would be an audit file that
     * stops growing. A reader checking it later would see a clean history rather than a blind
     * one, which is the wrong way round for a security record.
     *
     * It does NOT throw, and that is the other half. This runs on the reject path of a
     * sandboxed render, so an exception here would turn "the audit log is unwritable" into
     * "the endpoint is down" — an attacker who can fill a disk could take the surface with it.
     * The application log is the right channel: it is watched, and it does not gate the
     * request. Laravel's logger is used when the container has it, and PHP's is the fallback
     * so the message survives outside a booted application.
     */
    private static function reportWriteFailure(string $detail): void
    {
        $message = 'WireKit sandbox audit log is not writable — '.$detail
            .'. Rejected renders are going unrecorded.';

        if (function_exists('app') && app()->bound('log')) {
            app('log')->warning($message);

            return;
        }

        error_log($message);
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

    // Never returns null — every branch below produces a path. The nullable return type
    // invited callers to handle a case that cannot happen.
    private static function resolveLogDir(): string
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
