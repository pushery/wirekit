{{-- optimistic-ui: n/a — presentational
     Not a component but an @include of `calendar`, which sizes its table with it; it holds no
     control and no state. --}}
{{-- The row that tells a calendar's table how wide its columns want to be.

     A day button is `w-full`, so on its own it asks for no more than the minimum target, and a
     calendar inside a container that sizes to its content (a flex item, an inline block, a
     fit-content frame) would shrink to that minimum on a desktop. Each cell here holds two
     halves of a day's column side by side: together they ask for the full column, a day at the
     size token of the calendar's `size` plus its cell's padding, where there is room, and since
     the line may break between them they ask for half of it where there is not, which is less
     than a day's minimum. The column of week numbers asks for nothing and takes the width of its
     numbers. Nothing of the row shows or is announced: no height, hidden from assistive
     technology, and nothing in it takes focus. --}}
@php
    // Written out per size so that a Tailwind scan finds every class whole.
    $sizingHalf = match ($size ?? 'md') {
        'sm' => 'w-[calc((var(--size-wk-sm)+0.25rem)/2)]',
        'lg' => 'w-[calc((var(--size-wk-lg)+0.25rem)/2)]',
        default => 'w-[calc((var(--size-wk-md-compact)+0.25rem)/2)]',
    };
@endphp
<tfoot aria-hidden="true">
    <tr>
        @if($weekNumbers ?? false)
            <td data-wk-prose-skip class="p-0"></td>
        @endif
        @for ($column = 0; $column < 7; $column++)
            <td data-wk-prose-skip class="p-0"><span class="block h-0 overflow-hidden"><span class="inline-block {{ $sizingHalf }}"></span><span class="inline-block {{ $sizingHalf }}"></span></span></td>
        @endfor
    </tr>
</tfoot>
