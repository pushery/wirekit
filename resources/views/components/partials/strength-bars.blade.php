{{-- optimistic-ui: n/a — sub-component
     Not a component but an @include shared by `strength-meter` and `password-input`. It draws
     the bars of a strength meter and accepts nothing a server could refuse. --}}
{{-- THE BARS OF A STRENGTH METER, one markup for both meters so the two look the same by
     construction. Each bar is drawn in its color before Alpine starts, so a page that has not
     booted yet shows the strength rather than an empty track, and follows `barStyle(i)` of the
     surrounding scope after that. The color ladder is utils/strength.js.

     Params: $count (how many bars), $colors (each bar's color before Alpine starts, a list).

     An unlit bar carries an edge in the strong border color, which a muted fill alone does not
     give it against the page; a lit bar has none. The same pair as strengthBarStyle(). --}}
@for ($wkBar = 0; $wkBar < $count; $wkBar++)
    @php($wkBarColor = $colors[$wkBar] ?? 'var(--color-wk-bg-muted)')
    <div
        class="h-1 flex-1 rounded-full transition-colors duration-[var(--transition-wk-duration)]"
        style="background-color: {{ $wkBarColor }}; box-shadow: {{ $wkBarColor === 'var(--color-wk-bg-muted)' ? 'inset 0 0 0 max(1px, var(--border-wk-width)) var(--color-wk-border-strong)' : 'none' }}"
        x-bind:style="barStyle({{ $wkBar }})"
    ></div>
@endfor
