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

        while (($line = fgets($in)) !== false) {
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
