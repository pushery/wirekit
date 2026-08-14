/**
 * WireKit Tiptap Editor Bundle (IIFE).
 *
 * A separate bundle from `wirekit.js` (the main bundle) so developers who load
 * the core bundle (`wirekit.core.js`) plus the rich-text editor can add JUST
 * the editor glue without pulling in the full overlay bundle (Floating UI +
 * focus-trap). Registers a single Alpine factory under `wirekitEditor` — the
 * name the `<x-wirekit::editor>` Blade template wires into x-data.
 *
 * This bundle ships ZERO editor-engine code (same shape as the ApexCharts
 * adapter). The engine is the developer's peer dependency (Tiptap recommended),
 * exposed as a `window.wirekitEditor(config)` factory before this script loads
 * (the legacy `window.tiptapEditor` name still works as a deprecated alias):
 *
 *   import { Editor } from '@tiptap/core';
 *   import StarterKit from '@tiptap/starter-kit';
 *   window.wirekitEditor = (config) => new Editor({ ...config, extensions: [StarterKit] });
 *
 * The editor is ALSO registered in the full `wirekit.js` bundle — this split
 * is purely additive for the core-bundle-plus-editor case.
 */
import wirekitEditor from './components/editor.js';
import { reportLateRegistration } from './utils/late-registration.js';

function registerEditorComponent() {
    Alpine.data('wirekitEditor', wirekitEditor);
}

// Primary path: register before Alpine.start() processes the DOM.
let reachedByInitEvent = false;
document.addEventListener('alpine:init', () => {
    reachedByInitEvent = true;
    registerEditorComponent();
});

// Fallback: if Alpine was already started before this script loaded, register
// immediately. Alpine.data() is idempotent — double-registration is safe, and it
// reaches DOM Alpine has not walked yet. An editor already rendered before this
// bundle arrived is beyond rescue, though, so say that rather than imply otherwise.
if (window.Alpine?.version) {
    registerEditorComponent();
    reportLateRegistration('wirekit-tiptap.js', () => reachedByInitEvent);
}
