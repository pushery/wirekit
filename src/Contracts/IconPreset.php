<?php

declare(strict_types=1);

namespace Pushery\WireKit\Contracts;

interface IconPreset
{
    /**
     * Mapping of semantic alias names to actual Blade Icon identifiers.
     * Must contain exactly 26 entries (all aliases from the specification).
     *
     * @return array<string, string>
     */
    public function icons(): array;

    /**
     * Composer package name required for this preset.
     * Used in error messages when the package is not installed.
     */
    public function requires(): string;
}
