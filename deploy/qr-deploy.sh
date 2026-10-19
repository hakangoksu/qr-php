#!/usr/bin/env bash
set -euo pipefail

allowed_app="${1:?this key is not bound to an app}"

read -r app tag extra<<< "${SSH_ORIGINAL_COMMAND:-}"

if [[ "${app:-}" != "$allowed_app" ]]; then
    echo "this key may only deploy $allowed_app" >&2
    exit 2
fi

case "${app:-}" in
    qr-php) port=5103 ;;
    *) echo "unknown app: '${app:-}'" >&2; exit 2;;
esac

if [[ ! "${tag:-}" =~ ^[0-9a-f]{40}$ || -n "${extra:-}" ]]; then
    echo "usage: <app> <40-character commit sha>" >&2
    exit 2
fi

cd "/opt/$app"

healthy() {
    for _ in $(seq 1 30); do
        if curl -fs -o /dev/null "http://127.0.0.1:$port/health"; then
            return 0
        fi
        sleep 2
    done
    return 1
}

switch_to() {
    sed -i "s/^APP_TAG=.*/APP_TAG=$1/" .env
    docker compose up -d app
}

previous=$(sed -n 's/^APP_TAG=//p' .env)

APP_TAG="$tag" docker compose pull app

switch_to "$tag"
if healthy; then
    echo "deployed $app $tag, health OK"
    exit 0
fi
echo "$app $tag did not become healthy within 60 seconds" >&2
docker compose logs --tail 50 app >&2

if [[ "$previous" =~ ^[0-9a-f]{40}$ && "$previous" != "$tag" ]]; then
    echo "rolling back to $previous" >&2
    switch_to "$previous"
    if healthy; then
        echo "rolled back to $previous, health OK" >&2
    else
        echo "rollback to $previous is not healthy either" >&2
    fi
else
    echo "no previous version to roll back to" >&2
fi

exit 1
