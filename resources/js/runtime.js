/**
 * The shared runtime every WireKit Alpine component leans on: the magics, the directives and the
 * page-wide installers, without a single component factory. `wirekit.esm.js` installs it from
 * `register()` and from its default installer, and the per-page bundle (`wirekit.auto.js`) starts
 * from it, so a page that renders three components loads none of the others.
 */
import { position } from './utils/floating.js';
import { registerAncestorDataMagic } from './utils/ancestor-data.js';
import { registerScrollToMagic } from './utils/scroll-to.js';
import { registerInlineStepMagic } from './utils/inline-direction.js';
import { registerPagerFocusMagics } from './utils/pager-focus.js';
import { registerIndeterminateDirective } from './utils/indeterminate.js';
import { registerGroupedDigitsDirective } from './utils/grouped-digits.js';
import { registerCallerRefDirective } from './utils/caller-ref.js';
import { registerCallerModelDirective } from './utils/caller-model.js';
import { registerImeDirective } from './utils/ime.js';
import { registerFlashDirective } from './utils/flash.js';
import { registerScrollIntoViewDirective } from './utils/scroll-into-view.js';
import { registerStuckDirective } from './utils/stuck.js';
import { registerAnnounceBusyDirective } from './utils/busy-announcer.js';
import { registerCounterDirective } from './utils/counter.js';
import { registerTableLabelsDirective } from './utils/table-labels.js';
import { registerDoubleTapDirective } from './utils/double-tap.js';
import { registerRangeSelectDirective } from './utils/range-select.js';
import { registerInsertTokenDirective } from './utils/insert-token.js';
import { registerUnsavedDirective } from './utils/unsaved.js';
import { registerCurrentDirective } from './utils/current.js';
import { defineShortcut, registerShortcutDirective } from './utils/shortcuts.js';
import { registerFindableDirective } from './utils/findable.js';
import { registerFitTextDirective } from './utils/fit-text.js';
import { registerFocusFirstDirective } from './utils/focus-first.js';
import { registerFocusUnitDirective } from './utils/focus-unit.js';
import { registerClearedFieldMemory } from './utils/cleared-field.js';
import { registerFieldValidity } from './utils/field-validity.js';
import { installAlertArrival } from './utils/alert-arrival.js';
import { installArrowFocus } from './utils/arrow-focus.js';
import { installScrollInset } from './utils/scroll-inset.js';
import { installFocusInvalid } from './utils/focus-invalid.js';
import { installSubmitOnce } from './utils/submit-once.js';
import { installCanceledTransitionHandler } from './utils/canceled-transition.js';
import collapse from '@alpinejs/collapse';
import { installOverlayRoot } from './utils/overlay-root.js';

/**
 * The shared runtime every WireKit component leans on, separated from the component
 * registrations so a developer who wants three components can obtain it without
 * referencing every other one.
 *
 * Called by the default installer and by `register()`; it is idempotent, so using both
 * is harmless.
 */
