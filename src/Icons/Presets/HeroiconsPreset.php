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
            // The plain glass rather than the circle variant. The circle carries more ink and
            // sits closer to the solid glyphs of a rail, but ink coverage says how much of the
            // box is filled, not what kind of mark it is: in the navigation row (close, menu,
            // chevrons, check) every glyph is a thin outline, and a filled disc there would be
            // a different kind of mark rather than a heavier one. One alias cannot fit both,
            // and the row decides: a decision rather than a threshold, so an ink measurement
            // does not overturn it.
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

            // The common dashboard icons
            // every new integrator reaches for on first install:
            'home' => 'heroicon-m-home',
            'moon' => 'heroicon-m-moon',
            'sun' => 'heroicon-m-sun',

            // `system` is the third theme state beside `sun` and `moon`, so a theme
            // control can name all three of its positions. No icon family spells the
            // concept the same way (computer-desktop / monitor / device-desktop), so the
            // WORD is ours and the glyph is each set's own — which is what these aliases
            // are for. Every target below exists in its set.
            //
            // `article` is deliberately absent: two of the sets would need a substitute
            // glyph for it, and "editorial unit" is an arguable concept to begin with.
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

            // The text-formatting marks a composer toolbar is built from. With `code`,
            // `link` and `list-bullets`, part of the same toolbar, these five complete the
            // group, so a formatting bar names its buttons out of this one set; drawing
            // some by hand would put two iconographies in one row of controls.
            //
            // All four families draw a genuine cognate: the bold B, the slanted I, the
            // struck and the underlined letterform, the numbered list. Phosphor spells
            // four of them `text-*`, which is why the right-hand sides differ across the
            // presets — the word is the CONCEPT, the picture is each family's own.
            //
            // `list-numbers` takes that spelling on purpose rather than Lucide's
            // `list-ordered`: `list-bullets` and `list-checks` are already here under it,
            // and a vocabulary offering `list-bullets` beside `list-ordered` leaves the
            // reader guessing which half of the pair it speaks.
            'bold' => 'heroicon-m-bold',
            'italic' => 'heroicon-m-italic',
            'strikethrough' => 'heroicon-m-strikethrough',
            'underline' => 'heroicon-m-underline',
            'list-numbers' => 'heroicon-m-numbered-list',
            'lock-key' => 'heroicon-m-lock-closed',
            'map-pin' => 'heroicon-m-map-pin',
            'percent' => 'heroicon-m-percent-badge',
            'sliders' => 'heroicon-m-adjustments-horizontal',
            'trend-up' => 'heroicon-m-arrow-trending-up',
            'chart-line' => 'heroicon-m-presentation-chart-line',
            'folders' => 'heroicon-m-folder',

            // A telephone, for a call control ("Voice call", "Call"). Without a word for
            // it a page borrows one such as `pulse`, which does not resolve on the
            // default config at all, and the missing word turns into a broken page.
            'phone' => 'heroicon-m-phone',
            'book-open' => 'heroicon-m-book-open',
            'sign-out' => 'heroicon-m-arrow-right-on-rectangle',
            'megaphone' => 'heroicon-m-megaphone',
            'map' => 'heroicon-m-map',
            'file-text' => 'heroicon-m-document-text',

            // Common semantic aliases, carried by EVERY base preset (heroicons/lucide/
            // phosphor/tabler) so they resolve without stacking an extension. `live` is
            // one of the shared words too: every base preset carries it (signal / radio),
            // while the marketing preset carries no `live` entry at all.
            'copy' => 'heroicon-m-clipboard-document',
            'globe' => 'heroicon-m-globe-alt',
            'book' => 'heroicon-m-book-open',
            'lightbulb' => 'heroicon-m-light-bulb',

            // SaaS app aliases — high-frequency dashboard / settings /
            // billing icons every signed-in app reaches for. Shared keyset across
            // every base preset; identifiers follow each upstream library.
            'settings' => 'heroicon-m-cog-6-tooth',
            'gear' => 'heroicon-m-cog-6-tooth',
            'dashboard' => 'heroicon-m-squares-2x2',
            // A page's arrangement, which cards it shows and where. Heroicons has no layout
            // glyph of its own; the group of rectangles is that arrangement.
            'layout' => 'heroicon-m-rectangle-group',
            'billing' => 'heroicon-m-credit-card',
            'credit-card' => 'heroicon-m-credit-card',

            // Infrastructure & system — subprocessor / breach registers, hosting,
            // status dashboards, all drawn from the blade-heroicons Mini set.
            'server' => 'heroicon-m-server',
            'database' => 'heroicon-m-circle-stack', // heroicons has no `database`
            'cloud' => 'heroicon-m-cloud',
            'desktop' => 'heroicon-m-computer-desktop',
            'shield' => 'heroicon-m-shield-check', // heroicons has no plain `shield`
            'shield-check' => 'heroicon-m-shield-check',
            'inbox' => 'heroicon-m-inbox',
            'bolt' => 'heroicon-m-bolt',
            'refresh' => 'heroicon-m-arrow-path', // heroicons calls it arrow-path
            // Undo — the counter-clockwise arrow every set ships under a different name.
            // Named for the CONCEPT, not for one family's spelling: Phosphor's word for it,
            // `arrow-counter-clockwise`, would make the vocabulary read differently
            // depending on which set a project loads. Lucide `undo`, tabler
            // `arrow-back-up`, phosphor `arrow-counter-clockwise` and heroicons
            // `arrow-uturn-left` are four real undo glyphs, not four things that look
            // roughly alike.
            'undo' => 'heroicon-m-arrow-uturn-left', // heroicons calls it arrow-uturn-left
            // Media controls, so audio playback draws its buttons through the icon
            // component rather than with inline SVGs.
            'play' => 'heroicon-m-play',
            'pause' => 'heroicon-m-pause',
            'stop' => 'heroicon-m-stop',
            'speaker' => 'heroicon-m-speaker-wave',
            'mute' => 'heroicon-m-speaker-x-mark',
            'microphone' => 'heroicon-m-microphone',
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
            'users' => 'heroicon-m-users',
            'history' => 'heroicon-m-clock',
            'legal' => 'heroicon-m-scale',
            'badge' => 'heroicon-m-identification',
            'layers' => 'heroicon-m-square-3-stack-3d',

            // `stack` is the concept `layers` already names, shipped as its own
            // word because that is the word applications write. Undeclared, the
            // name would not fail cleanly either: Phosphor calls its own glyph
            // `stack`, so the raw-name fallthrough would answer it under that one
            // family and throw under the rest, which reads as "the icon broke when
            // we changed preset" rather than as a word nobody promised. Two
            // spellings on one glyph is what this vocabulary also does for
            // settings/gear, book/book-open and billing/credit-card.
            'stack' => 'heroicon-m-square-3-stack-3d',

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
            'more' => 'heroicon-m-ellipsis-horizontal',
            // The pop-up button's marker: a stacked pair of chevrons, one up and one down.
            // It says "this shows the current choice, and there are others" — the distinction
            // from a single downward chevron, which says "this opens a list of actions". A
            // scope switcher in a breadcrumb is one control that needs it, and every
            // set draws it: Heroicons and Lucide as chevrons, Phosphor and Tabler as carets.
            // Same shape, same meaning, so the word is honest in all four.
            'chevron-up-down' => 'heroicon-m-chevron-up-down',
            'more-vertical' => 'heroicon-m-ellipsis-vertical',
            'arrows-left-right' => 'heroicon-m-arrows-right-left',
            // Every part of a page opened or closed at once, as a page's "Expand all" and
            // "Collapse all" do. Heroicons has no fold glyph; its arrows pointing out and in are
            // the pair it draws for opening and closing.
            'expand-all' => 'heroicon-m-arrows-pointing-out',
            'collapse-all' => 'heroicon-m-arrows-pointing-in',
            'hash' => 'heroicon-m-hashtag',
            'shield-warning' => 'heroicon-m-shield-exclamation',
            'prohibit' => 'heroicon-m-no-symbol', // Heroicons names the concept after the sign, not the act.
            'scan' => 'heroicon-m-viewfinder-circle', // The nearest true cognate: a framing reticle.

            // Notification, labeling, mail and media — concepts every
            // administrative interface has. Without a word here a page reaches for
            // the icon package's own glyph name, which resolves only while that
            // optional package happens to be installed: a dependency on the icon
            // set rather than a contract with this library.
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
            // Heroicons ships no register, till or drawer glyph in any style; the nearest in
            // the set is `calculator`. A cash
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

            'arrow-down' => 'heroicon-m-arrow-down',
            'arrow-right' => 'heroicon-m-arrow-right',
            'arrow-up' => 'heroicon-m-arrow-up',
            // NOT `arrow-top-right-on-square` — that is the box-with-escaping-arrow glyph,
            // and `external-link` above already owns it. Pointing both words at it would
            // render them identically here while they stay two distinct glyphs on lucide,
            // phosphor and tabler, so switching preset would swap an external-link mark for
            // a plain diagonal arrow. Heroicons ships the true cognate, so the word means
            // one thing.
            'arrow-up-right' => 'heroicon-m-arrow-up-right',
            'chart-pie' => 'heroicon-m-chart-pie',
            // The CONCEPT name for what the marketing extension spells
            // `cursor-arrow-rays`, heroicons' name for its own picture rather than a word
            // anybody would reach for; that spelling stays in the extension. All four
            // sets draw a pointer with an action mark.
            'click' => 'heroicon-m-cursor-arrow-rays',
            'code-bracket' => 'heroicon-m-code-bracket',
            'cog-6-tooth' => 'heroicon-m-cog-6-tooth',
            // Every set draws the same isometric box for this concept, so the word means
            // one thing whichever is installed.
            'cube' => 'heroicon-m-cube',
            // Likewise for sparkles. The SINGULAR `sparkle` stays in the extension:
            // phosphor and heroicons each ship only one sparkle glyph, so carrying both
            // words here would make them indistinguishable there.
            'sparkles' => 'heroicon-m-sparkles',
            'command-line' => 'heroicon-m-command-line',
            'finger-print' => 'heroicon-m-finger-print',
            'fire' => 'heroicon-m-fire',
            'heart' => 'heroicon-m-heart',
            'heart-outline' => 'heroicon-o-heart', // the outline glyph: the concept is the outline
            'link' => 'heroicon-m-link',
            // A paperclip, not a chain link: without a word for an attachment, an "Attach
            // file" button reaches for whatever is near, an external-link box included.
            // All four sets ship a real paperclip.
            'attach' => 'heroicon-m-paper-clip',
            'live' => 'heroicon-m-signal',
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
            'video' => 'heroicon-m-video-camera',
            'emoji' => 'heroicon-m-face-smile',
            'lock-closed' => 'heroicon-m-lock-closed',
            'open-source' => 'heroicon-m-code-bracket',
            // Every set ships a real brush; tabler draws it as `brush`, and the word names
            // the concept rather than one family's spelling.
            'paint-brush' => 'heroicon-m-paint-brush',
            'puzzle-piece' => 'heroicon-m-puzzle-piece',
            'security' => 'heroicon-m-lock-closed',
            'speed' => 'heroicon-m-bolt',
            'squares-2x2' => 'heroicon-m-squares-2x2',
            'star' => 'heroicon-m-star',
            'star-outline' => 'heroicon-o-star', // the only outline glyph here: the concept is the outline
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
