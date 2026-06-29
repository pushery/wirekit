<?php

declare(strict_types=1);

namespace Pushery\WireKit\Icons\Presets;

use Pushery\WireKit\Contracts\IconPreset;

/**
 * Heroicons preset — uses Mini (20px) style for optimal UI element sizing.
 *
 * @see https://heroicons.com
 */
final class HeroiconsPreset implements IconPreset
{
    public function icons(): array
    {
        return [
            // Navigation & Actions
            'close' => 'heroicon-m-x-mark',
            'menu' => 'heroicon-m-bars-3',
            'search' => 'heroicon-m-magnifying-glass',
            'chevron-down' => 'heroicon-m-chevron-down',
            'chevron-up' => 'heroicon-m-chevron-up',
            'chevron-left' => 'heroicon-m-chevron-left',
            'chevron-right' => 'heroicon-m-chevron-right',
            'check' => 'heroicon-m-check',
            'plus' => 'heroicon-m-plus',
            'minus' => 'heroicon-m-minus',

            // Status & Feedback
            'info' => 'heroicon-m-information-circle',
            'success' => 'heroicon-m-check-circle',
            'warning' => 'heroicon-m-exclamation-triangle',
            'danger' => 'heroicon-m-x-circle',

            // Objects & Visibility
            'user' => 'heroicon-m-user',
            'calendar' => 'heroicon-m-calendar',
            'trash' => 'heroicon-m-trash',
            'edit' => 'heroicon-m-pencil-square',
            'eye' => 'heroicon-m-eye',
            'eye-off' => 'heroicon-m-eye-slash',
            'upload' => 'heroicon-m-arrow-up-tray',
            'download' => 'heroicon-m-arrow-down-tray',
            'sort-asc' => 'heroicon-m-bars-arrow-up',
            'sort-desc' => 'heroicon-m-bars-arrow-down',
            'filter' => 'heroicon-m-funnel',
            'external-link' => 'heroicon-m-arrow-top-right-on-square',

            // common dashboard icons
            // every new integrator reaches for on first install:
            'home' => 'heroicon-m-home',
            'moon' => 'heroicon-m-moon',
            'sun' => 'heroicon-m-sun',
            'book-open' => 'heroicon-m-book-open',
            'sign-out' => 'heroicon-m-arrow-right-on-rectangle',
            'megaphone' => 'heroicon-m-megaphone',
            'map' => 'heroicon-m-map',
            'file-text' => 'heroicon-m-document-text',

            // Common semantic aliases (v2.6.4) — promoted from the heroicons-app/
            // marketing extension presets so they resolve on EVERY base preset
            // without stacking. Every base preset (heroicons/lucide/phosphor/
            // tabler) shares this identical keyset; `live` stays marketing-specific
            // (no clean universal-core equivalent across libraries).
            'copy' => 'heroicon-m-clipboard-document',
            'globe' => 'heroicon-m-globe-alt',
            'book' => 'heroicon-m-book-open',
            'lightbulb' => 'heroicon-m-light-bulb',

            // SaaS app aliases (v2.9.0) — high-frequency dashboard / settings /
            // billing icons every signed-in app reaches for. Shared keyset across
            // every base preset; identifiers follow each upstream library.
            'settings' => 'heroicon-m-cog-6-tooth',
            'gear' => 'heroicon-m-cog-6-tooth',
            'dashboard' => 'heroicon-m-squares-2x2',
            'billing' => 'heroicon-m-credit-card',
            'credit-card' => 'heroicon-m-credit-card',
        ];
    }

    public function requires(): string
    {
        return 'blade-ui-kit/blade-heroicons';
    }
}
