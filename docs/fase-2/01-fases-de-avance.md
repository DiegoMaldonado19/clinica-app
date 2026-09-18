# 01 — Fases de avance de la Fase 2

> **Documento 1 de 5** · Fase 2 · Audiencia: equipo técnico
> Ventana: **8 → 20 de septiembre de 2026** · 12 fases · una fase = un día = un commit

---

## 1. Cómo se lee este documento

Cada fase es una unidad **cerrada y verificable**: se abre una rama, se construye, la tubería
pasa en verde, se fusiona por *squash* a `main` y queda un solo commit. Si una fase no pasa su
verificación, no se fusiona y no empieza la siguiente.

| Convención | Valor | Origen |
|---|---|---|
| Rama | `feature/<ID>-descripcion-corta` | `Entregable_2_Final/04` §1.3 |
| Commit | *Conventional Commits* en español, sin tildes en el asunto | `Entregable_2_Final/04` §1.3 |
| Fusión | *Squash* a `main`, nunca fusión directa | `Entregable_2_Final/04` §1.2 |
| Plantilla de PR | Trazabilidad HU / RF / RN / `to-be-xx` | `Entregable_2_Final/04` §1.4 |
| Estrategia | Trunk-based, `main` como única rama de larga vida | ADR-021 |

**No hay rama `develop`.** La justificación y el riesgo de esa desviación respecto al §3.1 del
enunciado están declarados en ADR-021: el proyecto lo desarrolla una persona, y `develop` no
integraría nada.

---

## 2. Mapa de las 12 fases

```
F-01 ─┬─ F-02 ── F-03 ──┬── F-04 ──┐
      │                 │          │
      └─ (entorno)      └ (identidad)
                                   │
                        F-05 ──────┴── F-06 ── F-07 ── F-08 ── F-09
                      (dominio)      (público) (bandeja) (pagos) (cancelacion)
                                                                      │
                                              ══════ LINEA DE CORTE ══╪══════
                                                                      │
                                                        F-10 ── F-11 ─┴─ F-12
                                                      (SOAP)  (bitacora) (docs)
```

**La línea de corte importa.** Al cerrar **F-09** el enunciado de la Fase 2 está cumplido por
completo: autenticación, roles, CRUD, notificaciones y **un proceso complejo finalizado**
(`to-be-01` → `to-be-02` → `to-be-03/03b` → `to-be-04`). F-10 y F-11 añaden el expediente
clínico, que es alcance propio, no exigencia del enunciado.

**Orden de recorte si el calendario se aprieta:** primero HU-13, después F-11, después F-10.
Nunca se recorta de F-01 a F-09.

---

## 3. Detalle por fase

### F-01 · Entorno, esqueleto hexagonal y tubería · **8 de septiembre**

| | |
|---|---|
| **Rama** | `feature/T-01-entorno-y-esqueleto` |
| **Commit** | `chore(setup): entorno docker, esqueleto hexagonal y tuberia de CI` |
| **Cierra** | T-01, T-02, T-03 · requisito D-8 (agentes de IA) |
| **Depende de** | — |

**Alcance**

- `docker/php/Dockerfile` (`php:8.4-fpm-alpine` con `pdo_mysql`, `redis`, `gd`, `intl`, `zip`,
  `bcmath`), `docker/nginx/default.conf`.
- `compose.yaml` con `nginx`, `app`, `worker`, `scheduler`; `compose.override.yaml` con
  `mariadb`, `redis`, `minio`, `mailpit`.
- `src/` con los siete contextos y `Shared`, según la estructura de paquetes del doc 02 §4.
- Autocarga PSR-4 con `"App\\": ["app/", "src/"]` — así el namespace de los diagramas de
  clases se cumple literal y `app/` queda delgado.
- `deptrac.yaml` con capas `Domain`, `Application`, `Infrastructure` por contexto y la regla
  de dependencia: `Domain` no puede depender de nada externo.
