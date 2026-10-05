{{-- optimistic-ui: n/a — sub-component
     A partial of input. It puts a token into the field, in the browser, and sends nothing. --}}
{{-- The tokens an input offers under it, each a button that puts its token into the field: at the
     caret the reader left there, or at the end before they placed one. Included with:

       $insertFor       the field's id
       $insertTokens    token => what it stands for, or null where the token says enough
       $insertName      the group's name
       $insertDisabled  whether the field takes no token, being disabled or read-only

     A button is named by its content, the token and, hidden, what it stands for, so the name holds
     the text a reader sees and can say. `x-wk-insert-token` (resources/js/utils/insert-token.js)
     takes no expression and so runs under Alpine's CSP build. --}}
<div data-wk-prose-skip data-wk-insert-tokens x-data x-wk-insert-token data-wk-insert-for="{{ $insertFor }}" role="group" aria-label="{{ $insertName }}" class="flex flex-wrap gap-[var(--gap-wk-xs)]">
    @foreach($insertTokens as $token => $meaning)
        <x-wirekit::button type="button" intent="neutral" surface="outline" size="xs" :disabled="$insertDisabled" data-wk-insert-token="{{ $token }}"><span class="font-[family-name:var(--font-wk-mono)]">{{ $token }}</span>@if(filled($meaning))<span class="sr-only">, {{ $meaning }}</span>@endif</x-wirekit::button>
    @endforeach
</div>
