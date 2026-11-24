/**
 * Charts are built one per task.
 *
 * Alpine releases every `$nextTick` callback of a tick in one loop, inside one task, so a chart
 * built in one waited there with every other chart that started at the same time: a dashboard
 * whose parts arrive together built all its charts one after another in a single task, and the
 * page answered nothing until the last one was drawn. Here each build runs in a task of its own,
 * in the order the charts asked, so between two builds the browser paints what is drawn and
 * answers the reader.
 *
 * A build asked for during a component's start runs once the start's task is done, after Alpine
 * has started the component's children, as a build in `$nextTick` did.
 */

const builds = [];
let waiting = false;

function buildTheNext() {
    waiting = false;
    const build = builds.shift();

    try {
        build?.();
    } finally {
        // Asked for after the build, so what the build queued runs before the next one, and in a
        // `finally`, so a build that throws leaves the queue running.
        if (builds.length > 0) {
            askForATurn();
        }
    }
}

function askForATurn() {
    if (waiting) {
        return;
    }

    waiting = true;
    setTimeout(buildTheNext, 0);
}

/**
 * Queue a chart's build.
 *
 * @param {() => void} build
 * @returns {() => void} take the build back out, for a chart that is gone before its turn
 */
export function queueChartBuild(build) {
    const entry = () => build();
    builds.push(entry);
    askForATurn();

    return () => {
        const at = builds.indexOf(entry);

        if (at !== -1) {
            builds.splice(at, 1);
        }
    };
}
