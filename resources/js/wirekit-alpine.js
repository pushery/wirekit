/**
 * WireKit Alpine Bundle (IIFE).
 *
 * Self-contained drop-in: bundles Alpine.js core + every WireKit Alpine
 * component, registers them all, then calls Alpine.start() automatically.
 *
 * When to use this bundle vs. wirekit.js / wirekit.core.js:
 *
 *   wirekit.js          — your app already runs Alpine and registers
 *                         WireKit components yourself (current sample-app,
 *                         Laravel-Livewire setups). BYO Alpine.
 *
 *   wirekit-alpine.js   — you want a self-contained drop that gives
 *                         you Alpine + every WireKit primitive in one
 *                         tag (docs site iframe srcdoc, isolated preview
 *                         surfaces, sample landing pages).
 *
 *   wirekit.core.js     — you only need the chart component, no overlays.
 *
 * The two bundles are mutually compatible (loading both is a no-op once
 * Alpine is detected) but developers should pick exactly one. Loading
 * wirekit-alpine.js when Alpine is already on the page produces a console
 * warning and skips the second registration.
 *
 * Bundle size budget: Alpine core (~12KB gzip) + WireKit components
 * (~8KB gzip) ≈ ~20KB gzip.
 */

import Alpine from 'alpinejs';
import { registerAncestorDataMagic } from './utils/ancestor-data.js';
import { registerIndeterminateDirective } from './utils/indeterminate.js';
import collapse from '@alpinejs/collapse';
import { installOverlayRoot } from './utils/overlay-root.js';

import wirekitChartJs from './components/chart.js';
import wirekitDropdown from './components/dropdown.js';
import wirekitSubmenu from './components/submenu.js';
import wirekitTooltip from './components/tooltip.js';
import wirekitModal from './components/modal.js';
import wirekitDrawer from './components/drawer.js';
import wirekitToast from './components/toast.js';
import wirekitTreeView from './components/tree-view.js';
import wirekitHoverCard from './components/hover-card.js';
import wirekitOtpInput from './components/otp-input.js';
import wirekitTagsInput from './components/tags-input.js';
import wirekitInput from './components/input.js';
import wirekitNumberInput from './components/number-input.js';
import wirekitSidebarDisclosure from './components/sidebar-disclosure.js';
import wirekitAppRail from './components/app-rail.js';
import wirekitSidebarRail from './components/sidebar-rail.js';
import wirekitClipboardButton from './components/clipboard-button.js';
import wirekitReplayButton from './components/replay-button.js';
import wirekitAccordion from './components/accordion.js';
import wirekitTreeViewNode from './components/tree-view-node.js';
import wirekitScrollFade from './components/scroll-fade.js';
import wirekitScrollToTop from './components/scroll-to-top.js';
import wirekitDropdownTrigger from './components/dropdown-trigger.js';
import wirekitCodeBlock from './components/code-block.js';
import wirekitSlider from './components/slider.js';
import wirekitPasswordInput from './components/password-input.js';
import wirekitSegmentedControl from './components/segmented-control.js';
import wirekitPricingTable from './components/pricing-table.js';
import wirekitSortable from './components/sortable.js';
import wirekitReadingProgress from './components/reading-progress.js';
import wirekitFileUpload from './components/file-upload.js';
import wirekitTablist from './components/tablist.js';
import wirekitTabs from './components/tabs.js';
import wirekitCountdown from './components/countdown.js';
import wirekitReadingMeta from './components/reading-meta.js';
import wirekitReadingBookmark from './components/reading-bookmark.js';
import wirekitDevWarning from './components/dev-warning.js';
import wirekitDataTableColumnMenu from './components/data-table-column-menu.js';
import wirekitInlineEdit from './components/inline-edit.js';
import wirekitMultiSelect from './components/multi-select.js';
import wirekitCombobox from './components/combobox.js';
import wirekitDismissible from './components/dismissible.js';
import wirekitRating from './components/rating.js';
import wirekitReaction from './components/reaction.js';
import wirekitRangeSlider from './components/range-slider.js';
import wirekitPopover from './components/popover.js';
import wirekitScopeSwitcher from './components/scope-switcher.js';
import wirekitCommandPalette from './components/command-palette.js';
import wirekitContextMenu from './components/context-menu.js';
import wirekitMenubar from './components/menubar.js';
import wirekitNavigationMenu from './components/navigation-menu.js';
import wirekitAlertDialog from './components/alert-dialog.js';
import wirekitCarousel from './components/carousel.js';
import wirekitThemeController from './components/theme-controller.js';
import wirekitFab from './components/fab.js';
import wirekitCalendar from './components/calendar.js';
import wirekitTableSort from './components/table-sort.js';
import wirekitTour from './components/tour.js';
import wirekitResizableHandle from './components/resizable.js';
import wirekitImageCompare from './components/image-compare.js';
import wirekitLightbox from './components/lightbox.js';
import wirekitConversation from './components/conversation.js';
import wirekitAssistantMessage from './components/assistant-message.js';
import wirekitStatAnimate from './components/stat-animate.js';
import wirekitAnimate from './components/animate.js';
import wirekitReadingSpine from './components/reading-spine.js';
import wirekitReadingMinimap from './components/reading-minimap.js';
import wirekitReadingToc from './components/reading-toc.js';
import wirekitEditor from './components/editor.js';
import wirekitColorPicker from './components/color-picker.js';
import wirekitFilterBuilder from './components/filter-builder.js';
import wirekitStatusMatrix from './components/status-matrix.js';
import wirekitNotificationCenter from './components/notification-center.js';
import wirekitDataTable from './components/data-table.js';
import wirekitEventCalendar from './components/event-calendar.js';
import wirekitMap from './components/map.js';
import wirekitStickyPanelShadows from './components/sticky-panel.js';
import wirekitStream from './components/stream.js';

