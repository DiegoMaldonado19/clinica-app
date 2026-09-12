# 03 — Requisitos que debe cumplir la Fase 2

> **Documento 3 de 3** · Fase 2 · Audiencia: equipo, cliente y evaluación
> Cruza el §8.1 y el §6 del enunciado contra el alcance que definimos en
> `Entregable_1_Final/` y `Entregable_2_Final/`, y dice para cada punto **dónde queda**.

---

## 1. La pregunta que responde este documento

El §8.3 del enunciado dice que la calificación se hace «alrededor a la combinación de la
primera toma de requerimientos y primera fase el alcanzable». Es decir: **no se evalúa contra
un alcance genérico, sino contra el que nosotros mismos declaramos.** Este documento fija ese
alcance para la Fase 2 de modo que sea verificable y no interpretable.

Tres estados posibles para cada requisito:

| Estado | Significa |
|---|---|
| ✅ **Cubierto** | Ya existe, entregado en Fase 1 o en la documentación de Fase 2 |
| 🔨 **Se construye** | Es trabajo de estos 12 días, con fase asignada |
| ⏭️ **Diferido** | Fase 3, con justificación y sprint asignado |

---

## 2. Requisitos de desarrollo (§8.1, Fase 2)

El enunciado enumera cuatro. Se desglosan y se les asigna fase del documento 01.

### 2.1 «Primeras funcionalidades: desarrollo del frontend y complejas del backend»

| Qué | Estado | Fase | Referencia |
|---|---|---|---|
| Landing pública con perfil, servicios, tarifas y FAQ | 🔨 | F-06 | doc 10 §1.2 · mockups `L-01`, `L-02`, `L-03` |
| Wizard público de agendamiento en tres pasos | 🔨 | F-06 | mockups `A-01` → `A-04` |
| Portal del paciente: mis citas, mi pago | 🔨 | F-08, F-09 | mockups `P-01`, `P-02` |
| Panel administrativo Filament | 🔨 | F-03, F-07, F-08, F-10 | mockups `D-02`, `D-03`, `D-04` |
| Backend complejo: agregado con 13 estados y sus transiciones | 🔨 | F-05 | doc 08 §1 |
| Backend complejo: bloqueo de slot bajo concurrencia | 🔨 | F-06 | RN-04 |
| Backend complejo: trabajos diferidos con verificación de estado | 🔨 | F-07, F-09 | doc 02 §8.1 |

**Lo que hace «complejo» a este backend no es el volumen: son los plazos y la concurrencia.**
Un sistema de 87 sesiones al mes no tiene problema de escala; tiene una máquina de estados con
seis plazos distintos (24 h, 72 h, 3 h, 1 h, T-24 h, T-2 h) y un recurso —el horario— que dos
personas pueden pedir en el mismo milisegundo.

### 2.2 «Implementación de base de datos y migraciones versionadas»

| Qué | Estado | Fase | Referencia |
|---|---|---|---|
| Modelo entidad-relación diseñado | ✅ | — | doc 06 §1 |
| Diccionario de datos y justificación de la normalización a 3FN | ✅ | — | doc 06 §3 y §4 |
| Migraciones versionadas en el repositorio | 🔨 | F-02, F-03, F-05, F-08, F-10 | doc 06 §7 |
| Semillas idempotentes de los 12 catálogos | 🔨 | F-02 | doc 06 §2 |
| Reglas RN-01…RN-20 como parámetros en base, no constantes | 🔨 | F-02 | `business_rule_settings`, doc 06 §3.10 |
| Migraciones ejecutadas de forma automatizada por la tubería | ⏭️ | Fase 3 | doc 04 §3.3, fase 1 del despliegue |
| Patrón expandir/contraer aplicado | 🔨 | desde F-02 | doc 04 §5.2 |

El enunciado exige en §5.2 «un sistema de control de versiones para el esquema (ej. Flyway,
Liquibase, Sequelize Migrations, Alembic)». **El equivalente en este stack son las migraciones
de Laravel**, versionadas en el repositorio y aplicadas en orden determinista. La
automatización total —que corran solas al desplegar— es Fase 3 porque requiere la tubería de
despliegue, que requiere la infraestructura.

### 2.3 «Auth, CRUD de entidades, roles, notificaciones y un proceso complejo finalizado»

