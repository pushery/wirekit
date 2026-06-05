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
    //            the image by a thin gap in the page background color. This
    //            is the more prominent "presence ring" look used by apps like
    //            Slack, Discord and iMessage.
    // Ignored when `status` is null.
    'statusVariant' => config('wirekit.components.avatar.status-variant', 'dot'),
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\AvatarPalette;
    use Pushery\WireKit\WireKit;

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
    $sizeClasses = match ($size) {
        'xs' => 'w-6 h-6 text-[length:var(--text-wk-sm)]',
        'sm' => 'w-8 h-8 text-[length:var(--text-wk-sm)]',
        'md' => 'w-10 h-10 text-[length:var(--text-wk-md)]',
        'lg' => 'w-12 h-12 text-[length:var(--text-wk-lg)]',
        'xl' => 'w-16 h-16 text-[length:var(--text-wk-lg)]',
        default => WireKit::validateProp('avatar', 'size', $size, ['xs', 'sm', 'md', 'lg', 'xl']),
    };

    // Shape: circle (default) or square with medium radius
    $shapeClasses = match ($shape) {
        'circle' => 'rounded-full',
        'square' => 'rounded-[var(--radius-wk-md)]',
        default => $shape,
    };

    // Status indicator color + size scaling (dot sits on bottom-right)
    $statusSize = match ($size) {
        'xs', 'sm' => 'w-2 h-2',
        'md' => 'w-2.5 h-2.5',
        'lg' => 'w-3 h-3',
        'xl' => 'w-3.5 h-3.5',
        default => 'w-2.5 h-2.5',
    };

    $statusColor = match ($status) {
        'online' => 'bg-[var(--color-wk-success)]',
        'busy' => 'bg-[var(--color-wk-danger)]',
        'away' => 'bg-[var(--color-wk-warning)]',
        'offline' => 'bg-[var(--color-wk-text-muted)]',
        default => null,
    };

    // Ring variant — double-ring presence look via box-shadow. Two concentric
    // shadows: inner layer = gap in page background, outer layer = status color.
    // Uses inline box-shadow instead of Tailwind ring-offset-* utilities (removed
    // in Tailwind v4). Box-shadow never affects layout / box sizing.
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
            'online' => 'var(--color-wk-success)',
            'busy' => 'var(--color-wk-danger)',
            'away' => 'var(--color-wk-warning)',
            'offline' => 'var(--color-wk-text-muted)',
            default => 'transparent',
        };
        $statusRingStyle = "box-shadow: 0 0 0 {$gap}px var(--color-wk-bg-elevated), 0 0 0 {$total}px {$ringColor}";
    }

    // Status dot accessibility labels (for screen readers)
    $statusLabel = match ($status) {
        'online' => 'Online',
        'busy' => 'Busy',
        'away' => 'Away',
        'offline' => 'Offline',
        default => null,
    };
@endphp

@php
    // Combine the optional ring box-shadow with the optional from-initials
    // color pair into one style attribute.
    $avatarStyle = trim(implode(' ', array_filter([$fromInitialsStyle, $statusRingStyle ? $statusRingStyle.';' : ''])));
@endphp
<span {{ $attributes->class([$baseClasses, $sizeClasses, $shapeClasses]) }}
    @if($avatarStyle) style="{{ $avatarStyle }}" @endif
>
    @if($src)
        <img src="{{ $src }}" alt="{{ $alt ?? '' }}" class="w-full h-full object-cover" />
    @elseif($initials)
        <span aria-hidden="{{ $alt ? 'true' : 'false' }}">{{ $initials }}</span>
        @if($alt)
            <span class="sr-only">{{ $alt }}</span>
        @endif
    @else
        <svg
            @if($alt)
                role="img" aria-label="{{ $alt }}"
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
        <span
            role="status"
            aria-label="{{ $statusLabel }}"
            @class([
                'absolute bottom-0 right-0',
                'rounded-full',
                'ring-2 ring-[var(--color-wk-bg-elevated)]',
                $statusSize,
                $statusColor,
            ])
        ></span>
    @endif

    @if($status && $statusVariant === 'ring' && $statusLabel)
        <span role="status" aria-label="{{ $statusLabel }}" class="sr-only"></span>
    @endif
</span>