/**
 * Detect a pre-existing Alpine instance. Loading wirekit-alpine.js when
 * the developer's app ALSO loaded its own Alpine produces a "double
 * Alpine" runtime warning that's hard to debug — better to log a clean
 * console hint and skip our registration in that case.
 */
function alreadyHasAlpine() {
    return typeof window !== 'undefined'
        && typeof window.Alpine !== 'undefined'
        && typeof window.Alpine.version === 'string';
}

/**
 * The Alpine that will actually walk this page — the host's if it brought one, ours if not.
 *
 * THIS IS THE WHOLE FIX, AND THE BUG IT REPLACES WAS THE OPPOSITE READING. When an Alpine was
 * already present, this bundle logged a warning and skipped EVERY `Alpine.data(...)` call. Its
 * intent was right — two Alpines must not both start — but skipping the registrations threw
 * out the half that still had to happen. The page then had one running Alpine walking markup
 * full of `x-data="wirekitDropdown()"` that nothing anywhere had registered.
 *
 * The symptom was a page that renders perfectly and does nothing, which is the failure mode
 * this repository hunts. Measured on the sample's teleport-seam page under `?bundle=csp`:
 * 31 console errors, the readable ones being `wirekitDropdown is not defined`,
 * `wirekitAlertDialog is not defined`, `selected is not defined`, `dropdownOpen is not
 * defined` — one Alpine expression failure per component, each killing that component's init.
 * The thirteen `Illegal invocation` errors ahead of them came from inside `livewire.js`'s own
 * effect machinery, which is downstream damage and named a file with nothing to do with it.
 *
 * Registering on the host instead is what a self-contained bundle owes a page that already has
 * an Alpine: cooperate with the one that is running rather than compete with it and lose.
 */
const hostAlpine = alreadyHasAlpine() ? window.Alpine : null;

/** Where every registration below goes. */
const target = hostAlpine ?? Alpine;

