{{-- optimistic-ui: n/a — presentational
     Not a component but an @include of `calendar`, which sizes its table with it; it holds no
     control and no state. --}}
{{-- The row that tells a calendar's table how wide its columns want to be.

     A day button is `w-full`, so on its own it asks for no more than the minimum target, and a
     calendar inside a container that sizes to its content (a flex item, an inline block, a
     fit-content frame) would shrink to that minimum on a desktop. Each cell here holds two
     halves of a day's column side by side: together they ask for the full column, a day at the
     token's size plus its cell's padding, where there is room, and since the line may break
     between them they ask for half of it where there is not, which is less than a day's
     minimum. Nothing of the row shows or is announced: no height, hidden from assistive
     technology, and nothing in it takes focus. --}}
<tfoot aria-hidden="true">
    <tr>
        @for ($column = 0; $column < 7; $column++)
            <td data-wk-prose-skip class="p-0"><span class="block h-0 overflow-hidden"><span class="inline-block w-[calc((var(--size-wk-md-compact)+0.25rem)/2)]"></span><span class="inline-block w-[calc((var(--size-wk-md-compact)+0.25rem)/2)]"></span></span></td>
        @endfor
    </tr>
</tfoot>
