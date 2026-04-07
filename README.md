# Modolus

Modolus is a minimal event-driven PHP framework built from scratch under strict constraints:
- Single entry point: `index.php`
- No MVC and no renamed MVC equivalent
- Stateless modules
- Native PHP only
- Internal request dispatch
- Custom AST-based template engine with custom tags

## Project Structure

```
.
├── index.php
├── test.php
├── README.md
└── modolus
    ├── bootstrap.php
    ├── Core
    │   ├── BlogLedger.php
    │   ├── Kernel.php
    │   ├── PathMatrix.php
    │   ├── SignalHub.php
    │   └── TemplateForest.php
    ├── Contracts
    │   └── TagNodeContract.php
    ├── Tags
    │   ├── BlogItemTag.php
    │   ├── BlogListTag.php
    │   ├── SiteIntroTag.php
    │   └── SiteLayoutTag.php
    ├── Modules
    │   ├── Blog
    │   │   ├── module.php
    │   │   └── templates
    │   │       └── index.tpl
    │   └── Site
    │       ├── module.php
    │       └── templates
    │           └── home.tpl
    └── Data
        └── blog.sqlite (created automatically)
```

## Core Concepts

## 1) Request Hub (Kernel)
`Kernel` coordinates one request lifecycle:
1. Receives request payload (`host`, `path`, `method`)
2. Emits `request.received`
3. Resolves route via `PathMatrix`
4. Emits `route.matched`
5. Runs matched action
6. Drains queued events
7. Renders template via `TemplateForest`
8. Emits `response.ready`
9. Returns final response

No state persists across requests. A fresh kernel is created each run.

## 2) Deterministic Conflict Resolution
`PathMatrix` resolves route conflicts using an information-density score, not a hardcoded priority list.

For each matching route:
- `hostSpecificity`: literal subdomain/domain labels matched
- `pathSpecificity`: literal path segments matched
- `nodeDepth`: depth of route node file/folder path
- wildcard penalties for host/path placeholders

Score vector:
- `infoScore = hostSpecificity*1000 + pathSpecificity*100 + nodeDepth*10 - wildcardPenalty`
- tie-break with remaining vector fields
- final tie-break lexicographically by route id

This is deterministic and justified: route candidates with more concrete information win over generic ones.

Concrete conflict example:
- Route A: `host=*`, `path=/`, `node=templates/home.tpl`
- Route B: `host=blog.adam.local`, `path=/`, `node=templates/blog/index.tpl`
- Request: `blog.adam.local/`

Both match path `/`, but B has higher host specificity and deeper node structure, so B wins.

`test.php` includes a regression demonstration:
- legacy first-match resolver fails this case
- current scoring resolver fixes it

## 3) Event System (`SignalHub`)

- Modules register listeners with an explicit numeric order.
- `emit()` enqueues events (simulated async queue).
- `drain()` processes queued events FIFO.
- For one event, listeners execute in deterministic order:
  - lowest `order` first
  - listener name as stable tie-break
- Listener outputs are patches merged into request context.

Event propagation:
1. Event enters queue
2. Drain loop dequeues one event
3. Matching listeners are sorted deterministically
4. Each listener receives `payload + current context`
5. Returned patch mutates current request context
6. Loop continues until queue empty

## 4) Module System

Each module is isolated and returns a manifest array:
- `routes`
- `actions`
- `tags`
- `listeners`

Modules do not hold shared mutable state.
Interaction is only via events and request-scoped context patches.

Examples:
- Blog module emits `blog.posts.loaded`
- Site module listens and computes `blog_count`
- Site module emits `site.home.rendering`
- Blog module listens and enriches home bio text

## 5) Template System (`TemplateForest`)

- Templates are plain `.tpl` files
- No PHP execution inside templates
- Parser builds an AST tree (`text` and `tag` nodes)
- Supports:
  - custom tags (`<module:tag ... />`)
  - attributes
  - nested tags
- Custom tags map to PHP classes implementing `TagNodeContract`
- Rendering is deterministic because AST traversal order is fixed

Example custom template snippet:

```xml
<site:layout title="Adam | Blog">
  <blog:list>
    <blog:item />
  </blog:list>
</site:layout>
```

## Application: Adam's Personal Site

Implemented pages:
- Homepage: `/`
- Blog page: `/blog`
- Subdomain blog route: `blog.adam.local/`

Blog data:
- SQLite file auto-created at `modolus/Data/blog.sqlite`
- Schema initialized automatically
- Seed data inserted when table is empty
- Blog rendering uses custom tags (`blog:list`, `blog:item`)

## How To Run

Serve with built-in PHP server:

```bash
php -S 127.0.0.1:8080
```

Then open:
- `http://127.0.0.1:8080/`
- `http://127.0.0.1:8080/blog`

## Run With Docker Compose

Build and run in an isolated container:

```bash
docker compose up --build
```

Then open:
- `http://127.0.0.1:8080/`
- `http://127.0.0.1:8080/blog`

Stop:

```bash
docker compose down
```

## Tests

Run all tests via CLI:

```bash
php test.php
```

Testing style:
- native PHP only
- Arrange-Act-Assert per test

Coverage includes:
- routing and dispatching
- event queue ordering
- template AST parsing and nested rendering
- module interaction through events
- SQLite init + read/write
- failing regression case and fix demonstration

## Why This Is Not MVC (or a renamed variant)

This system has no triad split into request handlers + data models + view objects.
Instead it has:
- signal queue for orchestration
- route conflict math based on information density
- module manifests that declare behavior as data + closures
- tag-class rendering over AST

There is no controller-like dispatch object, no model abstraction layer, and no view include pipeline.

## Self-Evaluation

Real weaknesses:
- The template parser is intentionally small and not fully XML/HTML compliant (edge cases like malformed attribute quoting are not exhaustively handled).
- `PathMatrix` currently requires same segment count between pattern and URL; no catch-all segments.
- Queue is simulated async (in-process), not external durable queue.
- Context merging is broad (`array_replace_recursive`) and could overwrite unintended keys in larger systems.
- SQLite schema management is minimal and has no migration versioning.

Edge cases:
- Complex host wildcards (e.g. mixed wildcard and missing labels) are accepted but not deeply validated.
- Unknown HTML-like tags are emitted as literal tags; invalid HTML names are not blocked.
- If two routes produce identical score vectors and id naming is poor, tie-break still works but may not reflect business intent.

Concrete improvements:
1. Add schema migrations table and versioned migration runner.
2. Add stricter template tokenizer with line/column error reporting.
3. Add route linting step to detect ambiguous score ties at boot.
4. Replace generic context merge with namespaced event payload channels.
5. Add optional disk-backed queue adapter while keeping the same `emit/drain` API.
5. Add optional disk-backed queue adapter while keeping the same `emit/drain` API.
