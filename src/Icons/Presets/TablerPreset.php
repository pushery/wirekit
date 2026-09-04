<?php

declare(strict_types=1);

namespace Pushery\WireKit\Icons\Presets;

use Pushery\WireKit\Contracts\IconPreset;

/**
 * Tabler preset — outline style.
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

            // The common dashboard icons.
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

            // The text-formatting marks a composer toolbar is built from — see
            // `HeroiconsPreset` for why the group was closed and how the targets were
            // verified. Tabler names all five the way the concepts are named here.
            'bold' => 'tabler-bold',
            'italic' => 'tabler-italic',
            'strikethrough' => 'tabler-strikethrough',
            'underline' => 'tabler-underline',
            'list-numbers' => 'tabler-list-numbers',
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
            // Undo — the counter-clockwise arrow every set ships under a different name.
            // Named for the CONCEPT, not for one family's spelling: the proposal reached us
            // as `arrow-counter-clockwise`, which is Phosphor's word for it and would have
            // made the vocabulary read differently depending on which set a project loads.
            // Verified against the SVG files: lucide `undo`, tabler `arrow-back-up`,
            // phosphor `arrow-counter-clockwise`, heroicons `arrow-uturn-left` — four real
            // undo glyphs, not four things that look roughly alike.
            'undo' => 'tabler-arrow-back-up', // tabler's undo glyph
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

            // ─── The overflow affordance, and five words an adopting application
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
            'more' => 'tabler-dots',
            // The pop-up button's marker: a stacked pair of chevrons, one up and one down.
            // It says "this shows the current choice, and there are others" — the distinction
            // from a single downward chevron, which says "this opens a list of actions". A
            // scope switcher in a breadcrumb is the first thing here to need it, and every
            // set draws it: Heroicons and Lucide as chevrons, Phosphor and Tabler as carets.
            // Same shape, same meaning, so the word is honest in all four.
            'chevron-up-down' => 'tabler-caret-up-down',
            'more-vertical' => 'tabler-dots-vertical',
            'arrows-left-right' => 'tabler-arrows-left-right',
            'hash' => 'tabler-hash',
            'shield-warning' => 'tabler-shield-exclamation',
            'prohibit' => 'tabler-ban',
            'scan' => 'tabler-scan',

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
            // ---------------------------------------------------------------
            // Promoted from the heroicons extension presets on 2026-08-26.
            // ---------------------------------------------------------------
            // These words lived only in `heroicons-app` / `heroicons-marketing`, which
            // emit heroicon identifiers exclusively. A lucide, phosphor or tabler
            // install therefore could not reach them at all — stacking the extensions
            // resolved the alias onto a glyph that set does not ship, and blade-icons
            // threw rather than degrading.
            //
            // Each one was checked against the real SVG trees in vendor/, per set, per
            // name — not against a website and not by assuming a spelling. What is
            // taken here is the CONCEPT; the target is each family's own picture of it,
            // which is why the right-hand sides differ.
            //
            // Deliberately NOT taken, and the reasons are worth keeping:
            //   a11y — no genuine cognate in all three.
            //   cursor-arrow-rays — the SPELLING stays out, and the concept came in as
            //     `click` below. A family's name for its own drawing is not a word the
            //     vocabulary can share; the concept behind it is.
            //   ai, sparkle, cube-transparent — a spelling variant of a word that is
            //     already here, not a second concept. `sparkle` beside `sparkles`, or
            //     `cube-transparent` beside `cube`, gives the vocabulary two ways to ask
            //     for one picture, and a reader has no way to guess which.
            //
            // ⚠️ This list named `sparkles` and `cube` as well, and both are declared
            // below it — in this file and in the three sibling presets, which carried
            // the same paragraph word for word. The reason it gave was that a cognate
            // existed but was ALREADY another alias's target, and that is not the rule
            // this vocabulary follows. Sharing a glyph is ordinary here: measured across
            // the four base presets, 10 to 14 targets each carry more than one alias, on
            // purpose. `danger` and `x-circle` are the same picture; so are `settings`,
            // `gear` and `cog-6-tooth`. Two words for two INTENTS that happen to look
            // alike is the pattern. Two spellings of one word is not, and that is the
            // line above. (It also cited lucide's inventory as the evidence, in all four
            // files — including the two that are not about lucide.)

            'arrow-down' => 'tabler-arrow-down',
            'arrow-right' => 'tabler-arrow-right',
            'arrow-up' => 'tabler-arrow-up',
            'arrow-up-right' => 'tabler-arrow-up-right',
            'chart-pie' => 'tabler-chart-pie',
            // Promoted out of the marketing extension under the CONCEPT name. The
            // extension spells it `cursor-arrow-rays`, which is heroicons' name for its
            // own picture rather than a word anybody would reach for; that spelling stays
            // there as the older name and is not removed. All four sets draw a pointer
            // with an action mark, checked against the svg files in vendor/ per set.
            'click' => 'tabler-click',
            'code-bracket' => 'tabler-code',
            'cog-6-tooth' => 'tabler-settings',
            'cube' => 'tabler-cube',
            'sparkles' => 'tabler-sparkles',
            'command-line' => 'tabler-terminal',
            'finger-print' => 'tabler-fingerprint',
            'fire' => 'tabler-flame',
            'heart' => 'tabler-heart',
            'link' => 'tabler-link',
            'attach' => 'tabler-paperclip',
            'live' => 'tabler-radio',
            'lock-closed' => 'tabler-lock',
            'open-source' => 'tabler-git-branch',
            // Promoted out of the marketing extension: every set ships a real brush.
            // The paragraph above listed this word as having no cognate in all three,
            // which the svg trees refute — tabler draws it as `brush`, and taking the
            // concept rather than one family's spelling is exactly the rule here.
            'paint-brush' => 'tabler-brush',
            'puzzle-piece' => 'tabler-puzzle',
            'security' => 'tabler-shield',
            'speed' => 'tabler-gauge',
            'squares-2x2' => 'tabler-layout-grid',
            'star' => 'tabler-star',
            'swatch' => 'tabler-palette',
            'unlock' => 'tabler-lock-open',
            'user-group' => 'tabler-users',
            'x-circle' => 'tabler-circle-x',
        ];
    }

    public function requires(): string
    {
        return 'secondnetwork/blade-tabler-icons';
    }
}
