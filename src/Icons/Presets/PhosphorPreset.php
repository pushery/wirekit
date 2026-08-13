<?php

declare(strict_types=1);

namespace Pushery\WireKit\Icons\Presets;

use Pushery\WireKit\Contracts\IconPreset;

/**
 * Phosphor preset — regular weight, 1500+ icons in 6 styles.
 *
 * Uses the regular weight (no suffix) for all aliases.
 *
 * @see https://phosphoricons.com
 */
final class PhosphorPreset implements IconPreset
{
    public function icons(): array
    {
        return [
            // Navigation & Actions
            'close' => 'phosphor-x',
            'menu' => 'phosphor-list',
            'search' => 'phosphor-magnifying-glass',
            'chevron-down' => 'phosphor-caret-down',
            'chevron-up' => 'phosphor-caret-up',
            'chevron-left' => 'phosphor-caret-left',
            'chevron-right' => 'phosphor-caret-right',
            'check' => 'phosphor-check',
            'plus' => 'phosphor-plus',
            'minus' => 'phosphor-minus',

            // Status & Feedback
            'info' => 'phosphor-info',
            'success' => 'phosphor-check-circle',
            'warning' => 'phosphor-warning',
            'danger' => 'phosphor-x-circle',

            // Objects & Visibility
            'user' => 'phosphor-user',
            'calendar' => 'phosphor-calendar-blank',
            'trash' => 'phosphor-trash',
            'edit' => 'phosphor-pencil-simple',
            'eye' => 'phosphor-eye',
            'eye-off' => 'phosphor-eye-slash',
            'upload' => 'phosphor-upload-simple',
            'download' => 'phosphor-download-simple',
            'sort-asc' => 'phosphor-sort-ascending',
            'sort-desc' => 'phosphor-sort-descending',
            'filter' => 'phosphor-funnel',
            'external-link' => 'phosphor-arrow-square-out',

            // common dashboard icons.
            'home' => 'phosphor-house',
            'moon' => 'phosphor-moon',
            'sun' => 'phosphor-sun',

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
            'system' => 'phosphor-monitor',
            'code' => 'phosphor-code',
            'key' => 'phosphor-key',
            'rocket-launch' => 'phosphor-rocket-launch',
            'chart-line' => 'phosphor-chart-line',
            'folders' => 'phosphor-folders',

            // A seventh, and it came from the docs rather than from a report: five
            // blueprint pages reached for `pulse` and two of them meant a telephone
            // ("Voice call", "Call"). The vocabulary had no word for one, so the
            // pages borrowed a name that does not resolve on the default config at
            // all — which is how a missing word turns into a broken page.
            'phone' => 'phosphor-phone',
            'book-open' => 'phosphor-book-open',
            'sign-out' => 'phosphor-sign-out',
            'megaphone' => 'phosphor-megaphone',
            'map' => 'phosphor-map-trifold',
            'file-text' => 'phosphor-file-text',

            // Common semantic aliases (v2.6.4) — shared keyset with every base
            // preset so they resolve without stacking; `live` stays marketing.
            'copy' => 'phosphor-copy',
            'globe' => 'phosphor-globe',
            'book' => 'phosphor-book-open',
            'lightbulb' => 'phosphor-lightbulb',

            // SaaS app aliases (v2.9.0) — shared keyset with every base preset.
            // Phosphor names: gear (settings) + squares-four (dashboard).
            'settings' => 'phosphor-gear',
            'gear' => 'phosphor-gear',
            'dashboard' => 'phosphor-squares-four',
            'billing' => 'phosphor-credit-card',
            'credit-card' => 'phosphor-credit-card',

            // Infrastructure & system — mapped to Phosphor's icon names.
            // `hard-drives` (plural), because Phosphor has no `server` at all —
            // stacked drives is its rack metaphor. The alias pointed at
            // `phosphor-server` and rendered nothing: Blade Icons throws on an
            // unknown name, so a page using it broke rather than degraded.
            //
            // It survived because this preset's targets were UNCHECKABLE: the
            // Phosphor package sat in `suggest`, so no test could resolve a single
            // one of its 57 aliases. Heroicons was in require-dev and had no such
            // defect. That asymmetry is the actual cause, and it is fixed alongside
            // this line — see IconPresetTargetTest.
            'server' => 'phosphor-hard-drives',
            'database' => 'phosphor-database',
            'cloud' => 'phosphor-cloud',
            'shield' => 'phosphor-shield-check', // parity with the base `shield` alias
            'shield-check' => 'phosphor-shield-check',
            'inbox' => 'phosphor-tray', // phosphor calls the inbox glyph `tray`
            'bolt' => 'phosphor-lightning', // phosphor calls it `lightning`
            'refresh' => 'phosphor-arrows-clockwise', // phosphor's rotate glyph
            // Media controls.
            'play' => 'phosphor-play',
            'pause' => 'phosphor-pause',
            'stop' => 'phosphor-stop',
            'speaker' => 'phosphor-speaker-high',
            'mute' => 'phosphor-speaker-slash',
            'microphone' => 'phosphor-microphone',
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
            'users' => 'phosphor-users',
            'history' => 'phosphor-clock-counter-clockwise',
            'legal' => 'phosphor-scales',
            'badge' => 'phosphor-identification-badge',
            'layers' => 'phosphor-stack',

            // `stack` is the concept `layers` already names, shipped as its own
            // word because that is the word applications write — the navigation
            // above reached for `stack` and got a synonym it does not use.
            // Undeclared, the name did not fail cleanly either: Phosphor calls its
            // own glyph `stack`, so the raw-name fallthrough answered it under that
            // one family and threw under the rest, which reads as "the icon broke
            // when we changed preset" rather than as a word nobody promised. Two
            // spellings on one glyph is what this vocabulary already does for
            // settings/gear, book/book-open and billing/credit-card.
            'stack' => 'phosphor-stack',

            // Notification, labeling, mail and media — concepts every
            // administrative interface has, and none of them had a word here.
            // A page that needed one reached for the icon package's own glyph
            // name, which resolves only while that optional package happens to
            // be installed: a dependency on the icon set rather than a contract
            // with this library. `bell` and `bell-slash` move here from the
            // app extension — same glyph, so nothing rendered changes.
            'bell' => 'phosphor-bell',
            'bell-slash' => 'phosphor-bell-slash',
            'tag' => 'phosphor-tag',
            'send' => 'phosphor-paper-plane-tilt',
            'envelope' => 'phosphor-envelope',
            'store' => 'phosphor-storefront',
            'cart' => 'phosphor-shopping-cart',
            'receipt' => 'phosphor-receipt',
            'truck' => 'phosphor-truck',
            'package' => 'phosphor-package',
            'barcode' => 'phosphor-barcode',
            // Phosphor is the one family that draws the thing itself.
            'cash-register' => 'phosphor-cash-register',
            'user-add' => 'phosphor-user-plus',
            'user-remove' => 'phosphor-user-minus',
            'building' => 'phosphor-building',
            'webhook' => 'phosphor-webhooks-logo',
            'arrow-left' => 'phosphor-arrow-left',
            'clock' => 'phosphor-clock',
            'lock' => 'phosphor-lock',
            'archive' => 'phosphor-archive',
            'image' => 'phosphor-image',
            'message' => 'phosphor-chat-circle',
            'reply' => 'phosphor-arrow-bend-up-left',
            'forward' => 'phosphor-arrow-bend-up-right',
        ];
    }

    public function requires(): string
    {
        return 'codeat3/blade-phosphor-icons';
    }
}
