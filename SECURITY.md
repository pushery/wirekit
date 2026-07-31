# Security Policy

## Reporting a vulnerability

Please report security issues **privately**, through GitHub's private vulnerability reporting:

**[Report a vulnerability](https://github.com/pushery/wirekit/security/advisories/new)**

That form is private between you and the maintainers until a fix is published. Please do not
open a public issue, and please do not post details in a pull request — a public description is
a working exploit for everyone still on the affected version.

If the form is unavailable to you for any reason, open an issue saying only that you have
something to report, with no detail, and you will be contacted for the rest.

### What to include

Whatever you have. A short description of the impact is more useful than a complete write-up,
and a rough reproduction is more useful than a polished one:

- What an attacker can do, in one sentence
- The component or command involved, and the WireKit version
- Steps or a snippet that shows it happening

You will get an acknowledgement that a human has read it. If the report turns out not to be a
vulnerability, you will be told why rather than left waiting.

## Scope

WireKit renders markup and ships JavaScript that runs in your users' browsers, so the reports
that matter most are:

- **Markup a developer's data can escape.** A prop or slot whose value reaches the page
  unescaped, so content from a database or a form becomes HTML.
- **JavaScript that evaluates what it should only read.** A component that treats an attribute
  as an expression, or a payload path where a string becomes code.
- **A component that leaks between users.** State that survives where it should not — a cached
  render, a shared identifier, a token in markup.
- **A published artifact that carries more than it should.** The distributed package and the
  machine-readable manifests are meant to contain nothing but public content.

Out of scope, because they describe the application rather than this library: a missing
Content-Security-Policy in your own app, a dependency's advisory that WireKit does not reach
(report it upstream), and anything requiring an attacker to already control your server.

## Supported versions

Fixes land on the current minor release. WireKit follows semantic versioning, so a security fix
arrives as a patch you can take without changing any of your own code.

Older minors do not receive backports. If you are on one and cannot upgrade, say so in your
report — the constraint is worth knowing and may change the answer.
