# infra — Infraestructura en AWS con Terraform

Este paquete crea en AWS lo que el pipeline necesita para desplegar dev, stage y prod: la red, la
EC2, la base de datos, los buckets, los respaldos y los permisos. No despliega la aplicación; eso
lo hace `.github/workflows/ci-cd.yml` por SSH.

El primer despliegue completo está en la guía de despliegue de la entrega
(`Entregable_Final/guia-de-despliegue.md`): cuentas, secretos de GitHub y el orden de los pasos.

## Qué crea

| Archivo | Recursos |
|---|---|
| `bootstrap/main.tf` | Se aplica una vez. Crea el bucket del estado de Terraform (versionado y privado), el proveedor OIDC de GitHub y el rol `clinica-infra`, que solo asume el Environment `infra` |
| `main.tf` | El backend en S3 con bloqueo por archivo, las variables, el presupuesto de USD 85 con aviso al 80 % y las salidas |
| `network.tf` | VPC `10.20.0.0/16` con una subred pública y dos privadas, sin NAT. Grupo `clinica-web`: 22, 80, 443, 8081 y 8082. Grupo `clinica-db`: 3306 solo desde `clinica-web` |
| `ec2.tf` | EC2 `t3.medium` con Ubuntu 24.04 e IP elástica, disco cifrado, IMDSv2 y el rol que lee y escribe objetos solo en los tres buckets |
| `rds.tf` | MariaDB 11.4.13 `db.t4g.micro`: privada, cifrada, con TLS obligatorio, PITR de 7 días y protección contra borrado |
| `s3.tf` | Un bucket privado y versionado por entorno, y el respaldo semanal de la base con AWS Backup, que guarda 4 semanas |
| `user-data.sh` | Primer arranque de la EC2: Docker, Compose y containerd con versión fija, bitácoras de contenedor rotadas y 2 GB de swap |
| `.terraform.lock.hcl` | Fija el proveedor `hashicorp/aws` 6.69.1 |

Cuesta unos USD 50 al mes.

## Cómo se usa

Terraform 1.16.6 corre en contenedor, la misma versión que la CI:

```bash
alias terraform='docker run --rm -it -u "$(id -u):$(id -g)" -e HOME=/tmp -v "$HOME/.aws:/tmp/.aws:ro" \
  -e AWS_PROFILE -e TF_VAR_db_admin_password -e TF_VAR_ssh_public_key -e TF_VAR_alert_email \
  -v "$PWD:/w" -w /w hashicorp/terraform:1.16.6'
```

1. **Bootstrap**, una vez, con credenciales de administrador:
   `cd infra/bootstrap && terraform init && terraform apply`. Da `state_bucket` e `infra_role_arn`.
   Su estado queda en `bootstrap/terraform.tfstate`, ignorado por git.
2. **Primer `apply`**, desde tu máquina, porque el primer PR ya necesita la EC2 para desplegar dev:
   ```bash
   cd infra
   export TF_VAR_db_admin_password="$(openssl rand -hex 24)" TF_VAR_ssh_public_key="$(cat ~/.ssh/clinica-ec2.pub)" TF_VAR_alert_email="tu-correo@ejemplo.com"
   terraform init -backend-config="bucket=<state_bucket>"
   terraform apply
   ```
3. **Cambios posteriores:**
   - se fusionan por PR, y el PR no ejecuta Terraform;
   - luego se corre Actions → **Infraestructura** → `plan`, con aprobación del Environment `infra`;
   - si el plan es el esperado, otra ejecución con `apply`.

   El workflow entra a AWS por OIDC, sin llaves guardadas.

| Variable | Qué es |
|---|---|
| `db_admin_password` | Contraseña del usuario maestro de RDS. Es *write-only*: no queda en el estado |
| `ssh_public_key` | Llave pública con la que el pipeline entra a la EC2 |
| `alert_email` | Correo que recibe el aviso de presupuesto |

| Salida | Se usa como |
|---|---|
| `ec2_host` | Secreto `EC2_HOST` de dev, stage y prod |
| `db_host` | Secreto `DB_HOST` de dev, stage y prod |
| `buckets` | Secreto `AWS_BUCKET` de cada entorno |

## Límites

Están declarados en `docs/adr/fase-3.md`:
- una EC2 para los tres entornos, que comparten su rol (ADR-023);
- el puerto 22 abierto para los runners de GitHub, solo con llave (ADR-027);
- sin balanceador ni Multi-AZ. Cuándo agregarlos lo dice el documento de escalabilidad de la entrega.

La base tiene protección contra borrado. Para destruir todo, sigue «Apagar todo» en la guía de
despliegue.