// The overlay root goes in FIRST, and outside the branch below — it is plain DOM and
// has nothing to do with which Alpine won.
//
// It sat inside the else-branch, and that was a fatal in the exact configuration this
// bundle is most often reached for. A Livewire app already has Alpine, so this bundle
// skips itself by design — but the markup still teleports to `#wk-overlay-root`, and
// `x-teleport` on a selector that matches nothing does not warn, it throws inside
// Alpine's init walk. So one skipped container took the whole page's Alpine down with
// it, and the visible symptom was every OTHER component reporting "is not defined".
//
// Cheap when it is redundant (one div), and the difference between an inert bundle and
// a broken page when it is not.
installOverlayRoot();

    // Alpine's collapse plugin, registered before any component.
    //
    // Four components ask for `x-collapse` — collapsible, sidebar/group,
    // sidebar/collapsible and tree-view/node — and nothing registered it. Alpine
    // warns once per element and the directive does nothing, so the region
    // appeared and vanished instantly instead of animating, exactly as if the
    // animation had been chosen against. One of those files even says
    // "(already bundled)".
    //
    // Registering it costs 646 gzipped bytes on every bundle, including for
    // developers who render no disclosure at all. That is the trade, and it was
    // the owner's to make: the docs already promise the animation, so not paying
    // it means shipping a promise that is not true.
    // `collapse(Alpine)`, NOT `Alpine.plugin(collapse)`, and the difference is not
    // style. Alpine's own `plugin(cb)` is `cb(alpine_default)` — it hands the
    // callback its OWN module singleton and ignores the receiver. This bundle is
    // itself an installer, called as `Alpine.plugin(WireKit)` with an Alpine the
    // developer imported from their own build, so going through `plugin` would
    // register the directive on a DIFFERENT Alpine than the one running their
    // page. Calling the installer directly registers it on whichever Alpine is
    // actually in hand.
    // On the RUNNING Alpine — `target`, not the imported one. Registering the directive on a
    // module singleton the page never starts is exactly the defect the paragraph above warns
    // about for `Alpine.plugin`, and this line had it: with a host Alpine present, `x-collapse`
    // went to the bundled Alpine and four components lost their animation silently.
    collapse(target);

if (hostAlpine) {
    // Not an error, and not silent either. The page works after this — but the reason a
    // developer reaches for THIS bundle is usually the CSP guarantee, and that guarantee is
    // about which Alpine build evaluates expressions. The one now running is the host's, so
    // the guarantee is the host's to keep, not this bundle's to promise.
    console.warn(
        '[wirekit-alpine] An Alpine is already on this page (Livewire ships one), so WireKit '
        + 'registered its components on THAT Alpine instead of starting a second one. Two '
        + 'Alpines cannot share a DOM.\n'
        + 'Everything works — but if you chose this bundle for its CSP build, note that the '
        + 'Alpine now evaluating your expressions is the page\'s own, not this one. Load '
        + 'dist/wirekit.js against your existing Alpine to make that explicit.',
    );
}

