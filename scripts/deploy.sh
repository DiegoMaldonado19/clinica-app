#!/usr/bin/env bash
# Despliega un entorno en la EC2. El pipeline lo corre por SSH dentro del clon
# del entorno, con el .env ya escrito. Lo que la app no debe ver (la contrasena
# maestra de RDS y el token de Docker Hub) llega por stdin, una linea CLAVE=valor.
set -euo pipefail

while IFS= read -r line; do export "${line?}"; done
set -a
# shellcheck source=/dev/null # lo escribe el pipeline
. ./.env
set +a

# Docker Hub limita las descargas anonimas por IP, y dev se redespliega en cada push del PR.
docker login -u "$DOCKERHUB_USERNAME" --password-stdin <<< "$DOCKERHUB_TOKEN" > /dev/null
trap 'docker logout > /dev/null' EXIT

# La base y el usuario del entorno, sin tocar los de los otros dos. TLS
# verificado contra la CA de RDS; ALTER USER aplica una contrasena rotada.
MYSQL_PWD=$DB_ADMIN_PASSWORD docker run --rm -i -e MYSQL_PWD \
  -v "$PWD/docker/php/rds-us-east-1.pem:/rds-ca.pem:ro" mariadb:11.4.13 \
  mariadb -h "$DB_HOST" -P "$DB_PORT" -u clinica_admin --ssl-ca=/rds-ca.pem <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USERNAME'@'%' IDENTIFIED BY '$DB_PASSWORD';
ALTER USER '$DB_USERNAME'@'%' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON \`$DB_DATABASE\`.* TO '$DB_USERNAME'@'%';
SQL

docker compose pull --quiet
docker compose run --rm -T app php artisan migrate --force --seed
docker compose up -d --wait --remove-orphans
docker image prune -af > /dev/null
