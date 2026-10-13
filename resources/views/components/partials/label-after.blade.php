{{-- optimistic-ui: n/a — presentational
     Markup a caller puts after a field's label; it renders no control of its own. --}}
{{-- What follows a field's label and its help, in their row: a mark such as a badge. It stands
     outside the <label> and outside the help button, so it joins neither the field's name nor the
     button's.

     Params: $labelAfter (the caller's slot). --}}
<span data-wk-label-after class="inline-flex flex-wrap items-center gap-[var(--gap-wk-xs)]">{{ $labelAfter }}</span>
