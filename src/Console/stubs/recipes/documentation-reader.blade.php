{{-- Recipe: Documentation Reader — reading-shell with a section spine, a minimap and the time to read under the title.
     Full reference: https://docs.wirekit.app/blueprints/recipes/documentation-reader

     The shell places the progress bar, the spine and the minimap around your content and builds
     their links from its headings, so a heading you add or remove shows up in all three. A heading
     without an id gets one from its text; set it yourself where a link should survive an edit of
     the wording. Give every page its own bookmarkKey, so a returning reader is offered the place
     they left on that page. --}}
<div>
    <x-wirekit::reading-shell
        bookmarkKey="docs-authentication"
        :minimap="true"
        :meta="false"
        density="comfortable"
    >
        <x-wirekit::prose>
            <header>
                <h1>API Reference: Authentication</h1>
                {{-- The shell's own time to read is switched off above, and this one stands under
                     the title instead, where a reader looks for it. --}}
                <x-wirekit::reading-meta :showRemaining="true" :wpm="200" />
            </header>

            <article>
                <h2 id="overview">Overview</h2>
                <p>Every request authenticates with a bearer token. The sections below cover the token format, rotation, scopes and rate limits.</p>

                <h3 id="token-format">Token format</h3>
                <p>A token is a random string with a prefix that names its environment, so a test token can never reach live data.</p>

                <h3 id="required-headers">Required headers</h3>
                <p>Send <code>Authorization: Bearer {token}</code> with every request. The token never appears in a URL, which keeps it out of access logs and browser history.</p>

                <h2 id="token-rotation">Token rotation</h2>
                <p>Tokens do not expire on their own. Rotating one issues a new token and keeps the old one valid for 24 hours, long enough for a deploy to pick up the new one.</p>

                <h2 id="scopes">Scopes</h2>
                <p>Give a token only the operations it needs. A leaked read-only token cannot change anything, and a request outside its scopes is answered with 403 and the missing scope.</p>

                <h2 id="rate-limits">Rate limits</h2>
                <p>Each token has its own limit of 100 requests a minute. Every response carries the remaining count, so a client can slow down before it is refused.</p>
            </article>
        </x-wirekit::prose>
    </x-wirekit::reading-shell>
</div>
