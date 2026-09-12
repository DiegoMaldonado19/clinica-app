# 02 — Configuración de infraestructura

> **Documento 2 de 3** · Fase 2 · Audiencia: equipo técnico
> Alcance: **entorno reproducible local + tubería de integración continua**
> La topología AWS de producción está en `Entregable_2_Final/03-infraestructura-cloud.md` y se
> aplica en Fase 3.

---

## 1. Qué cubre esta fase y qué no

El enunciado lista «infraestructura finalizada» entre los entregables de la Fase 2. Este
documento la entrega en el único sentido verificable a esta altura del proyecto: **una
infraestructura que corre, que cualquiera puede levantar en cinco comandos y que la tubería
ejercita en cada Pull Request.**

| Capa | Fase 2 | Fase 3 |
|---|---|---|
| Cómputo | Contenedores en la máquina del desarrollador | ASG de 2 × `t4g.small` en 2 zonas + ALB |
| Base de datos | `mariadb:11.4` en contenedor | Amazon RDS MariaDB 11.4 con PITR de 5 min |
| Caché | `redis:7-alpine` en contenedor | ElastiCache Redis 7, un nodo, solo caché |
| Almacenamiento | MinIO (compatible con S3) | Amazon S3, bucket privado |
| Correo | Mailpit | Amazon SES |
| Secretos | `.env` local, fuera del repositorio | SSM Parameter Store, cadena segura |
| Integración | GitHub Actions | Igual |
| Despliegue | — | GitHub Actions + SSM, rolling |
| Infraestructura como código | — | Terraform |

**La diferencia entre las dos columnas es de proveedor, no de forma.** Los mismos cuatro
servicios de aplicación (`nginx`, `app`, `worker`, `scheduler`) corren en ambas; lo que cambia
es de dónde vienen la base, la caché, el almacenamiento y el correo. Es lo que hace que la
Fase 3 sea un despliegue y no un rediseño.

---

## 2. Topología local

```
                         Navegador
                             │
                        :80  │
                  ┌──────────┴───────────┐
                  │        nginx         │   docker/nginx/default.conf
                  └──────────┬───────────┘
                       :9000 │ fastcgi
     ┌─────────────────┬─────┴─────┬──────────────────┐
     │                 │           │                  │
┌────┴─────┐   ┌───────┴────┐  ┌───┴───────┐          │
│   app    │   │   worker   │  │ scheduler │          │
│ php-fpm  │   │ queue:work │  │schedule:work│        │
└────┬─────┘   └───────┬────┘  └───┬───────┘          │
     └─────────────────┴───────────┴──────────────────┘
                             │
        ┌────────────┬───────┼─────────┬──────────────┐
        │            │       │         │              │
   ┌────┴────┐  ┌────┴───┐ ┌─┴──────┐ ┌┴─────────┐
   │ mariadb │  │ redis  │ │ minio  │ │ mailpit  │
   │  11.4   │  │   7    │ │  :9001 │ │  :8025   │
   │  :3306  │  │ :6379  │ │        │ │          │
   └─────────┘  └────────┘ └────────┘ └──────────┘
        └──────── solo en compose.override.yaml ─────┘
```

**Los cuatro de arriba son los mismos que en producción. Los cuatro de abajo no existen en
producción**: allí los sustituyen RDS, ElastiCache, S3 y SES. Esa separación es la razón de que
haya dos archivos de Compose y no uno.

---

## 3. Servicios

### 3.1 `compose.yaml` — los que también corren en producción

| Servicio | Imagen | Rol | Puerto | Notas |
|---|---|---|---|---|
| `nginx` | `nginx:alpine` | Servidor web | `80` → `8080` interno | Health check en `/up` |
| `app` | build `docker/php/Dockerfile` | PHP-FPM 8.4 | `9000` interno | `php:8.4-fpm-alpine` + `pdo_mysql`, `redis`, `gd`, `intl`, `zip`, `bcmath` |
| `worker` | mismo build que `app` | `queue:work --queue=critical,mail,documents,default` | — | **Contenedor separado**: un despliegue no debe matar un trabajo a medio ejecutar |
| `scheduler` | mismo build que `app` | `schedule:work` | — | Con `withoutOverlapping()` sobre el bloqueo `database` |

**El procesador de colas corre aparte del web** por dos razones: aislar el despliegue de los
trabajos en curso, y poder escalar las colas de forma independiente en Fase 3.

