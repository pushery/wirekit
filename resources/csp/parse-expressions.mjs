/**
 * The oracle for `php artisan wirekit:csp-audit`.
 *
 * ## Why this is a node script and not PHP
 *
 * The question "does this expression work without `script-src 'unsafe-eval'`"
 * has exactly one correct oracle: Alpine's own CSP parser. Anything else — a
 * regex catalog of forbidden constructs, a PHP re-implementation of the
 * grammar — is a GUESS about that oracle, and guessing is how this got sized
 * wrong the first time it was attempted: the estimate assumed the CSP build
 * accepted only property names and method calls, which had been true of an
 * older build. Measured against the real parser, the affected surface was a
 * fifth of the guess.
 *
 * The grammar is also wider than it looks. Object literals, chains, ternaries,
 * index access and the usual operators all parse. Reading an expression and
 * judging it by eye reliably over-reports.
 *
 * ## Why it matters that it is exact
 *
 * The failure is SILENT. An expression outside the grammar is never evaluated:
 * it throws nothing, logs nothing, and the page looks correct while the control
 * is dead. There is no symptom to notice, so the only defense is to check.
 *
 * ## Protocol
 *
 * Reads `{ "expressions": ["…", …] }` on stdin, writes
 * `{ "ok": true, "results": [{ "ok": bool, "error": string|null }, …] }` on
 * stdout — one result per input, in order. On a setup problem it writes
 * `{ "ok": false, "error": "…" }` and exits 1, because a run that could not
 * measure must never look like a run that found nothing.
 */
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';

import { forbiddenGlobalsIn } from './unresolvable-globals.mjs';

/**
 * An expression that is outside the grammar in a way the grammar cannot express.
 *
 * The verdict this script produces comes entirely from an instrument it lifts
 * out of somebody else's bundle. If that instrument ever stops discriminating —
 * a version whose slice boundaries still match but whose parser accepts
 * everything, a stub, a bad splice — every expression comes back `ok` and the
 * run is a PASS over the whole catalog. That failure has no symptom: it looks
 * exactly like a clean codebase.
 *
 * So the oracle is asked a question it must get wrong before it is trusted with
 * questions whose answers are unknown. An arrow function is the right canary:
 * Alpine's CSP grammar has no function expressions at all, and it cannot grow
 * them without ceasing to be a CSP grammar — the whole point is that no code is
 * constructed at runtime.
 */
const CANARY = '() => 1';

/**
 * Lift Alpine's Tokenizer + Parser out of the CSP bundle.
 *
 * They are module-private, so there is no import to use. Slicing the source is
 * deliberate AND checked: the boundaries are asserted, so an Alpine upgrade that
 * moves or renames them fails loudly instead of silently auditing nothing.
 */
function loadCspParser() {
    const require = createRequire(import.meta.url);

    let bundlePath;
    try {
        // Resolved from the CALLING project, not from this package: the developer
        // installs @alpinejs/csp, and their version is the one their build ships.
        bundlePath = require.resolve('@alpinejs/csp/dist/module.esm.js', {
            paths: [process.cwd()],
        });
    } catch (error) {
        // The resolve failure is kept as the cause. The message below is the one a
        // developer can act on; the original says WHERE resolution stopped, which is
        // what distinguishes "not installed" from "installed somewhere this cannot see".
        throw new Error(
            'Alpine\'s CSP build is not installed. Add it as a dev dependency:\n'
            + '  npm install --save-dev @alpinejs/csp\n\n'
            + 'It is the only correct oracle for this question — a pattern list would be a '
            + 'guess about the grammar, and the grammar is wider than it looks.',
            { cause: error },
        );
    }

    const source = readFileSync(bundlePath, 'utf8');
    const start = source.indexOf('var Token = class {');
    const end = source.indexOf('var Evaluator = class {');

    if (start === -1 || end === -1 || end <= start) {
        throw new Error(
            'Could not locate Alpine\'s CSP Tokenizer/Parser inside @alpinejs/csp.\n'
            + 'The bundle layout changed in this version, so the audit cannot read the grammar '
            + 'it is supposed to check against. It refuses to report a verdict rather than '
            + 'report a clean one it did not measure.'
        );
    }

    // eslint-disable-next-line no-new-func -- dev tooling reading a parser out of a bundle; never shipped to a browser
    return new Function(`${source.slice(start, end)}\nreturn { Tokenizer, Parser };`)();
}

function readStdin() {
    return new Promise((resolve, reject) => {
        let raw = '';
        process.stdin.setEncoding('utf8');
        process.stdin.on('data', (chunk) => { raw += chunk; });
        process.stdin.on('end', () => resolve(raw));
        process.stdin.on('error', reject);
    });
}

try {
    const { Tokenizer, Parser } = loadCspParser();

    // Prove the instrument still IS an instrument before reading anything off
    // it. Everything below this line is a verdict from it.
    let canaryRejected = false;
    try {
        new Parser(new Tokenizer(CANARY).tokenize()).parse();
    } catch {
        canaryRejected = true;
    }

    if (! canaryRejected) {
        throw new Error(
            `Alpine's CSP parser accepted \`${CANARY}\`, which it must reject.\n`
            + 'The grammar exists precisely so that no function is constructed at runtime, so a '
            + 'build that parses an arrow function is not the CSP build — or the slice taken from '
            + 'it is not the parser. Either way the audit has no working oracle, and a verdict '
            + 'from a broken oracle is a PASS over everything. It refuses to report one.'
        );
    }

    const payload = JSON.parse(await readStdin());
    const expressions = Array.isArray(payload.expressions) ? payload.expressions : [];

    const results = expressions.map((expression) => {
        let ast;

        try {
            ast = new Parser(new Tokenizer(expression).tokenize()).parse();
        } catch (error) {
            return { ok: false, error: String(error?.message ?? error), globals: [] };
        }

        // Parsing is only half the restriction. The evaluator resolves an
        // identifier against the Alpine scope alone — there is no window
        // fallback — so an expression can be flawless grammar and still throw
        // on the first name it reads.
        const globals = forbiddenGlobalsIn(ast);

        if (globals.length > 0) {
            const names = globals.join(', ');

            return {
                ok: false,
                globals,
                error: `names ${names}, which Alpine's CSP evaluator cannot resolve `
                    + '(it resolves identifiers against the Alpine scope only, with no window fallback)',
            };
        }

        return { ok: true, error: null, globals: [] };
    });

    process.stdout.write(JSON.stringify({ ok: true, results }));
} catch (error) {
    process.stdout.write(JSON.stringify({ ok: false, error: String(error?.message ?? error) }));
    process.exit(1);
}
