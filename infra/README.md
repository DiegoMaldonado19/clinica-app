# Infraestructura y primer despliegue

Terraform crea todo lo que vive en AWS; GitHub Actions despliega los tres entornos sobre ello.

| Recurso | Detalle |
|---|---|
| EC2 `t3.medium` con IP elástica | Ubuntu 24.04, Docker 29.9.1 y Compose 5.6.0 (`user-data.sh`). Corre dev, stage y prod, cada uno en `~/clinica/<entorno>`, con el asistente de cada entorno en su red interna |
| RDS MariaDB 11.4.13 `db.t4g.micro` | Subredes privadas, cifrada, TLS obligatorio, PITR de 7 días. Una base y un usuario por entorno |
| S3 | Un bucket privado y versionado por entorno. La EC2 entra con su rol, sin llaves |
| AWS Backup | Respaldo completo de RDS cada domingo a las 02:00 de Guatemala, 4 semanas |
| Presupuesto | Aviso por correo al 80 % de USD 85 al mes, y si el pronóstico lo supera |
| IAM | Rol de la EC2 (objetos de los 3 buckets), rol de GitHub por OIDC (`clinica-infra`, solo el Environment `infra`) y rol de AWS Backup |

Costo estimado: unos USD 50 al mes.

## Antes de empezar

