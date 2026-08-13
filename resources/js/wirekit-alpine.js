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
import wirekitSidebarRail from './components/sidebar-rail.js';
import wirekitClipboardButton from './components/clipboard-button.js';
import wirekitReplayButton from './components/replay-button.js';
import wirekitAccordion from './components/accordion.js';
import wirekitTreeViewNode from './components/tree-view-node.js';
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
    collapse(Alpine);

if (alreadyHasAlpine()) {

    console.warn(
        '[wirekit-alpine] Alpine is already present on window — skipping '
        + 'self-contained bundle. Use dist/wirekit.js instead when BYO Alpine.',
    );
} else {
    // Magics before components: a component's own expressions may use them.
    registerAncestorDataMagic(Alpine);

    Alpine.data('wirekitChartJs', wirekitChartJs);
    Alpine.data('wirekitDropdown', wirekitDropdown);
    Alpine.data('wirekitSubmenu', wirekitSubmenu);
    Alpine.data('wirekitTooltip', wirekitTooltip);
    Alpine.data('wirekitModal', wirekitModal);
    Alpine.data('wirekitDrawer', wirekitDrawer);
    Alpine.data('wirekitToast', wirekitToast);
    Alpine.data('wirekitTreeView', wirekitTreeView);
    Alpine.data('wirekitHoverCard', wirekitHoverCard);
    Alpine.data('wirekitOtpInput', wirekitOtpInput);
    Alpine.data('wirekitTagsInput', wirekitTagsInput);
    Alpine.data('wirekitInput', wirekitInput);
    Alpine.data('wirekitNumberInput', wirekitNumberInput);
    Alpine.data('wirekitSidebarDisclosure', wirekitSidebarDisclosure);
    Alpine.data('wirekitSidebarRail', wirekitSidebarRail);
    Alpine.data('wirekitClipboardButton', wirekitClipboardButton);
    Alpine.data('wirekitReplayButton', wirekitReplayButton);
    Alpine.data('wirekitAccordion', wirekitAccordion);
    Alpine.data('wirekitTreeViewNode', wirekitTreeViewNode);
    Alpine.data('wirekitScrollToTop', wirekitScrollToTop);
    Alpine.data('wirekitDropdownTrigger', wirekitDropdownTrigger);
    Alpine.data('wirekitCodeBlock', wirekitCodeBlock);
    Alpine.data('wirekitSlider', wirekitSlider);
    Alpine.data('wirekitPasswordInput', wirekitPasswordInput);
    Alpine.data('wirekitSegmentedControl', wirekitSegmentedControl);
    Alpine.data('wirekitPricingTable', wirekitPricingTable);
    Alpine.data('wirekitSortable', wirekitSortable);
    Alpine.data('wirekitReadingProgress', wirekitReadingProgress);
    Alpine.data('wirekitFileUpload', wirekitFileUpload);
    Alpine.data('wirekitTabs', wirekitTabs);
    Alpine.data('wirekitCountdown', wirekitCountdown);
    Alpine.data('wirekitReadingMeta', wirekitReadingMeta);
    Alpine.data('wirekitReadingBookmark', wirekitReadingBookmark);
    Alpine.data('wirekitDevWarning', wirekitDevWarning);
    Alpine.data('wirekitDataTableColumnMenu', wirekitDataTableColumnMenu);
    Alpine.data('wirekitInlineEdit', wirekitInlineEdit);
    Alpine.data('wirekitMultiSelect', wirekitMultiSelect);
    Alpine.data('wirekitRating', wirekitRating);
    Alpine.data('wirekitReaction', wirekitReaction);
    Alpine.data('wirekitCombobox', wirekitCombobox);
    Alpine.data('wirekitDismissible', wirekitDismissible);
    Alpine.data('wirekitRangeSlider', wirekitRangeSlider);
    Alpine.data('wirekitPopover', wirekitPopover);
    Alpine.data('wirekitCommandPalette', wirekitCommandPalette);
    Alpine.data('wirekitContextMenu', wirekitContextMenu);
    Alpine.data('wirekitMenubar', wirekitMenubar);
    Alpine.data('wirekitNavigationMenu', wirekitNavigationMenu);
    Alpine.data('wirekitAlertDialog', wirekitAlertDialog);
    Alpine.data('wirekitCarousel', wirekitCarousel);
    Alpine.data('wirekitThemeController', wirekitThemeController);
    Alpine.data('wirekitFab', wirekitFab);
    Alpine.data('wirekitCalendar', wirekitCalendar);
    Alpine.data('wirekitTableSort', wirekitTableSort);
    Alpine.data('wirekitTour', wirekitTour);
    Alpine.data('wirekitResizableHandle', wirekitResizableHandle);
    Alpine.data('wirekitImageCompare', wirekitImageCompare);
    Alpine.data('wirekitLightbox', wirekitLightbox);
    Alpine.data('wirekitConversation', wirekitConversation);
    Alpine.data('wirekitAssistantMessage', wirekitAssistantMessage);
    Alpine.data('wirekitStatAnimate', wirekitStatAnimate);
    Alpine.data('wirekitAnimate', wirekitAnimate);
    Alpine.data('wirekitReadingSpine', wirekitReadingSpine);
    Alpine.data('wirekitReadingMinimap', wirekitReadingMinimap);
    Alpine.data('wirekitReadingToc', wirekitReadingToc);
    Alpine.data('wirekitEditor', wirekitEditor);
    Alpine.data('wirekitColorPicker', wirekitColorPicker);
    Alpine.data('wirekitFilterBuilder', wirekitFilterBuilder);
    Alpine.data('wirekitStatusMatrix', wirekitStatusMatrix);
    Alpine.data('wirekitNotificationCenter', wirekitNotificationCenter);
    Alpine.data('wirekitDataTable', wirekitDataTable);
    Alpine.data('wirekitEventCalendar', wirekitEventCalendar);
    Alpine.data('wirekitMap', wirekitMap);
    Alpine.data('wirekitStickyPanelShadows', wirekitStickyPanelShadows);
    Alpine.data('wirekitStream', wirekitStream);

    // Expose Alpine on window so developers (and the docs site's replay
    // button) can call Alpine.initTree(element) for re-mounting.
    window.Alpine = Alpine;

    Alpine.start();
}
