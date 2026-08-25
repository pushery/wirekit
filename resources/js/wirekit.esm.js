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
 * The shared runtime every WireKit component leans on, separated from the component
 * registrations so a developer who wants three components can obtain it without
 * referencing the other seventy-five.
 *
 * Called by the default installer AND by `register()`; it is idempotent, so using both
 * is harmless.
 */
export function installRuntime(Alpine) {
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

    registerAncestorDataMagic(Alpine);

    // `indeterminate` is a DOM property with no HTML attribute, so something has to
    // apply it after EVERY render — not only the first. See utils/indeterminate.js.
    registerIndeterminateDirective(Alpine);
}

/**
 * Register a CHOSEN SUBSET of components, plus the shared runtime.
 *
 *   import { register, wirekitDropdown, wirekitModal } from '.../dist/wirekit.esm.js';
 *   Alpine.plugin((Alpine) => register(Alpine, { wirekitDropdown, wirekitModal }));
 *
 * This is what makes the ESM bundle worth choosing over the IIFE one. Until it existed
 * the module had exactly ONE export — the default installer, which references all 78
 * factories — so no bundler could drop anything and a one-component app shipped the
 * whole graph: 235,403 bytes raw / 65.7 KB gzip, byte-for-byte the size of the plain
 * IIFE build. The docs advertised tree-shaking the whole time.
 *
 * The keys are the `x-data` names the Blade components emit, so passing the imported
 * bindings straight through is both the shortest and the correct spelling.
 */
export function register(Alpine, components) {
    installRuntime(Alpine);

    for (const [name, factory] of Object.entries(components)) {
        Alpine.data(name, factory);
    }
}

/**
 * The default installer: every component, unchanged.
 *
 * A developer who wants all of WireKit keeps `Alpine.plugin(WireKit)` and pays for the
 * whole library, which is the correct trade for that case — the subset path above is
 * for the one who does not.
 */
export default function (Alpine) {
    installRuntime(Alpine);

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
    Alpine.data('wirekitAppRail', wirekitAppRail);
    Alpine.data('wirekitSidebarRail', wirekitSidebarRail);
    Alpine.data('wirekitClipboardButton', wirekitClipboardButton);
    Alpine.data('wirekitReplayButton', wirekitReplayButton);
    Alpine.data('wirekitAccordion', wirekitAccordion);
    Alpine.data('wirekitTreeViewNode', wirekitTreeViewNode);
    Alpine.data('wirekitScrollFade', wirekitScrollFade);
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
    Alpine.data('wirekitTablist', wirekitTablist);
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
    Alpine.data('wirekitScopeSwitcher', wirekitScopeSwitcher);
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

/*
 * Every factory by name, so a bundler can keep the three a developer imports and drop
 * the seventy-five they do not. Generated from the import list above; the two must stay
 * in step, which `EsmBundleExportsEveryComponentTest` asserts.
 */
export {
    wirekitChartJs,
    wirekitDropdown,
    wirekitSubmenu,
    wirekitTooltip,
    wirekitModal,
    wirekitDrawer,
    wirekitToast,
    wirekitTreeView,
    wirekitHoverCard,
    wirekitOtpInput,
    wirekitTagsInput,
    wirekitInput,
    wirekitNumberInput,
    wirekitSidebarDisclosure,
    wirekitAppRail,
    wirekitSidebarRail,
    wirekitClipboardButton,
    wirekitReplayButton,
    wirekitAccordion,
    wirekitTreeViewNode,
    wirekitScrollFade,
    wirekitScrollToTop,
    wirekitDropdownTrigger,
    wirekitCodeBlock,
    wirekitSlider,
    wirekitPasswordInput,
    wirekitSegmentedControl,
    wirekitPricingTable,
    wirekitSortable,
    wirekitReadingProgress,
    wirekitFileUpload,
    wirekitTablist,
    wirekitTabs,
    wirekitCountdown,
    wirekitReadingMeta,
    wirekitReadingBookmark,
    wirekitDevWarning,
    wirekitDataTableColumnMenu,
    wirekitInlineEdit,
    wirekitMultiSelect,
    wirekitCombobox,
    wirekitDismissible,
    wirekitRating,
    wirekitReaction,
    wirekitRangeSlider,
    wirekitPopover,
    wirekitScopeSwitcher,
    wirekitCommandPalette,
    wirekitContextMenu,
    wirekitMenubar,
    wirekitNavigationMenu,
    wirekitAlertDialog,
    wirekitCarousel,
    wirekitThemeController,
    wirekitFab,
    wirekitCalendar,
    wirekitTableSort,
    wirekitTour,
    wirekitResizableHandle,
    wirekitImageCompare,
    wirekitLightbox,
    wirekitConversation,
    wirekitAssistantMessage,
    wirekitStatAnimate,
    wirekitAnimate,
    wirekitReadingSpine,
    wirekitReadingMinimap,
    wirekitReadingToc,
    wirekitEditor,
    wirekitColorPicker,
    wirekitFilterBuilder,
    wirekitStatusMatrix,
    wirekitNotificationCenter,
    wirekitDataTable,
    wirekitEventCalendar,
    wirekitMap,
    wirekitStickyPanelShadows,
    wirekitStream,
};