{
    // Magics before components: a component's own expressions may use them.
    registerAncestorDataMagic(target);

    // `indeterminate` is a DOM property with no HTML attribute, so something has to
    // apply it after EVERY render — not only the first. See utils/indeterminate.js.
    registerIndeterminateDirective(target);

    target.data('wirekitChartJs', wirekitChartJs);
    target.data('wirekitDropdown', wirekitDropdown);
    target.data('wirekitSubmenu', wirekitSubmenu);
    target.data('wirekitTooltip', wirekitTooltip);
    target.data('wirekitModal', wirekitModal);
    target.data('wirekitDrawer', wirekitDrawer);
    target.data('wirekitToast', wirekitToast);
    target.data('wirekitTreeView', wirekitTreeView);
    target.data('wirekitHoverCard', wirekitHoverCard);
    target.data('wirekitOtpInput', wirekitOtpInput);
    target.data('wirekitTagsInput', wirekitTagsInput);
    target.data('wirekitInput', wirekitInput);
    target.data('wirekitNumberInput', wirekitNumberInput);
    target.data('wirekitSidebarDisclosure', wirekitSidebarDisclosure);
    target.data('wirekitAppRail', wirekitAppRail);
    target.data('wirekitSidebarRail', wirekitSidebarRail);
    target.data('wirekitClipboardButton', wirekitClipboardButton);
    target.data('wirekitReplayButton', wirekitReplayButton);
    target.data('wirekitAccordion', wirekitAccordion);
    target.data('wirekitTreeViewNode', wirekitTreeViewNode);
    target.data('wirekitScrollFade', wirekitScrollFade);
    target.data('wirekitScrollToTop', wirekitScrollToTop);
    target.data('wirekitDropdownTrigger', wirekitDropdownTrigger);
    target.data('wirekitCodeBlock', wirekitCodeBlock);
    target.data('wirekitSlider', wirekitSlider);
    target.data('wirekitPasswordInput', wirekitPasswordInput);
    target.data('wirekitSegmentedControl', wirekitSegmentedControl);
    target.data('wirekitPricingTable', wirekitPricingTable);
    target.data('wirekitSortable', wirekitSortable);
    target.data('wirekitReadingProgress', wirekitReadingProgress);
    target.data('wirekitFileUpload', wirekitFileUpload);
    target.data('wirekitTablist', wirekitTablist);
    target.data('wirekitTabs', wirekitTabs);
    target.data('wirekitCountdown', wirekitCountdown);
    target.data('wirekitReadingMeta', wirekitReadingMeta);
    target.data('wirekitReadingBookmark', wirekitReadingBookmark);
    target.data('wirekitDevWarning', wirekitDevWarning);
    target.data('wirekitDataTableColumnMenu', wirekitDataTableColumnMenu);
    target.data('wirekitInlineEdit', wirekitInlineEdit);
    target.data('wirekitMultiSelect', wirekitMultiSelect);
    target.data('wirekitRating', wirekitRating);
    target.data('wirekitReaction', wirekitReaction);
    target.data('wirekitCombobox', wirekitCombobox);
    target.data('wirekitDismissible', wirekitDismissible);
    target.data('wirekitRangeSlider', wirekitRangeSlider);
    target.data('wirekitPopover', wirekitPopover);
    target.data('wirekitScopeSwitcher', wirekitScopeSwitcher);
    target.data('wirekitCommandPalette', wirekitCommandPalette);
    target.data('wirekitContextMenu', wirekitContextMenu);
    target.data('wirekitMenubar', wirekitMenubar);
    target.data('wirekitNavigationMenu', wirekitNavigationMenu);
    target.data('wirekitAlertDialog', wirekitAlertDialog);
    target.data('wirekitCarousel', wirekitCarousel);
    target.data('wirekitThemeController', wirekitThemeController);
    target.data('wirekitFab', wirekitFab);
    target.data('wirekitCalendar', wirekitCalendar);
    target.data('wirekitTableSort', wirekitTableSort);
    target.data('wirekitTour', wirekitTour);
    target.data('wirekitResizableHandle', wirekitResizableHandle);
    target.data('wirekitImageCompare', wirekitImageCompare);
    target.data('wirekitLightbox', wirekitLightbox);
    target.data('wirekitConversation', wirekitConversation);
    target.data('wirekitAssistantMessage', wirekitAssistantMessage);
    target.data('wirekitStatAnimate', wirekitStatAnimate);
    target.data('wirekitAnimate', wirekitAnimate);
    target.data('wirekitReadingSpine', wirekitReadingSpine);
    target.data('wirekitReadingMinimap', wirekitReadingMinimap);
    target.data('wirekitReadingToc', wirekitReadingToc);
    target.data('wirekitEditor', wirekitEditor);
    target.data('wirekitColorPicker', wirekitColorPicker);
    target.data('wirekitFilterBuilder', wirekitFilterBuilder);
    target.data('wirekitStatusMatrix', wirekitStatusMatrix);
    target.data('wirekitNotificationCenter', wirekitNotificationCenter);
    target.data('wirekitDataTable', wirekitDataTable);
    target.data('wirekitEventCalendar', wirekitEventCalendar);
    target.data('wirekitMap', wirekitMap);
    target.data('wirekitStickyPanelShadows', wirekitStickyPanelShadows);
    target.data('wirekitStream', wirekitStream);

    // Expose Alpine on window so developers (and the docs site's replay
    // button) can call Alpine.initTree(element) for re-mounting.
    //
    // Only when this bundle's Alpine is the one running. With a host Alpine present it is
    // already on `window` and already started, and overwriting it would hand the page a
    // second, unstarted instance under the name everything else reads.
    if (! hostAlpine) {
        window.Alpine = Alpine;

        Alpine.start();
    }
}
