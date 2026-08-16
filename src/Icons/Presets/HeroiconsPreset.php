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
            'system' => 'heroicon-m-computer-desktop',
            'code' => 'heroicon-m-code-bracket',
            'key' => 'heroicon-m-key',
            'rocket-launch' => 'heroicon-m-rocket-launch',
            'broadcast' => 'heroicon-m-radio',
            'chart-bar' => 'heroicon-m-chart-bar',
            'coins' => 'heroicon-m-banknotes',
            'gift' => 'heroicon-m-gift',
            'list-bullets' => 'heroicon-m-list-bullet',
            'list-checks' => 'heroicon-m-clipboard-document-check',
            'lock-key' => 'heroicon-m-lock-closed',
            'map-pin' => 'heroicon-m-map-pin',
            'percent' => 'heroicon-m-percent-badge',
            'sliders' => 'heroicon-m-adjustments-horizontal',
            'trend-up' => 'heroicon-m-arrow-trending-up',
            'chart-line' => 'heroicon-m-presentation-chart-line',
            'folders' => 'heroicon-m-folder',

            // A seventh, and it came from the docs rather than from a report: five
            // blueprint pages reached for `pulse` and two of them meant a telephone
            // ("Voice call", "Call"). The vocabulary had no word for one, so the
            // pages borrowed a name that does not resolve on the default config at
            // all — which is how a missing word turns into a broken page.
            'phone' => 'heroicon-m-phone',
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

            // Infrastructure & system — subprocessor / breach registers, hosting,
            // status dashboards. Verified against blade-heroicons Mini SVGs.
            'server' => 'heroicon-m-server',
            'database' => 'heroicon-m-circle-stack', // heroicons has no `database`
            'cloud' => 'heroicon-m-cloud',
            'shield' => 'heroicon-m-shield-check', // heroicons has no plain `shield`
            'shield-check' => 'heroicon-m-shield-check',
            'inbox' => 'heroicon-m-inbox',
            'bolt' => 'heroicon-m-bolt',
            'refresh' => 'heroicon-m-arrow-path', // heroicons calls it arrow-path
            // Media controls. An app with audio playback had to
            // hand-roll inline SVGs — against the catalog's own "use the icon
            // component" rule — because the base set had no play/pause at all.
            'play' => 'heroicon-m-play',
            'pause' => 'heroicon-m-pause',
            'stop' => 'heroicon-m-stop',
            'speaker' => 'heroicon-m-speaker-wave',
            'mute' => 'heroicon-m-speaker-x-mark',
            'microphone' => 'heroicon-m-microphone',
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
            'users' => 'heroicon-m-users',
            'history' => 'heroicon-m-clock',
            'legal' => 'heroicon-m-scale',
            'badge' => 'heroicon-m-identification',
            'layers' => 'heroicon-m-square-3-stack-3d',

            // `stack` is the concept `layers` already names, shipped as its own
            // word because that is the word applications write — the navigation
            // above reached for `stack` and got a synonym it does not use.
            // Undeclared, the name did not fail cleanly either: Phosphor calls its
            // own glyph `stack`, so the raw-name fallthrough answered it under that
            // one family and threw under the rest, which reads as "the icon broke
            // when we changed preset" rather than as a word nobody promised. Two
            // spellings on one glyph is what this vocabulary already does for
            // settings/gear, book/book-open and billing/credit-card.
            'stack' => 'heroicon-m-square-3-stack-3d',

            // Notification, labeling, mail and media — concepts every
            // administrative interface has, and none of them had a word here.
            // A page that needed one reached for the icon package's own glyph
            // name, which resolves only while that optional package happens to
            // be installed: a dependency on the icon set rather than a contract
            // with this library. `bell` and `bell-slash` move here from the
            // app extension — same glyph, so nothing rendered changes.
            'bell' => 'heroicon-m-bell',
            'bell-slash' => 'heroicon-m-bell-slash',
            'tag' => 'heroicon-m-tag',
            'send' => 'heroicon-m-paper-airplane',
            'envelope' => 'heroicon-m-envelope',
            'store' => 'heroicon-m-building-storefront',
            'cart' => 'heroicon-m-shopping-cart',
            'receipt' => 'heroicon-m-receipt-percent',
            'truck' => 'heroicon-m-truck',
            'package' => 'heroicon-m-archive-box',
            'barcode' => 'heroicon-m-qr-code',
            // Heroicons ships no register, till or drawer glyph in any style — measured
            // against the installed set, which has `calculator` and nothing nearer. A cash
            // register is a keypad-and-display device, so the substitution reads, and the
            // alternative is worse than a near miss: a name off the contract resolves
            // through whichever family happens to be active, so it survives until the day
            // somebody switches preset — and then it is missing from the navigation of
            // every signed-in page at once. Same trade already taken one line above.
            'cash-register' => 'heroicon-m-calculator',
            'user-add' => 'heroicon-m-user-plus',
            'user-remove' => 'heroicon-m-user-minus',
            'building' => 'heroicon-m-building-office',
            'webhook' => 'heroicon-m-link',
            'arrow-left' => 'heroicon-m-arrow-left',
            'clock' => 'heroicon-m-clock',
            'lock' => 'heroicon-m-lock-closed',
            'archive' => 'heroicon-m-archive-box',
            'image' => 'heroicon-m-photo',
            'message' => 'heroicon-m-chat-bubble-left-right',
            'reply' => 'heroicon-m-arrow-uturn-left',
            'forward' => 'heroicon-m-arrow-uturn-right',
        ];
    }

    public function requires(): string
    {
        return 'blade-ui-kit/blade-heroicons';
    }
}
