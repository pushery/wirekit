<?php

declare(strict_types=1);

namespace Pushery\WireKit\Contracts;

interface IconPreset
{
    /**
     * Mapping of semantic alias names to Blade Icon identifiers.
     *
     * Base presets (heroicons, lucide, phosphor, tabler) MUST contain the
     * 26 standard aliases — enforced by IconSystemTest.
     *
     * Stackable extension presets (e.g. heroicons-marketing) may define any
     * aliases. Multiple presets can be active simultaneously via the
     * `wirekit.icons.presets` config array; later entries override earlier
     * ones, and consumer aliases override all presets.
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
