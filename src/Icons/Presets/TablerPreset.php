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

            // `system` is the third theme state beside `sun` and `moon`, so a theme
            // control can name all three of its positions. No icon family spells the
            // concept the same way (computer-desktop / monitor / device-desktop), so the
            // WORD is ours and the glyph is each set's own — which is what these aliases
            // are for. Every target below exists in its set.
            //
            // `article` is deliberately absent: two of the sets would need a substitute
            // glyph for it, and "editorial unit" is an arguable concept to begin with.
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

            // The text-formatting marks a composer toolbar is built from, explained in
            // `HeroiconsPreset`. Tabler names all five the way the concepts are named here.
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

            // A telephone, for a call control ("Voice call", "Call"). Without a word for
            // it a page borrows one such as `pulse`, which does not resolve on the
            // default config at all, and the missing word turns into a broken page.
            'phone' => 'tabler-phone',
            'book-open' => 'tabler-book',
            'sign-out' => 'tabler-logout',
            'megaphone' => 'tabler-speakerphone',
            'map' => 'tabler-map',
            'file-text' => 'tabler-file-text',

            // Common semantic aliases — shared keyset with every base preset so they
            // resolve without stacking an extension.
            // Tabler names: world (globe) + bulb (lightbulb).
            'copy' => 'tabler-copy',
            'globe' => 'tabler-world',
            'book' => 'tabler-book',
            'lightbulb' => 'tabler-bulb',

            // SaaS app aliases — shared keyset with every base preset.
            'settings' => 'tabler-settings',
            'gear' => 'tabler-settings',
            'dashboard' => 'tabler-layout-dashboard',
            'layout' => 'tabler-layout',
            'billing' => 'tabler-credit-card',
            'credit-card' => 'tabler-credit-card',

            // Infrastructure & system — mapped to Tabler's icon names.
            'server' => 'tabler-server',
            'database' => 'tabler-database',
            'cloud' => 'tabler-cloud',
            'desktop' => 'tabler-device-desktop',
            'shield' => 'tabler-shield-check', // parity with the base `shield` alias
            'shield-check' => 'tabler-shield-check',
            'inbox' => 'tabler-inbox',
            'bolt' => 'tabler-bolt',
            'refresh' => 'tabler-refresh',
            // Undo — the counter-clockwise arrow every set ships under a different name.
            // Named for the CONCEPT, not for one family's spelling: Phosphor's word for it,
            // `arrow-counter-clockwise`, would make the vocabulary read differently
            // depending on which set a project loads. Lucide `undo`, tabler
            // `arrow-back-up`, phosphor `arrow-counter-clockwise` and heroicons
            // `arrow-uturn-left` are four real undo glyphs, not four things that look
            // roughly alike.
            'undo' => 'tabler-arrow-back-up', // tabler's undo glyph
            // Media controls.
            'play' => 'tabler-player-play',
            'pause' => 'tabler-player-pause',
            'stop' => 'tabler-player-stop',
            'speaker' => 'tabler-volume',
            'mute' => 'tabler-volume-off',
            'microphone' => 'tabler-microphone',
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
            'users' => 'tabler-users',
            'history' => 'tabler-history',
            'legal' => 'tabler-scale',
            'badge' => 'tabler-badge',
            'layers' => 'tabler-stack-2',

            // `stack` is the concept `layers` already names, shipped as its own
            // word because that is the word applications write. Undeclared, the
            // name would not fail cleanly either: Phosphor calls its own glyph
            // `stack`, so the raw-name fallthrough would answer it under that one
            // family and throw under the rest, which reads as "the icon broke when
            // we changed preset" rather than as a word nobody promised. Two
            // spellings on one glyph is what this vocabulary also does for
            // settings/gear, book/book-open and billing/credit-card.
            //
            // It points at the target `layers` ships, so the two words share one glyph.
            'stack' => 'tabler-stack-2',

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
            'more' => 'tabler-dots',
            // The pop-up button's marker: a stacked pair of chevrons, one up and one down.
            // It says "this shows the current choice, and there are others" — the distinction
            // from a single downward chevron, which says "this opens a list of actions". A
            // scope switcher in a breadcrumb is one control that needs it, and every
            // set draws it: Heroicons and Lucide as chevrons, Phosphor and Tabler as carets.
            // Same shape, same meaning, so the word is honest in all four.
            'chevron-up-down' => 'tabler-caret-up-down',
            'more-vertical' => 'tabler-dots-vertical',
            'arrows-left-right' => 'tabler-arrows-left-right',
            // Every part of a page opened or closed at once. Tabler's fold is arrows meeting a
            // line and it ships no unfold, so expanding takes its arrows leaving a line.
            'expand-all' => 'tabler-separator-horizontal',
            'collapse-all' => 'tabler-fold',
            'hash' => 'tabler-hash',
            'shield-warning' => 'tabler-shield-exclamation',
            'prohibit' => 'tabler-ban',
            'scan' => 'tabler-scan',

            // Notification, labeling, mail and media — concepts every
            // administrative interface has. Without a word here a page reaches for
            // the icon package's own glyph name, which resolves only while that
            // optional package happens to be installed: a dependency on the icon
            // set rather than a contract with this library.
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
            // Tabler draws the thing itself, as Phosphor does:
            // `icons/outline/cash-register.svg`, category E-commerce, in the set since
            // Tabler 3.4. The name follows the same rule as its neighbors: the file
            // name, prefixed.
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

            'arrow-down' => 'tabler-arrow-down',
            'arrow-right' => 'tabler-arrow-right',
            'arrow-up' => 'tabler-arrow-up',
            'arrow-up-right' => 'tabler-arrow-up-right',
            'chart-pie' => 'tabler-chart-pie',
            // The CONCEPT name for what the marketing extension spells
            // `cursor-arrow-rays`, heroicons' name for its own picture rather than a word
            // anybody would reach for; that spelling stays in the extension. All four
            // sets draw a pointer with an action mark.
            'click' => 'tabler-click',
            'code-bracket' => 'tabler-code',
            'cog-6-tooth' => 'tabler-settings',
            'cube' => 'tabler-cube',
            'sparkles' => 'tabler-sparkles',
            'command-line' => 'tabler-terminal',
            'finger-print' => 'tabler-fingerprint',
            'fire' => 'tabler-flame',
            // The heart and the star are filled, as in every set that has both, so the pair with
            // their `-outline` names can show a state: saved and not yet, starred and not yet.
            'heart' => 'tabler-heart-filled',
            'heart-outline' => 'tabler-heart',
            'link' => 'tabler-link',
            'attach' => 'tabler-paperclip',
            'live' => 'tabler-radio',
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
            'video' => 'tabler-video',
            'emoji' => 'tabler-mood-smile',
            'lock-closed' => 'tabler-lock',
            'open-source' => 'tabler-git-branch',
            // Every set ships a real brush; tabler draws it as `brush`, and the word names
            // the concept rather than one family's spelling.
            'paint-brush' => 'tabler-brush',
            'puzzle-piece' => 'tabler-puzzle',
            'security' => 'tabler-shield',
            'speed' => 'tabler-gauge',
            'squares-2x2' => 'tabler-layout-grid',
            'star' => 'tabler-star-filled',
            'star-outline' => 'tabler-star',
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