- Pint, Larastan, Pest configurados con las suites `Unit`, `Integration`, `Feature`.
- `.github/workflows/ci.yml` completo (doc 04 §3.1).
- `CLAUDE.md`, `.claude/agents/` (3 subagentes), `.claude/commands/`, `.claude/settings.json`
  con los 2 hooks del doc 12 §2.3.

**Verificación**

```bash
docker compose up -d && docker compose ps          # 10 servicios
vendor/bin/deptrac analyse --fail-on-uncovered     # sin violaciones sobre src/ vacio
vendor/bin/pest                                    # la suite arranca
```

Prueba deliberada: crear una clase en `src/Scheduling/Domain/` que importe `Illuminate\Support\Str`
y comprobar que **Deptrac rompe la construcción**. Es CA-24, y se verifica el primer día
porque si llega tarde, el dominio ya está acoplado.

**Riesgo** — Es la fase con más piezas y ninguna funcionalidad visible. Se acepta: T-02 y T-04
son restricciones estructurales; introducirlas después obliga a reescribir lo construido.

---

### F-02 · Migraciones base, catálogos y semillas · **9 de septiembre**

| | |
|---|---|
| **Rama** | `feature/T-05-migraciones-y-catalogos` |
| **Commit** | `feat(shared): migraciones base, catalogos y semillas deterministas` |
| **Cierra** | T-04, T-05 · requisito D-2 |
| **Depende de** | F-01 |

**Alcance**

- Tablas de infraestructura del framework en driver `database` (doc 06 §3.12): `sessions`,
  `jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks`.
- Los doce catálogos del doc 06 §2 con semilla determinista: `roles`, `abilities`,
  `appointment_statuses` (13 estados), `payment_statuses`, `payment_methods`, `banks`,
  `notification_channels`, `notification_statuses`, `cancellation_reasons`,
  `service_categories`, `sexes`, `document_types`.
- `business_rule_settings` con la semilla de RN-01 a RN-20, incluidos los tres tramos de RN-06
  como JSON: `[{"h":24,"pct":0},{"h":1,"pct":50},{"h":0,"pct":100}]`.
- Convenciones del doc 06 §7 aplicadas desde la primera migración: UUID v7 en dominio, `BIGINT`
  en bitácoras, `ON DELETE RESTRICT`, UTC, dinero en `INT` de centavos, `utf8mb4_unicode_ci`.

**Verificación**

```bash
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan migrate --seed   # idempotente: no duplica filas
docker compose exec app php artisan migrate:status   # todas aplicadas
```

**Riesgo** — Una semilla no idempotente duplica catálogos en el segundo despliegue. Se prueba
ejecutándola dos veces, no confiando en que lo sea.

---

### F-03 · Identidad, autenticación y roles · **10 de septiembre**

| | |
|---|---|
| **Rama** | `feature/T-06-identity-auth-y-roles` |
| **Commit** | `feat(identity): autenticacion, roles y permisos por agregado` |
| **Cierra** | T-06 · requisitos D-3, D-4, D-5 |
| **Depende de** | F-02 |

**Alcance**

- Migraciones `users` (UUID v7, `must_change_password`, `temp_password_expires_at`),
  `patients`, `therapists`, `role_ability`.
- Login con sesión en base de datos, cookie `HttpOnly` / `Secure` / `SameSite=Lax`, 8 h de
  inactividad en el panel y 2 h en el portal (doc 05 §3.3).
- Recuperación de contraseña: enlace firmado de un solo uso, TTL de 60 min, que **invalida las
  sesiones activas**. Mensaje idéntico exista o no la cuenta (doc 05 §6).
- Contraseña temporal con TTL de 72 h y cambio obligatorio al primer ingreso (RN-15).
- Los 32 permisos atómicos de doc 05 §4.2 cargados como `Gate` desde `abilities`, y políticas
  por agregado. **Ningún `if ($user->role === 'admin')` disperso.**
