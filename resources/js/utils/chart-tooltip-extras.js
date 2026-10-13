/**
 * Further values a chart's tooltip names for each point, without a series of their own.
 *
 * The server writes them in the application's locale and sends them with the chart's data as
 * `wkTooltipExtras`: a list of `{ label, values }`, one value per point, null where a point has
 * none (`Pushery\WireKit\Charts\ChartTooltipExtras`). A chart factory takes them out of the
 * configuration before the library sees them and adds a row for each to the tooltip of a point.
 */

/**
 * Take the extras out of a chart's configuration, so the library never reads a key it does not know.
 *
 * @param {Object} config - The configuration or a data payload, changed in place.
 * @returns {Array|null} The extras, or null when the chart has none.
 */
export function takeTooltipExtras(config) {
    if (! config || typeof config !== 'object' || ! Object.prototype.hasOwnProperty.call(config, 'wkTooltipExtras')) {
        return null;
    }

    const extras = config.wkTooltipExtras;
    delete config.wkTooltipExtras;

    return Array.isArray(extras) ? extras : null;
}

/**
 * The rows the extras add to the tooltip of one point: a label and the value already written, for
 * each extra that has a value there.
 *
 * @param {Array|null} extras
 * @param {number} index - The point's index in the order of the chart's labels.
 * @returns {Array<{label: string, value: string}>}
 */
export function tooltipExtraRows(extras, index) {
    if (! Array.isArray(extras) || ! Number.isInteger(index) || index < 0) {
        return [];
    }

    return extras.flatMap((extra) => {
        const value = Array.isArray(extra?.values) ? extra.values[index] : null;

        return typeof extra?.label === 'string' && typeof value === 'string' ? [{ label: extra.label, value }] : [];
    });
}
