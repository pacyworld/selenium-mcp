# Changelog

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
