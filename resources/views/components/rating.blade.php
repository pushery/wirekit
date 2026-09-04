{{-- optimistic-ui: supported
     This layer does NOT own the value it shows. The optimistic scope nests INSIDE
     this component and binds to `rating`; the direction is not interchangeable,
     because a nested Alpine component reads and writes its parent's properties
     and never the reverse.

     Its labels were never in the way: the per-star label names the button's
     POSITION, so building it on the server is correct and does not block the
     announcement the way a value-bearing label does. --}}
@props([
    // The Livewire method this rating should call, when it should show the new
    // score before the server has agreed to it. Null (the default) leaves this
    // component byte-identical to what it has always rendered.
    // Extra arguments appended to the optimistic action call, after the new value.
    // A list of identical controls — one per row — needs to tell the server WHICH row,
    // and the optimistic layer has always been able to carry that: it spreads `args`
    // into the call. No component exposed it, so the capability existed and was
    // unreachable, and the only way to build the commonest optimistic surface there is
    // was to hand-mount the factory and give up the component.
    'optimisticArgs' => [],
    'optimistic' => null,
    'label' => null,
    // `error` was undeclared while `label` was not, so `:error` on a rating
    // landed in the attribute bag and rendered as a stray HTML attribute — a validation
    // message the developer wrote, silently not shown, on a control that IS part of forms.
    'error' => null,
    'hint' => null,
    'value' => 0,
    'max' => 5,
    'icon' => 'star',
    // Let the reader take the score back.
    //
    // This control implements the ARIA radiogroup model, and a radiogroup has
    // no way back to "nothing chosen" — a native radio cannot be deselected
    // from the keyboard either. By that measure the old behavior was correct,
    // which is why this is a prop and not a fix: lowering the floor for
    // everybody would change a documented model for every existing call site.
    //
    // It is opt-in because the two places a rating is most often used are not
    // radiogroups in spirit. A filter facet and an optional form field both
    // need "no opinion" to survive a mis-click; without it the server receives
    // a score nobody meant to give, and the zero state this component renders
    // and documents is reachable only until the first interaction.
    //
    // The gesture is picking the chosen star again, plus stepping down past the
    // first one. A separate clear control was the alternative and was not
    // taken: it puts a permanent extra element and an extra tab stop on every
    // rating on the page — including the majority that never clear — and it
    // needs a label and a position that only the call site knows.
    'clearable' => false,
    'readonly' => false,
    'size' => config('wirekit.components.rating.size', 'md'),
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $readonly = BooleanProp::from($readonly, false);
    $clearable = BooleanProp::from($clearable, false);

    // The seed stays byte-identical when the feature is off. It is an attribute
    // a Livewire morph rewrites, and Alpine re-initializes on the change — so a
    // key added unconditionally is a key added to every render of every rating
    // in the fleet, for a feature almost none of them asked for.
    $ratingSeed = '{ max: '.$max.($clearable ? ', clearable: true' : '').' }';

    use Pushery\WireKit\WireKit;
    use Pushery\WireKit\Support\LocalizedNumber;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('rating', $attributes->getAttributes());

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables the
    // control — the opposite of what the call site says, with no error either way.
    // Strip such flags when their value reads as false, before the bag reaches the control.
    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);


    $id = \Pushery\WireKit\Support\DomId::unique($attributes->get('id') ?? $attributes->get('name'), 'rating-'); // page-unique DOM id; see Support\DomId
    $name = $attributes->get('name', $id);
    // Strip the caller's `id` AND `name` from the bag: the deduped $id is rendered
    // explicitly as id="{{ $id }}", so leaving it in the bag would emit a second,
    // conflicting id attribute. `name` belongs on the hidden input below, which renders
    // it explicitly — left in the bag it also lands on the wrapper, where `name` is not
    // a valid attribute at all and does nothing but fail a validator.
    $attributes = $attributes->except(['id', 'name']);

    $wrapperClasses = WireKit::resolveClasses('rating', 'base', implode(' ', [
        'inline-flex flex-col gap-1',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    // Icon size scales with the size prop
    $iconSize = match ($size) {
        'sm' => 'h-4 w-4',
        'lg' => 'h-8 w-8',
        default => 'h-6 w-6',
    };

    // Icon shapes — each entry defines a viewBox and SVG path.
    // All paths are designed for a 24x24 viewBox.
    $iconShapes = [
        'star' => [
            'viewBox' => '0 0 24 24',
            'path' => 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z',
        ],
        'heart' => [
            'viewBox' => '0 0 24 24',
            'path' => 'M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z',
        ],
        'circle' => [
            'viewBox' => '0 0 24 24',
            'path' => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20z',
        ],
        'square' => [
            'viewBox' => '0 0 24 24',
            'path' => 'M5 3a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5z',
        ],
        'diamond' => [
            'viewBox' => '0 0 24 24',
            'path' => 'M12 2L2 12l10 10 10-10L12 2z',
        ],
        'thumb' => [
            'viewBox' => '0 0 24 24',
            'path' => 'M2 20h2V10H2v10zm20-9a2 2 0 0 0-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L13.17 2 7.59 7.59C7.22 7.95 7 8.45 7 9v10a2 2 0 0 0 2 2h9c.83 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73V11z',
        ],
    ];

    // Fallback to star if icon name is unknown
    $shape = $iconShapes[$icon] ?? $iconShapes['star'];

    // Support fractional values for readonly display (e.g. 3.78 average).
    // Interactive mode always clamps to integers.
    $numericValue = max(0, min((float) $value, (float) $max));
    $clamped = $readonly ? $numericValue : (int) $numericValue;

    // What a readonly rating announces. The component renders a partial star via
    // clip-path, so rounding here would make the picture and the words disagree —
    // it draws 4.2 and would say "4". maxPrecision keeps that precision while
    // dropping a trailing zero, so a clean 4 does not announce as "4.0"; the
    // trim this replaces assumed "." was the decimal separator and would have
    // mangled a localized "4,0".
    $announcedValue = $readonly ? LocalizedNumber::format((float) $numericValue, maxPrecision: 1) : $numericValue;
    $fullStars = (int) floor($numericValue);
    $fraction = $numericValue - $fullStars; // 0.0–0.99 for partial star
@endphp

@php
    // The optimistic layer NESTS INSIDE this component rather than wrapping it,
    // and that direction is not interchangeable: a nested Alpine component's
    // method reads and writes its parent's properties through `this`, never the
    // other way around. So it has to be the child to reach `rating`, and the
    // star buttons have to be inside it to reach its `run()`.
    //
    // `after: '_notify'` is what keeps a plain HTML form honest — the hidden
    // input is synced there, and without the call a rollback would leave the
    // form submitting the score that was just taken back.
    $optimisticConfig = ($optimistic === null || $readonly) ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'bind' => 'rating',
        'after' => '_notify',
        'action' => $optimistic,
        'args' => array_values((array) $optimisticArgs),
        'debug' => (bool) config('app.debug'),
        // A second pick while one is in flight would resolve by whichever answer
        // arrives last — network timing, which is both wrong and untestable.
        'mode' => 'reject',
        'messages' => [
            'pending' => __('wirekit::Saving'),
            'reverted' => __('wirekit::Could not save. Change undone.'),
        ],
    ]);
@endphp

<div
    {{ $attributes->except('aria-label')->whereDoesntStartWith('wire:model')->class([$wrapperClasses]) }}
    {{-- The server's own channel — see segmented-control for why this is a
         plain attribute rather than the hidden input this component binds. --}}
    data-wk-server-value="{{ $clamped }}"
        {{-- The observed value is NOT interpolated into the seed, and that is
             deliberate: a Livewire morph rewrites `x-data`, Alpine re-initializes
             on the change, and an effect queued against the pre-morph scope then
             writes the pre-morph value last. Keeping this attribute
             byte-identical across renders leaves the scope alone, so
             `data-wk-server-value` + observeServerValue is the one update path.
             The factory reads that same attribute at init. --}}
    x-data="wirekitRating({{ $ratingSeed }})"
>
    @if($label)
        @if($readonly)
            {{-- Plain text, not <label>. A readonly rating renders no form field
                 for a label to point at, so a <label for="…"> here would name an
                 element that does not exist — and a <label> with no control is
                 not a label at all, it is an orphan that assistive tech may drop
                 on the floor along with the text inside it. --}}
            <span class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)]">{{ $label }}</span>
        @else
            <x-wirekit::label :for="$id">{{ $label }}</x-wirekit::label>
        @endif
    @endif

    {{-- Hidden input for form submission / wire:model.

         Only when the rating is a CONTROL. A readonly rating is a record of what
         someone gave — displaying it must not put a form field on the page. It
         used to regardless, which meant a product grid shipped one stray field
         per card, with a name regenerated on every render that the developer
         could not even exclude from a submit. --}}
    @if($optimisticConfig)
        {{-- The optimistic scope opens ABOVE the hidden input, not below it.
             `_notify()` finds that input with `closest('[x-data]')` — and once
             this layer is on the page, the nearest x-data from inside it is
             THIS element. An input left outside would simply not be found, so
             the after-hook would run and do nothing: no error, no event, and a
             wire:model that never hears about the rollback. --}}
        <div x-data="wirekitOptimistic({{ $optimisticConfig }})">
    @endif

    @unless($readonly)
        {{-- The static `value` is not redundant next to `:value`. Alpine only
             writes the bound value once it has booted, so between render and
             hydration — and permanently, if Alpine never evaluates at all — the
             field carried an EMPTY value while the stars showed a score. A form
             submitted in that window silently sent nothing. The static
             attribute makes the field agree with what is on screen from the
             first paint; `:value` takes over the moment Alpine runs. --}}
        <input type="hidden" id="{{ $id }}" name="{{ $name }}" value="{{ $clamped }}" :value="rating" {{ $attributes->whereStartsWith('wire:model') }} />
    @endunless

    {{-- Two different things wear the same stars.

         A READONLY rating is a picture of a score: role="img" with one name, and
         nothing inside it to operate. It used to claim role="radiogroup" and mark
         every filled star aria-checked="true" — but a radiogroup is single-select
         by definition, so a 4-star rating announced FOUR simultaneously selected
         radios ("4 stars, selected, 3 stars, selected, …"). Nonsense, and it
         invited the reader to interact with something inert.

         An INTERACTIVE rating really is a radiogroup: picking a score IS choosing
         one of five. That path is unchanged. --}}
    <div
        @if($readonly)
            role="img"
            {{-- The SCORE, not the label. A visible label is already read as text
                 right above these stars, so naming the image with it would say it
                 twice AND lose the number — "Average rating, image" tells the
                 reader nothing about what was rated. Read together it comes out
                 as "Average rating — 4.2 out of 5 stars".

                 An explicit aria-label still wins: the caller knows their page. --}}
            aria-label="{{ $attributes->get('aria-label') ?? __('wirekit:::value out of :max stars', ['value' => $announcedValue, 'max' => $max]) }}"
        @else
            role="radiogroup"
            aria-label="{{ $label ?? $attributes->get('aria-label') ?? __('wirekit::Rating') }}"
            {{-- On the GROUP, not on each star: the message is about the rating, and
                 repeating it on five buttons would read it out five times. --}}
            @if($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif($hint) aria-describedby="{{ $id }}-hint" @endif
        @endif
        class="inline-flex gap-0.5"
    >
        @for($i = 1; $i <= $max; $i++)
            @if($readonly)
                {{-- Readonly: use <span> instead of <button> — not interactive.
                     Supports fractional fill via clip-path on partial stars. --}}
                @php
                    $isFull = $i <= $fullStars;
                    $isPartial = !$isFull && $i === $fullStars + 1 && $fraction > 0;
                    $isEmpty = !$isFull && !$isPartial;
                @endphp
                {{-- Silent: the container above already said "4.2 out of 5
                     stars". Naming each star would repeat the score five times. --}}
                <span aria-hidden="true" class="cursor-default">
                    @if($isPartial)
                        {{-- Partial icon: two overlapping SVGs — empty behind, filled clipped in front.

                             `block`, NOT `inline-block`: every full/empty star is a
                             bare <svg>, which Preflight renders display:block, so they
                             sit flush at the top of the flex item. An inline-block
                             wrapper instead baseline-aligns against the surrounding
                             line box, so whenever the strut's ascent (driven by the
                             INHERITED line-height) exceeds the icon height, the partial
                             star drops by the difference — measured 4px low at
                             size="sm" inside a product card. Block-level makes its box
                             model identical to its siblings. --}}
                        <span class="relative block {{ $iconSize }}">
                            {{-- Empty icon background --}}
                            <svg aria-hidden="true" class="{{ $iconSize }} text-[color:var(--color-wk-text-subtle)] fill-none absolute inset-0" viewBox="{{ $shape['viewBox'] }}" stroke="currentColor" stroke-width="1.5">
                                <path d="{{ $shape['path'] }}"/>
                            </svg>
                            {{-- Filled icon foreground, clipped to the fractional width --}}
                            <svg aria-hidden="true" class="{{ $iconSize }} text-[color:var(--color-wk-warning)] fill-[var(--color-wk-warning)] absolute inset-0" viewBox="{{ $shape['viewBox'] }}" stroke="currentColor" stroke-width="1.5" style="clip-path: inset(0 {{ (1 - $fraction) * 100 }}% 0 0)">
                                <path d="{{ $shape['path'] }}"/>
                            </svg>
                        </span>
                    @else
                        <svg
                            aria-hidden="true"
                            class="{{ $iconSize }} {{ $isFull ? 'text-[color:var(--color-wk-warning)] fill-[var(--color-wk-warning)]' : 'text-[color:var(--color-wk-text-subtle)] fill-none' }}"
                            viewBox="{{ $shape['viewBox'] }}"
                            stroke="currentColor"
                            stroke-width="1.5"
                        >
                            <path d="{{ $shape['path'] }}"/>
                        </svg>
                    @endif
                </span>
            @else
                {{-- Interactive: clickable buttons with hover/keyboard support.
                     Static aria-checked mirrors the initial value for axe-core's
                     pre-Alpine-init scan; Alpine overrides reactively. --}}
                <button
                    type="button"
                    role="radio"
                    {{-- EQUALITY, not the fill threshold — and both attributes have
                         to agree on that.

                         The readonly branch above records why: a radiogroup is
                         single-select, so marking every star up to the score
                         announces "4 stars, selected, 3 stars, selected, …" and
                         leaves the reader unable to tell which score is chosen.
                         That was corrected in the static markup and in the
                         readonly path, and the reactive binding kept the old
                         `>=` shape — so the defect simply reappeared the moment
                         Alpine booted, on the one path a pre-hydration scan
                         cannot see.

                         The FILL is a different question and legitimately keeps
                         `>=`: three lit stars are how a score of three looks.
                         That threshold lives in the SVG `:class` below, where it
                         says something about paint rather than about state. The
                         roving `:tabindex` further down already compares for
                         equality, for the same single-select reason. --}}
                    aria-checked="{{ (int) $value === $i ? 'true' : 'false' }}"
                    :aria-checked="rating === {{ $i }} ? 'true' : 'false'"
                    {{-- Translated, like every other label on this component. And
                         built on the SERVER on purpose: this names the button's
                         POSITION, which never changes — unlike a label that
                         embeds the current value, where server-side
                         pluralization is exactly what blocks an optimistic
                         update from being announced correctly. --}}
                    aria-label="{{ trans_choice('wirekit:::count star|:count stars', $i) }}"
                    @if($optimisticConfig)
                        {{-- run() writes through the binding, announces, and
                             fires the Livewire method; select() would write the
                             value directly and skip the snapshot.

                             `clearTarget()` rather than a ternary spelled out
                             here: run() takes the value to show while the
                             request is in flight, and whether picking this star
                             means "set it" or "take it back" is the rating
                             factory's decision — the same one select() makes on
                             the non-optimistic path. A nested call parses under
                             Alpine's CSP grammar; verified against Alpine's own
                             parser, not assumed. --}}
                        @click="run(clearTarget({{ $i }}))"
                        x-bind:aria-busy="isPending"
                    @else
                        @click="select({{ $i }})"
                    @endif
                    @mouseenter="hovered = {{ $i }}"
                    @mouseleave="hovered = 0"
                    {{-- Radiogroup keyboard model (APG): both axes move the
                         selection, Home/End jump to the ends. ArrowUp aliases
                         ArrowRight (more), ArrowDown aliases ArrowLeft (less). --}}
                    @keydown.arrow-right.prevent="stepUp()"
                    @keydown.arrow-up.prevent="stepUp()"
                    @keydown.arrow-left.prevent="stepDown()"
                    @keydown.arrow-down.prevent="stepDown()"
                    @keydown.home.prevent="selectFirst()"
                    @keydown.end.prevent="selectLast()"
                    :tabindex="rating === {{ $i }} || (rating === 0 && {{ $i }} === 1) ? '0' : '-1'"
                    class="transition-colors duration-[var(--transition-wk-duration)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] rounded-[var(--radius-wk-sm)] cursor-pointer"
                >
                    <svg
                        aria-hidden="true"
                        class="{{ $iconSize }} transition-colors duration-[var(--transition-wk-duration)]"
                        :class="(hovered >= {{ $i }} || (!hovered && rating >= {{ $i }}))
                            ? 'text-[color:var(--color-wk-warning)] fill-[var(--color-wk-warning)]'
                            : 'text-[color:var(--color-wk-text-subtle)] fill-none'"
                        viewBox="{{ $shape['viewBox'] }}"
                        stroke="currentColor"
                        stroke-width="1.5"
                    >
                        <path d="{{ $shape['path'] }}"/>
                    </svg>
                </button>
            @endif
        @endfor
    </div>

    @if($optimisticConfig)
        {{-- Outside the radiogroup — a live region is not a radio — and inside
             the optimistic scope. Rendered unconditionally and starting empty: a
             region that arrives together with its text is a new node, and
             nothing is announced at all. --}}
        <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
        </div>
    @endif

    {{-- Same shape as `input`: one region, error winning over hint, announced politely so
         it does not interrupt what the reader is doing. `aria-describedby` on the control
         points here — see the radiogroup below. --}}
    @if($error)
        <p id="{{ $id }}-error" aria-live="polite" aria-atomic="true" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $error }}</p>
    @elseif($hint)
        <p id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
