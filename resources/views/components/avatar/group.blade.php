@props([
    // Optional overflow counter. When set, a trailing "+N" chip is rendered
    // after the avatars (e.g. show 3 avatars + `:remaining="7"` → "+7"). The
    // developer controls how many avatars they place in the slot.
    'remaining' => null,
    // Sizes the overflow chip to match the avatars in the slot — set it to the
    // same size you pass to those avatars.
    'size' => config('wirekit.components.avatar.size', 'md'),
    // Accessible name for the group as a whole (e.g. "5 collaborators"). The
    // individual avatars keep their own alt text.
    'label' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Overlap + ring live in the .wk-avatar-group CSS class (dist/wirekit.css):
    // logical negative margin (RTL-safe) + a surface-color ring around each disc.
    $groupClasses = WireKit::resolveClasses('avatar.group', 'base', 'wk-avatar-group', $scope);

    // Counter chip mirrors the avatar size dims + circle shape so it sits flush
    // in the stack. Literal class strings per size for the Tailwind scanner.
    $chipSize = match ($size) {
        'xs' => 'w-6 h-6 text-[length:var(--text-wk-sm)]',
        'sm' => 'w-8 h-8 text-[length:var(--text-wk-sm)]',
        'lg' => 'w-12 h-12 text-[length:var(--text-wk-lg)]',
        'xl' => 'w-16 h-16 text-[length:var(--text-wk-lg)]',
        default => 'w-10 h-10 text-[length:var(--text-wk-md)]',
    };

    $chipClasses = implode(' ', [
        'inline-flex items-center justify-center shrink-0',
        'rounded-full',
        'bg-[var(--color-wk-bg-muted)]',
        'text-[color:var(--color-wk-text-muted)]',
        'font-[number:var(--font-wk-heading-weight)]',
        $chipSize,
    ]);
@endphp

<div role="group" @if($label) aria-label="{{ $label }}" @endif {{ $attributes->class([$groupClasses]) }}>
    {{ $slot }}
    @if($remaining !== null && (int) $remaining > 0)
        {{-- Overflow counter — decorative; the accessible total is conveyed by
             the group's aria-label, so this chip is hidden from AT. --}}
        <span class="{{ $chipClasses }}" aria-hidden="true">+{{ (int) $remaining }}</span>
    @endif
</div>
