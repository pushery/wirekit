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
            // Media controls.
            'play' => 'tabler-player-play',
            'pause' => 'tabler-player-pause',
            'stop' => 'tabler-player-stop',
            'speaker' => 'tabler-volume',
            'mute' => 'tabler-volume-off',
            'microphone' => 'tabler-microphone',
            // ── Admin navigation ────────────────────────────────────────────
            // Five concepts every administrative interface has, and none of them
            // was expressible. An application's main navigation could name two of its
            // seven items through this vocabulary and reached for the icon
            // package's OWN glyph names for the rest — `users`, `stack`,
            // `scales`, `clock-counter-clockwise`.
            //
            // Those raw names work, and that is the problem: they resolve because
            // the full package happens to be installed, which is a dependency on
            // the icon set rather than a contract with WireKit. When that application
            // tried to ship only the icons it actually renders, four test files
            // went red — the restriction removed the coincidence.
            //
            // `users` is a separate word from `user` on purpose: managing accounts
            // is a different menu item from your own profile, and the two sharing
            // one glyph was forced rather than chosen.
            'users' => 'tabler-users',
            'history' => 'tabler-history',
            'legal' => 'tabler-scale',
            'badge' => 'tabler-badge',
            'layers' => 'tabler-stack-2',

            // Notification, labeling, mail and media — concepts every
            // administrative interface has, and none of them had a word here.
            // A page that needed one reached for the icon package's own glyph
            // name, which resolves only while that optional package happens to
            // be installed: a dependency on the icon set rather than a contract
            // with this library. `bell` and `bell-slash` move here from the
            // app extension — same glyph, so nothing rendered changes.
            'bell' => 'tabler-bell',
            'bell-slash' => 'tabler-bell-off',
            'tag' => 'tabler-tag',
            'send' => 'tabler-send',
            'archive' => 'tabler-archive',
            'image' => 'tabler-photo',
            'message' => 'tabler-message-circle',
            'reply' => 'tabler-arrow-back-up',
            'forward' => 'tabler-arrow-forward-up',
        ];
    }

    public function requires(): string
    {
        return 'ryangjchandler/blade-tabler-icons';
    }
}
