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

if (alreadyHasAlpine()) {
    // eslint-disable-next-line no-console
    console.warn(
        '[wirekit-alpine] Alpine is already present on window — skipping '
        + 'self-contained bundle. Use dist/wirekit.js instead when BYO Alpine.',
    );
} else {
    Alpine.data('wirekitChart', wirekitChartJs);
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
    Alpine.data('wirekitCarousel', wirekitCarousel);
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

    // Expose Alpine on window so developers (and the docs site's replay
    // button) can call Alpine.initTree(element) for re-mounting.
    window.Alpine = Alpine;

    Alpine.start();
}
