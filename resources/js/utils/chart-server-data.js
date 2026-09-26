/**
 * A chart follows the labels and series its component renders on a Livewire update.
 *
 * The chart root carries `wire:ignore`: without it the morph would tear down the drawn chart on
 * every round trip. The price was that nothing new ever reached it either, attributes included,
 * so a page that filtered a table and a chart showed the new period in the table and the old
 * one in the chart. The component now renders its data half on a `<template>` right after the
 * root, outside the ignored element; the morph updates that attribute like any other, and this
 * reports the new payload to the factory, which updates the chart in place.
 *
 * An attribute and an observer rather than a Livewire hook, for the reason the indeterminate
 * directive gives: the attribute is written by the server on every render and by nobody else, so
 * a change to it IS the server's change, whatever wrote it, and the chart needs no Livewire to
 * be correct.
 *
 * Returns the function that stops following, or null when there is nothing to follow.
 */
export const WK_CHART_DATA_ATTRIBUTE = 'data-wk-chart-data';

/**
 * @param {Element|null|undefined} root  the chart's root element
 * @param {(payload: object) => void} onData  called with each NEW payload, never the first
 * @returns {(() => void)|null}
 */
export function followChartServerData(root, onData) {
    const carrier = root?.nextElementSibling;

    if (! carrier || ! carrier.hasAttribute?.(WK_CHART_DATA_ATTRIBUTE) || typeof MutationObserver === 'undefined') {
        return null;
    }

    let last = carrier.getAttribute(WK_CHART_DATA_ATTRIBUTE);

    const observer = new MutationObserver(() => {
        const value = carrier.getAttribute(WK_CHART_DATA_ATTRIBUTE);

        // The morph rewrites attributes it did not change as well; only a different payload
        // is news, and redrawing the same series would replay its animation for nothing.
        if (value === null || value === last) {
            return;
        }

        last = value;

        let payload;

        try {
            payload = JSON.parse(value);
        } catch {
            return;
        }

        if (payload && typeof payload === 'object') {
            onData(payload);
        }
    });

    observer.observe(carrier, { attributes: true, attributeFilter: [WK_CHART_DATA_ATTRIBUTE] });

    return () => observer.disconnect();
}
