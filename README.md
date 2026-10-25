# BPMS · Clínica de psicología

Sistema de gestión de procesos para una clínica de psicología de una sola profesional:
agendamiento en línea con aprobación por SLA, pagos con conciliación humana, cancelación y
reprogramación con política escalonada y crédito a favor, expediente clínico con nota SOAP
inmutable, bitácora de accesos, tablero de indicadores con exportación a Excel y PDF, y un
asistente conversacional que responde con fuente, agenda y escala las crisis.

Laravel 13 · Filament 5 · Livewire 4 · MariaDB 11.4 · Redis 7 · MinIO (S3) · Mailpit, y el
asistente en Python 3.13 con FastAPI y Claude Haiku 5.5.
El diseño completo está en `../Entregable_2_Final/`; el plan y el estado de la Fase 2, en
[`docs/fase-2/`](docs/fase-2/).

## Levantar el proyecto

Requiere Docker. Cinco comandos:

```bash
cp .env.example .env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate && echo "CLINICAL_ENCRYPTION_KEY=base64:$(head -c 32 /dev/urandom | base64)" >> .env
docker compose up -d --force-recreate app worker scheduler && docker compose exec app php artisan migrate --seed
```

La llave `CLINICAL_ENCRYPTION_KEY` cifra las notas SOAP y es distinta de `APP_KEY`. Sin ella,
el expediente no abre. El último comando recrea los contenedores para que la lean.

| Superficie | URL |
|---|---|
| Landing y agendamiento | http://localhost |
| Portal del paciente | http://localhost/portal |
| Panel (psicóloga y recepción) | http://localhost/admin |
| Correo capturado (Mailpit) | http://localhost:8025 |
| Consola de MinIO | http://localhost:9001 |

El asistente (`chatbot/`) no publica puertos: el widget de la landing le habla desde Laravel. Sin
`ANTHROPIC_API_KEY` en el `.env`, el widget no ofrece chat. En su lugar muestra las preguntas
frecuentes, el enlace para agendar, el teléfono y la línea de crisis (ADR-038). En local, el token
entre Laravel y el asistente tiene un valor de serie.

### Cuentas de demostración

Solo con `APP_ENV=local`. La contraseña de las tres es `password`. El personal configura su
segundo factor la primera vez que entra al panel; el código por correo llega a Mailpit.

| Perfil | Correo |
|---|---|
| Psicóloga (administradora) | `psicologa@clinica.test` |
| Recepción | `recepcion@clinica.test` |
| Paciente | `paciente@clinica.test` |

## Verificar

```bash
docker compose exec app vendor/bin/pint --test
docker compose exec app vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec app vendor/bin/deptrac analyse --fail-on-uncovered
docker compose exec app vendor/bin/pest
docker compose exec -e CACHE_STORE=null app vendor/bin/pest --testsuite=Feature   # CA-32
```

`vendor/bin/pest --testsuite=Unit` prueba el dominio sin base de datos ni framework: son las
reglas RN-xx y la máquina de 13 estados de la cita.

El asistente se verifica como en la CI, dentro de su imagen de Python y sin dejar archivos en el
repositorio. `pytest` incluye la batería de 20 casos de crisis:

```bash
docker run --rm -v "$PWD/chatbot:/src:ro" python:3.13.16-slim-trixie sh -c 'cp -r /src /tmp/chatbot && cd /tmp/chatbot \
  && pip install -q --root-user-action=ignore --disable-pip-version-check -r requirements-dev.txt \
  && ruff format --check . && ruff check . && mypy app tests && pytest && pip-audit -r requirements.txt'
```

## Estructura

```
src/<Contexto>/Domain          reglas de negocio, sin Illuminate\* (Deptrac lo verifica)
src/<Contexto>/Application     orquestación de los casos de uso
src/<Contexto>/Infrastructure  adaptadores: base de datos, colas, correo, candados
app/                           Laravel: paneles Filament, Livewire, modelos de lectura
chatbot/                       el asistente: FastAPI, clasificador de crisis y cliente de la API
docs/api/openapi.yaml          contrato de la API /api/v1 que usa el asistente
```

Contextos: `Scheduling`, `Billing`, `ClinicalRecords`, `Identity`, `Notifications`, `Analytics`,
`Shared`. Se comunican solo por eventos de dominio; `Analytics` solo lee.

## Despliegue en la nube

Una EC2 en AWS con los tres entornos, una RDS MariaDB con una base por entorno y un bucket S3
privado por entorno. Todo se crea con Terraform (`infra/`) y se despliega con GitHub Actions:

| Evento | Entorno | URL |
|---|---|---|
| Pull Request hacia `main` | dev | `https://<dominio>:8082` |
| Merge a `main` | stage | `https://<dominio>:8081` |
| Despacho manual de `CI/CD` desde `main`, con aprobación | prod | `https://<dominio>` |

- `ci-cd.yml` construye las imágenes (`clinica-app`, `clinica-chatbot` y `clinica-web`) una vez
  por árbol de git y promueve esas mismas de dev a stage y a prod. Prod nunca construye: recibe
  lo último que stage desplegó sano.
