@props([
    'src' => null,
    'alt' => null,
    'initials' => null,
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
    use Pushery\WireKit\WireKit;

    // Base classes: inline-flex for initials centering, muted fallback bg
    $baseClasses = WireKit::resolveClasses('avatar', 'base', implode(' ', [
        'relative inline-flex items-center justify-center',
        'bg-[var(--color-wk-bg-muted)]',
        'text-[var(--color-wk-text)]',
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
        default => $size,
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

    // Ring variant — uses Tailwind's ring utilities so the status color becomes
    // a 2px outset ring with a matching 2px gap in the elevated background,
    // producing the "double ring" presence look. The ring is drawn with
    // box-shadow so it never affects layout / box sizing, and sits outside the
    // existing 1px border-subtle on the avatar, keeping the normal bezel.
    $statusRingColor = match ($status) {
        'online' => 'ring-[var(--color-wk-success)]',
        'busy' => 'ring-[var(--color-wk-danger)]',
        'away' => 'ring-[var(--color-wk-warning)]',
        'offline' => 'ring-[var(--color-wk-text-muted)]',
        default => null,
    };

    // Ring width scales subtly with avatar size for visual balance — tiny
    // avatars get a thinner ring so the presence indicator doesn't overwhelm
    // the image itself.
    $statusRingWidth = match ($size) {
        'xs', 'sm' => 'ring-[length:1.5px]',
        'xl' => 'ring-[length:2.5px]',
        default => 'ring-[length:2px]',
    };

    // Gap between avatar and ring — same page-background color as the status
    // dot's ring, for a consistent look whether you pick dot or ring variants.
    $statusRingOffset = 'ring-offset-2 ring-offset-[var(--color-wk-bg-elevated)]';

    // Only apply ring classes when variant is 'ring' AND a status is set.
    $statusRingClasses = ($status && $statusVariant === 'ring' && $statusRingColor)
        ? "{$statusRingWidth} {$statusRingColor} {$statusRingOffset}"
        : '';

    // Status dot accessibility labels (for screen readers)
    $statusLabel = match ($status) {
        'online' => 'Online',
        'busy' => 'Busy',
        'away' => 'Away',
        'offline' => 'Offline',
        default => null,
    };
@endphp

<span {{ $attributes->class([$baseClasses, $sizeClasses, $shapeClasses, $statusRingClasses]) }}
    @if($status && $statusVariant === 'ring')
        role="status" aria-label="{{ $statusLabel }}"
    @endif
>
    @if($src)
        {{-- Image avatar: alt text required for accessibility --}}
        <img src="{{ $src }}" alt="{{ $alt ?? '' }}" class="w-full h-full object-cover" />
    @elseif($initials)
        {{-- Initials fallback — typically 1-2 uppercase letters --}}
        <span aria-hidden="{{ $alt ? 'true' : 'false' }}">{{ $initials }}</span>
        @if($alt)
            <span class="sr-only">{{ $alt }}</span>
        @endif
    @else
        {{-- Default icon fallback (user silhouette SVG) --}}
        <svg
            @if($alt)
                role="img" aria-label="{{ $alt }}"
            @else
                aria-hidden="true"
            @endif
            class="w-3/5 h-3/5 text-[var(--color-wk-text-muted)]"
            fill="currentColor"
            viewBox="0 0 24 24"
        >
            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" />
        </svg>
    @endif

    {{-- Status indicator (dot variant): dot overlaid on bottom-right corner.
         Skipped when statusVariant='ring' because the colored ring on the
         avatar container itself already conveys the presence state, and
         stacking both would be visually noisy. --}}
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
</span>