export function installRuntime(Alpine) {
    // Inline-Alpine components (combobox, data-table column menu) read this global
    // for panel positioning — they have no module scope to import from. Mirrors
    // the full IIFE bundle. Guarded so it is set once even on repeat plugin use.
    if (typeof window !== 'undefined') {
        window.wirekitPosition = position;
        // A shortcut that works out what it does registers here, as on the full bundle; an
        // importing app takes `defineShortcut` from the exports below.
        window.wirekitShortcut = defineShortcut;
    }

    // The overlay root before anything else: every teleported panel targets it, and
    // `x-teleport` treats a selector that matches nothing as fatal.
    installOverlayRoot();

    // Alpine's collapse plugin, registered before any component.
    //
    // The disclosure components ask for `x-collapse` (`grep -rl x-collapse
    // resources/views` lists them). Without the plugin Alpine warns once per element
    // and the directive does nothing, so a region appears and vanishes at once
    // instead of animating.
    //
    // The cost is part of every bundle's size in `dist/README.md`, paid by
    // developers who render no disclosure as well. The docs promise the animation,
    // so not paying it would ship a promise that is not true.
    // `collapse(Alpine)`, not `Alpine.plugin(collapse)`, and the difference is not
    // style. Alpine's own `plugin(cb)` is `cb(alpine_default)` — it hands the
    // callback its own module singleton and ignores the receiver. This bundle is
    // itself an installer, called as `Alpine.plugin(WireKit)` with an Alpine the
    // developer imported from their own build, so going through `plugin` would
    // register the directive on a different Alpine than the one running their
    // page. Calling the installer directly registers it on whichever Alpine is
    // actually in hand.
    collapse(Alpine);

    // Magics before components: a component's own expressions may use them.
    registerAncestorDataMagic(Alpine);
    registerScrollToMagic(Alpine);
    registerInlineStepMagic(Alpine);
    // A page turned under Livewire keeps the focus in its pager. See utils/pager-focus.js.
    registerPagerFocusMagics(Alpine);

    // `indeterminate` is a DOM property with no HTML attribute, so something has to
    // apply it after every render — not only the first. See utils/indeterminate.js.
    registerIndeterminateDirective(Alpine);
    // An amount field that groups its digits while it is typed, and whose value stays the
    // plain number. See utils/grouped-digits.js.
    registerGroupedDigitsDirective(Alpine);
    // An `x-ref` a caller writes on a component reaches the caller's `$refs`, not a
    // root the component renders. See utils/caller-ref.js.
    registerCallerRefDirective(Alpine);
    // An `x-model` a caller writes on a component whose field stands inside a root of the
    // component's own is read in the caller's scope. See utils/caller-model.js.
    registerCallerModelDirective(Alpine);
    // A key press that belongs to an input method's composition stops at the field. See
    // utils/ime.js.
    registerImeDirective(Alpine);
    registerFlashDirective(Alpine);
    // A result that appears after an action comes into view. See utils/scroll-into-view.js.
    registerScrollIntoViewDirective(Alpine);
    // A sticky element says when it rests at its line. See utils/stuck.js.
    registerStuckDirective(Alpine);
    // A button's wait is announced once it lasts. See utils/busy-announcer.js.
    registerAnnounceBusyDirective(Alpine);
    // A field's character count, counted in the browser. See utils/counter.js.
    registerCounterDirective(Alpine);
    // The cells of a table that stacks its rows, named by their columns. See utils/table-labels.js.
    registerTableLabelsDirective(Alpine);
    // A range of checkboxes picked with Shift held. See utils/range-select.js.
    registerRangeSelectDirective(Alpine);
    // Two taps on one element, as a touch screen and a mouse send them. See utils/double-tap.js.
    registerDoubleTapDirective(Alpine);
    // The tokens a button puts into an input, without a request. See utils/insert-token.js.
    registerInsertTokenDirective(Alpine);
    // A form that holds what its server does not have yet says so. See utils/unsaved.js.
    registerUnsavedDirective(Alpine);
    // A navigation Livewire keeps across a visit marks the page it arrived at. See utils/current.js.
    registerCurrentDirective(Alpine);
    // Keyboard shortcuts on the elements they act on, by area. See utils/shortcuts.js.
    registerShortcutDirective(Alpine);
    // Disclosure panels the browser find in page can open. See utils/findable.js.
    registerFindableDirective(Alpine);
    // A fitted text whose words a script writes keeps its copies in step. See utils/fit-text.js.
    registerFitTextDirective(Alpine);
    // A menu a button opens takes the focus to its first entry. See utils/focus-first.js.
    registerFocusFirstDirective(Alpine);
    // A control made of several parts fires `focus` and `blur` on its root as one field. See
    // utils/focus-unit.js.
    registerFocusUnitDirective(Alpine);
    // A field Livewire emptied after a successful action is not the reader's mistake.
    // See utils/cleared-field.js.
    registerClearedFieldMemory();
    // A field the browser holds invalid says why under the red it turns. See utils/field-validity.js.
    registerFieldValidity();
    // An alert a Livewire render adds is announced. See utils/alert-arrival.js.
    installAlertArrival();
    // A surface pinned to an edge keeps a focused element and a fragment's target clear of it.
    // See utils/scroll-inset.js.
    installScrollInset();
    // A radio the arrow keys reach keeps its focus ring in WebKit. See utils/arrow-focus.js.
    installArrowFocus();
    // `wirekit:focus-invalid` takes the focus to the first rejected field, opening what hides it.
    // See utils/focus-invalid.js.
    installFocusInvalid();
    // A form with `data-wk-submit-once` sends itself once until the page changes. See
    // utils/submit-once.js.
    installSubmitOnce();
    // A panel opened again while it closes reports no unhandled rejection: Alpine handles the
    // hide it cancels a frame late. See utils/canceled-transition.js.
    installCanceledTransitionHandler();
}
