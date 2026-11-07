/**
 * WireKit per page: the shared runtime, and the factories a page names.
 *
 * Built into `dist/auto/` as an ES module beside one module per factory
 * (`dist/auto/factories/<name>.js`), whose shared parts esbuild writes once as chunks. A page
 * imports this module and the factories its markup starts, and hands them to `register()`, so a
 * page that renders three components loads the runtime and those three.
 *
 * The runtime and the factories reach Alpine on `alpine:init`, as the other bundles do, so the
 * module script has to run before Alpine starts: the place of `@wirekitScripts` in front of
 * `@livewireScripts` keeps that, since a module script and its imports run in document order with
 * the deferred scripts after it.
 *
 * An element a later Livewire response or a visit adds may start a factory the page did not load.
 * Alpine asks every interceptor before it runs an element's directives, so the runtime holds such
 * an element back, loads the factory's module beside this one and starts the element then.
 */
/* global __WIREKIT_AUTO_FACTORIES__ */
import { installRuntime } from './runtime.js';
import { factoryToLoad } from './utils/factory-to-load.js';
import { reportLateRegistration } from './utils/late-registration.js';

/**
 * The names this bundle has a module for, written by the build. A name outside it, such as a
 * factory of another WireKit bundle or of the page, is left to Alpine as it is.
 */
const known = new Set(typeof __WIREKIT_AUTO_FACTORIES__ === 'undefined' ? [] : __WIREKIT_AUTO_FACTORIES__);

/** @type {Map<string, Promise<void>>} */
const loading = new Map();

/** @type {Map<string, Function>} */
const factories = new Map();

/** The Alpine the runtime went into, once it has. */
let alpine = null;

/**
 * @param {object} Alpine
 */
function install(Alpine) {
    if (alpine !== null) {
        return;
    }

    alpine = Alpine;
    installRuntime(Alpine);

    for (const [name, factory] of factories) {
        Alpine.data(name, factory);
    }

    // Every handler of an element returns while `_x_ignore` is set, and the walk does not enter
    // it, so an element whose factory is still on its way starts nothing until it is here.
    Alpine.interceptInit((el) => {
        const name = factoryToLoad(el.getAttribute?.('x-data') ?? null, known, factories);

        if (name === null) {
            return;
        }

        el._x_ignore = true;

        load(name).then(
            () => {
                delete el._x_ignore;

                if (el.isConnected) {
                    Alpine.initTree(el);
                }
            },
            (error) => {
                delete el._x_ignore;
                console.error(`[wirekit] the module of ${name} did not load, so this element stays as the server wrote it.`, error);
            },
        );
    });
}

/**
 * Load one factory's module from beside this one, once, and hand it to Alpine. The module's own
 * imports are chunks named by their content; the factory takes this module's `?v=`.
 *
 * @param {string} name
 * @returns {Promise<void>}
 */
function load(name) {
    if (!loading.has(name)) {
        const url = new URL(`factories/${name}.js${new URL(import.meta.url).search}`, import.meta.url);

        loading.set(name, import(url.href).then((module) => register({ [name]: module.default })));
    }

    return loading.get(name);
}

/**
 * Hand over factories by their `x-data` names. Before Alpine starts they wait for it; after, they
 * go to Alpine at once, for an element a later render adds.
 *
 * @param {Record<string, Function>} named
 */
export function register(named) {
    for (const [name, factory] of Object.entries(named)) {
        factories.set(name, factory);

        if (alpine !== null) {
            alpine.data(name, factory);
        }
    }
}

// Primary path: install before Alpine.start() walks the page.
let reachedByInitEvent = false;

if (typeof window !== 'undefined') {
    document.addEventListener('alpine:init', () => {
        reachedByInitEvent = true;
        install(window.Alpine);
    }, { once: true });

    // Alpine may already be on the page: Livewire assigns it before it starts it, and a module
    // that runs late finds it walking. Installing now reaches every element Alpine has not walked
    // yet; one it walked before this module ran stays dead, and reportLateRegistration says so
    // once, for the components this bundle has a module for.
    if (window.Alpine?.version) {
        install(window.Alpine);
        reportLateRegistration('wirekit.auto.js', () => reachedByInitEvent, [...known]);
    }
}
