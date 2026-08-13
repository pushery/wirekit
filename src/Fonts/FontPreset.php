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
     * The family this preset registers a metric-matched local face under.
     *
     * The face itself is generated into the family's own CSS by
     * `scripts/generate-font-fallbacks.py`; this is only the name both halves
     * agree on. They are held together by `FontFallbackMetricsTest`, because a
     * name that drifts on one side leaves a rule nothing selects — which looks
     * exactly like the shift it was added to remove.
     */
    public function fallbackFamily(): string
    {
        return "{$this->family} Fallback";
    }

    /**
     * Returns the full font-family value including fallbacks.
     *
     * The metric-matched face is named BEFORE the generic stack, and that order
     * is the whole point: with `font-display: swap` the browser paints a system
     * font first and swaps the web font in when it arrives. Whichever family the
     * browser lands on in the meantime decides how far the text moves. Naming
     * the overridden face first means it lands on one built to occupy the same
     * box, so there is nothing left to shift; naming it after the generic would
     * ship the rule and never reach it.
     *
     * A machine with none of the `local()` faces resolves nothing here and falls
     * through to the generic stack, which is the behavior before this existed.
     */
    public function fontFamily(): string
    {
        return "'{$this->family}', '{$this->fallbackFamily()}', {$this->fallback}";
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