### 3.2 `compose.override.yaml` — solo desarrollo

| Servicio | Imagen | Sustituye en producción a | Puerto | Credenciales |
|---|---|---|---|---|
| `mariadb` | `mariadb:11.4` | Amazon RDS | `3306` | `clinica` / `clinica` / base `clinica` |
| `redis` | `redis:7-alpine` | ElastiCache | `6379` | sin token en local |
| `minio` | `minio/minio` | Amazon S3 | `9000` API, `9001` consola | `minioadmin` / `minioadmin` |
| `mailpit` | `axllent/mailpit` | Amazon SES | `1025` SMTP, `8025` interfaz | — |
| `minio-init` | `minio/mc` | — | — | Crea el bucket privado y termina |
| `vite` | `node:24-alpine` | — | `5173` | Recarga en caliente del front |

**Levantar el proyecto completo no requiere ninguna cuenta en la nube.** Es la condición de que
T-01 vaya en la primera fase: sin entorno reproducible, todo lo demás se retrasa.

### 3.3 Health checks

Cada servicio de datos declara el suyo, y `app` depende de ellos con `condition:
service_healthy`. Sin eso, `php artisan migrate` corre contra una base que todavía no acepta
conexiones y falla de forma intermitente — el peor tipo de fallo, porque a veces pasa.

| Servicio | Comprobación |
|---|---|
| `mariadb` | `healthcheck.sh --connect --innodb_initialized` |
| `redis` | `redis-cli ping` |
| `minio` | `mc ready local` |
| `mailpit` | `/mailpit readyz` |
| `web` | `wget -qO- http://127.0.0.1:8080/up` |

**El health check usa `127.0.0.1`, no `localhost`.** Dentro del contenedor `localhost` resuelve
primero a `::1` y nginx solo escucha en IPv4: con `localhost` el servicio se marca
permanentemente enfermo aunque responda perfectamente desde fuera. El mismo cuidado aplica al
`curl` de verificación del despliegue rolling en Fase 3.

### 3.4 Recarga en caliente, y por qué la imagen de producción no la tiene

Un solo `docker/php/Dockerfile` con dos destinos. Lo que cambia entre ellos es exactamente lo
que debe cambiar y nada más:

| | `--target dev` | `--target prod` |
|---|---|---|
| Código | Bind mount del proyecto | **Copiado dentro de la imagen** |
| `opcache.validate_timestamps` | **`1`** con `revalidate_freq=0` | **`0`** |
| Dependencias | Con las de desarrollo | `--no-dev`, autoload de mapa de clases autoritativo |
| Assets del front | Servidor de Vite en el puerto 5173 | `public/build` construido en la etapa `assets` |
| Composer | Presente | **Eliminado de la imagen** |
| Usuario | `www-data` remapeado al uid del anfitrión | `www-data` (82) |

**La recarga en caliente de PHP es una sola línea de configuración.** Con
`opcache.validate_timestamps=1` y `revalidate_freq=0`, PHP comprueba la fecha de modificación
del fichero en cada petición: editar un `.php` surte efecto en la siguiente, sin reconstruir la
imagen y sin reiniciar el contenedor. En producción esa comprobación se apaga, porque el código
no cambia bajo los pies del proceso y revalidar en cada petición solo cuesta llamadas al
sistema.

**El front lo recarga Vite.** El servicio `vite` escribe `public/hot`, y el `@vite` de Blade
apunta al servidor de desarrollo mientras ese fichero exista. En producción no existe, y Blade
lee el manifiesto de `public/build`.

Dos detalles que hacen falta en este entorno concreto y no son adorno:

- **`usePolling: true` en `vite.config.js`.** El bind mount de WSL2 no propaga eventos inotify
  al contenedor; sin sondeo, Vite no ve los cambios y la recarga del front no ocurre.
- **`www-data` remapeado al uid del anfitrión, y el servicio `vite` corriendo con ese mismo
  uid.** Sin esto, `storage/`, `node_modules/` y `public/hot` quedan a nombre de `root` en el
  anfitrión y hacen falta permisos de superusuario para borrarlos.

**La imagen que sube al registro es la de `prod`, y no puede recargar en caliente.** Es
deliberado: una imagen que lee el código de un volumen externo no es reproducible, y ADR-016
exige que lo desplegado sea exactamente el artefacto que pasó por pruebas.