| Qué | Estado | Fase | Referencia |
|---|---|---|---|
| Registro de paciente (alta automática con credencial temporal) | 🔨 | F-06 | RF-06, RN-15 |
| Login con sesiones seguras | 🔨 | F-03 | doc 05 §3.3 |
| Recuperación de contraseña por correo | 🔨 | F-03 | doc 05 §3.2 |
| Cambio obligatorio de contraseña temporal | 🔨 | F-03 | RN-15 |
| Tres roles: administradora, recepción, paciente | 🔨 | F-03 | doc 05 §4.2 |
| 32 permisos atómicos con políticas por agregado | 🔨 | F-03 | doc 05 §4.2 |
| CRUD completo de `Appointment` (entidad principal) | 🔨 | F-05 → F-09 | doc 08 §1 |
| CRUD de pacientes, servicios, pagos y reglas de negocio | 🔨 | F-03, F-08 | Filament |
| Motor de notificaciones con idempotencia y reintentos | 🔨 | F-04 | doc 02 §2.2 |
| Notificación por correo en procesos críticos | 🔨 | F-04 | ADR-017 |
| **Un proceso complejo identificado finalizado** | 🔨 | **F-05 → F-09** | ver §2.3.1 |

#### 2.3.1 El proceso complejo elegido

**`to-be-01` → `to-be-02` → `to-be-03/03b` → `to-be-04`**: solicitud de pre-cita, aprobación
con SLA, gestión del pago y conciliación, cancelación con política escalonada.

Se elige este y no otro por cuatro razones:

1. **Es el corazón del negocio.** Es el proceso que produce el KPI-11, el indicador rector del
   proyecto: citas originadas sin intervención humana.
2. **Atraviesa cuatro contextos acotados** —Scheduling, Billing, Identity y Notifications— por
   eventos de dominio, sin una sola unión SQL entre ellos. Es lo que demuestra que la
   arquitectura no es decorativa.
3. **Concentra la dificultad real**: concurrencia sobre el slot, seis plazos, trabajos
   diferidos idempotentes, una máquina de 13 estados y una política de negocio parametrizable.
4. **Se puede terminar de verdad en la ventana disponible.** Un proceso a medias no cumple el
   requisito, que dice «finalizado».

Los estados que recorre, de principio a fin:

```
SOLICITADA ──aprobar──► CONFIRMADA_PENDIENTE_PAGO ──comprobante──► PAGO_EN_REVISION
    │                              │                                      │
    │ SLA vencido                  │ T-3h sin pago aprobado               │ conciliar
    ▼                              ▼                                      ▼
 EXPIRADA                   CANCELADA_IMPAGO                          AGENDADA
    │                                                                     │
    │ rechazo explicito                          ┌────────────────────────┼──────────┐
    ▼                                            ▼                        ▼          ▼
 RECHAZADA                            CANCELADA_SIN_CARGO      CANCELADA_CON_RECARGO  NO_SHOW
                                          (0 %, RN-06)          (50 % o 100 %, RN-06)  (100 %)
```

### 2.4 «Ambiente de trabajo con manejo de Agentes de IA»

| Qué | Estado | Fase | Referencia |
|---|---|---|---|
| Política de uso: dónde sí, dónde no | ✅ | — | doc 12 §3 · `Entregable_1_Final/08` §4 |
| `CLAUDE.md` con las instrucciones permanentes | 🔨 | F-01 | doc 12 §2.1 |
| Tres subagentes de alcance acotado | 🔨 | F-01 | doc 12 §2.2 |
| Hooks que ejecutan Deptrac y Pint automáticamente | 🔨 | F-01 | doc 12 §2.3 |
| Hook que bloquea `migrate:fresh` fuera de local | 🔨 | F-01 | doc 12 §2.3 |
| Trazabilidad del uso en commits y PRs | 🔨 | desde F-01 | doc 12 §5 |

**Este requisito tira en dirección contraria al §8.3 del enunciado**, que penaliza hasta con el
100 % de la nota el desconocimiento del propio código. El ambiente está diseñado para cumplir
ambos: los agentes hacen andamiaje repetitivo y traducción de Gherkin a pruebas; **no** deciden
reglas de negocio, no diseñan la máquina de estados y no escriben la lógica de cifrado ni de
control de acceso.

La regla que gobierna todo lo demás: **si no se puede explicar el código generado, no se
fusiona.**

---

## 3. Requisitos de documentación (§8.1, Fase 2)

