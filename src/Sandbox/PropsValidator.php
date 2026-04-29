<?php

declare(strict_types=1);

namespace Pushery\WireKit\Sandbox;

/**
 * Validate untrusted prop payloads against a per-component
 * sandbox schema before they reach the renderer.
 *
 * Rules enforced:
 *   1. Only props in the schema's `allowed` list pass through.
 *   2. Each prop value's type matches the schema's declared type.
 *   3. String values cap at 10KB (denial-of-service defence).
 *   4. Nested arrays cap at depth 5 (prevent recursion bombs).
 *   5. String values stripped of `<script>` / closing `</script>`
 *      sequences via `htmlspecialchars()` — Blade's `{{ }}` will
 *      double-escape downstream, but defence-in-depth here closes
 *      the gap if a slot ever receives raw output by mistake.
 *
 * Returns a `ValidationResult` carrying either the sanitized payload
 * or a list of violations. Never throws — the caller decides whether
 * to render or 422.
 */
final class PropsValidator
{
    private const MAX_STRING_LENGTH = 10_240;

    private const MAX_ARRAY_DEPTH = 5;

    /**
     * @param  array<string, array{type: string, required?: bool, default?: mixed, allowed_values?: array<int, mixed>}>  $schema
     * @param  array<string, mixed>  $payload
     */
    public static function validate(array $schema, array $payload): ValidationResult
    {
        $violations = [];
        $clean = [];

        // Reject any prop name not in the schema.
        foreach ($payload as $key => $value) {
            if (! is_string($key) || ! isset($schema[$key])) {
                $violations[] = "prop '{$key}' is not in the sandbox allowlist";

                continue;
            }
        }

        foreach ($schema as $name => $spec) {
            if (! array_key_exists($name, $payload)) {
                if (! empty($spec['required'])) {
                    $violations[] = "prop '{$name}' is required";

                    continue;
                }
                if (array_key_exists('default', $spec)) {
                    $clean[$name] = $spec['default'];
                }

                continue;
            }

            $value = $payload[$name];
            $expectedType = (string) ($spec['type'] ?? 'string');

            // Coerce HTML-form-derived strings into their declared scalar
            // types BEFORE the strict type check. Docs-app Sandbox `<select>`
            // / `<input type="number">` / `<input type="checkbox">` all send
            // string values over POST; without coercion, schemas with
            // `type: int` (e.g. heading.level) reject every form submission
            // with "expected int, got string". Coercion is conservative —
            // only fires when the declared type unambiguously implies the
            // target shape AND the input matches that shape.
            $value = self::coerceScalar($value, $expectedType);

            if (! self::typeMatches($value, $expectedType)) {
                $violations[] = "prop '{$name}' expected {$expectedType}, got ".gettype($value);

                continue;
            }

            if (isset($spec['allowed_values']) && is_array($spec['allowed_values'])
                && ! in_array($value, $spec['allowed_values'], true)) {
                $violations[] = "prop '{$name}' value not in allowed_values";

                continue;
            }

            $sanitized = self::sanitize($value, $name, $violations);
            if ($sanitized !== null || $value === null) {
                $clean[$name] = $sanitized;
            }
        }

        return new ValidationResult($clean, $violations);
    }

    private static function typeMatches(mixed $value, string $expected): bool
    {
        return match ($expected) {
            'string' => is_string($value),
            'int' => is_int($value),
            'float' => is_float($value) || is_int($value),
            'bool' => is_bool($value),
            'array' => is_array($value),
            'mixed' => true,
            default => false,
        };
    }

    /**
     * Conservative scalar coercion for HTML-form-derived input.
     *
     * Coerces ONLY when the value is a string that unambiguously matches
     * the declared target shape:
     *   - 'int':   /^-?\d+$/ → (int) cast
     *   - 'float': numeric (PHP's is_numeric) → (float) cast
     *   - 'bool':  exact 'true' / 'false' / '1' / '0' / 'on' → bool cast
     *
     * Anything else passes through unchanged so the strict type check that
     * follows still catches genuine mismatches (e.g. `body: 42` against
     * `type: string` — value is int, doesn't match the coercion shape, falls
     * through to the type check which then rejects). Direct-PHP callers
     * sending properly-typed values are unaffected (the value is already
     * the right type, coercion is a no-op).
     */
    private static function coerceScalar(mixed $value, string $expectedType): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return match ($expectedType) {
            'int' => preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : $value,
            'float' => is_numeric($value) ? (float) $value : $value,
            'bool' => match (strtolower($value)) {
                'true', '1', 'on' => true,
                'false', '0', 'off', '' => false,
                default => $value,
            },
            default => $value,
        };
    }

    /**
     * @param  array<int, string>  $violations
     */
    private static function sanitize(mixed $value, string $propName, array &$violations, int $depth = 0): mixed
    {
        if ($depth > self::MAX_ARRAY_DEPTH) {
            $violations[] = "prop '{$propName}' exceeds max nesting depth ".self::MAX_ARRAY_DEPTH;

            return null;
        }

        if (is_string($value)) {
            if (strlen($value) > self::MAX_STRING_LENGTH) {
                $violations[] = "prop '{$propName}' exceeds max string length ".self::MAX_STRING_LENGTH;

                return null;
            }

            // Defence-in-depth: HTML-escape every string. The renderer's
            // Blade interpolation does this too, but a slot that mistakenly
            // uses `{!! !!}` would let raw values through. Escape here so
            // the worst case is double-escaped output, not XSS.
            return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        }

        if (is_array($value)) {
            $clean = [];
            foreach ($value as $k => $v) {
                $cleanKey = is_string($k) ? htmlspecialchars($k, ENT_QUOTES, 'UTF-8') : $k;
                $clean[$cleanKey] = self::sanitize($v, $propName.'['.$k.']', $violations, $depth + 1);
            }

            return $clean;
        }

        // Scalars (int/float/bool/null) pass through unchanged
        return $value;
    }
}
