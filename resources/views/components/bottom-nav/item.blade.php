@aware(['interactive' => false])

@php
    use Pushery\WireKit\Support\BooleanProp;
    // `@aware` reads a value from the parent component, but — unlike `@props` —
    // it does NOT remove that key from the attribute bag. So when the key is also
    // written as an attribute on the tag, it survives into `{{ $attributes }}` and
    // renders as a stray HTML attribute on the element. Blade accepts both
    // spellings on a tag, so both are dropped here.
    $attributes = $attributes->except(['interactive']);
@endphp


@props([
    'href' => '#',
    'label' => '',
    // Which page this is. Marks the item as the current one. In interactive mode
    // it is the INITIAL current tab; clicks then move it client-side.
    'active' => false,
    // Optional icon name — see the icon component.
    'icon' => null,
    // A small count on the icon ("3 unread"). It is announced as part of the
    // item's name, never as bare punctuation.
    'badge' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $active = BooleanProp::from($active, false);

    // The accessible name has to carry the badge: "Inbox" and "Inbox, 3" are
    // different claims, and a reader who cannot see the dot deserves the number.
    $accessibleName = ($badge !== null && $badge !== '')
        ? trim($label).', '.$badge
        : $label;

    $classes = WireKit::resolveClasses('bottom-nav.item', 'base', implode(' ', [
        'wk-bottom-nav-item',
        'relative flex flex-1 flex-col items-center justify-center gap-1',
        // Vertical padding gives the corner badge (-top-1.5 on the icon) room so it
        // does not break through the bar's top border line.
        'px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-lg)]',
        'text-[length:var(--text-wk-xs)]',
        'transition-colors duration-[var(--transition-wk-duration)]',
        'focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-inset',
        // The current-tab look (accent color + heavier weight) is CSS keyed on
        // data-active (see dist/wirekit.css), NOT baked in here — so it follows the
        // state whether the server sets `active` or the interactive mode's Alpine
        // binding does. The marker line above it is the second, color-independent
        // signal (WCAG 1.4.1).
    ]), $scope);

    // Interactive mode (parent's `interactive`, via @aware): the tab flips its own
    // data-active on click and seeds the initial current tab, all against the
    // parent nav's `active` Alpine state. Merged into the bag rather than written
    // as @if inside the tag. Keyed by label — nav labels are distinct.
    $isInteractive = filter_var($interactive, FILTER_VALIDATE_BOOLEAN);
    if ($isInteractive) {
        $key = \Illuminate\Support\Js::from($label);
        $attributes = $attributes->merge([
            'x-init' => 'if ('.($active ? 'true' : 'false').' && active === null) active = '.$key,
            'x-on:click.prevent' => 'active = '.$key,
            'x-bind:data-active' => 'active === '.$key.' ? \'true\' : \'false\'',
            'x-bind:aria-current' => 'active === '.$key.' ? \'page\' : null',
        ]);
    }
@endphp

<a
    href="{{ $href }}"
    @if($active) aria-current="page" @endif
    aria-label="{{ $accessibleName }}"
    data-wk-bottom-nav-item
    data-active="{{ $active ? 'true' : 'false' }}"
    {{ $attributes->class([$classes]) }}
>
    @if($icon)
        <span class="relative inline-flex" aria-hidden="true">
            <x-wirekit::icon :name="$icon" class="h-6 w-6" />
            @if($badge !== null && $badge !== '')
                {{-- aria-hidden via the wrapper: the number is already in the
                     link's accessible name, and announcing it twice is worse
                     than not at all. --}}
                <span data-wk-bottom-nav-badge class="absolute -end-1.5 -top-1.5 inline-flex min-w-4 items-center justify-center rounded-[var(--radius-wk-full)] bg-[var(--color-wk-danger)] px-1 text-[length:var(--text-wk-xs)] leading-4 text-[color:var(--color-wk-danger-fg)]">{{ $badge }}</span>
            @endif
        </span>
    @endif

    <span class="truncate">{{ $label }}</span>
</a>
