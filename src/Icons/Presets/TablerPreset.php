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

            // Six words the vocabulary was missing, added together because they were
            // reported together — the eighth report of that shape, which is the finding
            // behind the finding.
            //
            // `system` is the third theme state. `sun` and `moon` were both here and
            // "follows the system" was not, so a theme control could name two of its
            // three positions. No icon family spells the concept the same way
            // (computer-desktop / monitor / device-desktop), so the WORD is ours and the
            // glyph is each set's own — which is what these aliases are for.
            //
            // Every target below was verified against the real set: Heroicons from the
            // installed package, the other three from each package's full recursive git
            // tree. A directory listing was tried first and is useless here — GitHub caps
            // it at 1000 entries, so "absent" would have meant nothing.
            //
            // `article` was proposed with these and deliberately left out: it needs two
            // substitutions and "editorial unit" is the most arguable of the seven.
            'system' => 'tabler-device-desktop',
            'code' => 'tabler-code',
            'key' => 'tabler-key',
            'rocket-launch' => 'tabler-rocket',
            'broadcast' => 'tabler-broadcast',
            'chart-bar' => 'tabler-chart-bar',
            'coins' => 'tabler-coins',
            'gift' => 'tabler-gift',
            'list-bullets' => 'tabler-list',
            'list-checks' => 'tabler-list-check',
            'lock-key' => 'tabler-lock',
            'map-pin' => 'tabler-map-pin',
            'percent' => 'tabler-percentage',
            'sliders' => 'tabler-adjustments',
            'trend-up' => 'tabler-trending-up',
            'chart-line' => 'tabler-chart-line',
            'folders' => 'tabler-folders',

            // A seventh, and it came from the docs rather than from a report: five
            // blueprint pages reached for `pulse` and two of them meant a telephone
            // ("Voice call", "Call"). The vocabulary had no word for one, so the
            // pages borrowed a name that does not resolve on the default config at
            // all — which is how a missing word turns into a broken page.
            'phone' => 'tabler-phone',
            'book-open' => 'tabler-book',
            'sign-out' => 'tabler-logout',
            'megaphone' => 'tabler-speakerphone',
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

            // `stack` is the concept `layers` already names, shipped as its own
            // word because that is the word applications write — the navigation
            // above reached for `stack` and got a synonym it does not use.
            // Undeclared, the name did not fail cleanly either: Phosphor calls its
            // own glyph `stack`, so the raw-name fallthrough answered it under that
            // one family and threw under the rest, which reads as "the icon broke
            // when we changed preset" rather than as a word nobody promised. Two
            // spellings on one glyph is what this vocabulary already does for
            // settings/gear, book/book-open and billing/credit-card.
            //
            // It points at the target `layers` already ships, which is the part that
            // matters here specifically: this preset's package cannot be installed
            // against the Laravel versions the library supports, so nothing verifies
            // its targets. Reusing an existing one adds no name that no test can
            // check — see IconPresetTargetTest.
            'stack' => 'tabler-stack-2',

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
            'envelope' => 'tabler-mail',
            'store' => 'tabler-building-store',
            'cart' => 'tabler-shopping-cart',
            'receipt' => 'tabler-receipt',
            'truck' => 'tabler-truck',
            'package' => 'tabler-package',
            'barcode' => 'tabler-barcode',
            // The one family that draws it and the one this repo cannot check locally:
            // the Tabler Blade package will not install against the Laravel versions
            // WireKit requires, so the target-existence test skips this preset by design.
            // Verified against Tabler's own source instead of guessed —
            // `icons/outline/cash-register.svg`, category E-commerce, shipped since 3.4.
            // The name follows the same rule as its neighbors: the file name, prefixed.
            'cash-register' => 'tabler-cash-register',
            'user-add' => 'tabler-user-plus',
            'user-remove' => 'tabler-user-minus',
            'building' => 'tabler-building',
            'webhook' => 'tabler-webhook',
            'arrow-left' => 'tabler-arrow-left',
            'clock' => 'tabler-clock',
            'lock' => 'tabler-lock',
            'archive' => 'tabler-archive',
            'image' => 'tabler-photo',
            'message' => 'tabler-message-circle',
            'reply' => 'tabler-arrow-back-up',
            'forward' => 'tabler-arrow-forward-up',
        ];
    }

    public function requires(): string
    {
        return 'secondnetwork/blade-tabler-icons';
    }
}
