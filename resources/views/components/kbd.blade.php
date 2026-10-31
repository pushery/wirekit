{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    'size' => 'md',
    // A shortcut in the grammar of `x-wk-shortcut` (`cmd+s`, `shift+?`, `g d`), drawn as its keys
    // the way the overview of keyboard shortcuts draws them: ⌘ S on a Mac and Ctrl S elsewhere,
    // named in the reader's language, and a sequence with "then" between its steps. A single-key
    // shortcut is drawn only while single keys work. With a shortcut the slot is not used.
    'shortcut' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('kbd', $attributes->getAttributes());

    $sizeClasses = match (WireKit::validateProp('kbd', 'size', $size, ['sm', 'md', 'lg'])) {
        'sm' => 'text-[length:var(--text-wk-xs,0.75rem)] px-1 py-0.5 min-w-5',
        'md' => 'text-[length:var(--text-wk-sm)] px-1.5 py-0.5 min-w-6',
        'lg' => 'text-[length:var(--text-wk-md)] px-2 py-1 min-w-7',
    };

    $classes = WireKit::resolveClasses('kbd', 'base', implode(' ', [
        'inline-flex items-center justify-center',
        'font-[family-name:var(--font-wk-mono,ui-monospace,monospace)]',
        'font-normal',
        'rounded-[var(--radius-wk-sm)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'border-b-2',
        'bg-[var(--color-wk-bg-muted)]',
        'text-[color:var(--color-wk-text)]',
        'leading-none',
        $sizeClasses,
    ]), $scope);

    $shortcutText = is_string($shortcut) ? trim($shortcut) : '';
    $display = \Pushery\WireKit\Support\KeyShortcuts::display($shortcutText);

    // A shortcut the grammar does not read is reported, and the slot is drawn in its place. With
    // nothing in the slot either, nothing is drawn: a key cap with no key is a box with nothing
    // to read, as a badge without text is (Support\SlotContent). A key a script writes into the
    // cap, through `x-text`, `x-html` or `wire:text`, is a key all the same.
    $keyFromScript = $attributes->has('x-text') || $attributes->has('x-html') || $attributes->has('wire:text');
    if ($shortcutText !== '' && $display === null) {
        \Pushery\WireKit\Support\StrictnessGate::reject('kbd', 'shortcut', $shortcutText, \Pushery\WireKit\Support\KeyShortcuts::EXPECTED, '');
    }

    if ($display !== null) {
        // The keys of both kinds of platform, unless they are drawn alike, and the stylesheet
        // shows the reader's one. Until the script has marked the root with the platform the
        // browser reports, the request's own is the guess, so a page does not change its keys
        // as it loads.
        $variants = $display['mac'] === $display['other'] ? ['' => $display['other']] : ['mac' => $display['mac'], 'other' => $display['other']];
        $guess = preg_match('/Macintosh|Mac OS X|iPhone|iPad|iPod/', (string) request()->userAgent()) === 1 ? 'mac' : 'other';
        $rowClasses = WireKit::resolveClasses('kbd', 'shortcut', 'inline-flex items-center gap-[var(--gap-wk-xs)]', $scope);
        $thenClasses = WireKit::resolveClasses('kbd', 'then', 'text-[length:var(--text-wk-xs,0.75rem)] text-[color:var(--color-wk-text-muted)]', $scope);
    }
@endphp

{{-- One element per key, as the overview draws them; the steps of a sequence stand with "then"
     between them. The last tags close on the line of the directives after them, because a
     newline there would render as a space in front of the text that follows the key. --}}
@if($display !== null)
<span data-wk-kbd-shortcut="{{ $shortcutText }}" data-wk-kbd-guess="{{ $guess }}"@if($display['single']) data-wk-kbd-single=""@endif {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$rowClasses])) }}>
    @foreach($variants as $platform => $steps)
        <span class="inline-flex items-center gap-[var(--gap-wk-xs)]"@if($platform !== '') data-wk-kbd-platform="{{ $platform }}"@endif>
            @foreach($steps as $index => $step)
                @if($index > 0)
                    <span class="{{ $thenClasses }}">{{ __('wirekit::then') }}</span>
                @endif
                @foreach($step as $key)
                    <kbd class="{{ $classes }}">{{ $key }}</kbd>
                @endforeach
            @endforeach
        </span>
    @endforeach
</span>@elseif(\Pushery\WireKit\Support\SlotContent::given($slot) || $keyFromScript)<kbd {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}>{{ $slot }}</kbd>@endif