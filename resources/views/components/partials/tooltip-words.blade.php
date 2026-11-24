{{-- optimistic-ui: n/a — sub-component
     The words a lazy tooltip describes its trigger with: what its panel says, hidden beside the
     trigger while the panel waits out of the page, and after the panel is built. The id is the
     panel's with `-words` after it, so the trigger names an element from the first render on and
     no two elements carry one id. The shortcut's keys follow the words, and the factory keeps them
     to the ones the panel shows (`_keepTheShownKeysOnly()`). Included with the tooltip's
     variables. --}}
<span id="{{ $tooltipId }}-words" hidden data-wk-tooltip-words x-ref="words">
    {{ $tipDescribedWords }}
    @wirekitLast($tipShortcutKeys !== null)
        <x-wirekit::kbd :shortcut="$tipShortcut" size="sm" :scope="$scope" />
    @endwirekitLast
</span>
