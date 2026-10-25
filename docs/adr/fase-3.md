# Registro de decisiones de la Fase 3

Continúa el registro del doc 01 de la Fase 2 (ADR-001 a ADR-022), con su mismo formato: contexto →
decisión → consecuencias → descartado. Cuando una decisión reemplaza o ajusta una anterior, lo dice
en su título.

| ADR | Decisión | Reemplaza o ajusta |
|---|---|---|
| [023](#adr-023--una-ec2-para-los-tres-entornos) | Una EC2 para los tres entornos | ADR-013 y ADR-022 |
| [024](#adr-024--redis-en-contenedor-no-gestionado) | Redis en contenedor | ADR-008 |
| [025](#adr-025--imágenes-públicas-en-docker-hub-promovidas-por-árbol) | Docker Hub con promoción por árbol | ADR-016 |
| [026](#adr-026--secretos-en-los-environments-de-github) | Secretos en los Environments de GitHub | Parameter Store del doc 05 |
| [027](#adr-027--despliegue-por-ssh-desde-el-job-del-environment) | Despliegue por SSH | ADR-015 |
| [028](#adr-028--https-con-lets-encrypt-por-dns-01-en-duckdns) | Let's Encrypt por DNS-01 en DuckDNS | — |
| [029](#adr-029--correo-por-smtp-de-gmail) | Correo por Gmail | SES de ADR-017 |
| [030](#adr-030--claude-haiku-55-como-modelo-del-asistente) | Claude Haiku 5.5 | ADR-010 |
| [031](#adr-031--segundo-factor-con-el-mfa-de-filament) | MFA de Filament | ADR-018 |
| [032](#adr-032--reportes-al-vuelo-sin-tablas-resumen) | Reportes al vuelo | Doc 05 §3.2 y `to-be-07` |
| [033](#adr-033--un-token-de-servicio-entre-laravel-y-el-asistente) | Un token de servicio | Concreta ADR-012 |
| [034](#adr-034--el-asistente-agenda-por-formulario) | El asistente agenda por formulario | Concreta ADR-012 |
| [035](#adr-035--canales-activos-por-configuración) | Canales activos por configuración | ADR-017 |
| [036](#adr-036--una-plantilla-genérica-de-whatsapp) | Una plantilla genérica de WhatsApp | — |
| [037](#adr-037--puertos-fel-y-pasarela-con-adaptador-nulo) | Puertos FEL y pasarela con adaptador nulo | — |
| [038](#adr-038--el-asistente-se-activa-con-su-llave) | El asistente se activa con su llave | — |
| [039](#adr-039--la-sesión-se-cierra-sola-al-terminar) | La sesión se cierra sola al terminar | Doc 09 §10 |
| [040](#adr-040--roles-del-personal-creados-desde-el-panel) | Roles del personal creados desde el panel | Doc 05 §4.2 |
| [041](#adr-041--fotos-de-perfil-servidas-por-la-aplicación) | Fotos de perfil servidas por la aplicación | — |

Al final, [los puntos del doc 05 §7 que este diseño no cumple](#puntos-del-doc-05-7-que-no-se-cumplen).

---

### ADR-023 · Una EC2 para los tres entornos

**Contexto.** ADR-013 pedía un balanceador y dos instancias en dos zonas, y ADR-022, EC2 con Docker
Compose. El techo de gasto es de USD 85 al mes (RNF-45), para una clínica de una sola psicóloga.

**Decisión.** Una `t3.medium` con IP elástica corre dev, stage y prod, cada uno como proyecto
Compose en `~/clinica/<entorno>`.
- Cada entorno tiene su base en la misma RDS, su usuario con `GRANT` solo sobre ella y su bucket.
- Los puertos HTTPS son 8082, 8081 y 443.
- Hay dos reinicios automáticos: `restart: unless-stopped` en los contenedores, y la recuperación
  automática simplificada de EC2, que AWS activa por omisión en las `t3`.

**Consecuencias.**
- No hay alta disponibilidad: el simulacro T-17 no aplica.
- Un despliegue recrea los contenedores y deja unos segundos sin servicio.
- Los tres entornos comparten el rol de la instancia. Aislarlos por IAM exige una instancia por
  entorno.
- Costo total: unos USD 50 al mes.

**Descartado.**
- Balanceador y dos instancias: más de USD 40 adicionales al mes.
- Una EC2 por entorno: el triple de cómputo.

**Cuándo reconsiderarlo.** Lo dice el documento de escalabilidad.

---

### ADR-024 · Redis en contenedor, no gestionado

**Contexto.** ADR-008 elige Redis gestionado, solo como caché: «si perder Redis pierde algo, no va
en Redis». Las sesiones y las colas ya viven en la base.

**Decisión.** Un `redis:7.4.11-alpine` por entorno, sin persistencia, en la red de su proyecto
Compose.

**Consecuencias.** Perderlo no pierde nada, y la CI lo comprueba: corre la suite de Feature con
`CACHE_STORE=null`. Ahorra unos USD 12 al mes.

**Descartado.** ElastiCache: paga disponibilidad para un dato que, por diseño, se puede perder.

---

### ADR-025 · Imágenes públicas en Docker Hub, promovidas por árbol

**Contexto.** ADR-016 elegía ECR privado. Para publicar en ECR, el job de despliegue tendría que
asumir un rol de AWS.

**Decisión.** Se usan tres repositorios públicos en Docker Hub: `clinica-app`, `clinica-web` y
`clinica-chatbot`.
- Cada imagen lleva dos etiquetas: el número de ejecución y `tree-<hash del árbol de git>`.
- Si el árbol ya tiene imagen, se promueve esa misma: dev, stage y prod reciben la misma.
- `clinica-web` se publica al final, así que su etiqueta marca que el trío está completo.
- Prod nunca construye: solo recibe imágenes que pasaron por stage.

**Consecuencias.**
- El único rol de GitHub en AWS es el de Terraform, detrás de aprobación.
- Las imágenes son inspeccionables, pero no llevan secretos y contienen lo mismo que el repositorio,
  que ya es público. `gitleaks` corre en cada PR.
- La EC2 descarga con un token, para no chocar con el límite de descargas anónimas.

**Descartado.**
- ECR: le da credenciales de AWS al job que despliega.
- Construir en cada entorno: la imagen de prod no sería la que se probó en stage.

---

### ADR-026 · Secretos en los Environments de GitHub

**Contexto.** El doc 05 inyectaba la configuración al arranque desde SSM Parameter Store, con un rol
de instancia que la lee.

**Decisión.** Cada Environment (`dev`, `stage`, `prod`) guarda sus secretos.
- El pipeline escribe el `.env` del entorno en la EC2 por SSH, con `umask 077`.
- Lo que la app no debe ver nunca llega al `.env`: la contraseña maestra de RDS y el token de Docker
  Hub van por stdin a `scripts/deploy.sh`.
- Si falta un valor obligatorio, el pipeline falla antes de desplegar.

**Consecuencias.**
- Rotar un secreto es cambiarlo en el Environment y volver a correr el despliegue.
- GitHub no deja volver a leer un secreto: las llaves de cifrado de prod se respaldan fuera
  (guía de despliegue de la entrega, §5.4).
- Construir y desplegar van en el mismo job, para que los secretos sean del Environment.

**Descartado.** Parameter Store: suma un permiso de lectura al rol de la instancia y un paso al
arranque, para el mismo resultado.

---

### ADR-027 · Despliegue por SSH desde el job del Environment

**Contexto.** ADR-015 desplegaba con `ssm:SendCommand`, sin puerto 22 ni llaves, con un rol de
despliegue asumido por OIDC.

**Decisión.** El job entra por SSH con una llave ed25519 dedicada (secreto `EC2_SSH_KEY`). Allí
actualiza el clon del entorno, escribe el `.env`, renueva el certificado y corre `scripts/deploy.sh`.

**Consecuencias.**
- El puerto 22 queda abierto a Internet, porque los runners de GitHub no tienen IP fija. Solo se
  entra con la llave.
- La llave del host se acepta en la primera conexión de cada runner (`accept-new`).
- La llave es de larga vida, aunque solo sirve para esta instancia. Se rota cambiando
  `TF_VAR_ssh_public_key` y el secreto.

**Descartado.**
- SSM Run Command: necesita el rol de despliegue en cada Environment, el agente y los permisos de la
  instancia, y la salida de los comandos llega con retraso.
- Un runner propio en la EC2: ejecutaría código de los PR dentro de prod.

---

### ADR-028 · HTTPS con Let's Encrypt por DNS-01 en DuckDNS

**Contexto.** No hay dominio propio. Los certificados de ACM solo sirven detrás de un balanceador o
de CloudFront.

**Decisión.** Un subdominio gratuito de DuckDNS.
- `scripts/tls.sh` emite el certificado con lego por DNS-01 y lo renueva con un cron diario. Hay uno
  solo para los tres entornos, porque solo cambia el puerto.
- nginx sin privilegios sirve TLS con HSTS. En prod, el 80 solo redirige al 443.

**Consecuencias.** Se depende de la disponibilidad de DuckDNS y de los límites de emisión de Let's
Encrypt (`tls.sh` solo pide uno nuevo si no existe). La llave vive en el host, con permisos 700.

**Descartado.**
- ACM: exige balanceador.
- Route 53 con dominio propio: unos USD 12 al año, más la zona.
- HTTP-01: el 80 solo está abierto en prod, y dev y stage no podrían validar.

---

### ADR-029 · Correo por SMTP de Gmail

**Contexto.** ADR-017 elegía SES. SES pide un dominio verificado con registros DKIM (CNAME), que
DuckDNS no admite, y una solicitud a AWS para salir del sandbox.

**Decisión.** Gmail por SMTP (`smtp.gmail.com:587`, STARTTLS) con una contraseña de aplicación. El
remitente es la misma cuenta.

**Consecuencias.** El límite de unos 500 mensajes al día alcanza para una clínica. La cuenta tiene
verificación en dos pasos, y la contraseña de aplicación se revoca sin tocar la cuenta.

**Descartado.** SES, por la verificación del dominio. Un proveedor transaccional de pago, porque a
este volumen no se justifica.

---

### ADR-030 · Claude Haiku 5.5 como modelo del asistente

**Contexto.** ADR-010 elegía Claude Sonnet 5.

**Decisión.** `claude-haiku-5-5` con esfuerzo bajo, y la base de conocimiento como documentos con
citas nativas.

**Consecuencias.**
- Menor costo y latencia para un asistente que responde sobre una base pequeña y llama a tres
  herramientas.
- Haiku 5.5 cachea el prompt desde 512 tokens.
- La seguridad no depende del modelo: el clasificador de crisis corre antes (RN-20) y la batería de
  20 casos está en la CI.

**Descartado.** Sonnet 5: su capacidad adicional no cambia el resultado de estas tareas y cuesta 20
veces más por token. En octubre de 2026, la entrada cuesta USD 2 por millón de tokens en Sonnet y
USD 0.10 en Haiku 5.5.

---

### ADR-031 · Segundo factor con el MFA de Filament

**Contexto.** ADR-018 y el doc 05 §3.1 diseñaban un código por correo propio, con la tabla
`two_factor_codes` y la plantilla NT-21.

**Decisión.** El MFA nativo de Filament, obligatorio para el personal.
- El código por correo es el método principal y su vigencia sale de RN-15. También hay aplicación
  TOTP y códigos de recuperación.
- Quien tiene los dos activos parte de la aplicación, y el correo sale solo si lo elige (F3-10).
  Antes iba primero el correo, y Filament lo enviaba al aceptar la contraseña aunque la persona
  fuera a usar la aplicación.
- Filament guarda el hash del código en la sesión y envía su propia notificación, en español.

**Consecuencias.**
- No existen `two_factor_codes` ni NT-21.
- El límite es de 5 intentos por minuto y por usuario, el nativo, en lugar de 5 por código.
- El aviso de cambio de correo usa la caché. Sin Redis, el enlace pendiente deja de valer y se pide
  otro; no se pierde nada (ADR-008).

**Descartado.** El código propio: repetiría lo que el framework ya resuelve y prueba.

---

### ADR-032 · Reportes al vuelo, sin tablas resumen

**Contexto.** El doc 05 §3.2 y `to-be-07` precalculaban cada noche tablas resumen. El reporte iba por
cola y avisaba con NT-18.

**Decisión.** Los indicadores se calculan al pedirlos y el archivo XLSX o PDF se genera en la misma
petición.

**Consecuencias.**
- Con los datos actuales, el tablero tarda de 9 a 25 ms (8 a 11 consultas) y el PDF unos 170 ms.
- No hay tablas resumen que mantener ni un dato que pueda quedar desactualizado.
- No hay cola ni NT-18.

**Descartado.** El precálculo nocturno. Vuelve cuando el tablero pase de 2 s (RNF-08); el umbral está
en el documento de escalabilidad.

---

### ADR-033 · Un token de servicio entre Laravel y el asistente

**Contexto.** ADR-012 hace del asistente el único cliente de la API pública `/api/v1`.

**Decisión.** Un token estático por entorno (`CHATBOT_API_TOKEN`) protege los dos sentidos:
- Laravel → asistente (`/chat` y `/bookings`);
- asistente → Laravel (`/api/v1`).

El asistente no publica puertos, y llega a la API por la escucha interna de nginx en el 8000, que
tampoco sale del host.

**Consecuencias.**
- Si el token está vacío, no entra nadie: los dos lados lo verifican.
- Rotarlo es cambiar un secreto y volver a desplegar.

**Descartado.** Sanctum: emite y revoca tokens por usuario, y aquí hay un solo cliente.

---

### ADR-034 · El asistente agenda por formulario

**Contexto.** CLAUDE.md prohíbe poner datos reales de pacientes en un prompt.

**Decisión.** El modelo propone un horario de `check_availability`, y el widget muestra un formulario
con nombre, correo, teléfono y NIT, más la política de cancelación. Al confirmar, el formulario va
a `POST /bookings` del asistente, que llama a la API de Laravel. Los datos de contacto nunca pasan
por el modelo.

**Consecuencias.** El chat tiene un solo camino para agendar, el mismo de la API, con sus
validaciones y su límite de solicitudes.

**Descartado.**
- Que el modelo pida los datos en la conversación.
- Que el widget llame a Laravel por su cuenta: serían dos caminos.

---

### ADR-035 · Canales activos por configuración

**Contexto.** ADR-017 dejaba el puerto `NotificationChannel` listo para más canales. La columna
`notification_channels.is_active` la reescribe `CatalogSeeder` en cada despliegue.

**Decisión.** Un canal existe si su configuración existe. WhatsApp se registra solo con
`WHATSAPP_TOKEN` y `WHATSAPP_PHONE_NUMBER_ID`. El correo siempre está y es el respaldo.

**Consecuencias.** Activar WhatsApp es agregar dos secretos y volver a desplegar. Sin ellos, todo
sale por correo, aunque el paciente haya elegido WhatsApp.

**Descartado.** Activarlo desde la base: el siguiente despliegue lo desactivaría.

---

### ADR-036 · Una plantilla genérica de WhatsApp

**Contexto.** Meta exige plantillas aprobadas para escribir fuera de la ventana de 24 horas. Hay más
de veinte avisos (NT).

**Decisión.** Una plantilla de utilidad, `aviso_clinica`, con dos variables: el asunto y el aviso ya
redactados.

**Consecuencias.** Se aprueba una sola plantilla. Meta podría rechazarla por genérica; la guía de
WhatsApp de F3-07 explica cómo pasar a una plantilla por NT.

**Descartado.** Una plantilla por NT desde el inicio: más de veinte aprobaciones para un canal que
todavía no se usa.

---

### ADR-037 · Puertos FEL y pasarela con adaptador nulo

**Contexto.** La facturación electrónica (FEL) y el pago con tarjeta están fuera del alcance de la
fase, pero el diseño debe poder recibirlos.

**Decisión.** Hay dos puertos de Billing, `FiscalDocumentIssuer` y `PaymentGateway`, con adaptadores
nulos, enganchados donde entra el dinero: `BillingService::approve()` y `collectAtDesk()`.

**Consecuencias.** El sistema funciona igual con los adaptadores nulos que con uno real (OCP y LSP).
Integrar un certificador o una pasarela es un adaptador y una línea en `AppServiceProvider`. La guía
está en F3-07.

**Descartado.** Dejar el enganche para después: agregarlo luego obliga a tocar el flujo de pagos.

---

### ADR-038 · El asistente se activa con su llave

**Contexto.** El pipeline exigía `ANTHROPIC_API_KEY` y, sin ella, no desplegaba nada. El autor
decidió desplegar sin contratar la API.

**Decisión.** La llave es opcional. `services.chatbot.enabled` vale `filled(env('ANTHROPIC_API_KEY'))`.
Sin llave, el widget muestra:
- el enlace a las preguntas frecuentes;
- el enlace para agendar;
- el teléfono;
- siempre, la línea de crisis (RF-62).

`CHATBOT_API_TOKEN` y `CLINIC_CRISIS_LINE` siguen siendo obligatorios.

**Consecuencias.**
- HU-24, HU-25 y HU-26 no se pueden demostrar en ese despliegue.
- Sin campo de mensaje, el clasificador de crisis no recibe nada. La línea queda a la vista.
- Activarlo es agregar el secreto con un límite de gasto y volver a desplegar; no hay que cambiar
  código.

**Descartado.**
- Mostrar el chat y responder «no disponible» a cada mensaje: parece un error.
- Una bandera aparte de la llave: serían dos valores que deben coincidir.

---

### ADR-039 · La sesión se cierra sola al terminar

**Contexto.** El doc 09 §10 dejaba la inasistencia a recepción: «el sistema propone; recepción
confirma», por el cargo del 100 %. En la práctica, las citas pasadas se quedaban en Agendada o En
curso:
- seguían ofreciendo reprogramar y cancelar;
- el tablero contaba como atendida una sesión que nunca se cerró.

El autor decidió el 25-oct que se cierren solas.

**Decisión.**
- `appointments:reconcile` corre cada 5 minutos:
  - una cita En curso con la sesión terminada pasa a Atendida;
  - una Agendada o en Pago en caja sin llegada registrada pasa a No Show, con el sistema como autor.
- Pago en caja → No Show es la única transición nueva de la máquina de estados. No genera cobro
  pendiente: no hubo pago.
- Cada acción tiene su plazo, en el dominio y en la interfaz:
  - aprobar, y cancelar o reprogramar a pedido del paciente, solo antes del inicio;
  - cancelar o reprogramar por indisponibilidad de la psicóloga, también después: nunca le cuesta
    al paciente (RN-18) y un bloqueo de agenda puede necesitarlo;
  - registrar la llegada, hasta el fin de la sesión. El panel la ofrece solo el día de la cita.

**Consecuencias.**
- Si nadie registra la llegada, la cita queda No Show y no admite nota clínica. Registrar la llegada
  a tiempo es parte del trabajo de recepción (MU-10).
- Sellar la nota de una cita ya cerrada no falla: cerrar es idempotente.
- Decide el reloj del servidor, sincronizado por NTP. El riesgo que señalaba el doc 09 queda en la
  sesión que empieza tarde sin llegada registrada.

**Descartado.**
- Un margen después del fin: nadie lo pidió y retrasa el cierre. Si hace falta, entra como parámetro.
- Un trabajo diferido por cita: el planificador ya corre y basta.
- Reprogramar un No Show dentro de un plazo: perdonaría el cargo de RF-30.

---

### ADR-040 · Roles del personal creados desde el panel

**Contexto.** El alta de usuarios pedía un «+» para crear roles. Había dos obstáculos:
- el acceso a cada panel estaba fijo en código para tres roles (`User::PANEL_ROLES`), así que un rol
  nuevo no entraba a ninguno;
- los permisos viven en `role_ability`, pero `RoleAbilitySeeder` la reescribía entera en cada
  despliegue.

**Decisión.**
- Paciente entra al portal. Cualquier otro rol entra al panel del personal, y ahí ve lo que sus
  permisos dejan.
- El «+» pide el nombre y los permisos (`abilities`) y guarda todo en una transacción, con la entrada
  `role.created` en la bitácora.
- `RoleAbilitySeeder` reescribe solo los permisos de los roles sembrados.
- El alta de usuarios ya no ofrece el rol Paciente: los pacientes nacen en Pacientes, con su ficha.

**Consecuencias.**
- Un rol nuevo puede recibir permisos clínicos. Solo quien tiene `user.create.staff` (la
  administradora) lo crea, y queda en la bitácora. Es una de las revisiones deliberadas del autor.
- No hay pantalla para cambiar los permisos de un rol ya creado.
- Los avisos internos al personal siguen yendo a Administradora y Recepción; un rol nuevo no los
  recibe.

**Descartado.**
- Copiar un rol base: menos flexible, y el mismo cambio en el acceso.
- Una columna `panel` por rol: con dos paneles, la regla basta.

---

### ADR-041 · Fotos de perfil servidas por la aplicación

**Contexto.** Se pidió una foto opcional en el perfil y en el alta de usuarios y pacientes. Los
archivos van a S3 privado, y en local a MinIO, cuyas URL prefirmadas apuntan a `minio:9000`: el
navegador no las alcanza.

**Decisión.**
- `users.avatar_path` guarda la ruta, siempre bajo `avatars/`.
- `GET /avatars/{user}` la sirve como `/comprobantes/{proof}`: la ven su dueño y el personal; los
  demás reciben 404.
- El campo acepta JPEG, PNG o WebP de hasta 2 MB, recortados a 500 px, con
  `preventFilePathTampering()`. La foto reemplazada se borra del bucket.

**Consecuencias.**
- Un paciente nunca ve la foto de otro paciente.
- Cada foto pasa por la aplicación; a 500 px, el costo es bajo.

**Descartado.**
- El disco `public` con `storage:link`: dejaría las fotos de pacientes en una URL pública y en el
  disco de la EC2.
- SVG: podría ejecutar código desde este dominio.

---

## Puntos del doc 05 §7 que no se cumplen

| Punto del checklist | Por qué no | ADR |
|---|---|---|
| «Las instancias solo aceptan tráfico del balanceador» | No hay balanceador: la EC2 recibe el HTTPS directamente, y el 22 para el pipeline | 023 y 027 |
| «El registro de imágenes no es accesible sin autenticación» | Las imágenes de Docker Hub son públicas. No llevan secretos y contienen lo mismo que el repositorio público | 025 |
| «El rol de construcción no despliega» | Construir y desplegar van en el mismo job, para que los secretos sean del Environment | 026 |
