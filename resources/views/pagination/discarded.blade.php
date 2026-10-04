{{-- Renders nothing. The two Livewire pager views make this the paginator's default simple view
     while Blade escapes the paginator they bind to the pagination component: Blade escapes a bound
     object it can cast to a string by rendering it, and a cursor paginator renders through that
     default, which Livewire has set to the pager view itself. The result is thrown away. --}}
