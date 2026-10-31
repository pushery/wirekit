{{-- optimistic-ui: n/a — client-only
     A question about the overlay around it. It asks; the overlay acts on the answer. --}}
{{-- The question a modal or a drawer asks before the reader discards what they typed into it, in
     an alert dialog of its own rather than the browser's. Included by modal.blade.php and
     drawer.blade.php as a child of their root, with:

       $question    the question, in the reader's language or the caller's own words
       $lockScroll  the overlay's own setting, so a question over an overlay inside a page region
                    leaves the page scrolling as the overlay does
       $scope       the overlay's personalization scope

     The overlay opens it (utils/overlay.js, `_mayDiscard()`), finding it by the marker among its
     root's children. Stay is the dialog's Cancel: focused when the question opens, the answer
     Escape gives, and focus goes back to where it was in the overlay. Discard calls the overlay's
     `_discardChanges()`, which the button reaches through the scope it shares with the overlay:
     the question closes, and the overlay closes as the dismissal the reader started.

     A child of the root, not of the panel: the panel's focus trap takes in a teleported panel
     whose template sits inside it, and the question's has a trap of its own.
     Without the confirm shortcut: a chord the reader may still hold from the overlay below must not
     discard what they typed. --}}
<x-wirekit::alert-dialog data-wk-discard-question :lock-scroll="$lockScroll" :describedby="false" :confirm-shortcut="false" :scope="$scope">
    <x-wirekit::alert-dialog.title>{{ $question }}</x-wirekit::alert-dialog.title>
    <x-wirekit::alert-dialog.actions>
        <x-wirekit::alert-dialog.cancel>
            <x-wirekit::button intent="neutral" surface="ghost" size="sm">{{ __('wirekit::Stay') }}</x-wirekit::button>
        </x-wirekit::alert-dialog.cancel>
        <x-wirekit::button intent="danger" size="sm" x-on:click="_discardChanges()">{{ __('wirekit::Discard') }}</x-wirekit::button>
    </x-wirekit::alert-dialog.actions>
</x-wirekit::alert-dialog>
