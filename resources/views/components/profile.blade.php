{{-- optimistic-ui: n/a — passthrough
     A profile summary with a developer-supplied menu; it owns nothing that changes. --}}
@props([
    'avatar' => null,
    'name' => null,
    'scope' => null,
    // Interactive mode: when true, the profile becomes a focusable
    // button-like element with role="button" + tabindex="0" plus
    // Enter / Space keyboard handlers that synthesize a click event.
    // Used when a profile sits inside a dropdown trigger (or any other
    // parent that listens for click + needs a focusable child for
    // keyboard-reachability). Default false preserves the pre-existing
    // presentational div byte-for-byte.
    'interactive' => false,
    // The element this renders. `button` is the better answer wherever the profile is
    // the thing being clicked: the browser gives a real button Space activation, Enter
    // activation and the button role for free, where `interactive` synthesizes the first
    // two from Alpine handlers that do not exist until Alpine boots. Same trade
    // `app-rail.item` took in v2.38.0, in the same direction.
    // The default stays `div` so every existing call site keeps the DOM it has — a
    // profile is often a passive summary inside something else that is already the
    // control, and nesting a button inside one would be invalid.
    'as' => 'div',
    // Corner rounding, on the control branch — the branch that has a background
    // and a focus ring for a radius to describe.
    //
    // Baked as `sm` before this prop existed, and that is one row's worth of
    // difference in the place this component is most often used: the account
    // trigger at the foot of a sidebar, sitting directly under navigation
    // entries that round on `--radius-wk-nav-item`. One control rounded unlike
    // every neighbor is exactly the mismatch that stops a repository from
    // adopting the component and keeps its hand-built row instead.
    //
    // `nav-item` is on the list for that reason and is not a rung: the shell
    // redefines it per surface (it derives from the panel radius minus the
    // padding), so no fixed rung can stand in for it.
    'radius' => 'sm',
    // Space between the avatar and the name. Reads the `--gap-wk-*` ladder —
    // the tighter of the two, and the one this component already stood on.
    'gap' => 'sm',
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('profile', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $interactive = BooleanProp::from($interactive, false);

    // Closed list rather than an open tag name: the only two shapes this layout is
    // written for are a passive box and a control. Anything else would render, and
    // would be a shape nobody checked.
    $tag = WireKit::validateProp('profile', 'as', (string) $as, ['div', 'button']);

    // A real <button> is focusable and clickable on its own, so it needs the affordance
    // and the focus ring whether or not `interactive` was also asked for — that prop
    // synthesizes what the tag already provides.
    $control = $interactive || $tag === 'button';

    // Both maps emit nothing for `none`, which is what the layout primitives
    // already do — `gap-0` and an empty string render the same, and one spelling
    // across the kit is worth more than the shorter one here.
    $gapClasses = match ($gap) {
        'none' => '',
        'xs' => 'gap-[var(--gap-wk-xs)]',
        'sm' => 'gap-[var(--gap-wk-sm)]',
        'md' => 'gap-[var(--gap-wk-md)]',
        'lg' => 'gap-[var(--gap-wk-lg)]',
        'xl' => 'gap-[var(--gap-wk-xl)]',
        '2xl' => 'gap-[var(--gap-wk-2xl)]',
        default => WireKit::validateProp('profile', 'gap', (string) $gap, ['none', 'xs', 'sm', 'md', 'lg', 'xl', '2xl']),
    };

    $radiusClasses = match ($radius) {
        'none' => '',
        'sm' => 'rounded-[var(--radius-wk-sm)]',
        'md' => 'rounded-[var(--radius-wk-md)]',
        'lg' => 'rounded-[var(--radius-wk-lg)]',
        'xl' => 'rounded-[var(--radius-wk-xl)]',
        'full' => 'rounded-[var(--radius-wk-full)]',
        'nav-item' => 'rounded-[var(--radius-wk-nav-item)]',
        default => WireKit::validateProp('profile', 'radius', (string) $radius, ['none', 'sm', 'md', 'lg', 'xl', 'full', 'nav-item']),
    };

    // Profile — avatar + name display for header areas.
    $classes = WireKit::resolveClasses('profile', 'base', implode(' ', [
        'flex items-center',
        $gapClasses,
        // Add focus-visible ring when this is a control — same shape as the
        // canonical button focus state (matches the button component).
        // `cursor-pointer` is not decoration on the button branch: Tailwind v4's
        // preflight sets `cursor: default` on <button>, which is where the pointer
        // would otherwise go missing. The rest of the UA chrome — the border, the
        // background, the button's own font — that same preflight already removes,
        // and Tailwind v4 is a hard requirement of this package.
        // An interactive row answers the pointer, not only the keyboard.
        //
        // This branch has always carried `cursor-pointer` and a focus ring, so a keyboard
        // reader saw the row react and a mouse reader saw nothing at all — while the
        // navigation entries directly above it in a sidebar footer light up on hover. The
        // same two tokens they use, so the footer row belongs to the same list it sits at
        // the bottom of.
        // An interactive row answers the pointer, not only the keyboard.
        //
        // This branch has always carried `cursor-pointer` and a focus ring, so a keyboard
        // reader saw the row react and a mouse reader saw nothing at all — while the
        // navigation entries directly above it in a sidebar footer light up on hover. The
        // same two tokens they use, so the footer row belongs to the same list it sits at
        // the bottom of.
        $control ? 'hover:bg-[var(--color-wk-bg-muted)] hover:text-[color:var(--color-wk-text)] transition-colors duration-[var(--transition-wk-duration)] ' : '',
        $control ? 'cursor-pointer focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-offset-[length:var(--ring-wk-offset)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-offset-[var(--color-wk-ring-offset)] '.$radiusClasses : '',
    ]), $scope);

    // accept either a string URL
    // OR an array shape `['src' => ..., 'initials' => ...]` (matching
    // `message.author` and the avatar-component convention). Pre-fix,
    // passing an array crashed with `htmlspecialchars(): Argument #1
    // must be of type string, array given` because the renderer did
    // `<img src="{{ $avatar }}">` without a normalizer.
    $avatarSrc = is_array($avatar) ? ($avatar['src'] ?? null) : (is_string($avatar) ? $avatar : null);
    $avatarInitials = is_array($avatar) ? ($avatar['initials'] ?? null) : null;
    $avatarAlt = is_array($avatar) ? ($avatar['alt'] ?? '') : '';
@endphp

<{{ $tag }}
    {{-- Never a submit button. A profile inside a form is a menu trigger, and the
         default type would post the form instead of opening the menu. --}}
    @if($tag === 'button') type="button" @endif
    {{-- The synthesized keyboard model is for the DIV branch only. On a real button it
         would be a second Enter/Space activation on top of the browser's own, firing the
         developer's click handler twice. --}}
    @if($interactive && $tag !== 'button')
        tabindex="0"
        role="button"
        x-on:keydown.enter.prevent="$el.click()"
        x-on:keydown.space.prevent="$el.click()"
    @endif
    {{ $attributes->class([$classes]) }}
>
    @if($avatarSrc)
        <img src="{{ $avatarSrc }}" alt="{{ $avatarAlt }}" class="h-8 w-8 rounded-full object-cover" />
    @elseif($avatarInitials)
        {{-- Initials fallback — same deterministic-palette shape as the --}}
        {{-- canonical avatar primitive uses for non-image avatars.       --}}
        <span
            aria-label="{{ $name ?? $avatarInitials }}"
            class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-[var(--color-wk-bg-muted)] text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-body-weight)] text-[color:var(--color-wk-text)]"
        >{{ $avatarInitials }}</span>
    @endif
    @if($name)
        {{-- In a collapsed sidebar rail the name becomes sr-only, exactly as a
             `sidebar.item` label does — visually gone, still the accessible name.
             Without this the name stays and WRAPS: measured at 43px, "Dana Ortiz"
             broke across two lines inside a column built for a 32px avatar, which is
             how a footer row ends up taller than the rail it sits in.

             `group-data-[settling]` as well as `group-data-[collapsed]`, because the
             collapse animates: hiding only at the end of it lets the name reflow once
             on the way there. --}}
        <span class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)] font-[number:var(--font-wk-body-weight)] group-data-[collapsed]/wk-sidebar:sr-only group-data-[settling]/wk-sidebar:sr-only">{{ $name }}</span>
    @endif
    {{ $slot }}
</{{ $tag }}>
