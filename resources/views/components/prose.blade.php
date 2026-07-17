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
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Prose — typography wrapper that styles raw HTML (h1–h6, p, ul, ol,
    // blockquote, code, table, a) with WireKit design tokens. Similar
    // to @tailwindcss/typography but token-driven.

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
            '[&_h1]:text-[length:var(--text-wk-2xl)] [&_h1]:mt-0 [&_h1]:mb-[var(--padding-wk-y-md)]',
            '[&_h2]:text-[length:var(--text-wk-xl)] [&_h2]:mt-[var(--padding-wk-y-xl)] [&_h2]:mb-[var(--padding-wk-y-sm)]',
            '[&_h3]:text-[length:var(--text-wk-lg)] [&_h3]:mt-[var(--padding-wk-y-lg)] [&_h3]:mb-[var(--padding-wk-y-sm)]',
            '[&_h4]:text-[length:var(--text-wk-md)] [&_h4]:mt-[var(--padding-wk-y-lg)] [&_h4]:mb-[var(--padding-wk-y-xs)]',
            '[&_p]:mb-[var(--padding-wk-y-md)]',
        ],
        'compact' => [
            '[&_h1]:text-[length:var(--text-wk-xl)] [&_h1]:mt-0 [&_h1]:mb-[var(--padding-wk-y-xs)]',
            '[&_h2]:text-[length:var(--text-wk-lg)] [&_h2]:mt-[var(--padding-wk-y-md)] [&_h2]:mb-[var(--padding-wk-y-xs)]',
            '[&_h3]:text-[length:var(--text-wk-md)] [&_h3]:mt-[var(--padding-wk-y-sm)] [&_h3]:mb-[var(--padding-wk-y-xs)]',
            '[&_h4]:text-[length:var(--text-wk-sm)] [&_h4]:mt-[var(--padding-wk-y-sm)] [&_h4]:mb-[var(--padding-wk-y-xs)]',
            '[&_p]:mb-[var(--padding-wk-y-sm)]',
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
        '[&_h1]:font-[number:var(--font-wk-heading-weight)] [&_h1]:leading-[var(--leading-wk-tight)]',
        '[&_h2]:font-[number:var(--font-wk-heading-weight)] [&_h2]:leading-[var(--leading-wk-tight)]',
        '[&_h3]:font-[number:var(--font-wk-heading-weight)] [&_h3]:leading-[var(--leading-wk-tight)]',
        '[&_h4]:font-[number:var(--font-wk-heading-weight)]',
        // Inline
        '[&_a]:text-[color:var(--color-wk-accent)] [&_a]:underline [&_a]:underline-offset-2',
        '[&_strong]:font-[number:var(--font-wk-heading-weight)]',
        // Lists
        '[&_ul]:list-disc [&_ul]:pl-[var(--padding-wk-x-lg)] [&_ul]:mb-[var(--padding-wk-y-md)]',
        '[&_ol]:list-decimal [&_ol]:pl-[var(--padding-wk-x-lg)] [&_ol]:mb-[var(--padding-wk-y-md)]',
        '[&_li]:mb-[var(--padding-wk-y-xs)]',
        // Blockquote
        '[&_blockquote]:border-l-4 [&_blockquote]:border-[var(--color-wk-border)] [&_blockquote]:pl-[var(--padding-wk-x-md)] [&_blockquote]:italic [&_blockquote]:text-[color:var(--color-wk-text-muted)] [&_blockquote]:mb-[var(--padding-wk-y-md)]',
        // Code
        // Inline code is the prime offender — tokens like `Foo::bar(string $x)`
        // have long no-space runs, so it gets the stronger `anywhere` (which
        // also lets the code element's min-content shrink, so it can't force
        // its parent wider than the viewport).
        '[&_code]:font-[family-name:var(--font-wk-mono)] [&_code]:text-[length:var(--text-wk-sm)] [&_code]:bg-[var(--color-wk-bg-muted)] [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:rounded-[var(--radius-wk-sm)] [&_code]:[overflow-wrap:anywhere]',
        '[&_pre]:bg-[var(--color-wk-bg-muted)] [&_pre]:rounded-[var(--radius-wk-md)] [&_pre]:p-[var(--padding-wk-x-md)] [&_pre]:mb-[var(--padding-wk-y-md)] [&_pre]:overflow-x-auto',
        '[&_pre_code]:bg-transparent [&_pre_code]:p-0',
        // Table
        '[&_table]:w-full [&_table]:mb-[var(--padding-wk-y-md)] [&_table]:border-collapse',
        '[&_th]:text-left [&_th]:font-[number:var(--font-wk-heading-weight)] [&_th]:py-[var(--padding-wk-y-sm)] [&_th]:px-[var(--padding-wk-x-sm)] [&_th]:border-b-2 [&_th]:border-[var(--color-wk-border)]',
        '[&_td]:py-[var(--padding-wk-y-sm)] [&_td]:px-[var(--padding-wk-x-sm)] [&_td]:border-b [&_td]:border-[var(--color-wk-border-subtle)]',
        // Horizontal rule
        '[&_hr]:border-[var(--color-wk-border)] [&_hr]:my-[var(--padding-wk-y-xl)]',
        // Images
        '[&_img]:rounded-[var(--radius-wk-md)] [&_img]:my-[var(--padding-wk-y-md)]',
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

<div @if($measureAttr) data-measure="{{ $measureAttr }}" @endif @if($presetValue) data-preset="{{ $presetValue }}" @endif {{ $attributes->class([$classes, $sizeClasses, $variantClasses]) }}>
    {{ $slot }}
</div>
