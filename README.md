# Selenium MCP Server

A PHP [Model Context Protocol](https://modelcontextprotocol.io/) server for browser automation via Selenium Grid, built on the [Enchilada Framework](https://buenapp.org/enchilada). Drop-in replacement for `@angiejones/mcp-selenium`.

## Features

- **18 MCP tools** covering browser lifecycle, element interaction, navigation, cookies, windows, frames, alerts, and diagnostics
- **2 resource templates** for accessibility tree snapshots and session status
- **BiDi diagnostics** — real-time console logs, JS errors, and network activity via WebSocket
- **PHAR deployable** — single-file distribution for easy installation
- **No Node.js** — pure PHP, instant startup, no npx/npm resolution delays
- **No Composer** — pure PHP with Enchilada Framework autoloading

## Quick Start

### 1. Download

```sh
curl -LO https://pacyworld.dev/pacyworld/selenium-mcp/releases/latest/download/selenium-mcp.phar
chmod +x selenium-mcp.phar
```

Or clone and run from source:

```sh
git clone https://pacyworld.dev/pacyworld/selenium-mcp.git
cd selenium-mcp
php bin/selenium-mcp --version
```

### 2. Configure

Copy `config/instances.json.sample` to your config location and edit:

```json
{
    "default": "grid",
    "instances": {
        "grid": {
            "grid_url": "http://localhost:4444",
            "default_browser": "firefox",
            "headless": true,
            "timeout": 30000
        }
    }
}
```

### 3. Add to your AI assistant

```json
{
    "mcpServers": {
        "selenium": {
            "command": "php",
            "args": ["/path/to/selenium-mcp.phar", "--config=/path/to/instances.json"]
        }
    }
}
```

Or if running from source:

```json
{
    "mcpServers": {
        "selenium": {
            "command": "php",
            "args": ["/path/to/selenium-mcp/bin/selenium-mcp", "--config=/path/to/instances.json"]
        }
    }
}
```

For single-grid setups, you can skip the config file entirely:

```json
{
    "mcpServers": {
        "selenium": {
            "command": "php",
            "args": ["/path/to/selenium-mcp/bin/selenium-mcp"],
            "env": {
                "SELENIUM_GRID_URL": "http://localhost:4444"
            }
        }
    }
}
```

Config file is auto-discovered from these locations (first found wins):
1. `--config=` CLI argument
2. `SELENIUM_MCP_CONFIG` environment variable
3. `SELENIUM_GRID_URL` environment variable (creates a default instance)
4. `config/instances.json` (relative to binary)

## Tools

### Browser Lifecycle
`start_browser`, `close_session`, `navigate`, `take_screenshot`

### Element Interaction
`interact`, `send_keys`, `get_element_text`, `get_element_attribute`, `upload_file`

### Keyboard & Script
`press_key`, `execute_script`

### Window, Frame & Alert
`window`, `frame`, `alert`

### Cookie Management
`add_cookie`, `get_cookies`, `delete_cookie`

### Diagnostics
`diagnostics`

See [docs/TOOLS.md](docs/TOOLS.md) for detailed per-tool documentation.

## Resources

| URI | Description |
|-----|-------------|
| `accessibility://current` | Accessibility tree snapshot — compact, structured representation of interactive elements and text content |
| `browser-status://current` | Current browser session status |

## Requirements

- PHP 8.4+ with `curl`, `openssl`, and `mbstring` extensions
- Selenium Grid 4.x accessible over HTTP

## Building the PHAR

```sh
php -d phar.readonly=0 bin/build-phar.php
```

## License

BSD 2-Clause — see [LICENSE](LICENSE).

## Credits

Built with the [Enchilada Framework](https://buenapp.org/enchilada) by [The Daniel Morante Company, Inc.](https://pacyworld.dev)
