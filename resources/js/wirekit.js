/**
 * WireKit Full Bundle (IIFE).
 *
 * Contains all Alpine components including overlay components.
 * Bundles Floating UI and focus-trap — no user install needed.
 */
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
import wirekitMultiSelect from './components/multi-select.js';
import wirekitRangeSlider from './components/range-slider.js';
import wirekitPopover from './components/popover.js';
import wirekitCommandPalette from './components/command-palette.js';
import wirekitContextMenu from './components/context-menu.js';
import wirekitMenubar from './components/menubar.js';
import wirekitNavigationMenu from './components/navigation-menu.js';
import wirekitAlertDialog from './components/alert-dialog.js';
import wirekitCarousel from './components/carousel.js';
import wirekitCalendar from './components/calendar.js';
import wirekitTableSort from './components/table-sort.js';
import wirekitTour from './components/tour.js';
import wirekitResizableHandle from './components/resizable.js';
import wirekitImageCompare from './components/image-compare.js';
import wirekitLightbox from './components/lightbox.js';
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

/**
 * Register all Alpine.data() components.
 * Called via alpine:init (normal path) or immediately if Alpine already started
 * (fallback for late-loading scripts or non-Livewire setups).
 */
function registerComponents() {
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
    Alpine.data('wirekitMultiSelect', wirekitMultiSelect);
    Alpine.data('wirekitRangeSlider', wirekitRangeSlider);
    Alpine.data('wirekitPopover', wirekitPopover);
    Alpine.data('wirekitCommandPalette', wirekitCommandPalette);
    Alpine.data('wirekitContextMenu', wirekitContextMenu);
    Alpine.data('wirekitMenubar', wirekitMenubar);
    Alpine.data('wirekitNavigationMenu', wirekitNavigationMenu);
    Alpine.data('wirekitAlertDialog', wirekitAlertDialog);
    Alpine.data('wirekitTableSort', wirekitTableSort);
    Alpine.data('wirekitCarousel', wirekitCarousel);
    Alpine.data('wirekitCalendar', wirekitCalendar);
    Alpine.data('wirekitTour', wirekitTour);
    Alpine.data('wirekitResizableHandle', wirekitResizableHandle);
    Alpine.data('wirekitImageCompare', wirekitImageCompare);
    Alpine.data('wirekitLightbox', wirekitLightbox);
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
}

// Primary path: register before Alpine.start() processes the DOM.
document.addEventListener('alpine:init', registerComponents);

// Fallback: if Alpine was already started before this script loaded
// (e.g. non-Livewire setups, late script loading), register immediately.
// Alpine.data() is idempotent — double-registration is safe.
if (window.Alpine?.version) {
    registerComponents();
}
