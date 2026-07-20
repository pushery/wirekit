<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\Mcp\McpCatalog;
use Pushery\WireKit\Mcp\McpServer;
use Pushery\WireKit\Support\VersionResolver;

/**
 * `wirekit:mcp-serve` — a local Model Context Protocol server over stdio.
 *
 * AI coding assistants (Claude Code / Cursor / Cline) spawn this as a child
 * process and talk JSON-RPC 2.0 over stdin/stdout to query the WireKit
 * component catalog live while authoring. It is the in-process, zero-hosting
 * delivery path: no port, no daemon, always version-matched to the installed
 * package. Everything it serves ships in the Packagist tarball — see McpServer.
 */
final class McpServeCommand extends Command
{
    protected $signature = 'wirekit:mcp-serve';

    protected $description = 'Run the WireKit MCP server (JSON-RPC over stdio) so AI coding assistants can query the component catalog';

    public function handle(): int
    {
        // The server reads a JSON-RPC stream piped by an editor. If launched
        // interactively (a TTY on stdin) there is nothing to read and fgets()
        // would block forever — print a hint and exit cleanly instead.
        if (\function_exists('stream_isatty') && @stream_isatty(\STDIN)) {
            $this->info('wirekit:mcp-serve speaks JSON-RPC 2.0 over stdio. Configure your editor / MCP client to spawn it — it is not meant to be run interactively.');

            return self::SUCCESS;
        }

        $server = new McpServer(new McpCatalog, VersionResolver::resolve());

        $in = fopen('php://stdin', 'r');
        $out = fopen('php://stdout', 'w');
        if ($in === false || $out === false) {
            $this->error('wirekit:mcp-serve: could not open stdio streams.');

            return self::FAILURE;
        }

        while (true) {
            $line = fgets($in);

            // A false read is NOT necessarily end-of-input. Node-based MCP
            // clients (Claude Code, Cursor, Cline — all spawning through libuv)
            // hand the child its stdio as a Unix socketpair rather than an
            // anonymous pipe, and on a socket PHP applies default_socket_timeout
            // (60s). An idle session therefore hits that timeout and fgets()
            // returns false with no EOF — so treating every false as EOF exited
            // cleanly after exactly 60 seconds of quiet, with status 0 and
            // nothing on stderr. The client reported "server disconnected" and a
            // reconnect worked instantly, which made it read as flakiness.
            if ($line === false) {
                if (self::isIdleTimeout($in)) {
                    continue;
                }

                break; // genuine EOF — the client closed the stream
            }

            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $message = json_decode($line, true);
            if (! is_array($message)) {
                $this->writeMessage($out, [
                    'jsonrpc' => '2.0',
                    'id' => null,
                    'error' => ['code' => -32700, 'message' => 'Parse error'],
                ]);

                continue;
            }

            $response = $server->handle($message);
            if ($response !== null) {
                $this->writeMessage($out, $response);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Did this false read come from an idle socket timeout rather than EOF?
     *
     * The reason for staying on a BLOCKING stream and distinguishing the two,
     * instead of the more common non-blocking-plus-retry loop: a non-blocking
     * fgets() returns whatever bytes have arrived, which for a half-delivered
     * message is a partial line with no trailing newline. That splits one
     * JSON-RPC message into two unparseable fragments, so the retry loop has to
     * reassemble lines by hand. Blocking reads always yield whole lines, and the
     * only thing that needed fixing was reading a timeout as a hangup.
     *
     * It is also cheaper: this loop wakes once per timeout interval, where a
     * 10ms retry loop wakes a hundred times a second for the entire session.
     *
     * @param  resource  $stream
     */
    private static function isIdleTimeout($stream): bool
    {
        $meta = stream_get_meta_data($stream);

        // Both conditions matter. A stream at EOF also reports no data, and
        // `timed_out` alone would then spin on a closed stream forever.
        return ($meta['timed_out'] ?? false) === true
            && ($meta['eof'] ?? false) === false;
    }

    /**
     * Write one newline-delimited JSON-RPC message (the MCP stdio framing) and
     * flush so the editor sees the reply immediately.
     *
     * @param  resource  $out
     * @param  array<string, mixed>  $message
     */
    private function writeMessage($out, array $message): void
    {
        fwrite($out, json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        fflush($out);
    }
}
