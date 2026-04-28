<?php

declare(strict_types=1);

namespace Pushery\WireKit\Sandbox;

use Pushery\WireKit\ComponentRegistry;

/**
 * Component allowlist for the sandbox renderer.
 *
 * The sandbox renders only components that:
 *   1. Live in `ComponentRegistry::all()` (the canonical list).
 *   2. Have a corresponding sandbox schema registered.
 *
 * Components without a schema are intentionally rejected — every
 * sandbox-renderable component must explicitly declare its allowed
 * prop set, slot content shape, and safe defaults. No implicit
 * sandboxing.
 */
final class ComponentAllowlist
{
    /**
     * Return true when a component is registered AND has a sandbox schema.
     */
    public static function allows(string $componentName): bool
    {
        if ($componentName === '') {
            return false;
        }

        // Defensive: kebab-case + dotted sub-component names only. Reject
        // anything carrying namespace separators, slashes, or whitespace.
        if (! preg_match('/^[a-z][a-z0-9-]*(\.[a-z][a-z0-9-]*)?$/', $componentName)) {
            return false;
        }

        // Sub-components like 'card.header' resolve to their parent registry row
        $parent = explode('.', $componentName)[0];
        $registry = ComponentRegistry::all();

        return isset($registry[$parent]) && SandboxSchemaRegistry::has($componentName);
    }

    /**
     * Return the sorted list of every component that the sandbox can
     * currently render.
     *
     * @return array<int, string>
     */
    public static function allowed(): array
    {
        $names = [];
        foreach (array_keys(ComponentRegistry::all()) as $name) {
            if (SandboxSchemaRegistry::has($name)) {
                $names[] = $name;
            }
        }
        sort($names);

        return $names;
    }
}
