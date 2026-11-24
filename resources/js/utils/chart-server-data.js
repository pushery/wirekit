/**
 * A chart follows the labels and series its component renders on a Livewire update.
 *
 * The chart root carries `wire:ignore`: without it the morph would tear down the drawn chart on
 * every round trip. The price is that nothing new reaches it either, attributes included, so a
 * page that filters a table and a chart would show the new period in the table and the old one
 * in the chart. So the component renders its data half on a `<template>` right after the root,
 * outside the ignored element; the morph updates that attribute like any other, and this
 * reports the new payload to the factory, which updates the chart in place. The chart's name,
 * which describes that data, travels with it and is set on the root here.
 *
 * An attribute and an observer rather than a Livewire hook, for the reason the indeterminate
 * directive gives: the attribute is written by the server on every render and by nobody else, so
 * a change to it is the server's change, whatever wrote it, and the chart needs no Livewire to
 * be correct.
 *
 * Returns the function that stops following, or null when there is nothing to follow.
 */
export const WK_CHART_DATA_ATTRIBUTE = 'data-wk-chart-data';

/**
 * The payload the component renders beside its chart right now, or null where it renders none.
 *
 * @param {Element|null|undefined} root  the chart's root element
 * @returns {string|null}
 */
export function chartServerDataNow(root) {
    const carrier = root?.nextElementSibling;

    return carrier?.hasAttribute?.(WK_CHART_DATA_ATTRIBUTE) ? carrier.getAttribute(WK_CHART_DATA_ATTRIBUTE) : null;
}

/**
 * @param {Element|null|undefined} root  the chart's root element
 * @param {(payload: object) => void} onData  called with each new payload, never the first
 * @param {string|null} [since]  the payload the chart was built from, read when it started; a
 *   newer one that arrived before the chart was built is reported at once
 * @returns {(() => void)|null}
 */
export function followChartServerData(root, onData, since = null) {
    const carrier = root?.nextElementSibling;

    if (! carrier || ! carrier.hasAttribute?.(WK_CHART_DATA_ATTRIBUTE) || typeof MutationObserver === 'undefined') {
        return null;
    }

    let last = since ?? carrier.getAttribute(WK_CHART_DATA_ATTRIBUTE);

    const report = () => {
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
            // The name follows the data. It sits on the ignored root, where the morph leaves it,
            // and a name that summarizes the data would go on describing the data the chart
            // loaded with.
            if (typeof payload.ariaLabel === 'string' && root.getAttribute('aria-label') !== payload.ariaLabel) {
                root.setAttribute('aria-label', payload.ariaLabel);
            }

            onData(payload);
        }
    };

    const observer = new MutationObserver(report);
    observer.observe(carrier, { attributes: true, attributeFilter: [WK_CHART_DATA_ATTRIBUTE] });

    // A chart is built a few tasks after it starts, one chart per task, and an update can land in
    // between; its payload is news to a chart built from the one before.
    report();

    return () => observer.disconnect();
}