- Panel Filament restringido por rol; CRUD de usuarios y pacientes.

**Verificación**

```bash
vendor/bin/pest --filter=Auth
```

Manual: un usuario con rol `secretary` que solicita `appointment.approve` pasa; el mismo
usuario contra `report.view.financial` recibe 403. Un identificador que el usuario no puede
ver devuelve **404, no 403** (doc 05 §7).

**Riesgo** — La matriz de 32 permisos es tediosa y es la base de F-10. Un permiso mal asignado
aquí se descubre en el expediente clínico, que es donde más caro sale.

---

### F-04 · Motor de notificaciones · **11 de septiembre**

| | |
|---|---|
| **Rama** | `feature/T-07-motor-de-notificaciones` |
| **Commit** | `feat(notifications): motor con idempotencia, reintentos y bitacora` |
| **Cierra** | T-07 · requisito D-6 |
| **Depende de** | F-03 |

**Alcance**

- Migraciones `notification_templates`, `notification_dispatches`,
  `notification_preferences`.
- Puerto `NotificationChannel` con el adaptador `MailChannel` apuntando a Mailpit en local.
- **Idempotencia por `(evento, destinatario, canal)`** (RN-17): el mismo evento procesado dos veces produce un solo
  envío.
- Reintentos con retroceso 1 / 5 / 25 min; al tercer fallo, NT-20 al administrador.
- `EventBus` sobre el bus del framework y el catálogo de los 28 eventos de dominio del doc 02
  §2.2, con esquema explícito y versionado — **nunca `$model->toArray()`**.
- Cuatro colas separadas por SLA de negocio: `critical`, `mail`, `documents`, `default`
  (doc 02 §8).

**Verificación**

```bash
vendor/bin/pest --filter=Notification
```

Casos obligatorios del doc 11 §2.1: mismo evento dos veces → un envío; recordatorio dentro de
la ventana de silencio → diferido a las 07:00; tres fallos → NT-20; cita cancelada no dispara
su recordatorio.

**Riesgo** — Media docena de historias dependen de este motor. Va antes que ellas por eso.

---

### F-05 · Dominio Scheduling y políticas de negocio · **12 de septiembre**

| | |
|---|---|
| **Rama** | `feature/HU-01-dominio-scheduling` |
| **Commit** | `feat(scheduling): agregado Appointment, politicas RN-01..RN-08 y pruebas de dominio` |
| **Cierra** | HU-01 · base del requisito D-7 |
| **Depende de** | F-02 |

**Alcance**

- `src/Scheduling/Domain/` completo según doc 08 §1: `Appointment` como único agregado que
  cambia el estado de una cita, `AppointmentStatus` con los 13 estados y sus transiciones,
  `TimeSlot`, `BookingSource`, `ScheduleBlock`.
- `src/Shared/Domain/ValueObject/`: `Money` (centavos + moneda), `Uuid`, `PhoneNumber`, `Email`.
- Políticas: `BookingWindowPolicy` (RN-01, RN-02), `ApprovalSlaPolicy` (RN-03, RN-04),
  `TieredCancellationPolicy` con `CancellationTier[]` (RN-06), `ReminderPolicy` (RN-08).
- `ClockInterface` con `SystemClock` y `FrozenClock`.
- Puertos `AppointmentRepository`, `SlotLockManager`, `AvailabilityProvider`.
- Migraciones `services`, `service_prices`, `availability_rules`, `appointments`,
  `appointment_status_log`, `slot_holds`, con el `UNIQUE (therapist_id, starts_at)` que es la
  defensa dura contra la doble reserva.

**Verificación** — **Esta es la fase donde se paga la arquitectura.**

```bash
vendor/bin/pest --testsuite=Unit    # sin base de datos, sin framework, en milisegundos
```

Los siete casos frontera de RN-06 del doc 11 §2.1, cada uno como prueba:

