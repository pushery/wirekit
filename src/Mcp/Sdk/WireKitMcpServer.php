<?php

declare(strict_types=1);

namespace Pushery\WireKit\Mcp\Sdk;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Contracts\Transport;
use Pushery\WireKit\Mcp\McpDefinitions;
use Pushery\WireKit\Support\VersionResolver;

/**
 * WireKit's MCP server on `laravel/mcp`, which `wirekit:mcp-serve` runs where the SDK is installed.
 *
 * The SDK speaks the protocol revisions it maintains, the newer handshake included, which the
 * package's own loop (McpServer) does not follow: that loop serves only where the SDK is absent,
 * and goes with the next major version. The tools and resources are the definitions both servers
 * read, so the two answer alike. Tools and resources are the capabilities announced; there are no
 * prompts, and a capability with nothing behind it is not offered.
 */
final class WireKitMcpServer extends Server
{
    protected string $name = 'wirekit';

    protected string $instructions = <<<'MARKDOWN'
        WireKit is the Blade component library this application renders with. Ask for a component's
        props, worked examples and accessibility before writing its markup, and for the conventions
        once before writing any Blade: they decide whether the markup passes this project's guards.
        MARKDOWN;

    /** @var array<string, array<string, bool>|\stdClass|string> */
    protected array $capabilities = [
        self::CAPABILITY_TOOLS => ['listChanged' => false],
        self::CAPABILITY_RESOURCES => ['listChanged' => false],
    ];

    public function __construct(Transport $transport)
    {
        parent::__construct($transport);

        $this->version = VersionResolver::resolve();
        $this->tools = array_map(static fn (array $definition): CatalogTool => new CatalogTool($definition), McpDefinitions::tools());
        $this->resources = array_map(static fn (array $definition): CatalogResource => new CatalogResource($definition), McpDefinitions::resources());
    }
}
