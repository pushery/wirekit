{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
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
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('avatar.group', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // Overlap + ring live in the .wk-avatar-group CSS class (dist/wirekit.css):
    // logical negative margin (RTL-safe) + a surface-color ring around each disc.
    $groupClasses = WireKit::resolveClasses('avatar.group', 'base', 'wk-avatar-group', $scope);

    // Counter chip mirrors the avatar size dims + circle shape so it sits flush
    // in the stack. Literal class strings per size for the Tailwind scanner.
    $chipSize = match ($size) {
        'xs' => 'w-[var(--size-wk-xs)] h-[var(--size-wk-xs)] text-[length:var(--text-wk-sm)]',
        'sm' => 'w-[var(--size-wk-sm)] h-[var(--size-wk-sm)] text-[length:var(--text-wk-sm)]',
        'lg' => 'w-[var(--size-wk-lg)] h-[var(--size-wk-lg)] text-[length:var(--text-wk-lg)]',
        'xl' => 'w-16 h-16 text-[length:var(--text-wk-lg)]',
        default => 'w-[var(--size-wk-md)] h-[var(--size-wk-md)] text-[length:var(--text-wk-md)]',
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
        {{-- Hidden from assistive technology ONLY when something else says the total.
             The reasoning was right and its premise was not: `aria-label` is optional here,
             so an unlabeled group hid the chip AND had no name — the "+3" existed for
             sighted readers alone, and nothing conveyed how many people were not shown.
             With a label the chip is genuinely redundant and stays hidden. --}}
        <span class="{{ $chipClasses }}" @if(filled($label)) aria-hidden="true" @endif>+{{ (int) $remaining }}</span>
    @endif
</div>