| Anticipación | Cargo esperado |
|---|---|
| 24 h 00 min 01 s | 0 % |
| **exactamente 24 h** | **0 %** |
| 23 h 59 min | 50 % |
| 1 h 00 min 01 s | 50 % |
| **exactamente 1 h** | **50 %** |
| 59 min | 100 % |
| Inasistencia | 100 % |

**Riesgo** — Si estas pruebas necesitan migrar una base de datos para correr, la frontera está
mal puesta y hay que corregirla aquí, no en F-09.

---

### F-06 · Agendamiento público: landing y wizard · **13 de septiembre**

| | |
|---|---|
| **Rama** | `feature/HU-02-agendamiento-publico` |
| **Commit** | `feat(scheduling): landing y solicitud de cita en tres pasos` |
| **Cierra** | HU-02, HU-03, T-09 · requisito D-1 |
| **Depende de** | F-05, F-04 |

**Alcance**

- Landing Livewire con `wire:navigate`, mobile-first, con la paleta y tipografía del sistema
  de diseño (`Entregable_1_Final/mockups/01-sistema-de-diseno.md`): primario `#4A5D4E`, fondo
  `#FDFCF9`, serif solo en títulos, radio de tarjeta 12 px, área táctil mínima 44 × 44 px.
- Módulos M1 (perfil profesional), M2 (servicios y tarifas), M5 (FAQ y contacto).
- Endpoint de disponibilidad con caché: **se cachean horarios, nunca personas** (doc 05 §4.4).
- Wizard de tres pasos según los mockups `A-01` → `A-02` → `A-03` → `A-04`:
  - `A-01` elegir servicio, con duración y tarifa **Q 300** visibles.
  - `A-02` calendario mensual; si el día elegido es hoy, MSG-01 con el canal telefónico (RN-01).
  - `A-03` datos, **política de cancelación con sus tres tramos completos** y aceptación
    explícita; `consents` con el texto versionado, no solo la marca de aceptación.
  - `A-04` confirmación.
- `SlotLockManager` con `Cache::lock` tomado **antes** de crear la cita (RN-04). Si el horario
  se ocupa mientras el visitante llena el formulario, se conservan los datos y se ofrecen
  horarios cercanos (RF-08).
- Alta automática del paciente con credencial temporal y correo de bienvenida (RF-06).
- Reconocimiento del paciente que regresa por correo (HU-03).

**Verificación**

```bash
vendor/bin/pest --testsuite=Feature --filter=Booking
```

- Dos solicitudes simultáneas para el mismo horario: una crea la cita, la otra recibe **409**.
- El horario se ocupa entre la validación y la inserción: falla limpiamente, **sin HOLD
  huérfano**.
- Intentar agendar para hoy desde la web: rechazado con MSG-01.
- Manual: CA-01 — una persona ajena al proyecto agenda sin instrucciones.

**Riesgo** — Es la fase con más superficie visible y la que más fácil se alarga por detalle
visual. El checklist de los mockups manda sobre el gusto.

---

### F-07 · Bandeja de aprobaciones y expiración por SLA · **14 de septiembre**

| | |
|---|---|
| **Rama** | `feature/HU-04-bandeja-y-sla` |
| **Commit** | `feat(scheduling): bandeja de aprobaciones priorizada por SLA y expiracion automatica` |
| **Cierra** | HU-04, HU-05 |
| **Depende de** | F-06 |

**Alcance**

- Página Filament según el mockup `D-02`, **ordenada por tiempo restante de SLA ascendente**,
  con semáforo de color **e icono** (nunca color solo).
- Handlers `ApproveAppointment` y `RejectAppointment`: orquestan, no deciden.
- `ExpirePreAppointmentJob` en la cola `critical`, que **verifica el estado antes de actuar**:
  si la cita ya salió de `SOLICITADA`, no hace nada.
- `scheduler` con `withoutOverlapping()` sobre el driver de bloqueo **`database`**, no Redis
  (doc 03 §2.3). Con dos instancias, ese detalle es la diferencia entre un recordatorio y dos.
