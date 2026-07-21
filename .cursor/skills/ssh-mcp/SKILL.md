---
name: ssh-mcp
description: >-
  Run shell commands on the cloudPusher / push-service production Linux host via
  the ssh-mcp MCP server. Use when debugging Horizon, queues, logs, Forge
  deployment, nginx, or anything that must run on the remote server—not on the
  local Mac.
---

# SSH MCP (remote push-service host)

This project registers [ssh-mcp](https://github.com/tufantunc/ssh-mcp) so the agent can run commands on the deployment host over SSH.

## Setup (once per machine)

1. Clone and build (already done if `ssh-mcp/build/index.js` exists):

   ```bash
   git clone https://github.com/tufantunc/ssh-mcp.git
   cd ssh-mcp && npm install
   ```

2. Copy `.cursor/ssh-mcp.env.example` → `.cursor/ssh-mcp.env` (gitignored).
3. Set `SSH_MCP_HOST`, `SSH_MCP_USER`, and either `SSH_MCP_KEY` or `SSH_MCP_PASSWORD`.
4. Reload MCP in Cursor (**Settings → MCP** or restart Cursor) so the `ssh-mcp` server connects.

The launcher is `.cursor/scripts/ssh-mcp.sh`, wired in `.cursor/mcp.json` and `~/.cursor/mcp.json`.

## When to use which tool

| Need | Use |
|------|-----|
| Remote shell on the Linux host (Horizon, logs, `artisan` on server) | **ssh-mcp** `exec` / `sudo-exec` |
| Local PHP, tests, Eloquent, Laravel docs | **laravel-boost** MCP |
| Local Herd site URL, PHP version | **herd** MCP |

Prefer **read-only** checks first (`php artisan horizon:status`, `tail` logs, `supervisorctl status`). Ask before destructive commands (`migrate:fresh`, `rm`, restarts that drop traffic, etc.).

## MCP tools

- **`exec`** — run a shell command as the SSH user.
- **`sudo-exec`** — run with sudo (requires `SSH_MCP_SUDO_PASSWORD` in env, or passwordless sudo). Disable entirely with `SSH_MCP_DISABLE_SUDO=1` in `ssh-mcp.env`.

Default command timeout is 60s unless `SSH_MCP_TIMEOUT` is set in `ssh-mcp.env`. Use `--maxChars=none` via `SSH_MCP_MAX_CHARS=none` for long one-liners.

Always read the tool schema under the MCP descriptors before calling tools.

## Paths on the server

Typical Forge-style layout (adjust per host):

- App root: `/home/forge/<site>/current`
- Shared `.env`: `/home/forge/<site>/shared/.env`
- Site hostname (CORS / PWA): `push-ncloud.on-forge.com` (Cloudflare — not the SSH host)

Useful remote checks:

```bash
cd /home/forge/*/current && php artisan about
php artisan horizon:status
php artisan queue:failed
tail -n 100 storage/logs/laravel.log
```

## Security

- Never commit `.cursor/ssh-mcp.env` or paste passwords into chat logs.
- Do not store production secrets in tracked files; only `ssh-mcp.env.example` belongs in git.
- Treat `sudo-exec` as high risk; confirm intent before system-wide changes.
