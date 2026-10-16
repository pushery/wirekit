<?php

declare(strict_types=1);

namespace Pushery\WireKit\Mcp\Sdk;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;
use Pushery\WireKit\Mcp\McpCatalog;
use Pushery\WireKit\Mcp\McpDefinitions;

/**
 * One of WireKit's MCP resources on `laravel/mcp`, built from its definition in McpDefinitions,
 * for the same reason CatalogTool is: one list of what exists, read by both servers.
 */
final class CatalogResource extends Resource
{
    /**
     * @param  array{uri: string, name: string, description: string, mimeType: string}  $definition
     */
    public function __construct(array $definition)
    {
        $this->uri = $definition['uri'];
        $this->name = $definition['name'];
        $this->title = $definition['name'];
        $this->description = $definition['description'];
        $this->mimeType = $definition['mimeType'];
    }

    public function handle(McpCatalog $catalog): Response
    {
        $read = McpDefinitions::read($catalog, $this->uri);

        if ($read === null) {
            return Response::error("Unknown resource: {$this->uri}");
        }

        return isset($read['missing']) ? Response::error($read['missing']) : Response::text($read['text']);
    }
}