El servicio `web` de producción se construye desde el mismo Dockerfile (etapa `web`) y copia
`public/` desde la etapa `prod`, de modo que **nginx y la aplicación nunca sirven versiones
distintas de los assets**.

---

## 4. Variables de entorno

`.env.example` se versiona; `.env` **nunca**. Los valores que siguen son los de desarrollo.

```dotenv
APP_NAME="Clinica Psicologia y Bienestar"
APP_ENV=local
APP_KEY=                       # php artisan key:generate
APP_DEBUG=true
APP_TIMEZONE=UTC               # UTC en la base; America/Guatemala solo en presentacion
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=mariadb
DB_PORT=3306
DB_DATABASE=clinica
DB_USERNAME=clinica
DB_PASSWORD=clinica

# ADR-008: si perder Redis pierde algo, no va en Redis.
SESSION_DRIVER=database        # perder la cache no debe desloguear a nadie
QUEUE_CONNECTION=database      # ni perder trabajos encolados
CACHE_STORE=redis              # solo agregados, catalogos y disponibilidad
REDIS_HOST=redis
REDIS_PORT=6379

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadmin
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=clinica-archivos
AWS_ENDPOINT=http://minio:9000
AWS_USE_PATH_STYLE_ENDPOINT=true

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_FROM_ADDRESS=no-reply@clinica.local

# Llave de cifrado de las notas SOAP. Parametro DISTINTO de APP_KEY,
# para poder rotarlo y auditarlo por separado (doc 05 §2.3).
CLINICAL_ENCRYPTION_KEY=
```

### 4.1 Las tres líneas que no se tocan

```dotenv
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=redis
```

Son ADR-008 escrito en configuración. La caché guarda agregados del tablero, catálogos,
tarifas, reglas `RN-xx` y disponibilidad; **las sesiones y las colas van a la base de datos, y
el contenido clínico nunca se cachea.** Es lo que permite que el nodo de caché sea único y sin
réplica en producción, y lo que hace que perder Redis solo degrade el rendimiento.

La tubería verifica esta regla, no la confía a la disciplina: hay un paso que corre la suite
crítica con `CACHE_STORE=null`.

### 4.2 Gestión de secretos

| | Fase 2 | Fase 3 |
|---|---|---|
| Dónde viven | `.env` local, en `.gitignore` | SSM Parameter Store, cadena segura |
| Cómo llegan | Compose los inyecta | `user-data` los descarga al arrancar |
| Credenciales de nube | Las de MinIO, que son de juguete | **No existen**: perfil de instancia (rol IAM) |
| Verificación | `gitleaks` en cada PR | Ídem, más auditoría de secretos |

**Ningún secreto entra en la imagen, en el repositorio ni en `compose.yaml`.** La llave de
cifrado clínico es un parámetro distinto de `APP_KEY` desde el primer día, precisamente para
que en Fase 3 no haya que separarlas sobre datos ya cifrados.

---

## 5. Base de datos y migraciones

### 5.1 Por qué MariaDB en local y no SQLite

El esqueleto venía con SQLite. Se sustituye en F-01, y no es una preferencia:

| Necesidad | SQLite | MariaDB 11.4 |
|---|---|---|
| *Trigger* de inmutabilidad de `clinical_notes` (RN-11) | Sintaxis distinta | Igual que en producción |
| `FOR UPDATE SKIP LOCKED` para el driver de colas `database` | No lo soporta | Sí — es lo que hace viable la decisión |
| Índice `UNIQUE (therapist_id, starts_at)` bajo concurrencia real | Bloqueo de archivo completo | Bloqueo de fila |
| Tipos `DATETIME(3)` en `appointment_status_log` | Aproximado | Exacto |

**Un entorno que difiere estructuralmente de producción no prueba lo que tiene que probar.**

### 5.2 Convenciones de migración

Las del doc 06 §7, resumidas para consulta rápida:

| Convención | Regla |
|---|---|
| Nomenclatura | `snake_case`, tablas en plural, columnas en singular |
| Claves primarias | `CHAR(36)` UUID v7 en entidades de dominio; `BIGINT` en bitácoras y catálogos |
| Claves foráneas | Siempre `ON DELETE RESTRICT`. Nada clínico o financiero se borra en cascada |
| Borrado | Lógico en pacientes, usuarios y citas. **Físico prohibido** en `clinical_notes`, `payments` y `audit_log` |
| Fechas | UTC en la base, sin excepción |
| Dinero | `INT UNSIGNED` de centavos + `CHAR(3)` de moneda. **Nunca `FLOAT`** |
| Catálogos | Migración más semilla idempotente, versionada |
| Charset | `utf8mb4_unicode_ci` en toda tabla y columna de texto |