- `infra.yml` corre `plan` o `apply` solo a mano, desde `main` y con aprobación, con credenciales
  de AWS por OIDC. Ningún evento del repositorio toca la infraestructura.
- `scripts/deploy.sh` y `scripts/tls.sh` corren en la EC2: base y usuario del entorno,
  migraciones, contenedores y el certificado de Let's Encrypt.

Los pasos del primer despliegue y los secretos de cada Environment están en la guía de despliegue
de la entrega (`Entregable_Final/guia-de-despliegue.md`). Qué crea Terraform y cómo se aplica está
en [`infra/README.md`](infra/README.md).

## Tecnologías y versiones

Las dependencias de Composer y npm quedan fijadas por `composer.lock` y `package-lock.json`.

**Aplicación**

| Tecnología | Versión |
|---|---|
| PHP | 8.4.27 |
| Laravel | 13.31.0 |
| Filament | 5.8.4 |
| Livewire | 4.4.8 |
| Vite · Tailwind CSS | 8.2.2 · 4.3.3 |
| laravel-dompdf · dompdf (PDF) | 3.1.2 · 3.1.6 |
| OpenSpout (XLSX) | 4.32.0 |

**Asistente** (`chatbot/requirements.txt` fija también las transitivas)

| Tecnología | Versión |
|---|---|
| Python | 3.13.16 |
| FastAPI · uvicorn | 0.142.4 · 0.54.0 |
| SDK `anthropic` · modelo | 1.12.1 · `claude-haiku-5-5` |
| pytest · ruff · mypy · pip-audit | 9.1.1 · 0.17.0 · 2.4.0 · 2.10.1 |

**Pruebas y calidad**

| Herramienta | Versión |
|---|---|
| Pest (sobre PHPUnit 13.3.2) | 5.1.4 |
| PHPStan · Larastan | 2.2.13 · 3.11.0 |
| Deptrac | 4.7.1 |
| Pint | 1.30.5 |

**Imágenes Docker**

| Imagen | Uso |
|---|---|
| `php:8.4.27-fpm-alpine` | Base de los destinos `dev` y `prod`; la CI corre dentro de `dev` |
| `mlocati/php-extension-installer:2.12.0` | Instala las extensiones de PHP |
| `composer:2.9.8` | Composer dentro de `dev` y `prod` |
| `node:24.21.0-alpine` | Construcción de los assets y Vite en local |
| `nginxinc/nginx-unprivileged:1.30.5-alpine` | Servidor web, sin root |
| `mariadb:11.4.13` | Base de datos en local y en la CI |
| `redis:7.4.11-alpine` | Caché en local y en la CI |
| `pgsty/minio:RELEASE.2026-08-04T00-00-00Z` | S3 en local; con su `mc` se crea el bucket |
| `axllent/mailpit:v1.31.3` | Correo capturado en local |
| `python:3.13.16-slim-trixie` | El asistente y su job de la CI |
| `goacme/lego:v4.35.2` | Certificado de Let's Encrypt en la EC2 |

**Nube**

| Tecnología | Versión |
|---|---|
| Terraform · proveedor `hashicorp/aws` | 1.16.6 · 6.69.1 |
| RDS MariaDB | 11.4.13 |
| EC2: Ubuntu 24.04 (AMI `20261004`) · Docker · Compose | 29.9.1 · 5.6.0 |

**GitHub Actions** (runner `ubuntu-24.04`)

| Action | Versión |
|---|---|
| `actions/checkout` | v7.0.1 |
| `docker/setup-buildx-action` | v4.4.1 |
| `docker/build-push-action` | v7.4.0 |
| `docker/login-action` | v4.6.0 |
| `gitleaks/gitleaks-action` | v3.0.0 |
| `hashicorp/setup-terraform` | v4.0.1 |
| `aws-actions/configure-aws-credentials` | v6.3.0 |

## Documentación

| Documento | Para |
|---|---|
| [`docs/fase-2/01`](docs/fase-2/01-fases-de-avance.md) | Plan por fases F-01…F-12 |
| [`docs/fase-2/02`](docs/fase-2/02-configuracion-de-infraestructura.md) | Infraestructura local y CI |
| [`docs/fase-2/03`](docs/fase-2/03-requisitos-de-la-fase.md) | Qué exige la fase y dónde quedó cada punto |
| [`docs/fase-2/04`](docs/fase-2/04-manual-de-usuario.md) | Manual de usuario |
| [`docs/fase-2/05`](docs/fase-2/05-documentacion-de-usuario.md) | Qué ve y qué puede hacer cada perfil |
| [`docs/api/openapi.yaml`](docs/api/openapi.yaml) | Contrato de la API del asistente (OpenAPI 3.1) |
| [`infra/README.md`](infra/README.md) | Paquete de Terraform: qué crea en AWS y cómo se aplica |
| [`docs/runbook.md`](docs/runbook.md) | Runbook de operación RB-01…RB-12 |
| [`docs/adr/fase-3.md`](docs/adr/fase-3.md) | Decisiones de la Fase 3 (ADR-023…ADR-038) |
