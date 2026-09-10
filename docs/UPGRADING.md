# UPGRADING — Selenium MCP Server

Developer/agent-facing. Read this **before** touching `libraries/`,
`bin/`, or any network I/O. Tool reference: `docs/TOOLS.md`.

## 1. The transport split (September 2026) changed the rules

Libraries, vendored byte-identical from canonical upstream **master**
(pull first, then copy — never from another consumer's tree):

| Vendored dir | Source | What it is |
|---|---|---|
| `libraries/EnchiladaMCP/` | `Enchilada/Extras` MCP/ | Protocol core. No I/O, no loop, no framework deps. |
| `libraries/Enchilada/Tortilla/` | `Enchilada/Tortilla` src/ | Wire transports, `EventLoop` port, `RequestEra`. |
| `libraries/Enchilada/Comal/` | `Enchilada/Comal` | Reactor (kqueue/ev). Presence opts stdio into reactor mode. |
| `libraries/EnchiladaHTTP/` + `EnchiladaMultiHTTP/` | Extras HTTP/ | curl engines (legacy globals, eponymous dirs). **Frozen.** |
| `libraries/EnchiladaWebSocket/` | Extras WebSocket/ | WebSocket client for the Selenium/WebDriver debug channel. |

## 2. Non-negotiables

1. **Eponymous vendoring, no guards.** Legacy global classes live in
   `libraries/<Class>/<Class>.class.php`. Never add
   `class_exists`/`require_once` guards: the framework autoloader is
   golden; a miss means wrong placement or namespace — fix that.
2. **Only `bin/selenium-mcp` knows both sides.** Transports take
   primitives (`handler`, `progress` callables), never `McpServer`.
3. **The event loop is opt-in and app-owned:**
   `ComalEventLoop::create()` → `$transport->setLoop($loop)` — this repo
   vendors Comal, so stdio runs reactor mode where pollable; keep it.
4. **`ping` is gone in MCP 2026-07-28** — `notifications/progress` is
   the only in-call liveness. Long-running tool work must keep progress
   flowing (transport progress timer in reactor mode; injected callable
   in blocking mode).
5. **No blocking network I/O inside tools.**
   - HTTP → `Tortilla\HttpClient` (loop-aware wait).
   - Raw-socket / WebSocket I/O → the `setTransport(?EventLoop,
     ?progress)` pattern (reference: mail-mcp `SocketImapClient`):
     non-blocking socket, buffered pump, fiber-parked `onReadable`
     waits under the reactor, bounded poll with per-slice progress
     without it.
   - `EventLoop` has no writability watcher: writability waits must
     probe (zero-timeout `stream_select`) between parked slices; an
     in-flight async connect reads `ENOTCONN` from
     `stream_socket_get_name` — re-probe, never treat as failure.
     (mail-mcp#27 review.)
   - `EnchiladaHTTP`/`EnchiladaMultiHTTP` are frozen: no MCP hooks, no
     behavior changes.

## 3. This server's wiring

- `bin/selenium-mcp` composition root: `$loop = ComalEventLoop::create()`
  → `StdioTransport` primitives → `setLoop` — Selenium sessions are
  long-lived; do not let tool bodies block the reactor (browser commands
  are the classic multi-second call).

## 4. Regression gates (before every commit)

- `phpunit` — green.
- Liveness suite:
  `php ~/Documents/Projects/engineering-docs/enchilada-extras/mcp-liveness-suite/transport-liveness.php --lib=libraries`
  (reactor mode, Comal present) — 9/9.
- Phar build + smoke (init/version/tools/ping/stderr/EOF).

## 5. Canonical references

- `engineering-docs/enchilada-extras/PLAN-TRANSPORT-SPLIT.md`
- `engineering-docs/enchilada-extras/DESIGN-RATIONALE-TRANSPORT-SPLIT.md`
- `engineering-docs/enchilada-extras/mcp-liveness-suite/README.md`
- `Enchilada/Extras` README (vendoring rules), `Enchilada/Tortilla` README