- Rechazo y expiración como desenlaces distintos, con estado y notificación propios (RF-14).

**Verificación**

```bash
vendor/bin/pest --filter=Sla
```

- El trabajo de expiración corre después de que la cita fue aprobada → sin efecto.
- El trabajo corre dos veces → sin efecto la segunda.
- CA-03: con el SLA reducido a 5 minutos, una pre-cita sin respuesta expira y libera el horario.

---

### F-08 · Pagos: comprobante y conciliación · **15 de septiembre**

| | |
|---|---|
| **Rama** | `feature/HU-06-pagos-y-conciliacion` |
| **Commit** | `feat(billing): comprobante de transferencia y conciliacion humana` |
| **Cierra** | HU-06, HU-07 |
| **Depende de** | F-07 |

**Alcance**

- Migraciones `payments` y `payment_proofs`, con el `UNIQUE (origin_bank_id, receipt_number)`
  que implementa RN-14.
- Puerto `PaymentProofStorage` con adaptador MinIO (S3 en Fase 3), **bucket privado** y URL
  prefirmada de 5 minutos.
- Portal del paciente `P-01`: pago pendiente con cuenta regresiva hasta T-3 h.
- Validación del **tipo MIME real**, no de la extensión; tamaño máximo; nombre generado por el
  sistema; `file_hash_sha256` para detectar el mismo archivo resubido.
- Solo se guardan los **últimos 4 dígitos** de la cuenta de origen. Guardar el número completo
  sería asumir riesgo sin beneficio operativo.
- Pantalla de conciliación `D-03` con datos capturados y comprobante **lado a lado**.
  La aprobación es **siempre humana**: el sistema valida, no decide (RN-09).
- Opción de pago en efectivo (`PAGO_EN_CAJA`), que se cobra en el check-in.

**Verificación**

```bash
vendor/bin/pest --filter=Payment
```

- Subir dos veces la misma boleta del mismo banco → rechazado por RN-14.
- Un archivo `.pdf` con contenido ejecutable → rechazado por MIME real.
- CA-20: petición anónima al bucket → **403**.

---

### F-09 · Cancelación, impago, inasistencia y recordatorios · **16 de septiembre**

| | |
|---|---|
| **Rama** | `feature/HU-09-cancelacion-y-recordatorios` |
| **Commit** | `feat(scheduling): cancelacion escalonada, impago, no-show y recordatorios` |
| **Cierra** | HU-08, HU-09, HU-10, HU-19, T-10 · **cierra el requisito D-7** |
| **Depende de** | F-08 |

**Alcance**

- `CancelUnpaidJob`: una cita sin pago **aprobado** al llegar T-3 h se cancela (RN-05, RN-07).
  Una cita en `PAGO_EN_REVISION` **se cancela igual**; una en `PAGO_EN_CAJA` **no**.
- Cancelación desde el portal `P-02` con **previsualización del cargo antes de confirmar**
  (RF-24), aplicando el tramo correcto de RN-06.
- `patient_credits`: una cancelación sin cargo sobre una cita pagada genera **crédito a favor**,
  no reembolso bancario (RN-16).
- Exoneración de recargo **solo por la psicóloga**, con motivo obligatorio y registro (RF-26).
- Inasistencia: 100 % de cargo y **no libera el horario** (RF-30).
- Recordatorios automáticos a T-24 h y T-2 h, verificando antes que la cita siga vigente,
  con la ventana de silencio de 21:00 a 07:00 (RN-08, RN-17).
- Comando de reconciliación cada 15 minutos que recupera expiraciones y cancelaciones
  perdidas si el procesador de colas estuvo caído (T-10, cubre R-06).

**Verificación** — **Es la verificación de extremo a extremo del proceso complejo.**

```bash
vendor/bin/pest
```

