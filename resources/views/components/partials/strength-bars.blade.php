{{-- optimistic-ui: n/a — sub-component
     Not a component but an @include shared by `strength-meter` and `password-input`. It draws
     the bars of a strength meter and accepts nothing a server could refuse. --}}
{{-- THE BARS OF A STRENGTH METER, one markup for both meters so the two look the same by
     construction. Each bar is drawn in its color before Alpine starts, so a page that has not
     booted yet shows the strength rather than an empty track, and follows `barColor(i)` of the
     surrounding scope after that. The color ladder is utils/strength.js.

     Params: $count (how many bars), $colors (each bar's color before Alpine starts, a list). --}}
@for ($wkBar = 0; $wkBar < $count; $wkBar++)
    <div
        class="h-1 flex-1 rounded-full transition-colors duration-[var(--transition-wk-duration)]"
        style="background-color: {{ $colors[$wkBar] ?? 'var(--color-wk-bg-muted)' }}"
        x-bind:style="'background-color:' + barColor({{ $wkBar }})"
    ></div>
@endfor
