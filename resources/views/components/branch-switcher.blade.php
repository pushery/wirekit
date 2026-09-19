{{-- optimistic-ui: n/a — client-only
     Moving between answers the server has already sent changes nothing on the server, so
     there is no result to show early. --}}
@props([
    // How many variants there are. Required in practice: a switcher that does not know its
    // own count cannot say "2 of 3", and the count is what the reader navigates by.
    'count' => 0,
    // Which one is showing, 1-based, because that is what the label says and what a caller
    // reasons about. Out of range is clamped rather than refused — a server that regenerates
    // and re-renders can legitimately hand over an index that no longer exists.
    'current' => 1,
    // Names the group for assistive technology. A switcher with no name is announced as a
    // group of nothing, so an unset label falls back to a translated default.
    'label' => null,
    // Whether the ends wrap. Off by default: a reader stepping through variants expects the
    // last one to be the last one, and silent wrap-around loses that.
    'loop' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\AlpinePayload;
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Fully qualified: this
    // view's imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('branch-switcher', $attributes->getAttributes());

    $loop = BooleanProp::from($loop, false);

    $total = max(0, (int) $count);
    // Clamped, not refused. A regenerate that drops a variant while the reader is on it is an
    // ordinary race, and throwing there would take down the whole turn over a stale index.
    $active = $total === 0 ? 0 : max(1, min($total, (int) $current));

    $groupLabel = filled($label) ? $label : __('wirekit::Response variants');

    $config = AlpinePayload::from([
        'total' => $total,
        'current' => $active,
        'loop' => $loop,
        // `resources/js` has no translator, so the copy is translated here and handed down.
        // The placeholders travel untranslated on purpose: the factory fills them, and a
        // catalog entry with the numbers already substituted could not be reused per step.
        'countLabel' => __('wirekit:::current of :total', ['current' => ':current', 'total' => ':total']),
        'announcement' => __('wirekit::Showing response :current of :total', ['current' => ':current', 'total' => ':total']),
    ]);

    $navClasses = WireKit::resolveClasses('branch-switcher', 'base', implode(' ', [
        'inline-flex items-center gap-[var(--gap-wk-xs)]',
        'text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]',
    ]), $scope);

    $buttonClasses = WireKit::resolveClasses('branch-switcher', 'button', implode(' ', [
        // `cursor-pointer` because preflight gives a button `cursor: default`, and the touch
        // target floor because these two are the smallest controls on an answer.
        'inline-flex cursor-pointer items-center justify-center wk-touch-target',
        'size-[var(--size-wk-sm)] rounded-[var(--radius-wk-full)]',
        'text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)]',
        'hover:bg-[var(--color-wk-bg-muted)]',
        'disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-transparent',
        'focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
        'wk-transition',
    ]), $scope);
@endphp

@if($total > 0)
    {{-- `x-modelable` is what lets a non-input element answer `wire:model`: the binding reaches
         the 1-based index rather than any inner control. A `wire:model` on the tag therefore
         means what a reader of the call site expects it to mean. --}}
    <div
        x-data="wirekitBranchSwitcher({{ $config }})"
        x-modelable="current"
        {{ $attributes->whereStartsWith(['wire:model', 'x-model'])->whereDoesntStartWith('x-modelable') }}
        data-wk-branch-switcher
        role="group"
        aria-label="{{ $groupLabel }}"
        {{ $attributes->whereDoesntStartWith(['wire:model', 'x-model'])->class([$navClasses]) }}
    >
        <button
            type="button"
            data-wk-branch-previous
            class="{{ $buttonClasses }}"
            aria-label="{{ __('wirekit::Previous response') }}"
            x-bind:disabled="! canGoPrevious()"
            x-on:click="previous()"
            x-on:keydown.arrow-left.prevent="previous()"
            x-on:keydown.arrow-right.prevent="next()"
        >
            <x-wirekit::icon name="chevron-left" size="sm" />
        </button>

        {{-- The count is a plain label rather than a live region: it changes on every step, and
             a live region here would interrupt the reader mid-move. The announcement below is
             the one that speaks, and it speaks the whole sentence once the move has settled. --}}
        <span data-wk-branch-count x-text="label()">{{ __('wirekit:::current of :total', ['current' => $active, 'total' => $total]) }}</span>

        <button
            type="button"
            data-wk-branch-next
            class="{{ $buttonClasses }}"
            aria-label="{{ __('wirekit::Next response') }}"
            x-bind:disabled="! canGoNext()"
            x-on:click="next()"
            x-on:keydown.arrow-left.prevent="previous()"
            x-on:keydown.arrow-right.prevent="next()"
        >
            <x-wirekit::icon name="chevron-right" size="sm" />
        </button>

        {{-- Rendered up front and filled afterwards. A live region created together with its
             text is inert — the same decision the assistant turn's announcer records, and the
             reason this element exists at all rather than an aria-live on the count. --}}
        <span
            class="sr-only"
            role="status"
            aria-live="polite"
            data-wk-branch-announcer
            x-text="announced"
        ></span>
    </div>
@endif
