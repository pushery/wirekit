<?php

declare(strict_types=1);

namespace Pushery\WireKit;

/**
 * Resolves intent × surface combinations into CSS class strings.
 *
 * Intents: primary, neutral, success, warning, danger, info
 * Surfaces: filled, outline, soft, ghost, link
 *
 * Old-style `variant` values (e.g. "primary", "danger") are mapped
 * to intent+surface pairs for backward compatibility.
 */
class VariantResolver
{
    public const INTENTS = ['primary', 'neutral', 'success', 'warning', 'danger', 'info'];

    public const SURFACES = ['filled', 'outline', 'soft', 'ghost', 'link'];

    /**
     * Resolve intent and surface into CSS classes for button-like components.
     */
    public static function resolve(string $intent, string $surface): string
    {
        return match ($surface) {
            'filled' => self::filled($intent),
            'outline' => self::outline($intent),
            'soft' => self::soft($intent),
            'ghost' => self::ghost($intent),
            'link' => self::link($intent),
            default => '',
        };
    }

    /**
     * Map a legacy variant string to an intent+surface pair.
     *
     * @return array{intent: string, surface: string}
     */
    public static function fromVariant(string $variant): array
    {
        return match ($variant) {
            'primary' => ['intent' => 'primary', 'surface' => 'filled'],
            'secondary' => ['intent' => 'neutral', 'surface' => 'filled'],
            'outline' => ['intent' => 'neutral', 'surface' => 'outline'],
            'ghost' => ['intent' => 'neutral', 'surface' => 'ghost'],
            'danger' => ['intent' => 'danger', 'surface' => 'filled'],
            'link' => ['intent' => 'primary', 'surface' => 'link'],
            'success' => ['intent' => 'success', 'surface' => 'filled'],
            'warning' => ['intent' => 'warning', 'surface' => 'filled'],
            'info' => ['intent' => 'info', 'surface' => 'filled'],
            'neutral' => ['intent' => 'neutral', 'surface' => 'filled'],
            default => ['intent' => 'primary', 'surface' => 'filled'],
        };
    }

    private static function filled(string $intent): string
    {
        return match ($intent) {
            'primary' => implode(' ', [
                'bg-[var(--color-wk-accent)]',
                'text-[var(--color-wk-accent-fg)]',
                'border-[var(--color-wk-accent)]',
                'hover:bg-[var(--color-wk-accent-hover)]',
                'hover:border-[var(--color-wk-accent-hover)]',
                'shadow-[var(--shadow-wk-sm)]',
            ]),
            'neutral' => implode(' ', [
                'bg-[var(--color-wk-bg-muted)]',
                'text-[var(--color-wk-text)]',
                'border-[var(--color-wk-bg-muted)]',
                'hover:bg-[var(--color-wk-bg-subtle)]',
                'shadow-[var(--shadow-wk-sm)]',
            ]),
            'success' => implode(' ', [
                'bg-[var(--color-wk-success)]',
                'text-[var(--color-wk-success-fg)]',
                'border-[var(--color-wk-success)]',
                'hover:bg-[var(--color-wk-success-hover)]',
                'shadow-[var(--shadow-wk-sm)]',
            ]),
            'warning' => implode(' ', [
                'bg-[var(--color-wk-warning)]',
                'text-[var(--color-wk-warning-fg)]',
                'border-[var(--color-wk-warning)]',
                'hover:bg-[var(--color-wk-warning-hover)]',
                'shadow-[var(--shadow-wk-sm)]',
            ]),
            'danger' => implode(' ', [
                'bg-[var(--color-wk-danger)]',
                'text-[var(--color-wk-danger-fg)]',
                'border-[var(--color-wk-danger)]',
                'hover:bg-[var(--color-wk-danger-hover)]',
                'hover:border-[var(--color-wk-danger-hover)]',
                'shadow-[var(--shadow-wk-sm)]',
            ]),
            'info' => implode(' ', [
                'bg-[var(--color-wk-info)]',
                'text-[var(--color-wk-info-fg)]',
                'border-[var(--color-wk-info)]',
                'hover:bg-[var(--color-wk-info-hover)]',
                'shadow-[var(--shadow-wk-sm)]',
            ]),
            default => '',
        };
    }

    private static function outline(string $intent): string
    {
        $borderColor = match ($intent) {
            'primary' => '--color-wk-accent',
            'neutral' => '--color-wk-border',
            'success' => '--color-wk-success',
            'warning' => '--color-wk-warning',
            'danger' => '--color-wk-danger',
            'info' => '--color-wk-info',
            default => '--color-wk-border',
        };

        $textColor = match ($intent) {
            'primary' => '--color-wk-accent-content',
            'neutral' => '--color-wk-text',
            'success' => '--color-wk-success',
            'warning' => '--color-wk-warning',
            'danger' => '--color-wk-danger',
            'info' => '--color-wk-info',
            default => '--color-wk-text',
        };

        return implode(' ', [
            'bg-[var(--color-wk-bg)]',
            "text-[var({$textColor})]",
            "border-[var({$borderColor})]",
            'hover:bg-[var(--color-wk-bg-subtle)]',
            'shadow-[var(--shadow-wk-sm)]',
        ]);
    }

    private static function soft(string $intent): string
    {
        $bgColor = match ($intent) {
            'primary' => '--color-wk-accent-bg',
            'neutral' => '--color-wk-bg-muted',
            'success' => '--color-wk-success-bg',
            'warning' => '--color-wk-warning-bg',
            'danger' => '--color-wk-danger-bg',
            'info' => '--color-wk-info-bg',
            default => '--color-wk-bg-muted',
        };

        $textColor = match ($intent) {
            'primary' => '--color-wk-accent-content',
            'neutral' => '--color-wk-text',
            'success' => '--color-wk-success',
            'warning' => '--color-wk-warning',
            'danger' => '--color-wk-danger',
            'info' => '--color-wk-info',
            default => '--color-wk-text',
        };

        return implode(' ', [
            "bg-[var({$bgColor})]",
            "text-[var({$textColor})]",
            'border-transparent',
        ]);
    }

    private static function ghost(string $intent): string
    {
        $textColor = match ($intent) {
            'primary' => '--color-wk-accent-content',
            'neutral' => '--color-wk-text',
            'success' => '--color-wk-success',
            'warning' => '--color-wk-warning',
            'danger' => '--color-wk-danger',
            'info' => '--color-wk-info',
            default => '--color-wk-text',
        };

        return implode(' ', [
            'bg-transparent',
            "text-[var({$textColor})]",
            'border-transparent',
            'hover:bg-[var(--color-wk-bg-subtle)]',
            'shadow-[var(--shadow-wk-none)]',
        ]);
    }

    private static function link(string $intent): string
    {
        $textColor = match ($intent) {
            'primary' => '--color-wk-accent-content',
            'danger' => '--color-wk-danger',
            default => '--color-wk-accent-content',
        };

        return implode(' ', [
            "text-[var({$textColor})]",
            'border-transparent',
            'underline-offset-4',
            'hover:underline',
            'p-0 h-auto',
        ]);
    }
}
