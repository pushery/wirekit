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
import { registerAncestorDataMagic } from './utils/ancestor-data.js';
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

export default function (Alpine) {
    // Inline-Alpine components (combobox, data-table column menu) read this global
    // for panel positioning — they have no module scope to import from. Mirrors
    // the full IIFE bundle. Guarded so it is set once even on repeat plugin use.
    if (typeof window !== 'undefined') {
        window.wirekitPosition = position;
    }

    // Magics before components: a component's own expressions may use them.

    // The overlay root BEFORE anything else: every teleported panel targets it, and
    // `x-teleport` treats a selector that matches nothing as fatal.
    installOverlayRoot();

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
