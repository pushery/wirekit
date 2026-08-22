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
            // The plain glass, and it went the other way once. Heroicons Mini draws it as a
            // 1.5-unit annulus in a 20-unit box, which measures 17.72% ink against 27–46% for
            // the solid masses beside it in a rail — so it was moved to the CIRCLE variant at
            // 42.70%, inside that band. That was a correct measurement of the wrong property:
            // ink coverage says how much of the box is filled, not what kind of mark it is,
            // and in the navigation row — close, menu, chevrons, check — every glyph is a thin
            // outline. A filled disc there is a different species, not a heavier weight.
            //
            // The band fits the rail and breaks the row, one alias cannot satisfy both, and
            // the maintainer chose the plain glass. `IconSystemTest` holds that as a decision
            // rather than as a threshold, so a future measurement does not quietly overturn it.
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
            'more' => 'heroicon-m-ellipsis-horizontal',
            // The pop-up button's marker: a stacked pair of chevrons, one up and one down.
            // It says "this shows the current choice, and there are others" — the distinction
            // from a single downward chevron, which says "this opens a list of actions". A
            // scope switcher in a breadcrumb is the first thing here to need it, and every
            // set draws it: Heroicons and Lucide as chevrons, Phosphor and Tabler as carets.
            // Same shape, same meaning, so the word is honest in all four.
            'chevron-up-down' => 'heroicon-m-chevron-up-down',
            'more-vertical' => 'heroicon-m-ellipsis-vertical',
            'arrows-left-right' => 'heroicon-m-arrows-right-left',
            'hash' => 'heroicon-m-hashtag',
            'shield-warning' => 'heroicon-m-shield-exclamation',
            'prohibit' => 'heroicon-m-no-symbol', // Heroicons names the concept after the sign, not the act.
            'scan' => 'heroicon-m-viewfinder-circle', // The nearest true cognate: a framing reticle.

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
