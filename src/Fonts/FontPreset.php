<?php

declare(strict_types=1);

namespace Pushery\WireKit\Fonts;

final class FontPreset
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $family,
        public readonly string $category,   // 'sans', 'serif', 'mono'
        public readonly string $cssFile,    // relative path to CSS file
        public readonly string $fallback,   // fallback font stack
    ) {}

    /**
     * Returns the full font-family value including fallbacks.
     */
    public function fontFamily(): string
    {
        return "'{$this->family}', {$this->fallback}";
    }

    /**
     * Path to the published CSS file in the user's public directory.
     *
     * The directory structure after vendor:publish mirrors the package structure:
     * resources/fonts/{category}/{key}/{key}.css → public/vendor/wirekit/fonts/{category}/{key}/{key}.css
     */
    public function publishedCssPath(): string
    {
        return "vendor/wirekit/fonts/{$this->category}/{$this->key}/{$this->key}.css";
    }
}
