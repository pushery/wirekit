{{--
    Tailwind v4 source-detection safelist for VariantResolver-emitted classes.

    PROBLEM
    -------
    `Pushery\WireKit\VariantResolver::resolve($intent, $surface)` returns
    Tailwind class strings at PHP runtime — text/bg/border arbitrary classes
    pointing at WireKit's design tokens. Examples include the danger-fg text
    color, the success-hover background, the accent fill, and the soft tint
    color-mix expressions.
    Tailwind v4 only scans the file paths the developer declares via `@source`;
    the canonical integration declares `@source 'vendor/pushery/wirekit/resources/views/**/*.blade.php'`,
    which scans BLADE templates only — Tailwind never reads `src/VariantResolver.php`,
    so any class that exists ONLY in the resolver and never appears literally in a
    Blade file is silently absent from the compiled CSS. The button renders that
    class but the rule has no definition, the property falls through to inheritance
    (Tailwind's body color = #0a0a0a), and axe contrast fails on the danger-filled
    button (4.05 instead of white-on-red 4.78).

    SOLUTION
    --------
    This file contains every dynamic class VariantResolver may emit, written
    literally so Tailwind's candidate scanner picks them up via the same
    `@source 'vendor/pushery/wirekit/resources/views/**/*.blade.php'` glob the
    developer already configured. The classes live inside a Blade `{{-- … --}}`
    comment, so they DO NOT render to HTML — but Tailwind reads the raw file
    contents and finds the candidates regardless of Blade syntax.

    NO developer-side configuration change is required: every WireKit developer's
    Tailwind setup already scans `resources/views/**/*.blade.php` per the
    integration docs, so this file is automatically picked up.

    MAINTENANCE
    -----------
    When `Pushery\WireKit\VariantResolver` adds a new intent, surface, or class
    string, add the resulting literal classes here in the same group. Removed
    classes can be deleted; orphaned classes only waste a few bytes in the
    compiled CSS until the developer rebuilds.

    Upstream axe-core regression coverage on `bg-[var(--color-wk-danger)]`
    buttons surfaces gaps: if a future VariantResolver change introduces a
    class that's missing from this safelist, the upstream contrast check
    fails with the `foreground color: #0a0a0a` symptom and blocks the
    regression from shipping.

    ────────────────────────────────────────────────────────────────────────
    filled() — six intents
    ────────────────────────────────────────────────────────────────────────

    primary:
      bg-[var(--color-wk-accent)]
      text-[color:var(--color-wk-accent-fg)]
      border-[var(--color-wk-accent)]
      hover:bg-[var(--color-wk-accent-hover)]
      hover:border-[var(--color-wk-accent-hover)]
      shadow-[var(--shadow-wk-sm)]

    neutral:
      bg-[var(--color-wk-bg-muted)]
      text-[color:var(--color-wk-text)]
      border-[var(--color-wk-bg-muted)]
      hover:bg-[var(--color-wk-bg-subtle)]
      shadow-[var(--shadow-wk-sm)]

    success:
      bg-[var(--color-wk-success)]
      text-[color:var(--color-wk-success-fg)]
      border-[var(--color-wk-success)]
      hover:bg-[var(--color-wk-success-hover)]
      shadow-[var(--shadow-wk-sm)]

    warning:
      bg-[var(--color-wk-warning)]
      text-[color:var(--color-wk-warning-fg)]
      border-[var(--color-wk-warning)]
      hover:bg-[var(--color-wk-warning-hover)]
      shadow-[var(--shadow-wk-sm)]

    danger:
      bg-[var(--color-wk-danger)]
      text-[color:var(--color-wk-danger-fg)]
      border-[var(--color-wk-danger)]
      hover:bg-[var(--color-wk-danger-hover)]
      hover:border-[var(--color-wk-danger-hover)]
      shadow-[var(--shadow-wk-sm)]

    info: aliases primary — uses the accent token chain, no info-specific
    classes (the --color-wk-info / --color-wk-info-fg / --color-wk-info-hover
    tokens do not exist in dist/wirekit.css; only --color-wk-info-text exists
    and is itself an alias of accent-content).

    ────────────────────────────────────────────────────────────────────────
    outline() — five intents (info aliases primary)
    ────────────────────────────────────────────────────────────────────────

    Shared:
      bg-[var(--color-wk-bg)]
      hover:bg-[var(--color-wk-bg-subtle)]
      shadow-[var(--shadow-wk-sm)]

    Per-intent text + border combinations:
      text-[color:var(--color-wk-accent-content)]
      text-[color:var(--color-wk-text)]
      text-[color:var(--color-wk-success-text)]
      text-[color:var(--color-wk-warning-text)]
      text-[color:var(--color-wk-danger-text)]
      border-[var(--color-wk-accent)]
      border-[var(--color-wk-border)]
      border-[var(--color-wk-success)]
      border-[var(--color-wk-warning)]
      border-[var(--color-wk-danger)]

    ────────────────────────────────────────────────────────────────────────
    soft() — five intents + neutral
    ────────────────────────────────────────────────────────────────────────

    Soft uses color-mix(in srgb, var(--color-wk-X) 12%, var(--color-wk-bg))
    rather than per-intent *-bg tokens — those tokens do not exist (only
    --color-wk-warning-bg exists, reserved for callout/alert).

    Shared:
      border-transparent

    Backgrounds:
      bg-[color-mix(in_srgb,var(--color-wk-accent)_12%,var(--color-wk-bg))]
      bg-[color-mix(in_srgb,var(--color-wk-success)_12%,var(--color-wk-bg))]
      bg-[color-mix(in_srgb,var(--color-wk-warning)_12%,var(--color-wk-bg))]
      bg-[color-mix(in_srgb,var(--color-wk-danger)_12%,var(--color-wk-bg))]
      bg-[var(--color-wk-bg-muted)]

    (text color classes shared with outline() above)

    ────────────────────────────────────────────────────────────────────────
    ghost() — six intents (transparent background)
    ────────────────────────────────────────────────────────────────────────

      bg-transparent
      border-transparent
      hover:bg-[var(--color-wk-bg-subtle)]
      shadow-[var(--shadow-wk-none)]

    (text color classes shared with outline() above)

    ────────────────────────────────────────────────────────────────────────
    link() — accent + danger only
    ────────────────────────────────────────────────────────────────────────

      text-[color:var(--color-wk-accent-content)]
      text-[color:var(--color-wk-danger-text)]
      border-transparent
      underline-offset-4
      hover:underline
      p-0
      h-auto

    ────────────────────────────────────────────────────────────────────────
    segmented-control() — selected / unselected segment appearance
    ────────────────────────────────────────────────────────────────────────

    Same problem, different origin. These used to be literals inside
    an Alpine `:class` ternary, where Tailwind's scanner did see them — but that
    also put them out of reach of WireKit::scope(), because resolveClasses runs
    at render time in PHP while `:class` is a runtime binding. Moving them into
    resolveClasses made them personalizable and, in the same step, invisible to
    the scanner. Both branches are listed here so the move stays purely additive.

      bg-[var(--color-wk-bg-elevated)]
      text-[color:var(--color-wk-text)]
      shadow-[var(--shadow-wk-sm)]
      font-[number:var(--font-wk-heading-weight)]
      text-[color:var(--color-wk-text-muted)]
      hover:text-[color:var(--color-wk-text)]

    ────────────────────────────────────────────────────────────────────────
    pricing-table() — selected / unselected billing-interval toggle
    ────────────────────────────────────────────────────────────────────────

    Same mechanism as segmented-control above: both branches of the interval
    toggle resolve through resolveClasses (so WireKit::scope() can reach them)
    and are interpolated into an Alpine `:class`, which the scanner cannot see.

    Every class here is already listed for segmented-control — repeated on
    purpose. Without its own entry, a later cleanup of that section would remove
    classes pricing-table has since come to depend on, and the toggle would lose
    its appearance with nothing failing.

      bg-[var(--color-wk-bg-elevated)]
      text-[color:var(--color-wk-text)]
      shadow-[var(--shadow-wk-sm)]
      text-[color:var(--color-wk-text-muted)]

--}}
