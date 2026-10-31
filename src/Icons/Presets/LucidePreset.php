<?php

declare(strict_types=1);

namespace Pushery\WireKit\Icons\Presets;

use Pushery\WireKit\Contracts\IconPreset;

/**
 * Lucide preset — outline-only style.
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

            // The common dashboard icons.
            'home' => 'lucide-home',
            'moon' => 'lucide-moon',
            'sun' => 'lucide-sun',

            // `system` is the third theme state beside `sun` and `moon`, so a theme
            // control can name all three of its positions. No icon family spells the
            // concept the same way (computer-desktop / monitor / device-desktop), so the
            // WORD is ours and the glyph is each set's own — which is what these aliases
            // are for. Every target below exists in its set.
            //
            // `article` is deliberately absent: two of the sets would need a substitute
            // glyph for it, and "editorial unit" is an arguable concept to begin with.
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

            // The text-formatting marks a composer toolbar is built from, explained in
            // `HeroiconsPreset`. `list-numbers` is the shared word; Lucide draws it as
            // `list-ordered`, and the word follows the spelling `list-bullets` sets.
            'bold' => 'lucide-bold',
            'italic' => 'lucide-italic',
            'strikethrough' => 'lucide-strikethrough',
            'underline' => 'lucide-underline',
            'list-numbers' => 'lucide-list-ordered',
            'lock-key' => 'lucide-lock-keyhole',
            'map-pin' => 'lucide-map-pin',
            'percent' => 'lucide-percent',
            'sliders' => 'lucide-sliders',
            'trend-up' => 'lucide-trending-up',
            'chart-line' => 'lucide-chart-line',
            'folders' => 'lucide-folders',

            // A telephone, for a call control ("Voice call", "Call"). Without a word for
            // it a page borrows one such as `pulse`, which does not resolve on the
            // default config at all, and the missing word turns into a broken page.
            'phone' => 'lucide-phone',
            'book-open' => 'lucide-book-open',
            'sign-out' => 'lucide-log-out',
            'megaphone' => 'lucide-megaphone',
            'map' => 'lucide-map',
            'file-text' => 'lucide-file-text',

            // Common semantic aliases — shared keyset with every base preset so they
            // resolve without stacking an extension.
            'copy' => 'lucide-copy',
            'globe' => 'lucide-globe',
            'book' => 'lucide-book-open',
            'lightbulb' => 'lucide-lightbulb',

            // SaaS app aliases — shared keyset with every base preset.
            'settings' => 'lucide-settings',
            'gear' => 'lucide-settings',
            'dashboard' => 'lucide-layout-dashboard',
            // A page's arrangement: a frame with a header and a side column. `layout` draws the
            // same glyph under the name Lucide retired.
            'layout' => 'lucide-panels-top-left',
            'billing' => 'lucide-credit-card',
            'credit-card' => 'lucide-credit-card',

            // Infrastructure & system — mapped to Lucide's icon names.
            'server' => 'lucide-server',
            'database' => 'lucide-database',
            'cloud' => 'lucide-cloud',
            'desktop' => 'lucide-monitor',
            'shield' => 'lucide-shield-check', // parity with the base `shield` alias
            'shield-check' => 'lucide-shield-check',
            'inbox' => 'lucide-inbox',
            'bolt' => 'lucide-zap', // lucide calls the bolt glyph `zap`
            'refresh' => 'lucide-refresh-cw', // lucide's rotate glyph
            // Undo — the counter-clockwise arrow every set ships under a different name.
            // Named for the CONCEPT, not for one family's spelling: Phosphor's word for it,
            // `arrow-counter-clockwise`, would make the vocabulary read differently
            // depending on which set a project loads. Lucide `undo`, tabler
            // `arrow-back-up`, phosphor `arrow-counter-clockwise` and heroicons
            // `arrow-uturn-left` are four real undo glyphs, not four things that look
            // roughly alike.
            'undo' => 'lucide-undo',
            // Media controls.
            'play' => 'lucide-play',
            'pause' => 'lucide-pause',
            'stop' => 'lucide-square', // lucide's stop glyph is a filled square
            'speaker' => 'lucide-volume-2',
            'mute' => 'lucide-volume-x',
            'microphone' => 'lucide-mic',
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
            'users' => 'lucide-users',
            'history' => 'lucide-history',
            'legal' => 'lucide-scale',
            'badge' => 'lucide-badge-check',
            'layers' => 'lucide-layers',

            // `stack` is the concept `layers` already names, shipped as its own
            // word because that is the word applications write. Undeclared, the
            // name would not fail cleanly either: Phosphor calls its own glyph
            // `stack`, so the raw-name fallthrough would answer it under that one
            // family and throw under the rest, which reads as "the icon broke when
            // we changed preset" rather than as a word nobody promised. Two
            // spellings on one glyph is what this vocabulary also does for
            // settings/gear, book/book-open and billing/credit-card.
            'stack' => 'lucide-layers',

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
            'more' => 'lucide-ellipsis', // Not `more-horizontal`, which upstream deprecated.
            // The pop-up button's marker: a stacked pair of chevrons, one up and one down.
            // It says "this shows the current choice, and there are others" — the distinction
            // from a single downward chevron, which says "this opens a list of actions". A
            // scope switcher in a breadcrumb is one control that needs it, and every
            // set draws it: Heroicons and Lucide as chevrons, Phosphor and Tabler as carets.
            // Same shape, same meaning, so the word is honest in all four.
            'chevron-up-down' => 'lucide-chevrons-up-down',
            'more-vertical' => 'lucide-ellipsis-vertical',
            'arrows-left-right' => 'lucide-arrow-left-right',
            // Every part of a page opened or closed at once: arrows leaving the fold line, and
            // arrows meeting it.
            'expand-all' => 'lucide-unfold-vertical',
            'collapse-all' => 'lucide-fold-vertical',
            'hash' => 'lucide-hash',
            'shield-warning' => 'lucide-shield-alert',
            'prohibit' => 'lucide-ban',
            'scan' => 'lucide-scan',

            // Notification, labeling, mail and media — concepts every
            // administrative interface has. Without a word here a page reaches for
            // the icon package's own glyph name, which resolves only while that
            // optional package happens to be installed: a dependency on the icon
            // set rather than a contract with this library.
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
            // Lucide ships no register or till glyph either: the same substitution, for
            // the same reason it is better than leaving the name off the contract. See
            // the note in the heroicons preset.
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
            //   merge, pin — Heroicons has no genuine cognate. Nothing in it draws two
            //     paths becoming one, and its only pin is `map-pin`, a place marker.
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

            'arrow-down' => 'lucide-arrow-down',
            'arrow-right' => 'lucide-arrow-right',
            'arrow-up' => 'lucide-arrow-up',
            'arrow-up-right' => 'lucide-arrow-up-right',
            'chart-pie' => 'lucide-chart-pie',
            // The CONCEPT name for what the marketing extension spells
            // `cursor-arrow-rays`, heroicons' name for its own picture rather than a word
            // anybody would reach for; that spelling stays in the extension. All four
            // sets draw a pointer with an action mark.
            'click' => 'lucide-mouse-pointer-click',
            'code-bracket' => 'lucide-code',
            'cog-6-tooth' => 'lucide-settings',
            'cube' => 'lucide-box',
            'sparkles' => 'lucide-sparkles',
            'command-line' => 'lucide-terminal',
            'finger-print' => 'lucide-fingerprint',
            'fire' => 'lucide-flame',
            'heart' => 'lucide-heart',
            // Lucide draws every glyph in outline and has no filled heart or star, so the filled and
            // the outline name draw one glyph here.
            'heart-outline' => 'lucide-heart',
            'link' => 'lucide-link',
            'attach' => 'lucide-paperclip',
            'live' => 'lucide-radio',
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
            'video' => 'lucide-video',
            'emoji' => 'lucide-smile',
            'lock-closed' => 'lucide-lock',
            'open-source' => 'lucide-git-branch',
            // Every set ships a real brush; tabler draws it as `brush`, and the word names
            // the concept rather than one family's spelling.
            'paint-brush' => 'lucide-paintbrush',
            'puzzle-piece' => 'lucide-puzzle',
            'security' => 'lucide-shield',
            'speed' => 'lucide-gauge',
            // `lucide-grid` carries two dividers on each axis — a 3x3 of nine cells, against
            // the four every other preset gives for this word. `grid-2x2` is one divider per
            // axis, which is what the alias is named for.
            'squares-2x2' => 'lucide-grid-2x2',
            'star' => 'lucide-star',
            'star-outline' => 'lucide-star',
            'swatch' => 'lucide-palette',
            'unlock' => 'lucide-unlock',
            'user-group' => 'lucide-users',
            'x-circle' => 'lucide-x-circle',
        ];
    }

    public function requires(): string
    {
        return 'mallardduck/blade-lucide-icons';
    }
}
