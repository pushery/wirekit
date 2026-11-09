{{-- optimistic-ui: n/a — presentational
     A glyph drawn inside the collapse controls of the sidebar and the app rail; it holds no value
     a server could accept or refuse. --}}
{{-- The collapse glyph, drawn by the sidebar's own control, by `sidebar.collapse-toggle` and by the
     app rail's toggle, so every control that folds a navigation column reads as the same button.

     A panel: the frame is the column, the rule inside it stands on the column's own side, and only
     the arrow turns. It points the way the column is about to move, so on the trailing side the two
     states swap, and the edge stays where the column is in both.

     The turn is written for the first paint as well. The arrow of a column that renders folded is
     turned in the markup, and the binding switches the class from there, so the arrow does not spin
     round when the script starts.

     Reads from the including scope: `$side` ('start' or 'end'), and `$collapsed`, the state the
     server rendered. `$glyphCollapsedExpr` names that state in the including Alpine scope,
     `collapsed` by default; the rail passes `! expanded`. --}}
@php
    $glyphOnEnd = ($side ?? 'start') === 'end';
    $glyphCollapsedExpr = $glyphCollapsedExpr ?? 'collapsed';
    $glyphCollapsedNow = (bool) ($glyphCollapsedNow ?? ($collapsed ?? false));

    // Written out per side. The arrow sits in the half away from the rule and is drawn pointing
    // left, the direction the turn below has always assumed, so its states swap on the trailing side.
    $glyphEdge = $glyphOnEnd ? 'M15 3.75v16.5' : 'M9 3.75v16.5';
    $glyphArrow = $glyphOnEnd ? 'M10.5 9.75 8.25 12l2.25 2.25' : 'M15.75 9.75 13.5 12l2.25 2.25';

    // Object syntax, so the binding removes the class the markup wrote when the state changes: a
    // ternary hands Alpine one class to add, and Alpine only takes away what it added itself. The
    // negation is built from the name, so the expression stays a name or a negated name.
    $glyphNegated = str_starts_with($glyphCollapsedExpr, '! ') ? substr($glyphCollapsedExpr, 2) : '! '.$glyphCollapsedExpr;
    $glyphTurn = "{ 'rotate-180': ".($glyphOnEnd ? $glyphNegated : $glyphCollapsedExpr).' }';
    $glyphTurnedNow = $glyphOnEnd ? ! $glyphCollapsedNow : $glyphCollapsedNow;
@endphp
<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h12A2.25 2.25 0 0 1 20.25 6v12A2.25 2.25 0 0 1 18 20.25H6A2.25 2.25 0 0 1 3.75 18V6Z" />
    <path stroke-linecap="round" d="{{ $glyphEdge }}" />
    <path stroke-linecap="round" stroke-linejoin="round" @class(['origin-center [transform-box:fill-box] transition-transform duration-[var(--transition-wk-duration)]', 'rotate-180' => $glyphTurnedNow]) :class="{{ $glyphTurn }}" d="{{ $glyphArrow }}" />
</svg>
