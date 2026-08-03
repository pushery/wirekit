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

            // SaaS app aliases (v2.9.0) — shared keyset with every base preset.
            'settings' => 'lucide-settings',
            'gear' => 'lucide-settings',
            'dashboard' => 'lucide-layout-dashboard',
            'billing' => 'lucide-credit-card',
            'credit-card' => 'lucide-credit-card',

            // Infrastructure & system — mapped to Lucide's icon names.
            'server' => 'lucide-server',
            'database' => 'lucide-database',
            'cloud' => 'lucide-cloud',
            'shield' => 'lucide-shield-check', // parity with the base `shield` alias
            'shield-check' => 'lucide-shield-check',
            'inbox' => 'lucide-inbox',
            'bolt' => 'lucide-zap', // lucide calls the bolt glyph `zap`
            'refresh' => 'lucide-refresh-cw', // lucide's rotate glyph
            // Media controls.
            'play' => 'lucide-play',
            'pause' => 'lucide-pause',
            'stop' => 'lucide-square', // lucide's stop glyph is a filled square
            'speaker' => 'lucide-volume-2',
            'mute' => 'lucide-volume-x',
            'microphone' => 'lucide-mic',
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
            'users' => 'lucide-users',
            'history' => 'lucide-history',
            'legal' => 'lucide-scale',
            'badge' => 'lucide-badge-check',
            'layers' => 'lucide-layers',

            // Notification, labeling, mail and media — concepts every
            // administrative interface has, and none of them had a word here.
            // A page that needed one reached for the icon package's own glyph
            // name, which resolves only while that optional package happens to
            // be installed: a dependency on the icon set rather than a contract
            // with this library. `bell` and `bell-slash` move here from the
            // app extension — same glyph, so nothing rendered changes.
            'bell' => 'lucide-bell',
            'bell-slash' => 'lucide-bell-off',
            'tag' => 'lucide-tag',
            'send' => 'lucide-send',
            'archive' => 'lucide-archive',
            'image' => 'lucide-image',
            'message' => 'lucide-message-circle',
            'reply' => 'lucide-reply',
            'forward' => 'lucide-forward',
        ];
    }

    public function requires(): string
    {
        return 'mallardduck/blade-lucide-icons';
    }
}