1. Agendar desde la landing → correo en Mailpit (`localhost:8025`).
2. La solicitud aparece en la bandeja ordenada por SLA.
3. Aprobar → enlace de pago; subir comprobante; conciliar.
4. Sin pago aprobado en T-3 h → cancelación automática (CA-04).
5. Cancelar a exactamente 24 h → 0 %; a exactamente 1 h → 50 %; a 59 min → 100 % (CA-05).
6. Inasistencia → 100 % y el horario **no** se libera (CA-06).
7. El procesador de colas apagado 2 horas → la reconciliación recupera lo perdido.

**Al fusionar esta fase, el enunciado de la Fase 2 está cumplido.**

---

### F-10 · Expediente clínico y nota SOAP · **17 de septiembre**

| | |
|---|---|
| **Rama** | `feature/HU-11-expediente-y-nota-soap` |
| **Commit** | `feat(clinical): ficha administrativa y nota SOAP con sellado inmutable` |
| **Cierra** | HU-11, HU-12 |
| **Depende de** | F-09, F-03 |

**Alcance**

- `clinical_records` con la **ficha administrativa** (motivo reportado, referido por, contacto
  de emergencia): visible para recepción.
- `clinical_notes` con los cuatro campos SOAP **cifrados a nivel de aplicación**, con una llave
  que no vive en la base de datos.
- Editor `D-04`: registrar, sellar y enmendar. La enmienda exige motivo y crea una versión
  nueva enlazada por `supersedes_note_id` (RN-11); **no hay `UPDATE` sobre una nota sellada**.
- Inmutabilidad en **tres capas**: el agregado del dominio, la política de autorización y el
  *trigger* `trg_clinical_notes_immutable`.
- Recepción **sin** `clinical_note.view/create/seal/amend` (doc 05 §4.3).

**Verificación**

```bash
vendor/bin/pest --filter=Clinical
```

- CA-07: sellar dos veces → error, no doble versión. `UPDATE` directo por SQL sobre una nota
  sellada → el trigger lo rechaza. Intento por interfaz y por API → rechazado.
- CA-08: recepción solicita leer una nota SOAP → **403**.
- El contenido SOAP en la base está cifrado, verificado consultando la tabla directamente.
- Enmendar sin motivo → rechazado.

**Riesgo** — El cifrado y la inmutabilidad de tres capas es lo más caro del proyecto. Es la
primera fase después de la línea de corte precisamente por eso.

---

### F-11 · Bitácora, bloqueo de agenda y agendamiento asistido · **18 de septiembre**

| | |
|---|---|
| **Rama** | `feature/HU-18-bitacora-y-bloqueo-de-agenda` |
| **Commit** | `feat(clinical): bitacora de acceso, bloqueo de agenda y agendamiento asistido` |
| **Cierra** | HU-18, HU-20, T-11 |
| **Depende de** | F-10 |

**Alcance**

- `audit_log` **de solo escritura desde la aplicación**: sin operación de edición ni de
  borrado. Una bitácora modificable no es una bitácora.
- Las ocho acciones mínimas del doc 05 §5.1, incluido `clinical_record.viewed` en **cada**
  lectura del expediente (RN-12) y el intento fallido de recepción.
- `schedule_blocks` (RN-18, `to-be-10`): la psicóloga bloquea su agenda **resolviendo
  explícitamente cada cita afectada antes de publicar el bloqueo**.
- Agendamiento asistido por recepción, con excepción a RN-01: puede agendar el mismo día.

**Verificación**

```bash
vendor/bin/pest --filter=Audit
```

- CA-12: tras un acceso de prueba al expediente, el registro aparece en la bitácora.
- CA-08 completo: el intento fallido de recepción queda **también** en bitácora.
- Bloquear un rango con citas dentro sin resolverlas → rechazado.

---

### F-12 · Manual de usuario, documentación de usuario y cierre · **19–20 de septiembre**

