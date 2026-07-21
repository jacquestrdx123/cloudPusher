#!/usr/bin/env bash
# Launches https://github.com/tufantunc/ssh-mcp with credentials from .cursor/ssh-mcp.env
# Prefers the local clone at ssh-mcp/build/index.js; falls back to npx.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
ENV_FILE="${SCRIPT_DIR}/../ssh-mcp.env"
LOCAL_MCP="${PROJECT_ROOT}/ssh-mcp/build/index.js"

if [[ ! -f "${ENV_FILE}" ]]; then
    echo "ssh-mcp: missing ${ENV_FILE}" >&2
    echo "Copy .cursor/ssh-mcp.env.example to .cursor/ssh-mcp.env and set SSH_MCP_HOST / SSH_MCP_USER." >&2
    exit 1
fi

# shellcheck source=/dev/null
source "${ENV_FILE}"

if [[ -z "${SSH_MCP_HOST:-}" || -z "${SSH_MCP_USER:-}" ]]; then
    echo "ssh-mcp: SSH_MCP_HOST and SSH_MCP_USER are required in ${ENV_FILE}" >&2
    exit 1
fi

if [[ -z "${SSH_MCP_KEY:-}" && -z "${SSH_MCP_PASSWORD:-}" ]]; then
    echo "ssh-mcp: set SSH_MCP_KEY or SSH_MCP_PASSWORD in ${ENV_FILE}" >&2
    exit 1
fi

args=("--host=${SSH_MCP_HOST}" "--user=${SSH_MCP_USER}")

if [[ -n "${SSH_MCP_PORT:-}" ]]; then
    args+=("--port=${SSH_MCP_PORT}")
fi

if [[ -n "${SSH_MCP_KEY:-}" ]]; then
    key_path="${SSH_MCP_KEY/#\~/$HOME}"
    args+=("--key=${key_path}")
fi

if [[ -n "${SSH_MCP_PASSWORD:-}" ]]; then
    args+=("--password=${SSH_MCP_PASSWORD}")
fi

if [[ -n "${SSH_MCP_SUDO_PASSWORD:-}" ]]; then
    args+=("--sudoPassword=${SSH_MCP_SUDO_PASSWORD}")
fi

if [[ -n "${SSH_MCP_SU_PASSWORD:-}" ]]; then
    args+=("--suPassword=${SSH_MCP_SU_PASSWORD}")
fi

if [[ -n "${SSH_MCP_TIMEOUT:-}" ]]; then
    args+=("--timeout=${SSH_MCP_TIMEOUT}")
fi

if [[ -n "${SSH_MCP_MAX_CHARS:-}" ]]; then
    args+=("--maxChars=${SSH_MCP_MAX_CHARS}")
fi

if [[ "${SSH_MCP_DISABLE_SUDO:-}" == "1" ]]; then
    args+=(--disableSudo)
fi

if [[ -f "${LOCAL_MCP}" ]]; then
    exec node "${LOCAL_MCP}" "${args[@]}"
fi

exec npx -y ssh-mcp -- "${args[@]}"
