# Selenium MCP Server

PHP-based [Model Context Protocol](https://modelcontextprotocol.io/) server for browser automation via Selenium Grid. Drop-in replacement for `@angiejones/mcp-selenium`.

## Features

- **18 tools** — full parity with the Node.js mcp-selenium server
- **2 resources** — browser status + accessibility tree snapshot
- **BiDi diagnostics** — console logs, JS errors, network activity via WebSocket
- **No Node.js** — pure PHP, instant startup, no npx resolution delays
- **Enchilada Framework 3.0** — consistent with Mail MCP, Forgejo MCP, OPNsense MCP
- **php-webdriver** — battle-tested Selenium client library (vendored, no Composer)

## Requirements

- PHP 8.4+ with `ext-curl`, `ext-openssl`, `ext-mbstring`
- Selenium Grid 4.x accessible over HTTP

## Quick Start

```bash
# Clone
git clone https://pacyworld.dev/pacyworld/selenium-mcp.git
cd selenium-mcp

# Configure
cp config/instances.json.sample config/instances.json
# Edit config/instances.json with your Grid URL

# Run (stdio mode for MCP clients)
php bin/selenium-mcp
```

## MCP Client Configuration

### Windsurf / VS Code

```json
{
  "selenium": {
    "command": "php",
    "args": [
      "/path/to/selenium-mcp/bin/selenium-mcp",
      "--config=/path/to/instances.json"
    ]
  }
}
```

### Environment Variable (simple)

```json
{
  "selenium": {
    "command": "php",
    "args": ["/path/to/selenium-mcp/bin/selenium-mcp"],
    "env": {
      "SELENIUM_GRID_URL": "http://localhost:4444"
    }
  }
}
```

## Configuration

`instances.json` supports multiple named Grid instances:

```json
{
  "default": "grid",
  "instances": {
    "grid": {
      "grid_url": "http://selenium:4444",
      "default_browser": "firefox",
      "headless": true,
      "timeout": 30000
    }
  }
}
```

## Tools

| Tool | Description |
|------|-------------|
| `start_browser` | Launch browser (chrome/firefox/edge/safari, headless, custom args) |
| `navigate` | Navigate to URL |
| `interact` | Mouse actions: click, doubleclick, rightclick, hover |
| `send_keys` | Type into element (clears first) |
| `get_element_text` | Get text content of element |
| `get_element_attribute` | Get attribute value |
| `press_key` | Simulate keyboard key press |
| `upload_file` | Upload file via input element |
| `take_screenshot` | Capture page screenshot (PNG) |
| `execute_script` | Execute JavaScript, return result |
| `window` | Window/tab management (list, switch, close) |
| `frame` | Frame switching (by locator, index, or default) |
| `alert` | Alert/confirm/prompt handling |
| `add_cookie` | Add cookie to session |
| `get_cookies` | Get cookies (all or by name) |
| `delete_cookie` | Delete cookies (specific or all) |
| `close_session` | Close browser session |
| `diagnostics` | Console logs, JS errors, network activity (BiDi) |

## Resources

| URI | Description |
|-----|-------------|
| `accessibility://current` | Accessibility tree snapshot (compact JSON) |
| `browser-status://current` | Current session status |

## Libraries

- **EnchiladaMCP** — MCP protocol + stdio transport
- **EnchiladaHTTP** — HTTP client
- **EnchiladaWebSocket** — I/O-agnostic WebSocket client (RFC 6455)
- **Facebook/WebDriver** — php-webdriver (remote Grid subset)

## License

BSD-2-Clause — The Daniel Morante Company, Inc.
