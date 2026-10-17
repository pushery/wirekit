{{-- optimistic-ui: n/a — client-only
     It lists the shortcuts the page has bound and keeps the reader's switch for single keys in the
     browser. The application stores the switch from the event it announces, so there is no
     server result to show early. --}}
@props([
    // The application's stored choice for single-key shortcuts: true, false, or null for the choice
    // this browser remembers. The switch announces each change as
    // `wirekit:shortcuts-single-keys-changed` with `{ enabled }`, so the application can keep it
    // with the person and render the next page with it.
    'singleKeys' => null,
    // The dialog's heading, "Keyboard shortcuts" in the reader's language by default.
    'heading' => null,
    // The level of the area headings inside the dialog ("Everywhere", "On this page"). The level
    // is the caller's: 2 by default, so an area reads as a section of the dialog its title names.
    'level' => 2,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\AlpinePayload;
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('shortcuts', $attributes->getAttributes());

    $choice = BooleanProp::orNull($singleKeys);

    // Clamped rather than trusted: an invalid level must never reach the tag, because `<h9>` is no
    // heading to any reader and the failure is silent.
    $levelValue = (int) $level;
    $levelValue = $levelValue >= 1 && $levelValue <= 6 ? $levelValue : 2;
    $headingText = filled($heading) ? (string) $heading : __('wirekit::Keyboard shortcuts');

    // One overview per page, under one name: `?`, `shortcuts.trigger` and the open event all reach
    // the dialog by it.
    $name = 'wirekit-shortcuts';

    // Translated here, since a word chosen in JavaScript cannot be: the overview's own entry and
    // one heading per area.
    $config = [
        'name' => $name,
        'label' => __('wirekit::Show keyboard shortcuts'),
        'headings' => [
            'global' => __('wirekit::Everywhere'),
            'dialog' => __('wirekit::In this dialog'),
            'page' => __('wirekit::On this page'),
        ],
    ];

    $contentClasses = WireKit::resolveClasses('shortcuts', 'content', 'flex flex-col gap-[var(--gap-wk-lg)]', $scope);

    $groupClasses = WireKit::resolveClasses('shortcuts', 'group', 'flex flex-col gap-[var(--gap-wk-xs)]', $scope);

    $headingClasses = WireKit::resolveClasses('shortcuts', 'heading', implode(' ', [
        'text-[length:var(--text-wk-sm)]',
        'font-semibold',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);

    $listClasses = WireKit::resolveClasses('shortcuts', 'list', implode(' ', [
        'flex flex-col',
        'divide-y divide-[var(--color-wk-border-subtle)]',
    ]), $scope);

    // A row keeps its keys at the end and wraps under a long name rather than squeezing it.
    $rowClasses = WireKit::resolveClasses('shortcuts', 'row', implode(' ', [
        'flex flex-wrap items-center justify-between',
        'gap-x-[var(--gap-wk-md)] gap-y-[var(--gap-wk-xs)]',
        'py-[var(--padding-wk-y-xs)]',
    ]), $scope);

    $labelClasses = WireKit::resolveClasses('shortcuts', 'label', implode(' ', [
        'min-w-0',
        'text-[length:var(--text-wk-sm)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);

    $keysClasses = WireKit::resolveClasses('shortcuts', 'keys', 'flex shrink-0 flex-wrap items-center gap-[var(--gap-wk-xs)]', $scope);

    $stepClasses = WireKit::resolveClasses('shortcuts', 'step', 'inline-flex items-center gap-[var(--gap-wk-xs)]', $scope);

    $mutedClasses = WireKit::resolveClasses('shortcuts', 'muted', implode(' ', [
        'text-[length:var(--text-wk-sm)]',
        'text-[color:var(--color-wk-text-muted)]',
    ]), $scope);

    // A caller's `x-ref` belongs to the caller's component, and this bag lands on our root,
    // which would keep it: CallerRef::onRoot() hands it to the root above.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);
    // A caller's listener for the open event goes in the other spelling, so both run
    // (Support\CallerListeners).
    $attributes = \Pushery\WireKit\Support\CallerListeners::beside($attributes, ['x-on:wirekit:shortcuts-open.window']);
@endphp

{{-- The root draws no box: the dialog is teleported to the overlay root, and an empty element in a
     layout's flex row would still take a gap. `data-wk-single-keys-choice` carries the
     application's choice, which the switch updates in place; `data-wk-shortcuts-debug` turns on
     the warning about a shortcut bound twice in one area. --}}
<div
    x-data="wirekitShortcuts({{ AlpinePayload::from($config) }})"
    x-on:wirekit:shortcuts-open.window="show()"
    data-wk-shortcuts
    @if($choice !== null) data-wk-single-keys-choice="{{ $choice ? 'true' : 'false' }}" @endif
    @if(config('app.debug')) data-wk-shortcuts-debug @endif
    {{ $attributes->class(['contents']) }}
>
    {{-- The switch is applied as it is flipped, so closing the dialog discards nothing and asks
         nothing. --}}
    <x-wirekit::modal :name="$name" :confirm-discard="false" :scope="$scope">
        <x-wirekit::modal.header>{{ $headingText }}</x-wirekit::modal.header>
        <x-wirekit::modal.body>
            <div class="{{ $contentClasses }}">
                {{-- Read when the dialog opens: only what applies at that moment, by area. --}}
                <template x-for="group in groups" :key="group.key">
                    <section class="{{ $groupClasses }}">
                        {{-- 2 by default: the dialog's own name is its heading, and an h3 after a
                             page's h1 would skip a level. --}}
                        <h{{ $levelValue }} data-wk-prose-skip class="{{ $headingClasses }}" x-text="group.heading"></h{{ $levelValue }}>
                        <dl class="{{ $listClasses }}">
                            <template x-for="item in group.items" :key="item.id">
                                <div class="{{ $rowClasses }}">
                                    <dt class="{{ $labelClasses }}" x-text="item.label"></dt>
                                    <dd class="{{ $keysClasses }}">
                                        {{-- One group of keys per step of a sequence, "then" between
                                             them; the symbols or names are this platform's. --}}
                                        <template x-for="(step, index) in item.steps" :key="index">
                                            <span class="{{ $stepClasses }}">
                                                <span x-show="index > 0" class="{{ $mutedClasses }}">{{ __('wirekit::then') }}</span>
                                                <template x-for="(key, position) in step" :key="position">
                                                    <x-wirekit::kbd size="sm" x-text="key" :scope="$scope" />
                                                </template>
                                            </span>
                                        </template>
                                    </dd>
                                </div>
                            </template>
                        </dl>
                    </section>
                </template>

                <p data-wk-prose-skip x-show="groups.length === 0" class="{{ $mutedClasses }}">{{ __('wirekit::No shortcuts apply here.') }}</p>

                {{-- A screen that turns single keys off (a barcode scanner types its codes as
                     keys) says so instead of offering a switch it would overrule. --}}
                <p data-wk-prose-skip x-show="screenOff" class="{{ $mutedClasses }}">{{ __('wirekit::Single-key shortcuts are off on this screen.') }}</p>

                <div x-show="! screenOff">
                    <x-wirekit::toggle
                        :label="__('wirekit::Single-key shortcuts')"
                        :hint="__('wirekit::Shortcuts made of one key, such as ?, work only while this is on.')"
                        x-bind:checked="singleKeys"
                        x-on:change="switchSingleKeys($event.target.checked)"
                        :scope="$scope"
                    />
                </div>
            </div>
        </x-wirekit::modal.body>
    </x-wirekit::modal>
</div>
