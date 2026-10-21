#!/usr/bin/env bash
# Certificado de Let's Encrypt por DNS-01 de DuckDNS, uno para los tres
# entornos. El pipeline lo copia a ~/clinica y lo corre en cada despliegue; el
# cron que deja instalado lo renueva a diario.
set -euo pipefail

cd ~/clinica
set -a
# shellcheck source=/dev/null # lo escribe el pipeline
. ./tls.env
set +a

# La IP elastica no cambia; actualizarla aqui solo cubre la primera vez. El
# token va por stdin para que no aparezca en la lista de procesos.
duckdns=$(curl -fsS -K - <<< "url = \"https://www.duckdns.org/update?domains=${TLS_DOMAIN%%.*}&token=$DUCKDNS_TOKEN\"")
[ "$duckdns" = OK ] || { echo "DuckDNS rechazo la actualizacion: $duckdns" >&2; exit 1; }

# lego corre como 101, el usuario de nginx-unprivileged, para que nginx lea la llave.
[ -d tls ] || sudo install -d -o 101 -g 101 -m 700 tls
lego() {
  docker run --rm -u 101:101 -e DUCKDNS_TOKEN -v "$PWD/tls:/tls" goacme/lego:v4.35.2 --path /tls "$@"
}

# El host no ve dentro de tls/ (700 de lego); se le pregunta a lego. Si falla,
# set -e corta: nunca se pide un certificado nuevo por error.
certificates=$(lego list)
case "$certificates" in
  *"$TLS_DOMAIN"*) action=renew ;;
  *) action=run ;;
esac
lego --accept-tos --email "$TLS_EMAIL" --dns duckdns --domains "$TLS_DOMAIN" "$action"

docker ps -q --filter label=com.docker.compose.service=web | xargs -r -I{} docker exec {} nginx -s reload

cron="17 4 * * * flock $HOME/clinica/tls.lock $HOME/clinica/tls.sh >> $HOME/clinica/tls.log 2>&1"
{ crontab -l 2>/dev/null | grep -vF clinica/tls.sh || true; echo "$cron"; } | crontab -
