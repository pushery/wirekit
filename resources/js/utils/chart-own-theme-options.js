/**
 * The colors and fonts in a Chart.js configuration that the theme writes as well, where the
 * developer set them.
 *
 * Chart.js keeps what it resolved when a chart was built, so a change of mode or theme recolors the
 * axes, the legend and the tooltip in the live chart's options. A value the developer wrote at one
 * of those places is theirs, as a color named on a dataset is: the theme pass leaves it as it was
 * written and recolors the rest.
 */

/** The places in a chart's options the theme writes, as paths. */
export const THEMED_OPTION_PATHS = [
    'color',
    'font.family',
    'plugins.legend.labels.color',
    'plugins.tooltip.backgroundColor',
    'plugins.tooltip.titleColor',
    'plugins.tooltip.bodyColor',
    'plugins.tooltip.borderColor',
    'plugins.tooltip.borderWidth',
    'plugins.tooltip.titleFont.family',
    'plugins.tooltip.bodyFont.family',
];

/** The places in each scale's options the theme writes, as paths below `scales.<id>`. */
export const THEMED_SCALE_PATHS = ['grid.color', 'ticks.color', 'border.color', 'angleLines.color', 'pointLabels.color'];

/**
 * The value at a dotted path, or undefined where the path leads nowhere.
 *
 * @param {*} node
 * @param {string} path
 * @returns {*}
 */
function valueAt(node, path) {
    return path.split('.').reduce((value, key) => (value !== null && typeof value === 'object' ? value[key] : undefined), node);
}

/**
 * The paths among `THEMED_OPTION_PATHS`, and among `THEMED_SCALE_PATHS` under each scale the
 * options name, that hold a value: a color, a number, or a function that returns one.
 *
 * @param {object|undefined} options - the configuration's `options`, before the factory adds its own
 * @returns {Set<string>}
 */
export function ownThemeOptionsOf(options) {
    const own = new Set();

    for (const path of THEMED_OPTION_PATHS) {
        if (valueAt(options, path) !== undefined) {
            own.add(path);
        }
    }

    const scales = valueAt(options, 'scales');

    if (scales !== null && typeof scales === 'object') {
        for (const [id, scale] of Object.entries(scales)) {
            for (const path of THEMED_SCALE_PATHS) {
                if (valueAt(scale, path) !== undefined) {
                    own.add(`scales.${id}.${path}`);
                }
            }
        }
    }

    return own;
}
