<?php

declare(strict_types=1);

namespace Pushery\WireKit\Icons\Presets;

use Pushery\WireKit\Contracts\IconPreset;

/**
 * Lucide preset — outline-only style, 1500+ icons.
 *
 * @see https://lucide.dev
 */
final class LucidePreset implements IconPreset
{
    public function icons(): array
    {
        return [
            // Navigation & Actions
            'close' => 'lucide-x',
            'menu' => 'lucide-menu',
            'search' => 'lucide-search',
            'chevron-down' => 'lucide-chevron-down',
            'chevron-up' => 'lucide-chevron-up',
            'chevron-left' => 'lucide-chevron-left',
            'chevron-right' => 'lucide-chevron-right',
            'check' => 'lucide-check',
            'plus' => 'lucide-plus',
            'minus' => 'lucide-minus',

            // Status & Feedback
            'info' => 'lucide-info',
            'success' => 'lucide-check-circle-2',
            'warning' => 'lucide-alert-triangle',
            'danger' => 'lucide-x-circle',

            // Objects & Visibility
            'user' => 'lucide-user',
            'calendar' => 'lucide-calendar',
            'trash' => 'lucide-trash-2',
            'edit' => 'lucide-pencil',
            'eye' => 'lucide-eye',
            'eye-off' => 'lucide-eye-off',
            'upload' => 'lucide-upload',
            'download' => 'lucide-download',
            'sort-asc' => 'lucide-arrow-up-a-z',
            'sort-desc' => 'lucide-arrow-down-z-a',
            'filter' => 'lucide-filter',
            'external-link' => 'lucide-external-link',

            // common dashboard icons.
            'home' => 'lucide-home',
            'moon' => 'lucide-moon',
            'sun' => 'lucide-sun',
            'book-open' => 'lucide-book-open',
            'sign-out' => 'lucide-log-out',
            'megaphone' => 'lucide-megaphone',
            'map' => 'lucide-map',
            'file-text' => 'lucide-file-text',

            // Common semantic aliases (v2.6.4) — shared keyset with every base
            // preset so they resolve without stacking; `live` stays marketing.
            'copy' => 'lucide-copy',
            'globe' => 'lucide-globe',
            'book' => 'lucide-book-open',
            'lightbulb' => 'lucide-lightbulb',
        ];
    }

    public function requires(): string
    {
        return 'mallardduck/blade-lucide-icons';
    }
}
