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
            // Undo — the counter-clockwise arrow every set ships under a different name.
            // Named for the CONCEPT, not for one family's spelling: the proposal reached us
            // as `arrow-counter-clockwise`, which is Phosphor's word for it and would have
            // made the vocabulary read differently depending on which set a project loads.
            // Verified against the SVG files: lucide `undo`, tabler `arrow-back-up`,
            // phosphor `arrow-counter-clockwise`, heroicons `arrow-uturn-left` — four real
            // undo glyphs, not four things that look roughly alike.
            'undo' => 'heroicon-m-arrow-uturn-left', // heroicons calls it arrow-uturn-left
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

            'arrow-down' => 'heroicon-m-arrow-down',
            'arrow-right' => 'heroicon-m-arrow-right',
            'arrow-up' => 'heroicon-m-arrow-up',
            // NOT `arrow-top-right-on-square` — that is the box-with-escaping-arrow glyph,
            // and `external-link` above already owns it. Pointing both words at it made them
            // render identically here while staying two distinct glyphs on lucide, phosphor
            // and tabler: switching preset silently swapped an external-link mark for a plain
            // diagonal arrow. Heroicons ships the true cognate, so the word means one thing.
            'arrow-up-right' => 'heroicon-m-arrow-up-right',
            'chart-pie' => 'heroicon-m-chart-pie',
            // Promoted out of the marketing extension under the CONCEPT name. The
            // extension spells it `cursor-arrow-rays`, which is heroicons' name for its
            // own picture rather than a word anybody would reach for; that spelling stays
            // there as the older name and is not removed. All four sets draw a pointer
            // with an action mark, checked against the svg files in vendor/ per set.
            'click' => 'heroicon-m-cursor-arrow-rays',
            'code-bracket' => 'heroicon-m-code-bracket',
            'cog-6-tooth' => 'heroicon-m-cog-6-tooth',
            // Promoted out of the marketing extension: every set draws the same isometric
            // box for this concept, so the word means one thing whichever is installed.
            'cube' => 'heroicon-m-cube',
            // Likewise. Note the SINGULAR `sparkle` stays in the extension — phosphor and
            // heroicons each ship only one sparkle glyph, so promoting both words would
            // make them indistinguishable there.
            'sparkles' => 'heroicon-m-sparkles',
            'command-line' => 'heroicon-m-command-line',
            'finger-print' => 'heroicon-m-finger-print',
            'fire' => 'heroicon-m-fire',
            'heart' => 'heroicon-m-heart',
            'link' => 'heroicon-m-link',
            // A paperclip, not a chain link: three blueprint pages were drawing an
            // external-link box on an "Attach file" button because the vocabulary had no
            // word for an attachment. All four sets ship a real paperclip, checked against
            // the svg files rather than against a website.
            'attach' => 'heroicon-m-paper-clip',
            'live' => 'heroicon-m-signal',
            'lock-closed' => 'heroicon-m-lock-closed',
            'open-source' => 'heroicon-m-code-bracket',
            // Promoted out of the marketing extension: every set ships a real brush.
            // The paragraph above listed this word as having no cognate in all three,
            // which the svg trees refute — tabler draws it as `brush`, and taking the
            // concept rather than one family's spelling is exactly the rule here.
            'paint-brush' => 'heroicon-m-paint-brush',
            'puzzle-piece' => 'heroicon-m-puzzle-piece',
            'security' => 'heroicon-m-lock-closed',
            'speed' => 'heroicon-m-bolt',
            'squares-2x2' => 'heroicon-m-squares-2x2',
            'star' => 'heroicon-m-star',
            'swatch' => 'heroicon-m-swatch',
            'unlock' => 'heroicon-m-lock-open',
            'user-group' => 'heroicon-m-user-group',
            'x-circle' => 'heroicon-m-x-circle',
        ];
    }

    public function requires(): string
    {
        return 'blade-ui-kit/blade-heroicons';
    }
}
