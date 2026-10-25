# Runbook de operación

Son los procedimientos RB-01 a RB-12 del doc 11 §4, adaptados a lo que se desplegó:
- una EC2 con los tres entornos (ADR-023);
- despliegue por SSH desde GitHub Actions (ADR-027);
- RDS con PITR de 7 días.

El primer despliegue está en la guía de despliegue de la entrega (`Entregable_Final/guia-de-despliegue.md`).
Qué crea Terraform está en [`infra/README.md`](../infra/README.md).

En los comandos:
- `<ec2_host>` y `<dominio>` son las salidas de Terraform y la variable `DOMAIN`;
- `<entorno>` es `dev`, `stage` o `prod`;
- los comandos `aws` corren en la máquina del autor, con la región `us-east-1`;
- los de `docker compose` corren en la EC2, dentro de `~/clinica/<entorno>`.

| # | Procedimiento | Frecuencia |
|---|---|---|
| [RB-01](#rb-01--desplegar-a-producción) | Desplegar a producción | Por versión |
| [RB-02](#rb-02--revertir-un-despliegue) | Revertir un despliegue | Ante incidente |
| [RB-03](#rb-03--restaurar-la-base-a-un-instante-concreto) | Restaurar la base a un instante concreto | Ante incidente |
| [RB-04](#rb-04--simulacro-de-restauración) | Simulacro de restauración | Mensual |
| [RB-05](#rb-05--rotar-un-secreto) | Rotar un secreto | Ante exposición o cada 6 meses |
| [RB-06](#rb-06--reemplazar-la-instancia) | Reemplazar la instancia | Ante incidente |
| [RB-07](#rb-07--diagnosticar-trabajos-encolados-detenidos) | Diagnosticar trabajos encolados detenidos | Ante alarma |
| [RB-08](#rb-08--diagnosticar-correo-no-entregado) | Diagnosticar correo no entregado | Ante NT-20 o KPI-10 |
| [RB-09](#rb-09--recuperar-un-entorno-degradado-sin-desplegar) | Recuperar un entorno degradado sin desplegar | Ante degradación |
| [RB-10](#rb-10--actualizar-lo-que-sabe-el-asistente) | Actualizar lo que sabe el asistente | Al cambiar tarifas o servicios |
| [RB-11](#rb-11--responder-a-una-alerta-de-riesgo-nt-24-o-nt-25) | Responder a una alerta de riesgo (NT-24 o NT-25) | Ante la alerta |
| [RB-12](#rb-12--responder-a-un-incidente-de-seguridad) | Responder a un incidente de seguridad | Ante incidente |

Para entrar a la EC2: `ssh -i ~/.ssh/clinica-ec2 ubuntu@<ec2_host>`.

---

## RB-01 · Desplegar a producción

1. Fusiona el PR. stage se despliega solo. Revisa que `https://<dominio>:8081/up` responda y prueba
   ahí lo que cambió.
2. Actions → **CI/CD** → *Run workflow* desde `main` → *Review deployments* → *Approve*.
3. Prod toma la imagen del último despliegue sano de stage, sin construir. Comprueba:
   - que `https://<dominio>/up` responda;
   - el número de imagen en `docker compose ps`, que debe ser el mismo de stage.

## RB-02 · Revertir un despliegue

- **dev o stage:** Actions → la ejecución anterior que terminó bien → *Re-run all jobs*. El árbol ya
  tiene imagen, así que se promueve esa misma sin construir.
- **prod:**
  1. revierte el commit en `main` con un PR (`git revert`);
  2. al fusionarlo, stage queda con la versión anterior;
  3. despacha prod (RB-01).
- **Las migraciones no se revierten.** Si la versión fallida cambió el esquema, se corrige hacia
  adelante con una migración nueva. Si además dañó datos, sigue RB-03.

## RB-03 · Restaurar la base a un instante concreto

**Nunca se restaura sobre la instancia de producción.** Hacerlo destruye la evidencia del incidente
e impide comparar.

1. Identifica el momento anterior al incidente en la bitácora (panel → Bitácora). La hora del panel
   es de Guatemala; el comando lleva UTC (+6 horas).
2. Restaura a una instancia temporal, en la red y con los parámetros de la original:
   ```bash
   SG=$(aws ec2 describe-security-groups --filters Name=group-name,Values=clinica-db \
     --query 'SecurityGroups[0].GroupId' --output text)
   aws rds restore-db-instance-to-point-in-time \
     --source-db-instance-identifier clinica \
     --target-db-instance-identifier clinica-restaurada \
     --restore-time 2026-10-30T15:00:00Z \
     --db-subnet-group-name clinica --vpc-security-group-ids "$SG" \
     --db-parameter-group-name clinica-mariadb114 --no-publicly-accessible
   aws rds wait db-instance-available --db-instance-identifier clinica-restaurada
   aws rds describe-db-instances --db-instance-identifier clinica-restaurada \
     --query 'DBInstances[0].Endpoint.Address' --output text
   ```
3. Verifica la temporal desde la EC2, con el `.env` de prod y solo el host cambiado:
   ```bash
   cd ~/clinica/prod && R=<endpoint de la temporal>
   docker compose run --rm -T -e DB_HOST=$R app php artisan migrate:status | tail -3
   docker compose run --rm -T -e DB_HOST=$R app php artisan tinker --execute 'foreach (["appointments","clinical_notes","payments","psych_test_assignments","audit_log"] as $t) echo $t."=".DB::table($t)->count()." ";'
   docker compose run --rm -T -e DB_HOST=$R app php artisan tinker --execute '$n = DB::table("clinical_notes")->orderByDesc("created_at")->first(); echo $n === null ? "sin notas" : (strlen((string) app(App\ClinicalRecords\Infrastructure\ClinicalCipher::class)->decrypt($n->soap_subjective)) > 0 ? "nota descifrada" : "no descifra");'
   ```
   El tercer comando es **el paso crítico**: sin `CLINICAL_ENCRYPTION_KEY`, un respaldo de notas
   clínicas no sirve. Imprime si descifra, nunca el contenido.
4. Decide con la psicóloga si se extraen datos puntuales de la temporal o si se promueve.
5. Para promover la temporal:
   1. pasa la original a `clinica-anterior` y luego la temporal a `clinica`, con
      `aws rds modify-db-instance --new-db-instance-identifier … --apply-immediately`;
   2. el endpoint vuelve a ser el de siempre, así que los secretos no cambian;
   3. reinicia los contenedores (`docker compose restart` en cada entorno);
   4. en Terraform, `terraform state rm aws_db_instance.main` y luego
      `terraform import aws_db_instance.main clinica`.
6. Anota la causa, el momento restaurado y el RTO real en la bitácora del runbook (abajo).
7. Borra la instancia que sobra:
   `aws rds delete-db-instance --db-instance-identifier <instancia> --skip-final-snapshot --delete-automated-backups`.

## RB-04 · Simulacro de restauración

Es RB-03 con el último instante restaurable, sin promover nada. Cuesta centavos: una `db.t4g.micro`
por menos de una hora.

1. Anota la hora de inicio y el último instante restaurable de prod:
   ```bash
   date -u +%FT%TZ
   aws rds describe-db-instances --db-instance-identifier clinica \
     --query 'DBInstances[0].LatestRestorableTime' --output text
   ```
2. Sigue el paso 2 de RB-03, con `--target-db-instance-identifier clinica-simulacro` y
   `--use-latest-restorable-time` en lugar de `--restore-time`.
3. Sigue el paso 3 de RB-03 y compara los conteos con prod: corre los mismos comandos sin `-e DB_HOST`.
4. Anota la hora en que todo quedó verificado y borra la temporal (paso 7 de RB-03).
5. Llena la tabla:

| Dato | Valor |
|---|---|
| Fecha | |
| Inicio (UTC) | |
| `LatestRestorableTime` al iniciar | |
| Temporal disponible (UTC) | |
| Verificado (UTC) | |
| **RTO medido** (inicio → verificado) | |
| **RPO** (inicio − `LatestRestorableTime`) | |
| Conteos iguales a prod | |
| Nota descifrada | |

Para la corrupción lógica de datos, el doc 03 §6.3 fija un RTO de 30 a 60 minutos y un RPO de
5 minutos, el que da el PITR de RDS.

## RB-05 · Rotar un secreto

Regla general: cambia el secreto en el Environment y vuelve a correr el último despliegue de ese
entorno.

| Secreto | Qué más hace falta |
|---|---|
| `DB_PASSWORD` | Nada. `deploy.sh` aplica la nueva con `ALTER USER` |
| `APP_KEY` | Nada, pero cierra las sesiones abiertas y anula los enlaces firmados pendientes |
| `CLINICAL_ENCRYPTION_KEY` | **No se rota cambiándolo:** las notas y las pruebas cifradas con la anterior dejan de leerse. Si se expone, primero se escribe y se prueba una migración que vuelva a cifrar con la nueva |
| `MAIL_PASSWORD` | Crea otra contraseña de aplicación en Google y revoca la anterior |
| `DOCKERHUB_TOKEN`, `DUCKDNS_TOKEN` | Genera el nuevo en el proveedor y revoca el anterior |
| `CHATBOT_API_TOKEN` | `openssl rand -hex 32`. Laravel y el asistente lo toman en el mismo despliegue |
| `EC2_SSH_KEY` | Agrega la llave pública nueva a `~/.ssh/authorized_keys` en la EC2 y cambia `EC2_SSH_KEY` en los tres entornos y `TF_VAR_ssh_public_key` en `infra`. Comprueba un despliegue y luego quita la anterior de `authorized_keys`. La EC2 solo lee la llave de Terraform al crearse |
| `DB_ADMIN_PASSWORD` | Sube `password_wo_version` en `rds.tf` y cambia `TF_VAR_db_admin_password`, por PR. Aplica con el workflow Infraestructura y actualiza `DB_ADMIN_PASSWORD` en los tres entornos |

## RB-06 · Reemplazar la instancia

Se usa cuando la EC2 está comprometida o no se recupera.

1. `terraform apply -replace=aws_instance.app` en `infra`. La IP elástica pasa a la instancia nueva:
   `EC2_HOST` y DuckDNS no cambian.
2. Vuelve a desplegar los tres entornos: *Re-run* del último PR para dev, del último push a `main`
   para stage, y prod por RB-01. Cada despliegue clona, escribe el `.env` y emite el certificado de
   nuevo.
3. En tu máquina, borra la huella vieja del host: `ssh-keygen -R <ec2_host>`.

Los datos no se pierden: viven en RDS y S3, y la EC2 no guarda estado.

## RB-07 · Diagnosticar trabajos encolados detenidos

```bash
docker compose ps worker scheduler
docker compose logs --tail 100 worker
docker compose exec app php artisan queue:failed
docker compose exec app php artisan tinker --execute 'echo DB::table("jobs")->count()." en cola";'
```
- Si el worker está caído o colgado: `docker compose restart worker`.
- Si hay trabajos fallidos por una causa ya corregida: `docker compose exec app php artisan queue:retry all`.
- El worker atiende las colas `critical`, `mail`, `documents` y `default`, en ese orden.

## RB-08 · Diagnosticar correo no entregado

NT-20 avisa a la administración cuando un envío falla tres veces. Los últimos fallidos:

```bash
docker compose exec app php artisan tinker --execute 'echo json_encode(DB::table("notification_dispatches")->join("notification_statuses","notification_statuses.id","=","notification_dispatches.status_id")->where("notification_statuses.code","FALLIDO")->orderByDesc("notification_dispatches.id")->limit(10)->get(["template_code","attempts","last_error"]));'
```

| Error | Causa | Qué hacer |
|---|---|---|
| `535 … Username and Password not accepted` | Se revocó la contraseña de aplicación o cambió la de la cuenta | RB-05, `MAIL_PASSWORD` |
| `550 5.4.5 Daily sending quota exceeded` | El límite diario de Gmail | Esperar 24 h. Si se repite, cambiar de proveedor (ADR-029) |
| Ninguno, pero el correo no llega | Filtro de spam del destinatario | Revisar la carpeta de spam y que el remitente sea la misma cuenta de Gmail |

## RB-09 · Recuperar un entorno degradado sin desplegar

El diseño rotaba instancias de un grupo de autoescalado; aquí hay una sola EC2 (ADR-023).
1. Revisa `docker compose ps` y `docker compose logs --tail 100 app web` del entorno.
2. Reinicia el entorno: `docker compose restart`.
3. Si es el host (memoria o disco): `free -h`, `df -h` y `docker system df`. Un `sudo reboot`
   recupera los tres entornos con `restart: unless-stopped`.
4. Si nada de eso basta, sigue RB-06.

## RB-10 · Actualizar lo que sabe el asistente

El asistente lee la clínica por la API, no de un archivo. No hace falta desplegar.
- **Preguntas frecuentes:** panel → Preguntas frecuentes. También se ven en la landing, aunque el
  asistente esté apagado.
- **Servicios y tarifas:** panel → Servicios y tarifas.
- **Vacaciones y ausencias:** panel → Bloqueos de agenda. El asistente ofrece solo los horarios libres.

## RB-11 · Responder a una alerta de riesgo (NT-24 o NT-25)

Las dos alertas son urgentes: salen aun en horas de silencio (RN-17).

**NT-24, crisis en el asistente.**
1. Llega con la señal y la sesión del chat. El asistente no guarda los mensajes ni los datos de la
   persona, así que no hay a quién llamar.
2. El widget ya mostró la línea de crisis y cerró la conversación automática.
3. Cada mes, revisa las alertas para recalibrar el clasificador. Si aparece un falso negativo, se
   corrige de inmediato con un caso nuevo en la batería de crisis. Los falsos positivos se toleran.

**NT-25, ítem 9 del PHQ-9.**
1. Abre el enlace del correo. El resultado está en el panel, y la lectura queda en la bitácora.
2. Contacta al paciente el mismo día, con el teléfono de su ficha.
3. Documenta el contacto y la decisión en la nota SOAP de la siguiente sesión.

## RB-12 · Responder a un incidente de seguridad

1. **Contener.** Rota lo expuesto (RB-05). Si la EC2 está comprometida:
   - quita su llave de `authorized_keys`;
   - toma una instantánea del disco para el análisis;
   - reemplázala (RB-06).
2. **Evaluar.**
   - La bitácora del panel muestra quién leyó cada expediente y cada intento denegado.
   - Los registros de GitHub Actions muestran quién desplegó qué.
   - CloudTrail guarda 90 días de llamadas a la API de AWS.
3. **Avisar** a la psicóloga, responsable de los datos, con lo que se sabe y lo que se hizo.
4. **Restaurar** si hubo datos alterados (RB-03).
5. **Aprender.** Anota la causa y el cambio que la evita en la bitácora del runbook.

---

## Bitácora del runbook

| Fecha | Procedimiento | Quién | Resultado y tiempos |
|---|---|---|---|
| | | | |
