/**
 * WireKit Full Bundle (IIFE).
 *
 * Contains all Alpine components including overlay components.
 * Bundles Floating UI and focus-trap — no user install needed.
 */
import { position } from './utils/floating.js';
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
import { reportLateRegistration } from './utils/late-registration.js';
import wirekitStream from './components/stream.js';

/**
 * Register all Alpine.data() components.
 * Called via alpine:init (normal path) or immediately if Alpine already started
 * (fallback for late-loading scripts or non-Livewire setups).
 */
function registerComponents() {
    // The overlay root BEFORE anything else: every teleported panel targets it, and
    // `x-teleport` treats a selector that matches nothing as fatal — so it has to exist
    // before Alpine walks the DOM, not merely before the first panel opens.
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
    // Narrower than the report, and worth writing down: in a LIVEWIRE app this
    // already worked, because Livewire bundles the same plugin and registers it
    // (`Alpine.directive("collapse", …)` in its own dist). The four components
    // were dead only where Livewire is absent — an Alpine-only app on
    // `wirekit-alpine.js`, or `wirekit.js` beside a bare Alpine.
    //
    // Registering it here is still right, for a reason the report did not give:
    // the components must not depend on a peer happening to provide a plugin
    // they ask for. Measured cost is +1397 bytes raw, +585 gzip on the main
    // bundle, paid by every developer including those who render no disclosure.
    // That trade was the owner's to make.
    //
    // `collapse(Alpine)`, NOT `Alpine.plugin(collapse)`, and the difference is
    // not style. Alpine's own `plugin(cb)` is `cb(alpine_default)` — it hands the
    // callback its OWN module singleton, ignoring the receiver. In the ESM
    // bundle, which is itself an installer a developer calls as
    // `Alpine.plugin(WireKit)` with an Alpine imported from their build, that
    // registers the directive on a DIFFERENT Alpine than the one running their
    // page. Calling the installer directly registers it on whichever Alpine is
    // actually in hand, which is the only one that can be right.
    collapse(Alpine);

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
    Alpine.data('wirekitTableSort', wirekitTableSort);
    Alpine.data('wirekitCarousel', wirekitCarousel);
    Alpine.data('wirekitThemeController', wirekitThemeController);
    Alpine.data('wirekitFab', wirekitFab);
    Alpine.data('wirekitCalendar', wirekitCalendar);
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
}

// Primary path: register before Alpine.start() processes the DOM.
let reachedByInitEvent = false;
document.addEventListener('alpine:init', () => {
    reachedByInitEvent = true;
    registerComponents();
});

// Fallback: Alpine may already be on the page (non-Livewire setups, late script
// loading). Registering now still reaches DOM Alpine has not walked YET — a Livewire
// morph, anything appended later — and Alpine.data() is idempotent, so this stays.
// What it cannot do is repair markup Alpine already walked: those elements are dead
// and a re-walk does not revive them. reportLateRegistration says so once, and only
// when such markup is actually on the page.
if (window.Alpine?.version) {
    registerComponents();
    reportLateRegistration('wirekit.js', () => reachedByInitEvent);
}

// Positioning helper, exposed globally for components whose Alpine logic lives
// INLINE in their Blade view and therefore has no module scope to import from —
// combobox is the case that forced this (291 lines of inline x-data). Same shape
// as the existing `window.wirekitEditor` factory. Without it such a component has
// to hand-roll flip/shift positioning, and two implementations of the same
// geometry drift apart. Assigned unconditionally so it is available whether Alpine
// starts before or after this bundle loads.
window.wirekitPosition = position;
