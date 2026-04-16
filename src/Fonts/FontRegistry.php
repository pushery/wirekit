<?php

declare(strict_types=1);

namespace Pushery\WireKit\Fonts;

final class FontRegistry
{
    /**
     * In-memory cache for all registered font presets.
     *
     * @var array<string, FontPreset>|null
     */
    private static ?array $presets = null;

    /**
     * Returns all available font presets, keyed by their unique identifier.
     *
     * Uses lazy initialization with a static cache to avoid rebuilding
     * the preset array on every call.
     *
     * @return array<string, FontPreset>
     */
    public static function all(): array
    {
        return self::$presets ??= self::buildPresets();
    }

    /**
     * Retrieves a single font preset by its key.
     *
     * Returns null if the key does not match any registered preset.
     */
    public static function get(string $key): ?FontPreset
    {
        return self::all()[$key] ?? null;
    }

    /**
     * Returns all font presets belonging to a given category.
     *
     * Valid categories: 'sans', 'serif', 'mono'.
     *
     * @return array<string, FontPreset>
     */
    public static function category(string $category): array
    {
        return array_filter(
            self::all(),
            fn (FontPreset $preset): bool => $preset->category === $category,
        );
    }

    /**
     * Clears the in-memory preset cache.
     *
     * Must be called in test setUp() to ensure clean state between tests.
     */
    public static function flush(): void
    {
        self::$presets = null;
    }

    /**
     * Builds the complete set of font presets across all categories.
     *
     * @return array<string, FontPreset>
     */
    private static function buildPresets(): array
    {
        $presets = [];

        // Shared fallback stacks for each category
        $sansFallback = 'ui-sans-serif, system-ui, sans-serif';
        $serifFallback = 'ui-serif, Georgia, serif';
        $monoFallback = 'ui-monospace, monospace';

        // --- Sans-Serif fonts (9) ---
        foreach (self::sansDefinitions() as $key => $data) {
            $presets[$key] = new FontPreset(
                key: $key,
                label: $data['label'],
                family: $data['family'],
                category: 'sans',
                cssFile: $data['css'],
                fallback: $sansFallback,
            );
        }

        // --- Serif fonts (5) ---
        foreach (self::serifDefinitions() as $key => $data) {
            $presets[$key] = new FontPreset(
                key: $key,
                label: $data['label'],
                family: $data['family'],
                category: 'serif',
                cssFile: $data['css'],
                fallback: $serifFallback,
            );
        }

        // --- Monospace fonts (6) ---
        foreach (self::monoDefinitions() as $key => $data) {
            $presets[$key] = new FontPreset(
                key: $key,
                label: $data['label'],
                family: $data['family'],
                category: 'mono',
                cssFile: $data['css'],
                fallback: $monoFallback,
            );
        }

        return $presets;
    }

    /**
     * Raw definitions for sans-serif font presets.
     *
     * @return array<string, array{label: string, family: string, css: string}>
     */
    private static function sansDefinitions(): array
    {
        return [
            'roboto' => [
                'label' => 'Roboto',
                'family' => 'Roboto',
                'css' => 'sans/roboto/roboto.css',
            ],
            'open-sans' => [
                'label' => 'Open Sans',
                'family' => 'Open Sans',
                'css' => 'sans/open-sans/open-sans.css',
            ],
            'lato' => [
                'label' => 'Lato',
                'family' => 'Lato',
                'css' => 'sans/lato/lato.css',
            ],
            'inter' => [
                'label' => 'Inter',
                'family' => 'Inter',
                'css' => 'sans/inter/inter.css',
            ],
            'montserrat' => [
                'label' => 'Montserrat',
                'family' => 'Montserrat',
                'css' => 'sans/montserrat/montserrat.css',
            ],
            'ibm-plex-sans' => [
                'label' => 'IBM Plex Sans',
                'family' => 'IBM Plex Sans',
                'css' => 'sans/ibm-plex-sans/ibm-plex-sans.css',
            ],
            'noto-sans' => [
                'label' => 'Noto Sans',
                'family' => 'Noto Sans',
                'css' => 'sans/noto-sans/noto-sans.css',
            ],
            'nunito-sans' => [
                'label' => 'Nunito Sans',
                'family' => 'Nunito Sans',
                'css' => 'sans/nunito-sans/nunito-sans.css',
            ],
            'dm-sans' => [
                'label' => 'DM Sans',
                'family' => 'DM Sans',
                'css' => 'sans/dm-sans/dm-sans.css',
            ],
        ];
    }

    /**
     * Raw definitions for serif font presets.
     *
     * @return array<string, array{label: string, family: string, css: string}>
     */
    private static function serifDefinitions(): array
    {
        return [
            'playfair-display' => [
                'label' => 'Playfair Display',
                'family' => 'Playfair Display',
                'css' => 'serif/playfair-display/playfair-display.css',
            ],
            'lora' => [
                'label' => 'Lora',
                'family' => 'Lora',
                'css' => 'serif/lora/lora.css',
            ],
            'merriweather' => [
                'label' => 'Merriweather',
                'family' => 'Merriweather',
                'css' => 'serif/merriweather/merriweather.css',
            ],
            'ibm-plex-serif' => [
                'label' => 'IBM Plex Serif',
                'family' => 'IBM Plex Serif',
                'css' => 'serif/ibm-plex-serif/ibm-plex-serif.css',
            ],
            'noto-serif' => [
                'label' => 'Noto Serif',
                'family' => 'Noto Serif',
                'css' => 'serif/noto-serif/noto-serif.css',
            ],
        ];
    }

    /**
     * Raw definitions for monospace font presets.
     *
     * @return array<string, array{label: string, family: string, css: string}>
     */
    private static function monoDefinitions(): array
    {
        return [
            'ibm-plex-mono' => [
                'label' => 'IBM Plex Mono',
                'family' => 'IBM Plex Mono',
                'css' => 'mono/ibm-plex-mono/ibm-plex-mono.css',
            ],
            'roboto-mono' => [
                'label' => 'Roboto Mono',
                'family' => 'Roboto Mono',
                'css' => 'mono/roboto-mono/roboto-mono.css',
            ],
            'source-code-pro' => [
                'label' => 'Source Code Pro',
                'family' => 'Source Code Pro',
                'css' => 'mono/source-code-pro/source-code-pro.css',
            ],
            'jetbrains-mono' => [
                'label' => 'JetBrains Mono',
                'family' => 'JetBrains Mono',
                'css' => 'mono/jetbrains-mono/jetbrains-mono.css',
            ],
            'space-mono' => [
                'label' => 'Space Mono',
                'family' => 'Space Mono',
                'css' => 'mono/space-mono/space-mono.css',
            ],
            'google-sans-code' => [
                'label' => 'Google Sans Code',
                'family' => 'Google Sans Code',
                'css' => 'mono/google-sans-code/google-sans-code.css',
            ],
        ];
    }
}