| | |
|---|---|
| **Rama** | `feature/T-16-manual-y-documentacion-de-usuario` |
| **Commit** | `docs: manual de usuario, documentacion de usuario y cierre de fase 2` |
| **Cierra** | T-16 · la documentación diferida en `Entregable_2_Final/00-README.md` §7 |
| **Depende de** | F-11 |

**Alcance**

- **Manual de usuario** MU-01 … MU-17 (doc 11 §5) con capturas reales del sistema levantado.
  Las secciones alcanzables en esta fase son MU-01, MU-03 a MU-05 y MU-07 a MU-13, MU-17;
  MU-02, MU-14, MU-15 y MU-16 se completan en Fase 3 con el chatbot y los reportes.
- **Documentación de usuario** por rol: qué ve y qué puede hacer paciente, recepción y
  psicóloga.
- `README.md` del repositorio: cómo levantar el proyecto en cinco comandos.
- Actualización de la tabla de trazabilidad de `Entregable_2_Final/00-README.md` §6 y §7.
- HU-13 (pruebas psicométricas) **solo si hay margen** — prioridad «Debería», bloqueada por
  la decisión pendiente DP-02.

**Verificación** — Una persona ajena al proyecto levanta el entorno siguiendo solo el README y
completa el recorrido de MU-01 sin ayuda.

---

## 4. Resumen y control de avance

| Fase | Día | Cierra | Puntos | Acumulado |
|---|---|---|---:|---:|
| F-01 | 8 sep | T-01, T-02, T-03 | 18 | 18 |
| F-02 | 9 sep | T-04, T-05 | 8 | 26 |
| F-03 | 10 sep | T-06 | 5 | 31 |
| F-04 | 11 sep | T-07 | 8 | 39 |
| F-05 | 12 sep | HU-01 | 5 | 44 |
| F-06 | 13 sep | HU-02, HU-03, T-09 | 18 | 62 |
| F-07 | 14 sep | HU-04, HU-05 | 16 | 78 |
| F-08 | 15 sep | HU-06, HU-07 | 16 | 94 |
| F-09 | 16 sep | HU-08, HU-09, HU-10, HU-19, T-10 | 23 | **117** ← enunciado cumplido |
| F-10 | 17 sep | HU-11, HU-12 | 16 | 133 |
| F-11 | 18 sep | HU-18, HU-20, T-11 | 18 | 151 |
| F-12 | 19–20 sep | T-16 (+ HU-13 opcional) | 3 (+8) | **154** |

**El plan original estimaba estos 154 puntos en 12 semanas.** Aquí se comprimen en 12 días, y
eso es una decisión consciente, no un descuido de estimación. La compresión se sostiene sobre
tres cosas: el diseño ya está escrito y no hay que decidirlo mientras se programa, el ambiente
de agentes de IA cubre el andamiaje repetitivo (doc 12 §3.1), y la línea de corte permite
entregar el enunciado completo en F-09 aunque F-10 a F-12 se recorten.

### 4.1 Definición de Terminado por fase

Una fase no se fusiona sin las siete casillas:

- [ ] Pruebas unitarias del dominio para toda regla RN-xx implementada.
- [ ] Prueba funcional del flujo por HTTP.
- [ ] `vendor/bin/deptrac analyse` en verde.
- [ ] Migraciones idempotentes y compatibles hacia atrás.
- [ ] Sin secretos en el diff (gitleaks).
- [ ] Nada que deba perderse se guardó en caché (ADR-008).
- [ ] Documentación actualizada si cambió un contrato o una regla.

### 4.2 Trazabilidad del uso de agentes de IA

Cada commit con aporte sustancial de un agente lo declara en el pie del mensaje, y el PR
responde «¿qué parte se generó y cómo se verificó?» (doc 12 §5). **No es un adorno de
honestidad: es lo que permite auditar después qué revisar con más cuidado.**

La regla que gobierna todo lo demás sigue siendo la del doc 12: **si no se puede explicar el
código generado, no se fusiona.** El enunciado penaliza el desconocimiento del proyecto hasta
con el 100 % de la nota.
