{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    // The same shape `x-wirekit::message` takes: ['name' => …, 'avatar' => …, 'role' => …].
    // The group prints it ONCE and the messages inside stop repeating it — that is the
    // whole component. Each message keeps its own timestamp: a run shares a sender, not
    // a moment.
    //
    // ⚠️ No angle brackets around a component name in this file, here or anywhere else in
    // it. Blade's tag compiler runs over the raw source before anything understands PHP
    // comments, so a tag written inside one is compiled as a tag — which leaves the view
    // with a `$component->withAttributes()` call and no component, and the error names an
    // undefined variable rather than the comment that caused it.
    'author' => [],
    'side' => 'left',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('message-group', $attributes->getAttributes());

    $sideValue = match ($side) {
        'left', 'right' => $side,
        default => WireKit::validateProp('message-group', 'side', $side, ['left', 'right']),
    };

    // Read exactly as `message` reads it, including the string shorthand, so the two can be
    // given the same `$author` array without a second shape to remember.
    $authorName = is_array($author) ? ($author['name'] ?? '') : (string) $author;
    $authorAvatar = is_array($author) ? ($author['avatar'] ?? null) : null;

    $alignClass = $sideValue === 'right' ? 'flex-row-reverse' : 'flex-row';
    $textAlign = $sideValue === 'right' ? 'items-end' : 'items-start';

    $baseClasses = WireKit::resolveClasses('message-group', 'base', implode(' ', [
        // The hook the stylesheet reaches through. It is what silences the repeated avatar
        // and name inside, and what rounds the stack's inner corners — see the
        // `.wk-message-group` block in the stylesheet for why the name is hidden rather
        // than removed.
        'wk-message-group',
        'flex gap-[var(--space-wk-sm,0.5rem)]',
        $alignClass,
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);
@endphp

<div
    role="group"
    {{-- Named after the sender, so a reader who lands inside the run hears whose it is
         before the first message. Only with a name: `role="group"` with an empty label is
         a group that announces nothing, which is worse than an unnamed one. --}}
    @if(filled($authorName))
        aria-label="{{ __('wirekit::Messages from :name', ['name' => $authorName]) }}"
    @endif
    {{ $attributes->class([$baseClasses]) }}
>
    {{-- The sender, once. --}}
    @if($authorAvatar || $authorName)
        <div class="shrink-0">
            <x-wirekit::avatar
                :src="$authorAvatar"
                :alt="$authorName"
                size="sm"
            />
        </div>
    @endif

    {{-- Tighter than the gap BETWEEN senders (`--space-wk-sm`), which is the visual half of
         grouping: the run reads as one block, and the next sender starts a new one. --}}
    <div class="flex flex-col {{ $textAlign }} gap-[var(--space-wk-xs,0.25rem)] min-w-0">
        {{-- The sender's name, once, above the run, in the type a message header uses: it takes
             the place of the names the stylesheet hides in those headers. Hidden from assistive
             technology, because the group's label and every message inside already give the
             sender, and a painted copy read as well would announce the name once more before
             the first message. Only with a name, so an avatar-only group paints no empty line.
             `max-w-full` is what lets `truncate` work here: a column that does not stretch its
             items makes an unbroken line as wide as its text, so without the cap a long name
             runs past the edge of the run instead of ending in an ellipsis. --}}
        @if(filled($authorName))
            <span data-wk-message-group-author aria-hidden="true" class="max-w-full truncate text-[length:var(--text-wk-sm)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">{{ $authorName }}</span>
        @endif
        {{ $slot }}
    </div>
</div>
