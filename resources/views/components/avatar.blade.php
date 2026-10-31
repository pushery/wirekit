{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    'src' => null,
    'alt' => null,
    'initials' => null,
    // When true (with `initials` set and no `src`), derive a deterministic
    // background color from the initials hash so the same person always
    // gets the same color — replaces per-app crc32-palette helpers. The
    // palette pairs an AA-contrast background with white text and is
    // theme-independent. See Pushery\WireKit\Support\AvatarPalette.
    'fromInitials' => false,
    'size' => config('wirekit.components.avatar.size', 'md'),
    'shape' => config('wirekit.components.avatar.shape', 'circle'),
    'status' => null,
    // How to visually render the status:
    //   'dot'  — small colored dot in the bottom-right corner (default).
    //   'ring' — full colored ring that surrounds the avatar, separated from
    //            the image by a thin gap in the elevated surface color. The
    //            more prominent of the two: presence reads at a glance in a
    //            dense roster, where a corner dot needs looking for.
    // Ignored when `status` is null.
    'statusVariant' => config('wirekit.components.avatar.status-variant', 'dot'),
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\AvatarPalette;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('avatar', $attributes->getAttributes());

    // No `status`, or one of these. An unknown one is reported (Support\StrictnessGate) and leaves
    // the status unset, as no `status` does, rather than taking the first of the list.
    $status = filled($status) ? \Pushery\WireKit\Support\NumericProp::text($status) : null;
    if ($status !== null && \Pushery\WireKit\WireKit::validateProp('avatar', 'status', $status, ['online', 'busy', 'away', 'offline']) !== $status) {
        $status = null;
    }

    // Echoed into the tag or bound, the URL is written escaped once, and an empty one or one of
    // white space is no picture, so the avatar shows its initials (Support\UrlProp::source()).
    $src = \Pushery\WireKit\Support\UrlProp::source($src);

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $fromInitials = BooleanProp::from($fromInitials, false, 'avatar', 'fromInitials');

    // from-initials: deterministic background derived from the initials hash.
    // Only applies to the initials-fallback path (no image). The inline style
    // overrides the muted base bg + default text color with the AA-contrast
    // palette pair so the same key always renders the same color.
    $fromInitialsStyle = '';
    if ($fromInitials && $initials && ! $src) {
        $palette = AvatarPalette::for((string) $initials);
        $fromInitialsStyle = "background-color: {$palette['bg']}; color: {$palette['fg']};";
    }

    // Base classes: inline-flex for initials centering, muted fallback bg
    $baseClasses = WireKit::resolveClasses('avatar', 'base', implode(' ', [
        'relative inline-flex items-center justify-center',
        'bg-[var(--color-wk-bg-muted)]',
        'text-[color:var(--color-wk-text)]',
        'font-[family-name:var(--font-wk-sans)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border-subtle)]',
        'overflow-hidden',
        'select-none',
        'shrink-0',
    ]), $scope);

    // Size classes: width, height, font size scaling
    // Fixed rem values provide a consistent avatar scale independent of input sizing
    $sizeClasses = match (WireKit::validateProp('avatar', 'size', $size, ['xs', 'sm', 'md', 'lg', 'xl'])) {
        'xs' => 'w-[var(--size-wk-xs)] h-[var(--size-wk-xs)] text-[length:var(--text-wk-2xs)]',
        'sm' => 'w-[var(--size-wk-sm)] h-[var(--size-wk-sm)] text-[length:var(--text-wk-sm)]',
        'md' => 'w-[var(--size-wk-md)] h-[var(--size-wk-md)] text-[length:var(--text-wk-md)]',
        'lg' => 'w-[var(--size-wk-lg)] h-[var(--size-wk-lg)] text-[length:var(--text-wk-lg)]',
        'xl' => 'w-16 h-16 text-[length:var(--text-wk-lg)]',
    };

    // Shape: circle (default) or square with medium radius.
    //
    // The default arm goes through the validator, like `size` above it. `default => $shape`
    // emitted the caller's string as a CSS CLASS, so `shape="cirlce"` produced
    // `class="cirlce"` — no radius, no warning, and a class name that looks deliberate to
    // anyone reading the markup.
    $shapeClasses = match ($shape) {
        'circle' => 'rounded-full',
        'square' => 'rounded-[var(--radius-wk-md)]',
        default => match (WireKit::validateProp('avatar', 'shape', $shape, ['circle', 'square'])) {
            'square' => 'rounded-[var(--radius-wk-md)]',
            default => 'rounded-full',
        },
    };

    // Same treatment for the status variant, and here the silence costs more: both places
    // that render presence compare against the exact string, so `statusVariant="Dot"`
    // removes the indicator AND its screen-reader label without rendering anything at all.
    $statusVariant = WireKit::validateProp('avatar', 'statusVariant', (string) $statusVariant, ['dot', 'ring']);

    // Status indicator color + size scaling (dot sits on bottom-right)
    $statusSize = match ($size) {
        'xs', 'sm' => 'w-2 h-2',
        'md' => 'w-2.5 h-2.5',
        'lg' => 'w-3 h-3',
        'xl' => 'w-3.5 h-3.5',
        default => 'w-2.5 h-2.5',
    };

    // The status in the color of its text tone, and in a shape of its own, so the four do not
    // rest on color alone (WCAG 1.4.1): online is a filled dot, busy a dot crossed by a bar, away
    // a half-filled dot, offline a hollow one. The shapes readers know from Teams and Fluent.
    $statusColor = match ($status) {
        'online' => 'text-[color:var(--color-wk-success-text)]',
        'busy' => 'text-[color:var(--color-wk-danger-text)]',
        'away' => 'text-[color:var(--color-wk-warning-text)]',
        'offline' => 'text-[color:var(--color-wk-text-muted)]',
        default => null,
    };

    // Ring variant — double-ring presence look via box-shadow. Two concentric
    // shadows: inner layer = gap in `--color-wk-bg-elevated`, outer layer = status color.
    // Inline rather than through the `ring-*` and `ring-offset-*` utilities, which
    // Tailwind v4 still has: the gap and both widths are computed here from `size`,
    // and one declaration carries them without a literal class per size and status.
    // Box-shadow never affects layout / box sizing.
    $statusRingStyle = '';
    if ($status && $statusVariant === 'ring') {
        $ringWidth = match ($size) {
            'xs', 'sm' => 1.5,
            'xl' => 2.5,
            default => 2,
        };
        $gap = 2;
        $total = $gap + $ringWidth;
        $ringColor = match ($status) {
            'online' => 'var(--color-wk-success-text)',
            'busy' => 'var(--color-wk-danger-text)',
            'away' => 'var(--color-wk-warning-text)',
            'offline' => 'var(--color-wk-text-muted)',
            default => 'transparent',
        };
        // The ring takes a shape per status as the dot does: solid when online, doubled when busy,
        // dotted when away, dashed when offline. A dotted and a dashed line are outlines, which a
        // shadow cannot draw; the outline's offset leaves the same gap around the picture.
        $statusRingStyle = match ($status) {
            'busy' => "box-shadow: 0 0 0 {$gap}px var(--color-wk-bg-elevated), 0 0 0 {$total}px {$ringColor}, 0 0 0 ".($total + $gap)."px var(--color-wk-bg-elevated), 0 0 0 ".($total + $gap + $ringWidth)."px {$ringColor}",
            'away' => "outline: {$ringWidth}px dotted {$ringColor}; outline-offset: {$gap}px",
            'offline' => "outline: {$ringWidth}px dashed {$ringColor}; outline-offset: {$gap}px",
            default => "box-shadow: 0 0 0 {$gap}px var(--color-wk-bg-elevated), 0 0 0 {$total}px {$ringColor}",
        };
    }

    // Status dot accessibility labels (for screen readers). Through the catalog,
    // like every other label WireKit renders on the developer's behalf — these
    // four were the only presence strings still typed in English, so a fully
    // translated application announced its own avatars in a second language.
    $statusLabel = match ($status) {
        'online' => __('wirekit::Online'),
        'busy' => __('wirekit::Busy'),
        'away' => __('wirekit::Away'),
        'offline' => __('wirekit::Offline'),
        default => null,
    };
@endphp

@php
    // Combine the optional ring box-shadow with the optional from-initials
    // color pair into one style attribute.
    $avatarStyle = trim(implode(' ', array_filter([$fromInitialsStyle, $statusRingStyle ? $statusRingStyle.';' : ''])));
@endphp
<span {{ $attributes->merge($avatarStyle ? ['style' => $avatarStyle] : [])->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$baseClasses, $sizeClasses, $shapeClasses])) }}
>
    @if($src)
        <img data-wk-prose-skip src="{{ $src }}" alt="{{ \Pushery\WireKit\Support\SlotContent::textOf($alt) }}" class="w-full h-full object-cover" />
    @elseif($initials)
        <span aria-hidden="{{ $alt ? 'true' : 'false' }}">{{ $initials }}</span>
        @if($alt)
            <span class="sr-only">{{ $alt }}</span>
        @endif
    @else
        <svg
            @if($alt)
                role="img" aria-label="{{ \Pushery\WireKit\Support\SlotContent::textOf($alt) }}"
            @else
                aria-hidden="true"
            @endif
            class="w-3/5 h-3/5 text-[color:var(--color-wk-text-muted)]"
            fill="currentColor"
            viewBox="0 0 24 24"
        >
            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" />
        </svg>
    @endif

    @if($statusColor && $statusVariant === 'dot')
        {{-- role="img", not role="status". The dot is a standing fact about the
             person, not something that just happened: role="status" makes it a
             live region, and an empty live region carrying only an aria-label is
             not reliably read in browse mode — so the presence state reached a
             screen reader as color alone. An icon that carries information is an
             image with a name, and a name on it is announced wherever the avatar
             is read, in browse mode as well as in focus mode. --}}
        <span
            role="img"
            aria-label="{{ $statusLabel }}"
            data-wk-status-shape="{{ match ($status) { 'busy' => 'bar', 'away' => 'half', 'offline' => 'hollow', default => 'filled' } }}"
            @class([
                'absolute bottom-0 right-0',
                'rounded-full',
                'bg-[var(--color-wk-bg-elevated)]',
                'ring-2 ring-[var(--color-wk-bg-elevated)]',
                $statusSize,
                $statusColor,
            ])
        ><svg class="block h-full w-full" viewBox="0 0 10 10" aria-hidden="true">
            @switch($status)
                @case('busy')
                    <circle cx="5" cy="5" r="5" fill="currentColor" />
                    <rect x="2.25" y="4.1" width="5.5" height="1.8" rx="0.9" fill="var(--color-wk-bg-elevated)" />
                    @break
                @case('away')
                    <circle cx="5" cy="5" r="4.25" fill="none" stroke="currentColor" stroke-width="1.5" />
                    <path d="M5 0a5 5 0 0 0 0 10z" fill="currentColor" />
                    @break
                @case('offline')
                    <circle cx="5" cy="5" r="4.25" fill="none" stroke="currentColor" stroke-width="1.5" />
                    @break
                @default
                    <circle cx="5" cy="5" r="5" fill="currentColor" />
            @endswitch
        </svg></span>
    @endif

    @if($status && $statusVariant === 'ring' && $statusLabel)
        {{-- The ring variant has no dot to name, so the label is real text in an
             sr-only span rather than an aria-label on an empty element. Text
             content is read in every mode; an aria-label on an empty span with a
             live-region role is read in almost none. --}}
        <span class="sr-only">{{ $statusLabel }}</span>
    @endif
</span>