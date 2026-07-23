/**
 * WireKit ESM Bundle.
 *
 * For power users who want tree-shaking via their own build pipeline.
 *
 * Usage in app.js:
 *   import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
 *   import WireKit from '../../vendor/pushery/wirekit/dist/wirekit.esm.js';
 *   Alpine.plugin(WireKit);
 *   Livewire.start();
 */
import { position } from './utils/floating.js';
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

export default function (Alpine) {
    // Inline-Alpine components (combobox, data-table column menu) read this global
    // for panel positioning — they have no module scope to import from. Mirrors
    // the full IIFE bundle. Guarded so it is set once even on repeat plugin use.
    if (typeof window !== 'undefined') {
        window.wirekitPosition = position;
    }

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
