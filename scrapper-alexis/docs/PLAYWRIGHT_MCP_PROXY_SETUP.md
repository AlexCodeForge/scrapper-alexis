# Playwright MCP Proxy Configuration Guide

## Problem Overview

When configuring Playwright MCP with a proxy server that requires authentication, you may encounter `ERR_INVALID_AUTH_CREDENTIALS` errors or a browser proxy authentication dialog. This happens because proxy credentials cannot be properly passed through command-line arguments in the standard format (`http://username:password@host:port`).

## Solution

The correct approach is to use a **combination** of:
1. A configuration file with `httpCredentials` for authentication
2. Command-line `--proxy-server` flag for the proxy server address
3. Command-line `--config` flag to load the configuration

## Step-by-Step Configuration

### 1. Create Playwright MCP Configuration File

Create a file named `playwright-mcp-config.json` in your Cursor configuration directory (e.g., `C:\Users\YourUsername\.cursor\`):

```json
{
  "browser": {
    "browserName": "chromium",
    "launchOptions": {
      "headless": false
    },
    "contextOptions": {
      "viewport": {
        "width": 1280,
        "height": 720
      },
      "httpCredentials": {
        "username": "your_proxy_username",
        "password": "your_proxy_password"
      }
    }
  },
  "capabilities": ["core", "tabs", "screenshot", "wait"]
}
```

**Key Points:**
- `httpCredentials` in `contextOptions` handles proxy authentication
- Set `headless: false` to see the browser in action (change to `true` for headless mode)
- Adjust viewport size as needed

### 2. Update MCP Configuration

Edit your `mcp.json` file (located in `C:\Users\YourUsername\.cursor\mcp.json`):

```json
{
  "mcpServers": {
    "Playwright": {
      "command": "npx",
      "args": [
        "-y",
        "@playwright/mcp@latest",
        "--config=C:/Users/YourUsername/.cursor/playwright-mcp-config.json",
        "--proxy-server=http://proxy_ip:proxy_port"
      ]
    }
  }
}
```

**Important Notes:**
- Use **forward slashes** (`/`) in the config path, even on Windows
- Replace `YourUsername` with your actual Windows username
- Replace `proxy_ip:proxy_port` with your actual proxy server address
- Do NOT include credentials in the `--proxy-server` URL

### 3. Restart Cursor/VS Code

After making these changes, **completely restart Cursor or VS Code** for the MCP server to reload with the new configuration.

## Configuration Example (Real Case)

Here's a working example with actual values:

**playwright-mcp-config.json:**
```json
{
  "browser": {
    "browserName": "chromium",
    "launchOptions": {
      "headless": false
    },
    "contextOptions": {
      "viewport": {
        "width": 1280,
        "height": 720
      },
      "httpCredentials": {
        "username": "gNhwRLuC",
        "password": "OZ7h82Gknc"
      }
    }
  },
  "capabilities": ["core", "tabs", "screenshot", "wait"]
}
```

**mcp.json:**
```json
{
  "mcpServers": {
    "Playwright": {
      "command": "npx",
      "args": [
        "-y",
        "@playwright/mcp@latest",
        "--config=C:/Users/Alex/.cursor/playwright-mcp-config.json",
        "--proxy-server=http://77.47.156.7:50100"
      ]
    }
  }
}
```

## Verification Steps

### Test 1: Check IP Address

Use Playwright MCP to navigate to a site that shows your IP address:

```javascript
// Navigate to whatsmyip.org
await page.goto('https://www.whatsmyip.org/');
```

If the proxy is working correctly, the displayed IP should match your **proxy server IP**, not your local machine IP.

### Test 2: Browser Console

Check if Playwright MCP shows available tools:
- In Cursor, the MCP panel should show Playwright with available tools
- If you see "No tools, prompts, or resources", the configuration failed to load

## Common Issues & Troubleshooting

### Issue 1: `ERR_INVALID_AUTH_CREDENTIALS`

**Cause:** Proxy credentials are embedded in the proxy URL instead of in the config file.

**Solution:** 
- Remove credentials from `--proxy-server` argument
- Add `httpCredentials` to the config file's `contextOptions`

### Issue 2: "No tools, prompts, or resources"

**Cause:** Configuration file not found or has syntax errors.

**Solution:**
- Verify the config file path is correct with forward slashes
- Check JSON syntax (use a JSON validator)
- Ensure the file exists at the specified location

### Issue 3: Proxy Authentication Dialog Appears

**Cause:** `httpCredentials` not properly configured.

**Solution:**
- Verify `httpCredentials` is inside `contextOptions`, not `launchOptions`
- Double-check username and password are correct
- Restart Cursor after making changes

### Issue 4: Connection Timeout

**Cause:** Proxy server is unreachable or blocked.

**Solution:**
- Test proxy connectivity outside of Playwright
- Verify firewall settings
- Check if proxy requires additional authentication methods

## Alternative Approach: Environment Variables

If the above approach doesn't work, you can try setting proxy credentials via environment variables:

**mcp.json:**
```json
{
  "mcpServers": {
    "Playwright": {
      "command": "npx",
      "args": [
        "-y",
        "@playwright/mcp@latest",
        "--proxy-server=http://proxy_ip:proxy_port"
      ],
      "env": {
        "HTTP_PROXY_USERNAME": "your_username",
        "HTTP_PROXY_PASSWORD": "your_password"
      }
    }
  }
}
```

**Note:** This method may not work with all proxy types.

## Additional Configuration Options

### Headless Mode

For automated scripts, enable headless mode:

```json
"launchOptions": {
  "headless": true
}
```

### Bypass Proxy for Specific Domains

Use the `--proxy-bypass` flag to exclude certain domains from proxying:

```json
"args": [
  "-y",
  "@playwright/mcp@latest",
  "--config=...",
  "--proxy-server=http://proxy:port",
  "--proxy-bypass=localhost,127.0.0.1,*.internal.example.com"
]
```

### Save Traces for Debugging

Enable trace saving to debug issues:

```json
"args": [
  "-y",
  "@playwright/mcp@latest",
  "--config=...",
  "--proxy-server=http://proxy:port",
  "--save-trace"
]
```

## Security Best Practices

1. **Never commit proxy credentials to version control**
   - Add config files with credentials to `.gitignore`
   - Use environment variables for sensitive data when possible

2. **Use encrypted storage**
   - Consider using a secrets manager
   - Store credentials in a secure location

3. **Rotate credentials regularly**
   - Update proxy passwords periodically
   - Monitor proxy access logs

4. **Limit proxy access**
   - Use proxy authentication
   - Restrict access by IP when possible

## References

- [Playwright MCP Official Documentation](https://github.com/microsoft/playwright-mcp)
- [Playwright Proxy Configuration](https://playwright.dev/docs/network#http-proxy)
- [ScrapingAnt Playwright MCP Proxy Guide](https://scrapingant.com/blog/playwright-mcp-server-proxy)

## Quick Reference

| File | Location | Purpose |
|------|----------|---------|
| `playwright-mcp-config.json` | `~/.cursor/` | Proxy authentication & browser settings |
| `mcp.json` | `~/.cursor/` | MCP server configuration |

**Must-remember:**
- ✅ Put authentication in `httpCredentials` inside `contextOptions`
- ✅ Use `--config` flag to load the configuration file
- ✅ Use `--proxy-server` for the proxy address (without credentials)
- ✅ Restart Cursor after configuration changes
- ✅ Test with whatsmyip.org to verify proxy is working

---

**Last Updated:** October 10, 2025  
**Tested With:** @playwright/mcp@latest, Cursor IDE




