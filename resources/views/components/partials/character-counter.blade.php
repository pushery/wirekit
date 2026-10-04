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
    $counterMax = is_numeric($counterMax ?? null) && (int) $counterMax >= 0 ? (int) $counterMax : null;

    $counterText = filled($counterFormat ?? null)
        ? strtr((string) $counterFormat, [
            ':remaining' => $counterMax === null ? '' : (string) max(0, $counterMax - $counterCount),
            ':count' => (string) $counterCount,
            ':max' => $counterMax === null ? '' : (string) $counterMax,
        ])
        : ($counterMax === null ? (string) $counterCount : $counterCount.' / '.$counterMax);

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
    data-wk-counter-locale="{{ str_replace('_', '-', app()->getLocale()) }}"
    @if($counterMax !== null && $counterCount > $counterMax) data-wk-counter-over @endif
    class="ms-auto shrink-0 text-end text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)] tabular-nums data-[wk-counter-over]:text-[color:var(--color-wk-danger-text)]"
>
    <span aria-hidden="true" data-wk-counter-text>{{ $counterText }}</span>
    <span class="sr-only" aria-live="polite" data-wk-counter-announcer></span>
</p>