| Documento exigido | Estado | Dónde | Acción en esta fase |
|---|---|---|---|
| Historias de usuario | ✅ | `Entregable_2_Final/07` §3, con Gherkin | Verificar trazabilidad al cerrar cada fase |
| Documentación técnica | ✅ | `Entregable_2_Final/01`–`13` | — |
| Diagramas de clases | ✅ | `Entregable_2_Final/08`, 7 contextos | Contrastar contra `src/` al cerrar F-05 y F-10 |
| **Infraestructura finalizada** | 🔨 | **`docs/fase-2/02`** | Compose local operativo + CI; AWS a Fase 3 |
| Diagrama de la arquitectura | ✅ | `Entregable_2_Final/02` §1 | — |
| Modelo entidad-relación | ✅ | `Entregable_2_Final/06` §1 | — |
| Estructura de paqueterías | ✅ | `Entregable_2_Final/02` §4 | **Materializarla en `src/`** (F-01) |
| Modelos de procesos técnicos completos | ✅ | `Entregable_2_Final/09`, 12 secuencias | — |
| Historias de usuario y casos de uso finalizadas | ✅ | `Entregable_2_Final/07` · `Entregable_1_Final/10` | — |
| **Documentación de usuario** | 🔨 | **F-12** | Diferida a Fase 2 en el README §7; vence ahora |
| **Manual de usuario** | 🔨 | **F-12** | MU-01…MU-17, doc 11 §5 |
| Secuencia de pantallas | ✅ | `Entregable_2_Final/10` §4 · mockups §6 | — |

**De doce documentos exigidos, nueve ya están entregados.** El trabajo documental real de esta
fase son tres: la infraestructura operativa, el manual de usuario y la documentación de
usuario. Los dos últimos estaban explícitamente diferidos —con su razón— en
`Entregable_2_Final/00-README.md` §7: un manual de una interfaz que no existe se reescribe
entero.

### 3.1 Alcance del manual de usuario en esta fase

| # | Sección | Rol | Fase 2 |
|---|---|---|---|
| MU-01 | Agendar una cita desde la web | Paciente | ✅ |
| MU-02 | Agendar conversando con el asistente | Paciente | ⏭️ requiere chatbot |
| MU-03 | Primer ingreso: contraseña | Paciente | ✅ (sin 2FA) |
| MU-04 | Pagar y subir el comprobante | Paciente | ✅ |
| MU-05 | Cancelar o reprogramar, y qué cuesta | Paciente | ✅ |
| MU-06 | Responder una prueba asignada | Paciente | ⏭️ HU-13 |
| MU-07 | Revisar y aprobar solicitudes | Recepción | ✅ |
| MU-08 | Conciliar pagos | Recepción | ✅ |
| MU-09 | Agendar por teléfono | Recepción | ✅ |
| MU-10 | Registrar llegada e inasistencia | Recepción | ✅ |
| MU-11 | Documentar una sesión y sellar la nota | Psicóloga | ✅ |
| MU-12 | Enmendar una nota sellada | Psicóloga | ✅ |
| MU-13 | Bloquear la agenda por vacaciones | Psicóloga | ✅ |
| MU-14 | Leer el tablero y exportar reportes | Psicóloga | ⏭️ Fase 3 |
| MU-15 | Cambiar plazos, tarifas y políticas | Psicóloga | ✅ |
| MU-16 | Actualizar lo que el asistente sabe | Psicóloga | ⏭️ requiere chatbot |
| MU-17 | Consultar quién accedió a un expediente | Psicóloga | ✅ |

**Trece de diecisiete.** Las cuatro ausentes dependen de funcionalidad diferida, no de falta de
tiempo para escribirlas.

---

## 4. Requisitos funcionales mínimos (§6 del enunciado)

Son los seis que el enunciado exige a todo proyecto, con independencia del alcance propio.

| §6 | Fase 2 | Fase 3 | Justificación del reparto |
|---|---|---|---|
| Registro, login, sesiones seguras | ✅ F-03, F-06 | — | — |
| Recuperación de contraseña | ✅ F-03 | — | — |
| **Notificaciones por correo en procesos críticos** | ✅ F-04 | — | El motor completo, con idempotencia y reintentos |
| **2FA por correo** | — | ⏭️ HU-21 | El propio enunciado lo ubica en Fase 3: «seguridad avanzada: eg. 2FA» |
| Gestión de roles (mínimo dos perfiles) | ✅ **tres** roles, F-03 | — | Administradora, recepción, paciente |
| CRUD completo de una entidad principal | ✅ `Appointment`, F-05→F-09 | — | Con sus 13 estados, no solo alta/baja/modificación |
| Módulo de comunicación | ✅ notificaciones, F-04 | ⏭️ chatbot HU-24 | El enunciado admite «chat, notificaciones, etc.» |
| **Reportes con exportación XLSX y PDF** | — | ⏭️ HU-14, HU-15 | El enunciado lo ubica en Fase 3: «finalización de reportes (PDF/Excel)» |

