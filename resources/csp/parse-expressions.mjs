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
 * `{ "ok": true, "grammar": { "reservedWordAsMember": bool },
 *    "prohibited": { "directives": [{ "name", "message" }, …], "tags": [{ "name", "message" }, …] },
 *    "results": [{ "ok": bool, "error": string|null }, …] }` on
 * stdout — one result per input, in order. `grammar` describes the parser that
 * produced those results, so advice derived from them can be true for THIS
 * installation rather than for the version somebody happened to test against.
 * `prohibited` is what the same build refuses whatever an expression says: a
 * directive it throws on before reading the value, and elements on which it
 * evaluates nothing. Grammar cannot find either, because neither depends on
 * how the expression is written.
 * On a setup problem it writes
 * `{ "ok": false, "error": "…" }` and exits 1, because a run that could not
 * measure must never look like a run that found nothing.
 */
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';

import { forbiddenGlobalsIn, globalReachingMembersIn } from './unresolvable-globals.mjs';

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
    const parser = new Function(`${source.slice(start, end)}\nreturn { Tokenizer, Parser };`)();

    return { ...parser, source };
}

/**
 * What the CSP build refuses whatever the expression says, read out of its own messages.
 *
 * The build registers its own handler for `x-html`, and that handler throws before it reads
 * the value; it refuses to evaluate anything on an `<iframe>` or a `<script>`. A grammar check
 * passes all three, because `body` is a perfectly good expression. So the set comes from the
 * same bundle the grammar comes from, by the wording of its errors, and nothing about it is
 * listed here.
 *
 * An empty set is an error, not a clean result: it means the wording moved, and a check that
 * silently stopped finding would certify the one thing it exists to catch.
 */
function prohibitedBy(source) {
    const unique = (matches) => [...new Map(matches.map((m) => [m[1], { name: m[1], message: m[0] }])).values()];

    const directives = unique([...source.matchAll(/Using the (x-[\w-]+) directive is prohibited in the CSP build/g)]);
    const tags = unique([...source.matchAll(/Evaluating expressions on an? (\w+) is prohibited in the CSP build/g)]);

    if (directives.length === 0 || tags.length === 0) {
        throw new Error(
            'Could not read what Alpine\'s CSP build refuses outright (a directive such as x-html, '
            + 'expressions on an iframe or a script) from @alpinejs/csp: the wording of its errors '
            + 'changed in this version. The audit refuses to report a verdict rather than certify '
            + 'directives it can no longer check.'
        );
    }

    return { directives, tags };
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
    const { Tokenizer, Parser, source } = loadCspParser();
    const prohibited = prohibitedBy(source);

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

    // Does THIS parser accept a reserved word as a member name?
    //
    // ⚠️ The answer changed under us. Up to `@alpinejs/csp` 3.17.2 the tokenizer emitted a
    // KEYWORD for `delete`, `new`, `typeof` and the rest of that set wherever they appeared, so
    // `$wire.delete(1)` died with `Expected IDENTIFIER but got KEYWORD "delete"` — the dead-button
    // class this command was written for. 3.17.3 accepts it. Measured both ways rather than read
    // out of a release note: the same probe against a downgraded `@alpinejs/csp` fails and against
    // the current one passes, while `items.filter(i => i.done)` is rejected by both.
    //
    // It is reported rather than assumed because the constraint here is `^3.15.12`: a developer
    // running this command can legitimately be on either side of that line, and the advice we
    // print about index access is TRUE on one side and FALSE on the other. A sentence that tells
    // somebody to rewrite working code is the same class of damage as a missed violation.
    let reservedWordAsMember = true;

    try {
        new Parser(new Tokenizer('a.delete').tokenize()).parse();
    } catch {
        reservedWordAsMember = false;
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
                warnings: [],
                error: `names ${names}, which Alpine's CSP evaluator cannot resolve `
                    + '(it resolves identifiers against the Alpine scope only, with no window fallback)',
            };
        }

        // Resolvable is not the same as evaluable, and the gap between them is where
        // a control dies quietly. The evaluator refuses a VALUE, not a name: it
        // throws on any property access that lands in the set built from
        // `globalThis`, so a chain can resolve completely, run, and be rejected at
        // the moment it touches something that happens to be global.
        //
        // Reported as a warning rather than a violation. The rule is an
        // over-approximation of a runtime question, and this command's worth is that
        // its findings can be acted on without being checked first — a warning that
        // turns out to be nothing costs a glance, a violation that turns out to be
        // nothing costs the next hundred their credibility.
        const reaching = globalReachingMembersIn(ast);

        return {
            ok: true,
            error: null,
            globals: [],
            warnings: reaching.length > 0
                ? [`reads ${reaching.join(', ')}, which resolves to a browser global — `
                    + 'the CSP evaluator refuses the VALUE, so this parses, runs, and is rejected']
                : [],
        };
    });

    process.stdout.write(JSON.stringify({ ok: true, grammar: { reservedWordAsMember }, prohibited, results }));
} catch (error) {
    process.stdout.write(JSON.stringify({ ok: false, error: String(error?.message ?? error) }));
    process.exit(1);
}
