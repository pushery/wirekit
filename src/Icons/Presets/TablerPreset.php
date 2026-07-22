<?php

declare(strict_types=1);

namespace Pushery\WireKit\Icons\Presets;

use Pushery\WireKit\Contracts\IconPreset;

/**
 * Tabler preset — outline style, 5700+ icons.
 *
 * @see https://tabler.io/icons
 */
final class TablerPreset implements IconPreset
{
    public function icons(): array
    {
        return [
            // Navigation & Actions
            'close' => 'tabler-x',
            'menu' => 'tabler-menu-2',
            'search' => 'tabler-search',
            'chevron-down' => 'tabler-chevron-down',
            'chevron-up' => 'tabler-chevron-up',
            'chevron-left' => 'tabler-chevron-left',
            'chevron-right' => 'tabler-chevron-right',
            'check' => 'tabler-check',
            'plus' => 'tabler-plus',
            'minus' => 'tabler-minus',

            // Status & Feedback
            'info' => 'tabler-info-circle',
            'success' => 'tabler-circle-check',
            'warning' => 'tabler-alert-triangle',
            'danger' => 'tabler-circle-x',

            // Objects & Visibility
            'user' => 'tabler-user',
            'calendar' => 'tabler-calendar',
            'trash' => 'tabler-trash',
            'edit' => 'tabler-edit',
            'eye' => 'tabler-eye',
            'eye-off' => 'tabler-eye-off',
            'upload' => 'tabler-upload',
            'download' => 'tabler-download',
            'sort-asc' => 'tabler-sort-ascending',
            'sort-desc' => 'tabler-sort-descending',
            'filter' => 'tabler-filter',
            'external-link' => 'tabler-external-link',

            // common dashboard icons.
            'home' => 'tabler-home',
            'moon' => 'tabler-moon',
            'sun' => 'tabler-sun',
            'book-open' => 'tabler-book',
            'sign-out' => 'tabler-logout',
            'megaphone' => 'tabler-megaphone',
            'map' => 'tabler-map',
            'file-text' => 'tabler-file-text',

            // Common semantic aliases (v2.6.4) — shared keyset with every base
            // preset so they resolve without stacking; `live` stays marketing.
            // Tabler names: world (globe) + bulb (lightbulb).
            'copy' => 'tabler-copy',
            'globe' => 'tabler-world',
            'book' => 'tabler-book',
            'lightbulb' => 'tabler-bulb',

            // SaaS app aliases (v2.9.0) — shared keyset with every base preset.
            'settings' => 'tabler-settings',
            'gear' => 'tabler-settings',
            'dashboard' => 'tabler-layout-dashboard',
            'billing' => 'tabler-credit-card',
            'credit-card' => 'tabler-credit-card',

            // Infrastructure & system — mapped to Tabler's icon names.
            'server' => 'tabler-server',
            'database' => 'tabler-database',
            'cloud' => 'tabler-cloud',
            'shield' => 'tabler-shield-check', // parity with the base `shield` alias
            'shield-check' => 'tabler-shield-check',
            'inbox' => 'tabler-inbox',
            'bolt' => 'tabler-bolt',
            'refresh' => 'tabler-refresh',
            // Media controls (WIRE-226).
            'play' => 'tabler-player-play',
            'pause' => 'tabler-player-pause',
            'stop' => 'tabler-player-stop',
            'speaker' => 'tabler-volume',
            'mute' => 'tabler-volume-off',
            'microphone' => 'tabler-microphone',
        ];
    }

    public function requires(): string
    {
        return 'ryangjchandler/blade-tabler-icons';
    }
}