**Dos de los ocho quedan para Fase 3, y ambos porque el enunciado mismo los coloca ahí.** No es
un recorte del alcance: es respetar el reparto por fases que el enunciado propone en su §8.1.

---

## 5. Criterios de aceptación aplicables a esta fase

De los 34 criterios de `Entregable_2_Final/11` §3, estos son los que se pueden verificar al
cerrar la Fase 2. Los demás requieren infraestructura desplegada, chatbot o reportes.

### 5.1 Funcionales

| # | Criterio | Cómo se verifica | Fase |
|---|---|---|---|
| CA-01 | Un visitante agenda desde la landing sin ayuda | Demostración con una persona ajena al proyecto | F-06 |
| CA-03 | Una pre-cita sin respuesta expira y libera el horario | Prueba con SLA reducido a 5 minutos | F-07 |
| CA-04 | Una cita sin pago aprobado se cancela en T-3 h | Prueba con límite reducido | F-09 |
| CA-05 | Cancelar aplica el tramo correcto de RN-06 | **Los siete casos frontera** | F-05, F-09 |
| CA-06 | Una inasistencia genera 100 % y no libera el horario | Prueba funcional | F-09 |
| CA-07 | Una nota sellada no puede editarse **por ninguna vía** | Intento por interfaz, por API y **por SQL directo** | F-10 |
| CA-08 | Recepción no puede leer notas clínicas | Intento por interfaz y por API | F-10 |
| CA-11 | Un usuario recupera su contraseña sin intervención | Demostración | F-03 |
| CA-12 | Todo acceso al expediente aparece en la bitácora | Consulta tras un acceso de prueba | F-11 |
| CA-14 | Cambiar el SLA afecta a las citas nuevas y no a las existentes | Demostración | F-02, F-07 |

### 5.2 No funcionales

| # | Criterio | Umbral | Fase |
|---|---|---|---|
| CA-15 | El calendario de disponibilidad responde | < 500 ms | F-06 |
| CA-17 | La agenda semanal no supera el umbral de consultas | **≤ 8 consultas** | F-07 |
| CA-20 | El almacenamiento de comprobantes no es público | Petición anónima → 403 | F-08 |
| CA-24 | **Un PR que viola la regla de dependencia es rechazado** | Prueba deliberada | F-01 |
| CA-32 | **Apagar la caché no desloguea a nadie ni pierde trabajos** | La suite crítica pasa con `CACHE_STORE=null` | F-01, F-02 |

**CA-05, CA-07 y CA-24 son los tres que más valor tienen para la evaluación**, porque cada uno
demuestra algo que no se puede fingir: que la política de negocio está parametrizada y probada
al segundo, que la inmutabilidad clínica es real en las tres capas, y que la arquitectura
hexagonal se verifica sola en lugar de ser una afirmación del diagrama.

### 5.3 Diferidos a Fase 3

CA-02 y CA-13 (asistente), CA-09 (exportación), CA-10 (2FA), CA-16 (tablero), CA-18, CA-19,
CA-21, CA-22, CA-23, CA-31, CA-33, CA-34 (infraestructura desplegada), y los seis de adopción
CA-25 a CA-30, que se miden **un mes después** de la fecha de corte.

---

## 6. Trazabilidad completa

Del requisito del enunciado a la fase que lo construye.

