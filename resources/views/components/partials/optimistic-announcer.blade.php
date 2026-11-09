{{-- optimistic-ui: n/a — sub-component
     The live region of an optimistic field, shared by the fields where it stands last. It is
     rendered from the first paint and starts empty: a live region that arrives together with its
     text is a new node, and nothing is announced. A field includes it with `@includeWhen`, which
     is no block to Livewire, so no morph markers stand around it. --}}
<div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