### 5.3 Patrón expandir y contraer

Obligatorio desde ahora, aunque su razón dura aparezca en Fase 3: con despliegue rolling
conviven la versión nueva en una instancia y la anterior en la otra, contra el mismo esquema.

| Despliegue | Migración | Código |
|---|---|---|
| 1 — Expandir | Añadir la columna nueva | Escribe en ambas, lee de la vieja |
| 2 — Migrar | Copiar los datos | Escribe en ambas, lee de la nueva |
| 3 — Contraer | Eliminar la columna vieja | Solo usa la nueva |

**Nunca se edita una migración ya fusionada.** Los entornos quedarían divergentes de forma
silenciosa, que es la peor manera de divergir.

### 5.4 Migraciones y caché

Toda migración que altere una entidad cacheada debe invalidar su prefijo en el mismo
despliegue. Sin eso, el panel muestra la forma anterior durante todo el TTL.

```php
public function up(): void
{
    Schema::table('services', fn (Blueprint $t) => $t->string('modality')->nullable());

    Cache::tags(['catalogs'])->flush();
}
```

---

## 6. Integración continua

`.github/workflows/ci.yml`, en cada Pull Request hacia `main`. **Construye la imagen para
verificar que construye; no la publica** (ADR-016).

| # | Paso | Qué impide |
|---|---|---|
| 1 | `pint --test` | Estilo divergente |
| 2 | `phpstan analyse` (Larastan) | Errores de tipo |
| 3 | **`deptrac analyse --fail-on-uncovered`** | **Que el dominio se acople al framework. Sin él, ADR-002 es una intención** |
| 4 | `pest --testsuite=Unit` | Regresión en una regla RN-xx. Corre **sin base de datos** |
| 5 | `pest --testsuite=Integration,Feature` contra MariaDB y Redis reales | Regresión en un adaptador o un flujo |
| 6 | **`pest --testsuite=Feature --filter=Critical` con `CACHE_STORE=null`** | **Que algo imprescindible acabe viviendo solo en Redis** |
| 7 | `gitleaks` | Un secreto en el diff |
| 8 | `composer audit` | Dependencia con vulnerabilidad conocida |
| 9 | `docker build` sin `push` | Que la imagen no construya |

Los servicios de la tubería replican los del `override`:

```yaml
services:
  mariadb:
    image: mariadb:11.4
    env:
      MARIADB_ROOT_PASSWORD: secret
      MARIADB_DATABASE: clinica_test
    options: >-
      --health-cmd="healthcheck.sh --connect --innodb_initialized"
      --health-interval=10s --health-timeout=5s --health-retries=5
  redis:
    image: redis:7-alpine
    options: >-
      --health-cmd="redis-cli ping"
      --health-interval=10s --health-timeout=5s --health-retries=5
```

### 6.1 Los tres pasos que sostienen la arquitectura

Los pasos 3, 4 y 6 no son control de calidad genérico: **son la arquitectura hecha
ejecutable.**

- Sin **Deptrac**, la regla de dependencia se pierde en el tercer sprint y nadie lo nota hasta
  que extraer un contexto se vuelve una reescritura.
- Sin **las pruebas unitarias sin base de datos**, ADR-002 no tiene evidencia: si probar RN-06
  exige migrar, la frontera está mal puesta.
- Sin **la prueba con `CACHE_STORE=null`**, alguien cacheará algo que no puede perderse y se
  descubrirá cuando Redis falle en producción.

### 6.2 Protección de `main`

Configurada en el repositorio, no confiada a la disciplina:

- Sin fusión directa: todo cambio entra por Pull Request.
- Verificaciones obligatorias en verde antes de poder fusionar.
- Sin reescritura de historial.
- La rama debe estar al día con `main`.
- Fusión por *squash*: cada commit de `main` corresponde a una fase completa.

---

## 7. Ambiente de agentes de IA

Es infraestructura de desarrollo, y cierra el requisito de Fase 2 «ambiente de trabajo con
manejo de Agentes de IA para la fase de programación implementada».

