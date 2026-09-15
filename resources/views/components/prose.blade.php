{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'size' => 'md',
    'variant' => 'default',
    // `density` — controls the heading + paragraph block-spacing scale.
    //   `comfortable` (default) — long-form-article rhythm: generous
    //       `--padding-wk-y-xl` (2.5 rem) top-margin on h2 headings,
    //       `--padding-wk-y-md` (0.75 rem) bottom-margin on paragraphs.
    //       Optimized for blog posts, docs, news articles where
    //       section breaks should breathe.
    //   `compact` — marketing/landing-page rhythm: tighter
    //       `--padding-wk-y-md` (0.75 rem) top-margin on h2, smaller
    //       heading sizes, `--padding-wk-y-sm` (0.5 rem) paragraph
    //       bottom-margin. Reaches for the tight rhythm a marketing
    //       page wants without forcing the developer to write
    //       `.ml h2 { margin: ... }` overrides.
    'density' => 'comfortable',
    // `measure` — readable line-length clamp (max-width). Long-form prose reads
    // best at ~65 characters per line; without a cap it runs the full container
    // width, which hurts readability on wide screens.
    //   `default` (default) — ~65ch via --measure-wk.
    //   `wide` — ~78ch via --measure-wk-wide.
    //   `none` — no clamp (full container width; the pre-v2.10.0 behavior).
    'measure' => 'default',
    // `preset` — a readability tuning applied on top of size/density/measure.
    // Kept as its OWN axis (a data-preset attribute + CSS) rather than a bundle
    // of prop defaults, so it composes with the existing props instead of
    // fighting them for precedence — the same shape as `measure`.
    //   `null` (default) — no tuning.
    //   `chat` — tight leading for a message bubble; the bubble already caps
    //       the line length, so the measure clamp would only fight it.
    //   `reading` — long-form: roomier leading for sustained reading.
    //   `large` — accessibility: bigger body text AND roomier leading, for
    //       users who scale text up. Not just a font-size bump: leading has to
    //       grow with it or large text reads worse, not better.
    'preset' => null,
    // `container` — opt in to adapting the rhythm to the COLUMN rather than the viewport.
    // Prose in a sidebar, a card or a table cell is narrow on a desktop screen, where every
    // viewport breakpoint says "wide" and the heading scale stays built for a full page.
    // With this set, the wrapper becomes a size container and the scale tightens below
    // 30rem of its own width — the `compact` rhythm, without the caller having to know
    // which column the prose landed in.
    //
    // Opt-IN, because `container-type: inline-size` is not free: the element's inline size
    // stops depending on its contents, so a prose inside a shrink-to-fit parent (an
    // `inline-block`, a `max-content` grid track, a floated box) collapses instead of
    // sizing itself. Every prose that is laid out by its parent is unaffected, which is
    // most of them — but not all, and the caller is the one who knows.
    'container' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // `container="false"` on an unbound tag is the string "false", which is truthy.
    $container = BooleanProp::from($container, false);

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('prose', $attributes->getAttributes());

    // Prose — typography wrapper that styles raw HTML (h1–h6, p, ul, ol,
    // blockquote, code, table, a) with WireKit design tokens. Similar
    // to @tailwindcss/typography but token-driven.
    //
    // Every element rule ends in the same exclusion, written out on each rule because Tailwind
    // compiles only the class names it can read literally in this file:
    //
    //     :not(:where(.not-wk-prose, .not-wk-prose *, [data-wk-prose-skip]))
    //
    // Prose styles the markup it is given, not the WireKit components nested in it. Every element
    // a component renders carries `data-wk-prose-skip`, which exempts that element and nothing
    // below it, so whatever sits in a component's slot is still styled and a table cell can hold
    // prose of its own. `not-wk-prose` is the developer's opt-out for a whole block: the element
    // and everything inside it. Without the exclusion, a button link inside prose came out
    // underlined, a code block wrapped and took a second padding, and table cells lost their own
    // padding. `:where()` adds no specificity, so every rule weighs exactly what it did before.

    // Density-aware heading + paragraph rules. `comfortable` keeps the
    // pre-v2.0.0 scale (back-compat default); `compact` tightens the
    // h2/h3 mt + p mb tokens so the prose fits marketing-page rhythm
    // without developer-side overrides.
    $presetValue = ($preset === null || $preset === '')
        ? null
        : (in_array($preset, ['chat', 'reading', 'large'], true)
            ? $preset
            : WireKit::validateProp('prose', 'preset', $preset, ['chat', 'reading', 'large']));

    $densityClasses = match ($density) {
        'comfortable' => [
            '[&_h1:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:text-[length:var(--text-wk-2xl)] [&_h1:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:mt-0 [&_h1:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:mb-[var(--padding-wk-y-md)]',
            '[&_h2:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:text-[length:var(--text-wk-xl)] [&_h2:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:mt-[var(--padding-wk-y-xl)] [&_h2:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:mb-[var(--padding-wk-y-sm)]',
            '[&_h3:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:text-[length:var(--text-wk-lg)] [&_h3:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:mt-[var(--padding-wk-y-lg)] [&_h3:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:mb-[var(--padding-wk-y-sm)]',
            '[&_h4:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:text-[length:var(--text-wk-md)] [&_h4:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:mt-[var(--padding-wk-y-lg)] [&_h4:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:mb-[var(--padding-wk-y-xs)]',
            '[&_p:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:mb-[var(--padding-wk-y-md)]',
        ],
        'compact' => [
            '[&_h1:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:text-[length:var(--text-wk-xl)] [&_h1:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:mt-0 [&_h1:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:mb-[var(--padding-wk-y-xs)]',
            '[&_h2:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:text-[length:var(--text-wk-lg)] [&_h2:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:mt-[var(--padding-wk-y-md)] [&_h2:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:mb-[var(--padding-wk-y-xs)]',
            '[&_h3:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:text-[length:var(--text-wk-md)] [&_h3:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:mt-[var(--padding-wk-y-sm)] [&_h3:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:mb-[var(--padding-wk-y-xs)]',
            '[&_h4:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:text-[length:var(--text-wk-sm)] [&_h4:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:mt-[var(--padding-wk-y-sm)] [&_h4:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:mb-[var(--padding-wk-y-xs)]',
            '[&_p:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:mb-[var(--padding-wk-y-sm)]',
        ],
        default => WireKit::validateProp('prose', 'density', $density, ['comfortable', 'compact']),
    };

    $classes = WireKit::resolveClasses('prose', 'base', implode(' ', array_merge([
        // `wk-prose` marker — load-bearing against developer prose
        // wrappers (typical pattern: `.docs-prose > :not([class*="wk-"])
        // { max-width: 75ch }`) that clamp every direct child of a
        // typography body to ~75ch line-length. Without the marker
        // the prose wrapper itself gets clamped and any
        // `<x-wirekit::reading-toc>` / `<x-wirekit::brand-bar>`
        // sibling that IS exempted by the same `wk-*` carve-out spans
        // visibly wider than the article body — a visible right-edge
        // mismatch that reads as "content broken" inside iframe-srcdoc
        // previews and any developer who renders a WireKit prose wrapper
        // inside a Tailwind-typography body.
        'wk-prose',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[color:var(--color-wk-text)]',
        'leading-[var(--leading-wk-relaxed)]',
        // Long-token overflow guard. `overflow-wrap` is an INHERITED property,
        // so setting it on the prose root cascades to every descendant
        // (headings, paragraphs, inline code, list items, table cells) —
        // letting an unbreakable run like a heading containing
        // `ComponentRegistry::category(string $category)` wrap mid-token
        // instead of running off the right edge on a narrow viewport. It only
        // breaks when a word would OTHERWISE overflow, so normal prose is
        // unaffected, and `<pre>` (white-space: pre + its own overflow-x-auto)
        // is unaffected too. Ugly mid-token wrapping beats off-screen overflow.
        // Written as an arbitrary-value class (NOT the bare-word utility form)
        // so the drift inventory traces it like every other arbitrary class
        // here — AND so Tailwind's content scanner doesn't pick the bare-word
        // token out of this very comment and emit an untraceable utility.
        '[overflow-wrap:break-word]',
        // Shared heading typography (font-weight + line-height), density
        // controls size + margin.
        '[&_h1:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:font-[number:var(--font-wk-heading-weight)] [&_h1:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:leading-[var(--leading-wk-tight)]',
        '[&_h2:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:font-[number:var(--font-wk-heading-weight)] [&_h2:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:leading-[var(--leading-wk-tight)]',
        '[&_h3:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:font-[number:var(--font-wk-heading-weight)] [&_h3:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:leading-[var(--leading-wk-tight)]',
        '[&_h4:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:font-[number:var(--font-wk-heading-weight)]',
        // Inline
        '[&_a:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:text-[color:var(--color-wk-accent-text)] [&_a:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:underline [&_a:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:underline-offset-2',
        '[&_strong:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:font-[number:var(--font-wk-heading-weight)]',
        // Lists
        '[&_ul:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:list-disc [&_ul:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:pl-[var(--padding-wk-x-lg)] [&_ul:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:mb-[var(--padding-wk-y-md)]',
        '[&_ol:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:list-decimal [&_ol:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:pl-[var(--padding-wk-x-lg)] [&_ol:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:mb-[var(--padding-wk-y-md)]',
        '[&_li:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:mb-[var(--padding-wk-y-xs)]',
        // Blockquote
        '[&_blockquote:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:border-l-4 [&_blockquote:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:border-[var(--color-wk-border)] [&_blockquote:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:pl-[var(--padding-wk-x-md)] [&_blockquote:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:italic [&_blockquote:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:text-[color:var(--color-wk-text-muted)] [&_blockquote:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:mb-[var(--padding-wk-y-md)]',
        // Code
        // Inline code is the prime offender — tokens like `Foo::bar(string $x)`
        // have long no-space runs, so it gets the stronger `anywhere` (which
        // also lets the code element's min-content shrink, so it can't force
        // its parent wider than the viewport).
        '[&_code:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:font-[family-name:var(--font-wk-mono)] [&_code:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:text-[length:var(--text-wk-sm)] [&_code:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:bg-[var(--color-wk-bg-muted)] [&_code:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:px-1.5 [&_code:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:py-0.5 [&_code:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:rounded-[var(--radius-wk-sm)] [&_code:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:[overflow-wrap:anywhere]',
        // ⚠️ A DESCENDANT `overflow-x` RULE ON <pre> USED TO BE HERE (the arbitrary-variant
        // form, spelled out it would be re-emitted by the scanner reading this comment —
        // Tailwind reads comments too, and writing the class here would resurrect the dead
        // rule in the shipped stylesheet), AND IT MADE THE DEVELOPER'S OWN
        // MARKUP INACCESSIBLE. A descendant selector turned every <pre> the caller passed
        // in into a horizontal scroll region, and a <pre><code> block holds nothing
        // focusable — so the hidden text was reachable by pointer only (WCAG 2.1.1,
        // Level A). prose ships no JavaScript, so it cannot put a `tabindex` on markup it
        // does not author; the only fix available to it is to stop creating the region.
        //
        // It was also invisible to every check we have: a static class scan cannot see a
        // descendant selector, and axe's `scrollable-region-focusable` fires only when the
        // content actually overflows at the tested viewport — the docs previews use short
        // snippets, so both sweeps reported it clean.
        //
        // Wrapping instead of scrolling shows the whole line rather than hiding half of it,
        // which is the better reading experience anyway. `anywhere` is the safety net for a
        // single unbroken token longer than the column.
        '[&_pre:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:bg-[var(--color-wk-bg-muted)] [&_pre:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:rounded-[var(--radius-wk-md)] [&_pre:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:p-[var(--padding-wk-x-md)] [&_pre:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:mb-[var(--padding-wk-y-md)]',
        '[&_pre:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:whitespace-pre-wrap [&_pre:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:[overflow-wrap:anywhere]',
        // A caller who WANTS horizontal scrolling can have it, by authoring the keyboard
        // model themselves: `<pre tabindex="0" role="region" aria-label="…">`. Scrolling is
        // then granted to exactly the markup that is reachable, which is the whole point —
        // the accessible path is the only path that scrolls.
        '[&_pre[tabindex]:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:whitespace-pre [&_pre[tabindex]:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:overflow-x-auto',
        '[&_pre_code:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:bg-transparent [&_pre_code:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:p-0',
        // Table
        '[&_table:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:w-full [&_table:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:mb-[var(--padding-wk-y-md)] [&_table:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:border-collapse',
        '[&_th:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:text-left [&_th:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:font-[number:var(--font-wk-heading-weight)] [&_th:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:py-[var(--padding-wk-y-sm)] [&_th:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:px-[var(--padding-wk-x-sm)] [&_th:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:border-b-2 [&_th:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:border-[var(--color-wk-border)]',
        '[&_td:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:py-[var(--padding-wk-y-sm)] [&_td:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:px-[var(--padding-wk-x-sm)] [&_td:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:border-b [&_td:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:border-[var(--color-wk-border-subtle)]',
        // Horizontal rule
        '[&_hr:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:border-[var(--color-wk-border)] [&_hr:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:my-[var(--padding-wk-y-xl)]',
        // Images
        '[&_img:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:rounded-[var(--radius-wk-md)] [&_img:not(:where(.not-wk-prose,.not-wk-prose_*,[data-wk-prose-skip]))]:my-[var(--padding-wk-y-md)]',
    ], $densityClasses)), $scope);

    $sizeClasses = match ($size) {
        'sm' => 'text-[length:var(--text-wk-sm)]',
        'md' => 'text-[length:var(--text-wk-md)]',
        'lg' => 'text-[length:var(--text-wk-lg)]',
        default => WireKit::validateProp('prose', 'size', $size, ['sm', 'md', 'lg']),
    };

    $variantClasses = match ($variant) {
        'default' => '',
        'muted' => 'text-[color:var(--color-wk-text-muted)]',
        default => WireKit::validateProp('prose', 'variant', $variant, ['default', 'muted']),
    };

    // Readable line-length clamp. The max-width lives in dist/wirekit.css on
    // `.wk-prose` (default ~65ch) + `.wk-prose[data-measure]`, so it stays
    // themeable via --measure-wk; here we only validate + expose the override as
    // a data attribute. `default` needs no attribute (the base `.wk-prose` rule).
    $measureAttr = match ($measure) {
        'default' => null,
        'wide' => 'wide',
        'none' => 'none',
        default => WireKit::validateProp('prose', 'measure', $measure, ['default', 'wide', 'none']),
    };
@endphp

<div @if($measureAttr) data-measure="{{ $measureAttr }}" @endif @if($presetValue) data-preset="{{ $presetValue }}" @endif @if($container) data-container @endif {{ $attributes->class([$classes, $sizeClasses, $variantClasses]) }}>
    {{ $slot }}
</div>
