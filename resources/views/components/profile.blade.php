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
    // How loud the name is. `default` is the color this component has always
    // emitted; `muted` is what a navigation column asks for.
    //
    // A sidebar's own entries stand muted at rest and go full on hover
    // (`sidebar/item.blade.php`). The account trigger sits directly under them, so a
    // name baked to the full color leaves exactly ONE row in the column permanently
    // brighter than every other — reported from a consuming project, which could only
    // work around it by abandoning the `name` prop for the slot and re-writing the
    // collapse handling by hand.
    //
    // A color on the wrapper does not reach it: the span sets its own and wins.
    'tone' => 'default',
    // Inline padding, on the page-edge spine ladder. `none` keeps the DOM this
    // component has always emitted.
    //
    // It exists for the same place `radius` does — the account trigger at the foot of a
    // sidebar. Without it the hover background hugs the name instead of drawing a row,
    // so the entry that closes a navigation list looks unlike every entry in it. That is
    // reachable from a call site as a class, and being reachable that way is exactly the
    // problem: a component whose most common use needs an incantation gets rebuilt by
    // hand instead of adopted.
    'padding' => 'none',
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

    // Muted goes full on HOVER, which is the half that makes it match rather than
    // merely dim: an entry above it does exactly that. The pair sits on the span
    // because the span is what overrides an inherited color, and it is keyed to a
    // NAMED group so a `group-hover:` a caller writes in the slot cannot capture it.
    $toneClasses = match ($tone) {
        'default' => 'text-[color:var(--color-wk-text)]',
        'muted' => 'text-[color:var(--color-wk-text-muted)] group-hover/wk-profile:text-[color:var(--color-wk-text)]',
        default => WireKit::validateProp('profile', 'tone', (string) $tone, ['default', 'muted']),
    };

    $paddingClasses = match ($padding) {
        'none' => '',
        'xs' => 'px-[var(--padding-wk-x-xs)]',
        'sm' => 'px-[var(--padding-wk-x-sm)]',
        'md' => 'px-[var(--padding-wk-x-md)]',
        'lg' => 'px-[var(--padding-wk-x-lg)]',
        'xl' => 'px-[var(--padding-wk-x-xl)]',
        default => WireKit::validateProp('profile', 'padding', (string) $padding, ['none', 'xs', 'sm', 'md', 'lg', 'xl']),
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

    // ── An avatar-only control IS the avatar ──────────────────────────────
    //
    // Reported from the starter kit, measured in both engines: the account trigger at
    // the top of a phone layout showed a 2px square ring around a round avatar, 35px
    // across a 32px circle, `radius="nav-item"` doing exactly what it was asked.
    //
    // A radius describes a BOX, and with no name and no slot there is no box — the
    // control hugs the circle, so the only shape that can look deliberate is the
    // circle's own. The prop keeps its meaning everywhere it has a row to describe,
    // and this is not only about the focus ring: the hover surface is the same
    // rounded-rect around the same circle.
    // `hasActualContent()` rather than `trim((string) $slot)`: Livewire wraps a rendered slot
    // in morph markers, so the string form of an EMPTY slot is not empty — an account trigger
    // inside a Livewire component would have kept its rounded-rect ring, and only there.
    $avatarOnly = $control && ! filled($name) && ! $slot->hasActualContent();

    if ($avatarOnly) {
        $radiusClasses = 'rounded-[var(--radius-wk-full)]';
    }

    // Profile — avatar + name display for header areas.
    $classes = WireKit::resolveClasses('profile', 'base', implode(' ', [
        'flex items-center',
        // Only where it is read: the hover half of `muted` needs the group, and a
        // profile that is not a control has no hover to answer.
        $tone === 'muted' && $control ? 'group/wk-profile' : '',
        $gapClasses,
        $paddingClasses,
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
        $control ? 'hover:bg-[var(--color-wk-bg-muted)] hover:text-[color:var(--color-wk-text)] transition-colors duration-[var(--transition-wk-duration)] ' : '',
        $control ? 'cursor-pointer focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-offset-[length:var(--ring-wk-offset)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-offset-[var(--color-wk-ring-offset)] '.$radiusClasses : '',
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

<{{ $tag }} data-wk-prose-skip
    {{-- Never a submit button. A profile inside a form is a menu trigger, and the
         default type would post the form instead of opening the menu. --}}
    @if($tag === 'button') type="button" @endif
    {{-- The synthesized keyboard model is for the DIV branch only. On a real button it
         would be a second Enter/Space activation on top of the browser's own, firing the
         developer's click handler twice. --}}
    @if($interactive && $tag !== 'button')
        tabindex="0"
        role="button"
        {{-- ⚠️ THE BARE `x-data` IS WHAT MAKES THE TWO HANDLERS BELOW EXIST. Alpine walks
             only the trees rooted at an element carrying `x-data` or `x-init` — everything
             else in the document is never visited, so an `x-on:` on an unscoped element is
             inert markup: no handler, no error, no console line. The row takes focus and
             announces itself as a button, and Enter does nothing.

             That is not a hypothetical placement. `interactive` is documented for exactly
             the case where a real `<button>` is ruled out — an account row closing a
             sidebar footer — and neither `sidebar` nor `shell-bar` opens a scope around
             their footer slot. The dropdown-trigger usage worked only because the dropdown
             happens to own one, and a Livewire-morphed row worked only because Alpine's
             mutation observer initializes markup inserted AFTER boot. The first server
             render outside both is the shape the docs recommend.

             Empty on purpose: an empty scope inherits from any ancestor scope, so nesting
             this inside a dropdown changes nothing. Gated on the caller not having supplied
             one, because HTML keeps the FIRST of two identical attributes — emitting ours
             unconditionally would silently discard theirs, which is the class
             `StrictnessGate::discardedScopeDirectives` warns about at runtime. --}}
        @if(! $attributes->has('x-data')) x-data @endif
        x-on:keydown.enter.prevent="$el.click()"
        x-on:keydown.space.prevent="$el.click()"
    @endif
    {{ $attributes->class([$classes]) }}
>
    @if($avatarSrc)
        <img data-wk-prose-skip src="{{ $avatarSrc }}" alt="{{ $avatarAlt }}" class="h-8 w-8 rounded-full object-cover" />
    @elseif($avatarInitials)
        {{-- The PRIMITIVE, not a copy of it. This was hand-rolled, and the comment above it
             claimed "the same deterministic-palette shape as the canonical avatar primitive"
             while painting a flat `--color-wk-bg-muted` — the one thing the palette exists to
             replace. Measured from a consuming kit before adopting this component: the copy
             also differed in border (none against a subtle one), text size (`xs` against `sm`)
             and weight (body against heading). Four differences under a comment asserting
             sameness.

             The cost is not cosmetic and it is why they kept a hand-build instead: the palette
             derives the color from the initials, so two people are two colors. Adopting the
             copy traded a color-coded list for a uniformly gray one, and in a list of people
             the color IS what tells two rows apart. --}}
        <x-wirekit::avatar
            :initials="$avatarInitials"
            from-initials
            size="sm"
            :alt="$name ?? $avatarInitials"
        />
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
        {{-- `min-w-0 flex-1 truncate` for the EXPANDED state, which the comment above
             diagnosed and then only fixed for the collapsed one: the same long name that
             wraps at 43px also wraps in a narrow-but-open column, and the row grows the
             same way. `min-w-0` is not decoration — a flex child defaults to `min-width:
             auto`, so `truncate` has nothing to shrink against and silently does nothing.
             That pairing is the trap; the class on its own reads as done. --}}
        <span class="min-w-0 flex-1 truncate text-left text-[length:var(--text-wk-sm)] {{ $toneClasses }} font-[number:var(--font-wk-body-weight)] wk-rail-hide">{{ $name }}</span>
    @endif
    {{ $slot }}
</{{ $tag }}>
