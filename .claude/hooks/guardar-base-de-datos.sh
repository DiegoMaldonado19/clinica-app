#!/usr/bin/env bash
# PreToolUse sobre Bash. Un agente que decide "limpiar la base para que las
# pruebas pasen" contra la conexion equivocada es un escenario plausible.
# Falla cerrado: sin .env legible con APP_ENV=local, no pasa.
set -uo pipefail

cd "${CLAUDE_PROJECT_DIR:-.}" || exit 0

comando=$(jq -r '.tool_input.command // empty')
grep -qE 'migrate:fresh|migrate:reset|db:wipe|DROP[[:space:]]+DATABASE' <<<"$comando" || exit 0

entorno=$(grep -m1 '^APP_ENV=' .env 2>/dev/null | cut -d= -f2- | tr -d '"'"'"' \r')

if [[ "$entorno" != "local" && "$entorno" != "testing" ]]; then
    echo "Bloqueado: comando destructivo de base de datos con APP_ENV='${entorno:-<sin .env>}'." >&2
    echo "Solo se permite con APP_ENV=local o testing." >&2
    exit 2
fi

exit 0
