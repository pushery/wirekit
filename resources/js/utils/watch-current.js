/**
 * `$watch` for a component that Alpine can initialize more than once on the same object.
 *
 * From Alpine 3.16 a Livewire update that changes a component's `x-data` expression keeps the
 * component's object: the object takes the values of the new expression, and `init()` runs on it
 * again. A watcher registered with `$watch` is released only when its element leaves the page, and
 * `$watch` hands back nothing to release it with, so the watchers of every earlier `init()` go on
 * running beside the new ones. A callback that announces an opening, dispatches an event or sends
 * a value then does so once for every `init()` that ever ran on the object.
 *
 * A watcher registered here belongs to the `init()` that registered it. The object carries the
 * `init` of the expression it was last initialized from, so once a newer one has run, an older
 * watcher finds a different `init` and stays quiet.
 *
 * @param {object}          component `this` inside `init()`
 * @param {string|Function} key       what to watch, as `$watch` takes it
 * @param {Function}        callback  called with the new and the old value, as `$watch` calls it
 */
export function watchCurrent(component, key, callback) {
    const init = component.init;

    component.$watch(key, (...args) => {
        if (component.init !== init) {
            return undefined;
        }

        return callback(...args);
    });
}

export default watchCurrent;
