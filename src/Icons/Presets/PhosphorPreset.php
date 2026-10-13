<?php

declare(strict_types=1);

namespace Pushery\WireKit\Icons\Presets;

use Pushery\WireKit\Contracts\IconPreset;

/**
 * Phosphor preset — regular weight, one of the six styles the set ships.
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

            // The common dashboard icons.
            'home' => 'phosphor-house',
            'moon' => 'phosphor-moon',
            'sun' => 'phosphor-sun',

            // `system` is the third theme state beside `sun` and `moon`, so a theme
            // control can name all three of its positions. No icon family spells the
            // concept the same way (computer-desktop / monitor / device-desktop), so the
            // WORD is ours and the glyph is each set's own — which is what these aliases
            // are for. Every target below exists in its set.
            //
            // `article` is deliberately absent: two of the sets would need a substitute
            // glyph for it, and "editorial unit" is an arguable concept to begin with.
            'system' => 'phosphor-monitor',
            'code' => 'phosphor-code',
            'key' => 'phosphor-key',
            'rocket-launch' => 'phosphor-rocket-launch',
            'broadcast' => 'phosphor-broadcast',
            'chart-bar' => 'phosphor-chart-bar',
            'coins' => 'phosphor-coins',
            'gift' => 'phosphor-gift',
            'list-bullets' => 'phosphor-list-bullets',
            'list-checks' => 'phosphor-list-checks',

            // The text-formatting marks a composer toolbar is built from, explained in
            // `HeroiconsPreset`. Phosphor spells the first four `text-*`, and its bold
            // mark is `text-b`, the bold letterform rather than a heavier stroke weight.
            'bold' => 'phosphor-text-b',
            'italic' => 'phosphor-text-italic',
            'strikethrough' => 'phosphor-text-strikethrough',
            'underline' => 'phosphor-text-underline',
            'list-numbers' => 'phosphor-list-numbers',
            'lock-key' => 'phosphor-lock-key',
            'map-pin' => 'phosphor-map-pin',
            'percent' => 'phosphor-percent',
            'sliders' => 'phosphor-sliders',
            'trend-up' => 'phosphor-trend-up',
            'chart-line' => 'phosphor-chart-line',
            'folders' => 'phosphor-folders',

            // A telephone, for a call control ("Voice call", "Call"). Without a word for
            // it a page borrows one such as `pulse`, which does not resolve on the
            // default config at all, and the missing word turns into a broken page.
            'phone' => 'phosphor-phone',
            'book-open' => 'phosphor-book-open',
            'sign-out' => 'phosphor-sign-out',
            'megaphone' => 'phosphor-megaphone',
            'map' => 'phosphor-map-trifold',
            'file-text' => 'phosphor-file-text',

            // Common semantic aliases — shared keyset with every base preset so they
            // resolve without stacking an extension.
            'copy' => 'phosphor-copy',
            'globe' => 'phosphor-globe',
            'book' => 'phosphor-book-open',
            'lightbulb' => 'phosphor-lightbulb',

            // SaaS app aliases — shared keyset with every base preset.
            // Phosphor names: gear (settings) + squares-four (dashboard).
            'settings' => 'phosphor-gear',
            'gear' => 'phosphor-gear',
            'dashboard' => 'phosphor-squares-four',
            'billing' => 'phosphor-credit-card',
            'credit-card' => 'phosphor-credit-card',

            // Infrastructure & system — mapped to Phosphor's icon names.
            // `hard-drives` (plural), because Phosphor has no `server` at all —
            // stacked drives is its rack metaphor. Blade Icons throws on an unknown
            // name, so a target the set does not ship breaks the page rather than
            // degrading it.
            'server' => 'phosphor-hard-drives',
            'database' => 'phosphor-database',
            'cloud' => 'phosphor-cloud',
            'desktop' => 'phosphor-monitor', // the glyph `system` uses, as in the other three presets
            'shield' => 'phosphor-shield-check', // parity with the base `shield` alias
            'shield-check' => 'phosphor-shield-check',
            'inbox' => 'phosphor-tray', // phosphor calls the inbox glyph `tray`
            'bolt' => 'phosphor-lightning', // phosphor calls it `lightning`
            'refresh' => 'phosphor-arrows-clockwise', // phosphor's rotate glyph
            // Undo — the counter-clockwise arrow every set ships under a different name.
            // Named for the CONCEPT, not for one family's spelling: Phosphor's word for it,
            // `arrow-counter-clockwise`, would make the vocabulary read differently
            // depending on which set a project loads. Lucide `undo`, tabler
            // `arrow-back-up`, phosphor `arrow-counter-clockwise` and heroicons
            // `arrow-uturn-left` are four real undo glyphs, not four things that look
            // roughly alike.
            // Deliberately absent from this vocabulary:
            //   arrow-counter-clockwise — the TARGET of the alias below. As a word it would
            //     make Phosphor's own spelling the contract, which is why `more` is not
            //     `dots-three`: name the CONCEPT, not one family's glyph name.
            //   users-three — Phosphor's spelling for `users`, which all four presets carry.
            //   warning-diamond, plugs-connected, user-switch — Heroicons has no genuine
            //     cognate for any of them. An alias that resolves in three sets and
            //     substitutes something merely similar in the fourth is worse than no
            //     alias: it looks like a contract until somebody switches preset, and then
            //     the iconography changes silently.
            'undo' => 'phosphor-arrow-counter-clockwise',
            // Media controls.
            'play' => 'phosphor-play',
            'pause' => 'phosphor-pause',
            'stop' => 'phosphor-stop',
            'speaker' => 'phosphor-speaker-high',
            'mute' => 'phosphor-speaker-slash',
            'microphone' => 'phosphor-microphone',
            // ── Admin navigation ────────────────────────────────────────────
            // Five concepts every administrative interface has. Without a word for each,
            // a navigation reaches for the icon package's OWN glyph names — `users`,
            // `stack`, `scales`, `clock-counter-clockwise`.
            //
            // Those raw names work, and that is the problem: they resolve because
            // the full package happens to be installed, which is a dependency on
            // the icon set rather than a contract with WireKit, and an application
            // that ships only the icons it renders loses them.
            //
            // `users` is a separate word from `user` on purpose: managing accounts
            // is a different menu item from your own profile, and the two need not
            // share one glyph.
            'users' => 'phosphor-users',
            'history' => 'phosphor-clock-counter-clockwise',
            'legal' => 'phosphor-scales',
            'badge' => 'phosphor-identification-badge',
            'layers' => 'phosphor-stack',

            // `stack` is the concept `layers` already names, shipped as its own
            // word because that is the word applications write. Undeclared, the
            // name would not fail cleanly either: Phosphor calls its own glyph
            // `stack`, so the raw-name fallthrough would answer it under that one
            // family and throw under the rest, which reads as "the icon broke when
            // we changed preset" rather than as a word nobody promised. Two
            // spellings on one glyph is what this vocabulary also does for
            // settings/gear, book/book-open and billing/credit-card.
            'stack' => 'phosphor-stack',

            // ─── The overflow affordance, and the other words an application
            // reaches for beside it.
            //
            // Every one of these is a true cognate in all four interchangeable
            // presets. That is the whole admission test: a word enters this
            // vocabulary only when every set we ship can answer it with a glyph
            // that means the same thing. An alias that resolves in three sets and
            // substitutes something else in the fourth is worse than no alias,
            // because it looks like a contract right up until somebody changes
            // preset.
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
            'more' => 'phosphor-dots-three',
            // The pop-up button's marker: a stacked pair of chevrons, one up and one down.
            // It says "this shows the current choice, and there are others" — the distinction
            // from a single downward chevron, which says "this opens a list of actions". A
            // scope switcher in a breadcrumb is one control that needs it, and every
            // set draws it: Heroicons and Lucide as chevrons, Phosphor and Tabler as carets.
            // Same shape, same meaning, so the word is honest in all four.
            'chevron-up-down' => 'phosphor-caret-up-down',
            'more-vertical' => 'phosphor-dots-three-vertical',
            'arrows-left-right' => 'phosphor-arrows-left-right',
            'hash' => 'phosphor-hash',
            'shield-warning' => 'phosphor-shield-warning',
            'prohibit' => 'phosphor-prohibit',
            'scan' => 'phosphor-scan',

            // Notification, labeling, mail and media — concepts every
            // administrative interface has. Without a word here a page reaches for
            // the icon package's own glyph name, which resolves only while that
            // optional package happens to be installed: a dependency on the icon
            // set rather than a contract with this library.
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
            // Phosphor draws the thing itself, as Tabler does.
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
            // ---------------------------------------------------------------
            // Words all four base presets carry.
            // ---------------------------------------------------------------
            // They live here rather than in `heroicons-app` / `heroicons-marketing`,
            // which emit heroicon identifiers exclusively: there a lucide, phosphor or
            // tabler install would resolve the alias onto a glyph its set does not
            // ship, and blade-icons throws rather than degrading.
            //
            // Each target is a glyph its set ships under exactly that name. What is
            // taken here is the CONCEPT; the target is each family's own picture of it,
            // which is why the right-hand sides differ.
            //
            // Deliberately NOT taken:
            //   a11y — no genuine cognate in all three.
            //   cursor-arrow-rays — the SPELLING stays out; the concept is `click`
            //     below. A family's name for its own drawing is not a word the
            //     vocabulary can share; the concept behind it is.
            //   ai, sparkle, cube-transparent — a spelling variant of a word that is
            //     already here, not a second concept. `sparkle` beside `sparkles`, or
            //     `cube-transparent` beside `cube`, gives the vocabulary two ways to ask
            //     for one picture, and a reader has no way to guess which.
            //
            // Sharing a glyph is ordinary here, and several targets in each base preset
            // carry more than one alias on purpose. `danger` and `x-circle` are the same
            // picture; so are `settings`, `gear` and `cog-6-tooth`. Two words for two
            // intents that happen to look alike is the pattern. Two spellings of one word
            // is not, and that is the line above.

            'arrow-down' => 'phosphor-arrow-down',
            'arrow-right' => 'phosphor-arrow-right',
            'arrow-up' => 'phosphor-arrow-up',
            'arrow-up-right' => 'phosphor-arrow-up-right',
            'chart-pie' => 'phosphor-chart-pie',
            // The CONCEPT name for what the marketing extension spells
            // `cursor-arrow-rays`, heroicons' name for its own picture rather than a word
            // anybody would reach for; that spelling stays in the extension. All four
            // sets draw a pointer with an action mark.
            'click' => 'phosphor-cursor-click',
            'code-bracket' => 'phosphor-code',
            'cog-6-tooth' => 'phosphor-gear',
            'cube' => 'phosphor-cube',
            // Phosphor's only sparkle glyph is spelled singular; it is the genuine cognate.
            'sparkles' => 'phosphor-sparkle',
            'command-line' => 'phosphor-terminal',
            'finger-print' => 'phosphor-fingerprint',
            'fire' => 'phosphor-fire',
            'heart' => 'phosphor-heart',
            'link' => 'phosphor-link',
            'attach' => 'phosphor-paperclip',
            'live' => 'phosphor-radio',
            /*
             * `video` and `emoji` name what a video-call button and an emoji picker show.
             * Without them such a control reaches for the nearest alias, and a "Video call"
             * label over a bar chart contradicts its own picture. That is worse than a plain
             * icon: a sighted reader trusts the picture and a screen-reader user is told the
             * other thing.
             *
             * Taken only because ALL FOUR interchangeable presets ship a genuine cognate:
             * heroicons `video-camera` / `face-smile`, lucide `video` / `smile`, phosphor
             * `video-camera` / `smiley`, tabler `video` / `mood-smile`.
             */
            'video' => 'phosphor-video-camera',
            'emoji' => 'phosphor-smiley',
            'lock-closed' => 'phosphor-lock',
            'open-source' => 'phosphor-git-branch',
            // Every set ships a real brush; tabler draws it as `brush`, and the word names
            // the concept rather than one family's spelling.
            'paint-brush' => 'phosphor-paint-brush',
            'puzzle-piece' => 'phosphor-puzzle-piece',
            'security' => 'phosphor-shield',
            'speed' => 'phosphor-gauge',
            'squares-2x2' => 'phosphor-squares-four',
            'star' => 'phosphor-star',
            'star-outline' => 'phosphor-star',
            'swatch' => 'phosphor-swatches',
            'unlock' => 'phosphor-lock-open',
            'user-group' => 'phosphor-users',
            'x-circle' => 'phosphor-x-circle',
        ];
    }

    public function requires(): string
    {
        return 'codeat3/blade-phosphor-icons';
    }
}
