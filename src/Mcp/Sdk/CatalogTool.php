<?php

declare(strict_types=1);

namespace Pushery\WireKit\Mcp\Sdk;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Pushery\WireKit\Mcp\McpCatalog;
use Pushery\WireKit\Mcp\McpDefinitions;

/**
 * One of WireKit's MCP tools on `laravel/mcp`, built from its definition in McpDefinitions.
 *
 * One class for all of them, each an instance: a class per tool would be a second declaration of
 * what exists beside the definitions both servers read, and the two could then disagree about a
 * name, a description or an argument. Every tool reads the shipped catalog and changes nothing.
 */
#[IsReadOnly]
#[IsIdempotent]
final class CatalogTool extends Tool
{
    /**
     * @param  array{name: string, description: string, inputSchema: array<string, mixed>}  $definition
     */
    public function __construct(private readonly array $definition)
    {
        $this->name = $definition['name'];
        $this->title = Str::headline($definition['name']);
        $this->description = $definition['description'];
    }

    /**
     * The arguments, from the JSON Schema of the definition: a string, a whole number or a
     * yes or no, each with its description, and required where the definition says so.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $input = $this->definition['inputSchema'];
        $required = is_array($input['required'] ?? null) ? $input['required'] : [];
        $arguments = [];

        foreach ((array) ($input['properties'] ?? []) as $name => $property) {
            $property = (array) $property;
            $type = match ($property['type'] ?? 'string') {
                'integer' => $schema->integer(),
                'boolean' => $schema->boolean(),
                default => $schema->string(),
            };

            $type = $type->description((string) ($property['description'] ?? ''));
            $arguments[(string) $name] = in_array($name, $required, true) ? $type->required() : $type;
        }

        return $arguments;
    }

    /** The answer McpDefinitions gives, in the same text the package's own server writes. */
    public function handle(Request $request, McpCatalog $catalog): Response
    {
        $answer = McpDefinitions::call($catalog, $this->definition['name'], $request->all());

        if (isset($answer['refusal'])) {
            return Response::error($answer['refusal']);
        }

        return Response::text(McpDefinitions::encode($answer['payload'] ?? []));
    }
}
