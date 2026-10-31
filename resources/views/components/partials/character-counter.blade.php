{{-- optimistic-ui: n/a — sub-component
     A partial of the field components. It counts what the reader types and changes nothing. --}}
{{-- The character count under a field: input and textarea with `counter`. Included with:

       $counterFor      the field's id
       $counterFormat   a sentence with :count, :max and :remaining, or null for the number
       $counterValue    the field's value as the server renders it, for the first count
       $counterMax      the field's maxlength, or null

     The visible count is hidden from a screen reader, which would read it out on every key; the
     live region beside it says the count in words once typing pauses. It starts empty, because a
     region that appears together with its text is announced by nothing. The counting itself is
     `x-wk-counter` (resources/js/utils/counter.js), which takes no expression and so runs under
     Alpine's CSP build. The first count is the server's, so the line has its width before any
     script runs, counted in UTF-16 code units like `maxlength` and the script. --}}
@php
    $counterValue = (string) ($counterValue ?? '');
    $counterCount = intdiv(strlen(mb_convert_encoding($counterValue, 'UTF-16LE', 'UTF-8')), 2);
    // A maximum no integer holds is none, where the cast would warn or give an unrelated number.
    $counterMax = is_numeric($counterMax ?? null) ? \Pushery\WireKit\Support\NumericProp::cast($counterMax) : null;
    $counterMax = $counterMax !== null && $counterMax >= 0 ? $counterMax : null;

    // The numbers as the page writes a count, `1.200 / 5.000` in German, as `x-wk-counter` writes them.
    $counterNumber = static fn (int $number): string => \Pushery\WireKit\Support\LocalizedNumber::integer($number);
    $counterText = filled($counterFormat ?? null)
        ? strtr((string) $counterFormat, [
            ':remaining' => $counterMax === null ? '' : $counterNumber(max(0, $counterMax - $counterCount)),
            ':count' => $counterNumber($counterCount),
            ':max' => $counterMax === null ? '' : $counterNumber($counterMax),
        ])
        : ($counterMax === null ? $counterNumber($counterCount) : $counterNumber($counterCount).' / '.$counterNumber($counterMax));
    $counterSeparators = \Pushery\WireKit\Support\LocalizedNumber::separators();

    $counterPhrases = [
        'count' => \Pushery\WireKit\Support\PluralPhrases::from('wirekit::{1} :count character|[2,*] :count characters'),
        'remaining' => \Pushery\WireKit\Support\PluralPhrases::from('wirekit::{1} :count character remaining|[2,*] :count characters remaining'),
        'over' => \Pushery\WireKit\Support\PluralPhrases::from('wirekit::{1} :count character over the limit|[2,*] :count characters over the limit'),
    ];
@endphp
<p
    data-wk-prose-skip
    data-wk-counter
    x-data
    x-wk-counter
    data-wk-counter-for="{{ $counterFor }}"
    @if(filled($counterFormat ?? null)) data-wk-counter-format="{{ $counterFormat }}" @endif
    data-wk-counter-phrases="{{ \Pushery\WireKit\Support\AlpinePayload::json($counterPhrases) }}"
    data-wk-counter-locale="{{ \Pushery\WireKit\Support\IcuLocale::tag() }}"
    @if($counterSeparators !== null) data-wk-counter-separators="{{ \Pushery\WireKit\Support\AlpinePayload::json($counterSeparators) }}" @endif
    @if($counterMax !== null && $counterCount > $counterMax) data-wk-counter-over @endif
    class="ms-auto shrink-0 text-end text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)] tabular-nums data-[wk-counter-over]:text-[color:var(--color-wk-danger-text)]"
>
    <span aria-hidden="true" data-wk-counter-text>{{ $counterText }}</span>
    <span class="sr-only" aria-live="polite" data-wk-counter-announcer></span>
</p>
