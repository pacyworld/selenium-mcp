# Tools Reference

Detailed documentation for all 18 Selenium MCP tools.

## Browser Lifecycle

### start_browser
Launch a browser session on the Selenium Grid.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `browser` | string | yes | Browser to launch: `chrome`, `firefox`, `edge`, `safari` |
| `options.headless` | boolean | no | Run in headless mode (default from config) |
| `options.arguments` | string[] | no | Additional browser arguments |

### navigate
Navigate to a URL.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `url` | string | yes | URL to navigate to |

### take_screenshot
Capture a screenshot of the current page. Returns a PNG image content block, or saves to a file path.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `outputPath` | string | no | File path to save the screenshot. If omitted, returns image/png content block. |

### close_session
Close the current browser session. No parameters.

## Element Interaction

### interact
Perform a mouse action on an element.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `action` | string | yes | Mouse action: `click`, `doubleclick`, `rightclick`, `hover` |
| `by` | string | yes | Locator strategy: `id`, `css`, `xpath`, `name`, `tag`, `class` |
| `value` | string | yes | Value for the locator strategy |
| `timeout` | number | no | Maximum wait for element in milliseconds (default: 10000) |

### send_keys
Type into an element. Clears the field first.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `by` | string | yes | Locator strategy: `id`, `css`, `xpath`, `name`, `tag`, `class` |
| `value` | string | yes | Value for the locator strategy |
| `text` | string | yes | Text to enter into the element |
| `timeout` | number | no | Maximum wait for element in milliseconds (default: 10000) |

### get_element_text
Get the text content of an element.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `by` | string | yes | Locator strategy: `id`, `css`, `xpath`, `name`, `tag`, `class` |
| `value` | string | yes | Value for the locator strategy |
| `timeout` | number | no | Maximum wait for element in milliseconds (default: 10000) |

### get_element_attribute
Get the value of an attribute on an element.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `by` | string | yes | Locator strategy: `id`, `css`, `xpath`, `name`, `tag`, `class` |
| `value` | string | yes | Value for the locator strategy |
| `attribute` | string | yes | Name of the attribute (e.g., `href`, `value`, `class`) |
| `timeout` | number | no | Maximum wait for element in milliseconds (default: 10000) |

### upload_file
Upload a file using a file input element.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `by` | string | yes | Locator strategy: `id`, `css`, `xpath`, `name`, `tag`, `class` |
| `value` | string | yes | Value for the locator strategy |
| `filePath` | string | yes | Absolute path to the file to upload |
| `timeout` | number | no | Maximum wait for element in milliseconds (default: 10000) |

## Keyboard & Script

### press_key
Simulate pressing a keyboard key.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `key` | string | yes | Key to press. Single character or named key: `Enter`, `Tab`, `Escape`, `Backspace`, `Delete`, `Space`, `Up`, `Down`, `Left`, `Right`, `Home`, `End`, `Page_Up`, `Page_Down`, `F1`–`F12`, `Control`, `Alt`, `Shift`, `Meta` |

### execute_script
Execute JavaScript in the browser and return the result.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `script` | string | yes | JavaScript code to execute |
| `args` | array | no | Arguments passed to the script (accessible via `arguments[0]`, `arguments[1]`, etc.) |

## Window, Frame & Alert

### window
Manage browser windows and tabs.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `action` | string | yes | Action: `list`, `switch`, `switch_latest`, `close` |
| `handle` | string | no | Window handle (required for `switch`) |

### frame
Switch focus to a frame or back to the main page.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `action` | string | yes | Action: `switch`, `default` |
| `by` | string | no | Locator strategy for frame element |
| `value` | string | no | Value for the locator strategy |
| `index` | number | no | Frame index (0-based) |
| `timeout` | number | no | Maximum wait in milliseconds (default: 10000) |

For `switch`, provide either `by`/`value` to locate the frame element, or `index` to switch by position.

### alert
Handle browser alert, confirm, or prompt dialogs.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `action` | string | yes | Action: `accept`, `dismiss`, `get_text`, `send_text` |
| `text` | string | no | Text to send (required for `send_text`) |
| `timeout` | number | no | Maximum wait in milliseconds (default: 5000) |

## Cookie Management

### add_cookie
Add a cookie to the current browser session. The browser must be on a page from the cookie's domain.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `name` | string | yes | Cookie name |
| `value` | string | yes | Cookie value |
| `domain` | string | no | Domain the cookie is visible to |
| `path` | string | no | Path the cookie is visible to |
| `secure` | boolean | no | Whether it is a secure cookie |
| `httpOnly` | boolean | no | Whether it is HTTP-only |
| `expiry` | number | no | Expiry as Unix timestamp (seconds since epoch) |

### get_cookies
Retrieve cookies from the current session.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `name` | string | no | Specific cookie name. If omitted, returns all cookies. |

### delete_cookie
Delete cookies from the current session.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `name` | string | no | Cookie name to delete. If omitted, deletes all cookies. |

## Diagnostics

### diagnostics
Retrieve browser diagnostics captured via WebDriver BiDi.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `type` | string | yes | Data type: `console`, `errors`, `network` |
| `clear` | boolean | no | Clear buffer after returning (default: false) |

Returns console logs, JavaScript errors, or network activity (requests/responses/failures) captured since the session started or since the last clear.
