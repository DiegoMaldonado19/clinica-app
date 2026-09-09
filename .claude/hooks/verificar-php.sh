#!/usr/bin/env bash
# PostToolUse sobre Edit/Write. Formatea el fichero tocado y, si vive en el
# dominio, comprueba la regla de dependencia en el acto: la violacion se sabe
# ahora, no en la revision del Pull Request.
set -uo pipefail

cd "${CLAUDE_PROJECT_DIR:-.}" || exit 0

file=$(jq -r '.tool_input.file_path // empty')
[[ "$file" == *.php ]] || exit 0

[[ -x vendor/bin/pint ]] && vendor/bin/pint --quiet "$file" >/dev/null 2>&1

case "$file" in
    */src/*|src/*)
        if [[ -x vendor/bin/deptrac ]]; then
            if ! salida=$(vendor/bin/deptrac analyse --no-progress 2>&1); then
                echo "Deptrac rechaza este cambio (ADR-002):" >&2
                echo "$salida" | grep -E 'DependsOnDisallowedLayer|must not depend' >&2
                exit 2
            fi
        fi
        ;;
esac

exit 0
