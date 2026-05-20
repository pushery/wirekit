@props([
    'size' => 'md',
    'variant' => 'default',
    // `density` — controls the heading + paragraph block-spacing scale.
    //   `comfortable` (default) — long-form-article rhythm: generous
    //       `--padding-wk-y-xl` (2.5 rem) top-margin on h2 headings,
    //       `--padding-wk-y-md` (0.75 rem) bottom-margin on paragraphs.
    //       Optimised for blog posts, docs, news articles where
    //       section breaks should breathe.
    //   `compact` — marketing/landing-page rhythm: tighter
    //       `--padding-wk-y-md` (0.75 rem) top-margin on h2, smaller
    //       heading sizes, `--padding-wk-y-sm` (0.5 rem) paragraph
    //       bottom-margin. Reaches for the tight rhythm a marketing
    //       page wants without forcing the developer to write
    //       `.ml h2 { margin: ... }` overrides.
    'density' => 'comfortable',
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
        '[&_code]:font-[family-name:var(--font-wk-mono)] [&_code]:text-[length:var(--text-wk-sm)] [&_code]:bg-[var(--color-wk-bg-muted)] [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:rounded-[var(--radius-wk-sm)]',
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
@endphp

<div {{ $attributes->class([$classes, $sizeClasses, $variantClasses]) }}>
    {{ $slot }}
</div>
