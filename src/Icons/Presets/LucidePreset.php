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
            'system' => 'lucide-monitor',
            'code' => 'lucide-code',
            'key' => 'lucide-key',
            'rocket-launch' => 'lucide-rocket',
            'broadcast' => 'lucide-radio',
            'chart-bar' => 'lucide-chart-bar',
            'coins' => 'lucide-coins',
            'gift' => 'lucide-gift',
            'list-bullets' => 'lucide-list',
            'list-checks' => 'lucide-list-checks',
            'lock-key' => 'lucide-lock-keyhole',
            'map-pin' => 'lucide-map-pin',
            'percent' => 'lucide-percent',
            'sliders' => 'lucide-sliders',
            'trend-up' => 'lucide-trending-up',
            'chart-line' => 'lucide-chart-line',
            'folders' => 'lucide-folders',

            // A seventh, and it came from the docs rather than from a report: five
            // blueprint pages reached for `pulse` and two of them meant a telephone
            // ("Voice call", "Call"). The vocabulary had no word for one, so the
            // pages borrowed a name that does not resolve on the default config at
            // all — which is how a missing word turns into a broken page.
            'phone' => 'lucide-phone',
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
            // Undo — the counter-clockwise arrow every set ships under a different name.
            // Named for the CONCEPT, not for one family's spelling: the proposal reached us
            // as `arrow-counter-clockwise`, which is Phosphor's word for it and would have
            // made the vocabulary read differently depending on which set a project loads.
            // Verified against the SVG files: lucide `undo`, tabler `arrow-back-up`,
            // phosphor `arrow-counter-clockwise`, heroicons `arrow-uturn-left` — four real
            // undo glyphs, not four things that look roughly alike.
            'undo' => 'lucide-undo',
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

            // `stack` is the concept `layers` already names, shipped as its own
            // word because that is the word applications write — the navigation
            // above reached for `stack` and got a synonym it does not use.
            // Undeclared, the name did not fail cleanly either: Phosphor calls its
            // own glyph `stack`, so the raw-name fallthrough answered it under that
            // one family and threw under the rest, which reads as "the icon broke
            // when we changed preset" rather than as a word nobody promised. Two
            // spellings on one glyph is what this vocabulary already does for
            // settings/gear, book/book-open and billing/credit-card.
            'stack' => 'lucide-layers',

            // ─── The overflow affordance, and five words a consuming project
            // reached for and did not find.
            //
            // EVERY ONE OF THESE IS A TRUE COGNATE IN ALL FOUR INTERCHANGEABLE
            // PRESETS — checked file by file, not assumed. That is the whole
            // admission test: a word enters this vocabulary only when every set
            // we ship can answer it with a glyph that means the same thing. An
            // alias that resolves in three sets and substitutes something else
            // in the fourth is worse than no alias, because it looks like a
            // contract right up until somebody changes preset.
            //
            // `more` rather than `overflow`, `ellipsis` or `dots-three`. The last
            // two are one family's own spelling, and enshrining a family spelling
            // as the contract word is the exact trap documented for `stack` above.
            // `overflow` is already spoken for in its CSS sense across this
            // codebase, and `ellipsis` collides with text truncation. `more` is
            // the concept, and the concept is what every other word here names.
            //
            // Both axes ship, because the commonest overflow affordance — a row's
            // action menu — is drawn vertically. One word without the other means
            // the next application writes the family's glyph name again.
            'more' => 'lucide-ellipsis', // Not `more-horizontal`, which upstream deprecated.
            // The pop-up button's marker: a stacked pair of chevrons, one up and one down.
            // It says "this shows the current choice, and there are others" — the distinction
            // from a single downward chevron, which says "this opens a list of actions". A
            // scope switcher in a breadcrumb is the first thing here to need it, and every
            // set draws it: Heroicons and Lucide as chevrons, Phosphor and Tabler as carets.
            // Same shape, same meaning, so the word is honest in all four.
            'chevron-up-down' => 'lucide-chevrons-up-down',
            'more-vertical' => 'lucide-ellipsis-vertical',
            'arrows-left-right' => 'lucide-arrow-left-right',
            'hash' => 'lucide-hash',
            'shield-warning' => 'lucide-shield-alert',
            'prohibit' => 'lucide-ban',
            'scan' => 'lucide-scan',

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
            'envelope' => 'lucide-mail',
            'store' => 'lucide-store',
            'cart' => 'lucide-shopping-cart',
            'receipt' => 'lucide-receipt',
            'truck' => 'lucide-truck',
            'package' => 'lucide-package',
            'barcode' => 'lucide-barcode',
            // Lucide ships no register or till glyph either — same measurement, same
            // substitution, and the same reason it is better than leaving the name off
            // the contract. See the note in the heroicons preset.
            'cash-register' => 'lucide-calculator',
            'user-add' => 'lucide-user-plus',
            'user-remove' => 'lucide-user-minus',
            'building' => 'lucide-building',
            'webhook' => 'lucide-webhook',
            'arrow-left' => 'lucide-arrow-left',
            'clock' => 'lucide-clock',
            'lock' => 'lucide-lock',
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
