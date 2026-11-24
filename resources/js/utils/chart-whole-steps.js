/**
 * Whole steps on the axes of a Chart.js chart whose values are whole numbers.
 *
 * Chart.js divides a value axis into round steps of any size, so a chart of counts between 0 and 3
 * reads 0, 0.5, 1, … 3, and one of nothing but zeros 0, 0.1, … 1. While every value the axis shows
 * is a whole number, `ticks.precision: 0` rounds its step to a whole number of at least 1. The x-axis
 * of a scatter and a bubble chart is a scale of numbers as well, and takes whole steps where every
 * point stands at a whole number on it, ratings from 1 to 5 among them. Chart.js does not look at
 * the values for that, so the chart reads them again with each update of its data, and an axis whose
 * values turn into decimals is divided as Chart.js divides it.
 */

/** The chart types Chart.js draws without an `x` or `y` value axis. */
const WITHOUT_A_VALUE_AXIS = ['pie', 'doughnut', 'radar', 'polarArea'];

/** The id of the value axis: `x` for bars that lie (`indexAxis: 'y'`), else `y`. */
function valueAxisOf(options) {
    return options && options.indexAxis === 'y' ? 'x' : 'y';
}

/**
 * Whether every value of the datasets is a whole number, with one value at least. A point is a
 * number, an object with the value under `key`, or the `[start, end]` of a floating bar; an empty
 * point is skipped, and anything else, a numeric string among it, is not a whole number. With
 * `positions` the values are where the points stand on the x-axis of a scatter or a bubble chart:
 * `x` of an object, the first of an `[x, y]` pair, and a bare number names no position.
 *
 * @param {Array<object>} datasets
 * @param {string} key - `y`, or `x` for bars that lie and for positions
 * @param {boolean} [positions]
 * @returns {boolean}
 */
export function holdsWholeNumbers(datasets, key = 'y', positions = false) {
    let values = 0;
    for (const dataset of datasets) {
        for (const point of (dataset && Array.isArray(dataset.data) ? dataset.data : [])) {
            if (point === null || point === undefined) {
                continue;
            }
            let read = point;
            if (typeof point === 'object' && ! Array.isArray(point)) {
                read = point[key];
            } else if (positions) {
                read = Array.isArray(point) ? point[0] : Number.NaN;
            }
            for (const value of Array.isArray(read) ? read : [read]) {
                if (value === null || value === undefined) {
                    continue;
                }
                if (! Number.isInteger(value)) {
                    return false;
                }
                values += 1;
            }
        }
    }

    return values > 0;
}

/** Whether the developer chose the steps of an axis: another scale type, or ticks of their own. */
function developerStepsTheAxis(options, axis) {
    const scale = options && options.scales ? options.scales[axis] : undefined;
    if (! scale || typeof scale !== 'object') {
        return false;
    }
    const ticks = scale.ticks && typeof scale.ticks === 'object' ? scale.ticks : {};

    return (scale.type !== undefined && scale.type !== 'linear')
        || ['precision', 'stepSize', 'count'].some((name) => ticks[name] !== undefined && ticks[name] !== null);
}

/** Writes or takes back the precision of one axis, by the values of the datasets drawn on it. */
function stepTheAxis(options, datasets, axis, positions) {
    const shown = (Array.isArray(datasets) ? datasets : []).filter((dataset) => {
        const id = dataset ? dataset[`${axis}AxisID`] : undefined;

        return id === undefined || id === axis;
    });
    const whole = holdsWholeNumbers(shown, axis, positions);

    if (whole) {
        if (! options.scales) options.scales = {};
        if (! options.scales[axis]) options.scales[axis] = {};
        if (! options.scales[axis].ticks) options.scales[axis].ticks = {};
        options.scales[axis].ticks.precision = 0;
    } else {
        const ticks = options.scales && options.scales[axis] ? options.scales[axis].ticks : undefined;
        if (ticks && ticks.precision === 0) {
            delete ticks.precision;
        }
    }

    return whole;
}

/**
 * Whether the developer chose the steps of the value axis: a scale type other than `linear`, or a
 * precision, a step size or a number of ticks of their own.
 *
 * @param {string} type - the chart's type
 * @param {object|undefined} options - the chart's options as the developer passed them
 * @returns {boolean}
 */
export function developerStepsTheValueAxis(type, options) {
    return WITHOUT_A_VALUE_AXIS.includes(type) || developerStepsTheAxis(options, valueAxisOf(options));
}

/**
 * Steps the value axis in whole numbers while every value it shows is one, and gives the steps back
 * to Chart.js where they are not. Only for an axis the developer left to Chart.js
 * (`developerStepsTheValueAxis()`), so a precision on it is the kit's own.
 *
 * @param {object} options - the options to write into: the configuration's before the chart is
 *   built, `chart.options` after
 * @param {Array<object>} datasets - every dataset of the chart; those on another value axis are left out
 * @returns {boolean} whether the axis steps in whole numbers now
 */
export function stepTheValueAxisInWholeNumbers(options, datasets) {
    return stepTheAxis(options, datasets, valueAxisOf(options), false);
}

/**
 * Whether the kit steps the x-axis of a scatter or a bubble chart as well, whose points stand at a
 * number on it: where the developer chose no steps of their own for it.
 *
 * @param {string} type - the chart's type
 * @param {object|undefined} options - the chart's options as the developer passed them
 * @returns {boolean}
 */
export function stepsTheXAxisOfItsPoints(type, options) {
    return (type === 'scatter' || type === 'bubble') && valueAxisOf(options) === 'y' && ! developerStepsTheAxis(options, 'x');
}

/**
 * Steps the x-axis of a scatter or a bubble chart in whole numbers while every point stands at a
 * whole number on it, and gives the steps back where one does not, as for the value axis.
 *
 * @param {object} options - as for `stepTheValueAxisInWholeNumbers()`
 * @param {Array<object>} datasets
 * @returns {boolean} whether the axis steps in whole numbers now
 */
export function stepTheXAxisInWholeNumbers(options, datasets) {
    return stepTheAxis(options, datasets, 'x', true);
}