- Credenciales de administrador de AWS en tu máquina (`aws configure`, región `us-east-1`).
- Una llave SSH para el pipeline: `ssh-keygen -t ed25519 -f ~/.ssh/clinica-ec2 -N ''`.
- Un subdominio de [DuckDNS](https://www.duckdns.org) y su token.
- Verificación en dos pasos en Gmail y una contraseña de aplicación.
- Un token de acceso de Docker Hub con lectura y escritura.

Terraform va en contenedor, con la misma versión que la CI:

```bash
alias terraform='docker run --rm -it -u "$(id -u):$(id -g)" -e HOME=/tmp -v "$HOME/.aws:/tmp/.aws:ro" \
  -e AWS_PROFILE -e TF_VAR_db_admin_password -e TF_VAR_ssh_public_key -e TF_VAR_alert_email \
  -v "$PWD:/w" -w /w hashicorp/terraform:1.16.6'
```

## 1. Bootstrap, una sola vez

Crea el bucket del estado de Terraform y el rol que asume GitHub Actions.

```bash
cd infra/bootstrap
terraform init && terraform apply
```

Anota `state_bucket` e `infra_role_arn`. El estado de este paso queda local en
`infra/bootstrap/terraform.tfstate` (ignorado por git); guárdalo si piensas destruirlo después.

## 2. Infraestructura

La primera vez se aplica desde tu máquina, porque el PR que despliega dev necesita la EC2 y la
base. Los cambios posteriores pasan por el workflow `Infraestructura`, que solo corre a mano y
con aprobación (paso 5).

```bash
cd infra
export TF_VAR_db_admin_password="$(openssl rand -hex 24)"   # guárdala: es DB_ADMIN_PASSWORD
export TF_VAR_ssh_public_key="$(cat ~/.ssh/clinica-ec2.pub)"
export TF_VAR_alert_email="tu-correo@ejemplo.com"
terraform init -backend-config="bucket=<state_bucket>"
terraform apply
```

Anota `ec2_host`, `db_host` y `buckets`. La contraseña de RDS es *write-only*: no queda en el
estado de Terraform.

## 3. GitHub

**Variables del repositorio** (Settings → Secrets and variables → Actions → Variables). Son
datos públicos, iguales en los tres entornos:

| Variable | Valor |
|---|---|
| `DOMAIN` | `<subdominio>.duckdns.org` |
| `CLINIC_PHONE`, `CLINIC_EMAIL`, `CLINIC_ADDRESS` | Datos de contacto reales de la clínica, los del pie de la landing |
| `CLINIC_CRISIS_LINE` | El número real de la línea de crisis. El widget del asistente lo muestra siempre (RF-62); sin él no se despliega |

**Environments** (Settings → Environments):

| Environment | Ramas que pueden desplegar | Revisor obligatorio |
|---|---|---|
| `dev` | Sin restricción (corre sobre el PR) | No |
| `stage` | `main` | No |
| `prod` | `main` | Sí, tú |
| `infra` | `main` | Sí, tú |

**Secretos de `dev`, `stage` y `prod`.** Los marcados como «distinto» cambian en cada entorno; los
demás llevan el mismo valor en los tres.

| Secreto | Valor |
|---|---|
| `DOCKERHUB_USERNAME`, `DOCKERHUB_TOKEN` | Tu usuario y el token de Docker Hub |
| `EC2_HOST` | `ec2_host` |
| `EC2_SSH_KEY` | Contenido de `~/.ssh/clinica-ec2` (la llave privada) |
| `DB_HOST` | `db_host` |
| `DB_ADMIN_PASSWORD` | El valor de `TF_VAR_db_admin_password` |
| `DB_PASSWORD` | Distinto: `openssl rand -hex 24` |
| `APP_KEY` | Distinto: `echo "base64:$(openssl rand -base64 32)"` |
| `CLINICAL_ENCRYPTION_KEY` | Distinto, y distinto de `APP_KEY`: el mismo comando |
| `AWS_BUCKET` | Distinto: `buckets.<entorno>` |
| `MAIL_USERNAME` | La cuenta de Gmail; también es el remitente |
| `MAIL_PASSWORD` | La contraseña de aplicación de Gmail, sin espacios |
| `ADMIN_EMAIL` | Correo real de la psicóloga en ese entorno |
| `DUCKDNS_TOKEN`, `TLS_EMAIL` | Token de DuckDNS y el correo para Let's Encrypt |
| `ANTHROPIC_API_KEY` | La API key de Anthropic del asistente, con límite de gasto en la consola de Anthropic |
| `CHATBOT_API_TOKEN` | Distinto: `openssl rand -hex 32`. Lo comparten Laravel y el asistente |
| `WHATSAPP_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID` | Opcionales. Sin los dos, el canal de WhatsApp no existe y los avisos salen por correo. Pasos en la guía de WhatsApp de F3-07 |

Ningún valor puede llevar comilla simple: el `.env` que escribe el pipeline los envuelve en `'…'`.

**Secretos de `infra`:** `AWS_ROLE_ARN` (`infra_role_arn`), `TF_STATE_BUCKET` (`state_bucket`),
`TF_VAR_db_admin_password`, `TF_VAR_ssh_public_key` y `TF_VAR_alert_email`.

**Protección de `main`:** Pull Request obligatorio, el check `quality` en verde y la rama al día con `main`.

## 4. Primer despliegue

1. Abre el PR hacia `main`. `quality` corre las pruebas y `deliver` despliega dev:
   `https://<dominio>:8082/up` debe responder.
2. Al fusionarlo, `deliver` despliega stage con la misma imagen: `https://<dominio>:8081`.
3. Actions → **CI/CD** → *Run workflow* desde `main` → aprueba → prod: `https://<dominio>`.
4. La psicóloga entra la primera vez por «¿Olvidaste tu contraseña?» con `ADMIN_EMAIL` y luego
   configura su segundo factor.

El primer despliegue de cada entorno emite el certificado de Let's Encrypt (común a los tres),
crea la base y su usuario, migra y siembra los catálogos.

## 5. Operación

| Tarea | Cómo |
|---|---|
| Entrar a la EC2 | `ssh -i ~/.ssh/clinica-ec2 ubuntu@<ec2_host>` |
| Estado y bitácoras de un entorno | `cd ~/clinica/<entorno> && docker compose ps` y `docker compose logs -f app worker` |
| Certificado | Lo renueva el cron a diario; su bitácora está en `~/clinica/tls.log` |
| Rotar un secreto | Cámbialo en el Environment y vuelve a correr el último despliegue de ese entorno |
| Volver a la versión anterior | En dev o stage, vuelve a correr la ejecución anterior que terminó bien. En prod, revierte el commit en `main` por PR y despacha prod cuando stage quede sano: prod siempre toma lo último que stage desplegó |
| Cambiar la infraestructura | Fusiona el cambio de `infra/` por PR (no corre nada). Luego Actions → **Infraestructura** → `plan`, apruébalo y revisa el plan; si es el esperado, otra ejecución con `apply`, también aprobada |

`scripts/deploy.sh` y `scripts/tls.sh` están hechos para la EC2: borran las imágenes sin uso y
programan el cron. No los corras en tu máquina.

## Límites declarados

- Los tres entornos comparten la EC2 y su rol IAM. Aislarlos por IAM exige una instancia por entorno.
- El puerto 22 está abierto a Internet porque los runners de GitHub no tienen IP fija. Solo se
  entra con la llave.
- La llave del host se acepta en la primera conexión de cada runner (`StrictHostKeyChecking accept-new`).
- Las imágenes de Docker Hub son públicas. No llevan secretos: contienen lo mismo que el repositorio.
- No hay vuelta atrás automática. Si los contenedores nuevos no quedan sanos, el entorno sigue con
  la versión fallida hasta volver a desplegar la anterior (paso 5). Si falla antes (migraciones,
  base), los contenedores anteriores siguen sirviendo.
