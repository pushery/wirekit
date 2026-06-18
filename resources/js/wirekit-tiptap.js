/**
 * WireKit Tiptap Editor Bundle (IIFE).
 *
 * A separate bundle from `wirekit.js` (the main bundle) so developers who load
 * the core bundle (`wirekit.core.js`) plus the rich-text editor can add JUST
 * the editor glue without pulling in the full overlay bundle (Floating UI +
 * focus-trap). Registers a single Alpine factory under `wirekitEditor` — the
 * name the `<x-wirekit::editor>` Blade template wires into x-data.
 *
 * This bundle ships ZERO Tiptap code (same shape as the ApexCharts adapter).
 * Tiptap is the developer's peer dependency, exposed as a
 * `window.tiptapEditor(config)` factory before this script loads:
 *
 *   import { Editor } from '@tiptap/core';
 *   import StarterKit from '@tiptap/starter-kit';
 *   window.tiptapEditor = (config) => new Editor({ ...config, extensions: [StarterKit] });
 *
 * The editor is ALSO registered in the full `wirekit.js` bundle — this split
 * is purely additive for the core-bundle-plus-editor case.
 */
import wirekitEditor from './components/editor.js';

function registerEditorComponent() {
    Alpine.data('wirekitEditor', wirekitEditor);
}

// Primary path: register before Alpine.start() processes the DOM.
document.addEventListener('alpine:init', registerEditorComponent);

// Fallback: if Alpine was already started before this script loaded, register
// immediately. Alpine.data() is idempotent — double-registration is safe.
if (window.Alpine?.version) {
    registerEditorComponent();
}