| Enunciado | Alcance propio | HU / T | RF | RN | `to-be` | Fase |
|---|---|---|---|---|---|---|
| Auth: login y sesiones | Identity | T-06 | — | RN-15 | `to-be-12` | F-03 |
| Auth: recuperación | Identity | HU-23 (parcial) | — | — | `to-be-12` | F-03 |
| Roles | Identity | T-06 | — | RN-13 | — | F-03 |
| Notificaciones | Notifications | T-07 | RF-54…RF-58 | RN-08, RN-17 | `to-be-06`, `06b` | F-04 |
| BD y migraciones | Shared | T-05 | — | — | — | F-02 |
| CRUD entidad principal | Scheduling | HU-01…HU-05 | RF-01…RF-15 | RN-01…RN-04 | `to-be-01`, `02` | F-05→F-07 |
| Proceso complejo · pago | Billing | HU-06, HU-07 | RF-16…RF-23 | RN-05, RN-07, RN-09, RN-14 | `to-be-03`, `03b` | F-08 |
| Proceso complejo · cancelación | Scheduling + Billing | HU-08…HU-10, HU-19 | RF-24…RF-30 | RN-06, RN-16 | `to-be-04`, `05b`, `11` | F-09 |
| Frontend | Landing + portal + panel | T-09 | RF-01…RF-08 | — | `to-be-01` | F-06 |
| Expediente clínico | ClinicalRecords | HU-11, HU-12, HU-18 | RF-34…RF-39 | RN-10…RN-12 | `to-be-05` | F-10, F-11 |
| Bloqueo de agenda | Scheduling | HU-20 | RF-31 | RN-18 | `to-be-10` | F-11 |
| Agentes de IA | Ambiente | — | — | — | — | F-01 |
| Infraestructura | Compose + CI | T-01, T-03 | RNF-xx | — | — | F-01 |
| Manual y doc. de usuario | Documentación | T-16 | — | — | — | F-12 |

---

## 7. Lo diferido, dicho explícitamente

Igual que en `Entregable_2_Final/00-README.md` §7: lo que no está, se declara. Una omisión
declarada es una decisión; una omisión callada es un descuido.

| Diferido | Estado | Cuándo | Por qué |
|---|---|---|---|
| Asistente conversacional (Python + FastAPI) | Diseñado en doc 02 §6 y doc 08 §7 | Sprint 4 / Fase 3 | Es el componente heterogéneo y el más caro. El enunciado no lo exige en Fase 2, y HU-26 (escalamiento de crisis) es su condición de existencia: publicarlo sin eso no es opción |
| 2FA por correo (HU-21) | Diseñado en doc 05 §3.1 | Sprint 4 / Fase 3 | El enunciado lo ubica en Fase 3, §8.1 |
| Tablero de indicadores (HU-14) | Diseñado en doc 06 §5.3 | Sprint 4 / Fase 3 | Ídem |
| Exportación XLSX y PDF (HU-15) | Diseñado en doc 11 §2 | Sprint 4 / Fase 3 | Ídem |
| Terraform, ALB, ASG, RDS, ElastiCache, ECR | Especificado en doc 03 y doc 04 §4 | T-12, Sprint 5 | «Infraestructura finalizada» se entrega como entorno reproducible + CI; el despliegue es Fase 3 |
| Simulacros de restauración y alta disponibilidad | Procedimientos en doc 03 §6.4 y doc 11 §1 | T-15, T-17, Sprint 5 | Requieren infraestructura real |
| Multitenencia | ADR-007, escalón T2 del doc 13 | Segundo cliente firmado | Fuera de alcance por completo, no diferida por tiempo |
| Pruebas psicométricas (HU-13) | Diseñado en doc 08 §3 | F-12 si hay margen, si no Fase 3 | Prioridad «Debería» y bloqueada por la decisión pendiente DP-02 |

---

## 8. Riesgos de esta fase

| Riesgo | Probabilidad | Mitigación |
|---|---|---|
| **El alcance no cabe en 12 días** | Alta | **Línea de corte en F-09**: al cerrarla, el enunciado está cumplido. Se recorta desde F-11 hacia atrás, nunca desde F-01 |
| El andamiaje hexagonal de 7 contextos consume días que no van a funcionalidad | Media | F-01 crea la estructura vacía una sola vez; los contextos se llenan solo cuando su fase llega |
| El cifrado y la inmutabilidad de F-10 se alargan | Media | Es la primera fase después de la línea de corte, precisamente para poder sacrificarla |
| Una regla RN-xx mal interpretada se propaga | Baja | Las políticas son objetos parametrizables probados sin base de datos (F-05); corregir una regla es cambiar una fila |
| Deptrac llega tarde y el dominio ya está acoplado | Baja | Va en F-01, con prueba deliberada de que rompe la construcción |
| Las decisiones pendientes DP-03 y DP-04 no llegan | Media | **Bloquean F-06**: sin horario de atención confirmado no hay calendario. Se solicitan antes del 12 de septiembre |
| Desconocimiento del código generado por agentes | Baja | Regla del doc 12 §4.4, declaración en cada commit y revisión línea por línea |

**El primero es el riesgo real, y está aceptado a conciencia.** El plan comprime 154 puntos
—doce semanas de la estimación original— en doce días. Lo que lo hace viable es que el diseño
ya está decidido y escrito: no hay que pensar la arquitectura mientras se programa, solo
ejecutarla.