```
clinica-app/
├── CLAUDE.md                        instrucciones permanentes del proyecto
└── .claude/
    ├── agents/
    │   ├── revisor-arquitectura.md  regla de dependencia, logica en controladores
    │   ├── generador-pruebas.md     Gherkin de una HU-xx → Pest
    │   └── revisor-consultas.md     N+1, indices ausentes, SELECT *
    ├── commands/
    │   ├── nueva-historia.md        andamiaje desde una HU-xx
    │   └── revisar-pr.md            revision previa al Pull Request
    └── settings.json                permisos y hooks
```

| Hook | Cuándo | Qué hace |
|---|---|---|
| `PostToolUse` sobre `src/**` | Tras cada edición del dominio | `deptrac analyse` sobre el fichero: la violación se sabe en el momento, no en el PR |
| `PostToolUse` sobre `**/*.php` | Tras cada edición | `pint` sobre el fichero |
| `PreToolUse` sobre `Bash` | Antes de ejecutar | Bloquea `migrate:fresh` y `db:wipe` fuera del entorno local |

**El tercero es el que previene un incidente real.** Un agente que decide «limpiar la base
para que las pruebas pasen» contra la conexión equivocada es perfectamente plausible.

`generador-pruebas` **no tiene acceso a la implementación**: recibe el Gherkin de la historia.
Generar pruebas leyendo el código produce pruebas que confirman el error.

---

## 8. Puesta en marcha

```bash
git clone git@github.com:DiegoMaldonado19/clinica-app.git
cd clinica-app
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

| Superficie | URL |
|---|---|
| Landing y portal | `http://localhost` |
| Panel administrativo | `http://localhost/admin` |
| Correo capturado | `http://localhost:8025` |
| Consola de MinIO | `http://localhost:9001` |

### 8.1 Comandos de verificación

```bash
vendor/bin/pint --test                              # estilo
vendor/bin/phpstan analyse --memory-limit=1G        # tipos
vendor/bin/deptrac analyse --fail-on-uncovered      # regla de dependencia
vendor/bin/pest                                     # suite completa
CACHE_STORE=null vendor/bin/pest --filter=Critical  # degradacion sin cache
```

---

## 9. Lista de verificación de esta fase

Antes de dar por terminada la infraestructura de la Fase 2:

- [ ] `docker compose up -d` levanta los nueve servicios y todos reportan sano.
- [ ] `migrate --seed` corre dos veces seguidas **sin duplicar filas de catálogo**.
- [ ] La suite completa pasa en un entorno recién clonado, sin pasos manuales adicionales.
- [ ] **Un PR con una violación deliberada de la regla de dependencia es rechazado** (CA-24).
- [ ] Un PR con un secreto falso en el diff es rechazado por `gitleaks`.
- [ ] **La suite crítica pasa con `CACHE_STORE=null`** (CA-32).
- [ ] Un PR **no publica ninguna imagen** en ningún registro.
- [ ] `.env` no está versionado y `.env.example` no contiene ningún valor real.
- [ ] Los comprobantes subidos **no** son accesibles sin URL prefirmada (CA-20 en local).
- [ ] El *trigger* de inmutabilidad de `clinical_notes` existe en la base tras migrar.
- [ ] Las tareas programadas usan `withoutOverlapping()` con el bloqueo `database`, no Redis.

---

## 10. Lo que queda pendiente para la Fase 3

Declarado, con su sprint asignado en `Entregable_1_Final/jira/backlog.csv`:

| Trabajo | Tarea | Por qué no ahora |
|---|---|---|
| Terraform completo: ALB, ASG de 2, RDS, ElastiCache, ECR | T-12 | Es despliegue, no diseño; el diseño ya está en el doc 03 |
| Despliegue rolling con federación de identidad | T-13 | Depende de T-12 |
| Endurecimiento y verificación de seguridad | T-14 | Se verifica contra infraestructura real |
| Simulacro de restauración documentado | T-15 | Requiere PITR de RDS |
| Simulacro de alta disponibilidad | T-17 | Requiere ALB y dos instancias |

**Ninguno de los cinco cambia el código de la aplicación.** Es la consecuencia buscada de
haber puesto el estado fuera de la instancia desde F-02: el salto a Fase 3 es de
infraestructura, no de aplicación.
