# Changelog

## v0.2.0 — 2026-05-31

### BiDi Diagnostics — Now Working

The `diagnostics` tool now returns real browser console logs, JS errors, and network
activity captured via WebDriver BiDi WebSocket events.

### New Capabilities
- **`acceptInsecureCerts`** — accept invalid/self-signed TLS certificates (required for sites using private CAs)
- **`platformName`** — target a specific Grid node OS (e.g. `WINDOWS`, `UNIX`, `LINUX`, `MAC`)
- **Page load timeout** — defaults to 30s (configurable via `timeout` in instances.json), prevents indefinite hangs on unreachable hosts
- **Agent guidance** — server instructions inform agents about `acceptInsecureCerts` and `platformName` upfront; element lookup errors suggest reading `accessibility://current`

### BiDi Wiring
- Wire BiDi WebSocket connection in `start_browser` — connects automatically when the Grid returns a `webSocketUrl` capability
- Integrate BiDi stream into StdioTransport's `stream_select()` event loop for passive event capture between tool calls
- Add `drainEvents()` to BiDiClient for buffered event retrieval before returning results
- Move BiDi lifecycle management to `SessionManager` (connectBidi/disconnectBidi/getBiDiClient)
- Simplify `DiagnosticsTools` — reads BiDiClient from SessionManager directly
- Auto-disconnect BiDi on `close_session`

### Bug Fixes
- Fix RFC 6455 GUID constant in EnchiladaWebSocket (was `5AB5DC76B97E`, correct: `5AB0FAB11C10`) — fixed upstream in Enchilada Extras, re-vendored
- Handle Selenium Grid BiDi proxy not forwarding client's Sec-WebSocket-Key (proxy-tolerant `strictAccept` option added upstream)

### Verified
- Firefox + Chrome on FreeBSD and Windows Grid nodes
- Console logs, warnings, JS errors with stack traces, network responses (URL, status, MIME type)
- `clear` parameter correctly resets log buffers
- `acceptInsecureCerts` tested against private-CA sites on all 3 Grid nodes
- `platformName` routing confirmed (WINDOWS targets Windows node)

## v0.1.0 — 2026-05-31

Initial release.

### Features
- 18 MCP tools with full Selenium WebDriver coverage
- 2 resource templates (`accessibility://current`, `browser-status://current`)
- Multi-instance configuration (multiple Selenium Grid servers)
- PHAR archive distribution
- CI/CD workflows (lint, release)

### Tool Categories
- **Browser Lifecycle**: start_browser (chrome/firefox/edge/safari, headless, custom args), close_session, navigate, take_screenshot (PNG image content)
- **Element Interaction**: interact (click/doubleclick/rightclick/hover), send_keys, get_element_text, get_element_attribute, upload_file
- **Keyboard & Script**: press_key (named keys + characters), execute_script (with arguments)
- **Window/Frame/Alert**: window (list/switch/close), frame (by locator/index/default), alert (accept/dismiss/get_text/send_text)
- **Cookie Management**: add_cookie, get_cookies, delete_cookie
- **Diagnostics**: console logs, JS errors, network activity via WebDriver BiDi

### Resources
- `accessibility://current` — DOM accessibility tree snapshot (compact JSON)
- `browser-status://current` — current session status

### Infrastructure
- Enchilada Framework 3.0 with ToolResult typed returns (new `ToolResult` value object)
- EnchiladaWebSocket library — I/O-agnostic WebSocket client for BiDi (new, contributed upstream as Extras PR #14)
- php-webdriver vendored (remote Grid subset, no symfony/process dependency)
- Forgejo Actions CI + release workflows
- PHAR builder
