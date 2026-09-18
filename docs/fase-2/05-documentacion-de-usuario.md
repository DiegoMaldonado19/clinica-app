# 05 — Documentación de usuario

> **Documento 5 de 5** · Fase 2 · Audiencia: la clínica (psicóloga y recepción) y evaluación
> Qué ve y qué puede hacer cada perfil. El paso a paso está en el manual (doc 04).

---

## 1. Los tres perfiles

| | Paciente | Recepción | Psicóloga (administradora) |
|---|---|---|---|
| **Dónde entra** | Landing (`/`) y **Mi portal** (`/portal`) | Panel (`/admin`) | Panel (`/admin`) |
| **Cierre por inactividad** | 2 horas | 8 horas | 8 horas |
| **Cómo obtiene su cuenta** | Automática, al pedir su primera cita | La crea la psicóloga | Sembrada al instalar |

Un paciente **no puede entrar al panel**, y el personal no entra al portal. Cada perfil tiene
su propio ingreso.

## 2. Qué puede hacer cada uno

Los 32 permisos atómicos del doc 05 §4.2, agrupados por lo que la persona hace.

| Acción | Paciente | Recepción | Psicóloga |
|---|:---:|:---:|:---:|
| Pedir una cita por la web | ✅ | — | — |
| Ver sus citas y pagar | ✅ solo las propias | — | — |
| Cancelar una cita, con el cargo a la vista | ✅ solo las propias | ✅ | ✅ |
| Aprobar o rechazar solicitudes | — | ✅ | ✅ |
| Agendar por teléfono, incluso el mismo día | — | ✅ | ✅ |
| Conciliar pagos | — | ✅ | ✅ |
| Registrar llegada e inasistencia | — | ✅ | ✅ |
| Dar de alta pacientes y ver su ficha administrativa | — | ✅ | ✅ |
| **Leer y escribir notas clínicas (SOAP)** | — | ❌ | ✅ |
| Exonerar un cargo | — | ❌ | ✅ |
| Bloquear la agenda | — | ❌ | ✅ |
| Cambiar tarifas, plazos y políticas | — | ❌ | ✅ |
| Consultar la bitácora de accesos | — | ❌ | ✅ |
| Crear cuentas de personal | — | ❌ | ✅ |

**La separación que no se relaja:** recepción ve la **ficha administrativa** (motivo de
consulta reportado, quién refirió, contacto de emergencia), pero **nunca el contenido
clínico**. Si recepción intenta abrir una nota, recibe «acceso denegado» y el intento queda en
la bitácora.

## 3. Qué ve cada uno

### Paciente

- **Landing**: perfil de la psicóloga, servicios y tarifas vigentes, cómo agendar y
  preguntas frecuentes. La política de cancelación mostrada es siempre la vigente.
- **Agendar**: el wizard de tres pasos (servicio → fecha y hora → datos y política) y la
  confirmación.
- **Mi portal**:
  - la tarjeta de pago pendiente, con cuenta regresiva
  - el crédito a favor, si existe
  - sus citas, con estado en palabras simples («Pago pendiente», «Revisando tu pago», «Confirmada»…)
  - las acciones que corresponden a cada una

  **Ningún dato clínico aparece en el portal.**

### Recepción

| Menú | Para qué |
|---|---|
| Bandeja de aprobaciones | Solicitudes ordenadas por tiempo restante, con semáforo |
| Citas | Todas las citas por estado; detalle con historial; agendar por teléfono |
| Conciliación de pagos | Comprobantes por revisar, lado a lado con los datos |
| Pacientes | Ficha del paciente y ficha administrativa |

### Psicóloga

Todo lo de recepción, y además:

| Menú | Para qué |
|---|---|
| Nota de la sesión (desde la cita) | Documentar, sellar y enmendar la nota SOAP |
| Bloqueos de agenda | Vacaciones y ausencias, resolviendo antes cada cita afectada |
| Servicios y tarifas | Programar tarifas nuevas con fecha de vigencia |
| Plazos y políticas | Los parámetros RN-01…RN-20, con historial |
| Bitácora | Quién accedió a qué, cuándo y desde dónde |
| Usuarios | Cuentas de personal y de pacientes |

## 4. Los estados de una cita

Lo que ve el personal y lo que ve el paciente.

| Estado (panel) | El paciente ve | Qué sigue |
|---|---|---|
| Solicitada | En revisión | Recepción aprueba o rechaza; si nadie responde, vence |
| Confirmada, pendiente de pago | Pago pendiente | El paciente sube su comprobante o elige efectivo |
| Pago en revisión | Revisando tu pago | Recepción concilia |
| Pago en caja | Pagas al llegar | Se cobra al registrar la llegada |
| Agendada | Confirmada | Llegada, inasistencia o cancelación |
| En curso | En sesión | La psicóloga sella la nota |
| Atendida | Atendida | — |
| Rechazada | No confirmada | — |
| Expirada | Venció sin respuesta | El horario ya se liberó |
| Cancelada por impago | Cancelada | El horario ya se liberó |
| Cancelada sin cargo | Cancelada | Crédito, si estaba pagada |
| Cancelada con recargo | Cancelada | Cargo del 50 % o del 100 % |
| No se presentó | No asististe | 100 %; el horario no se libera |

Todo estado se muestra con **color e icono**, nunca solo con color.

## 5. Lo que el sistema hace solo

| Qué | Cuándo |
|---|---|
| Vencer una solicitud sin respuesta y liberar el horario | A las 24 h, o 72 h si llegó en fin de semana |
| Cancelar una cita sin pago aprobado | 3 horas antes de la cita. Un comprobante en revisión **no** la salva; el pago en caja sí |
| Enviar recordatorios | 24 h y 2 h antes, solo si la cita sigue vigente |
| Recuperar lo que se haya quedado atrás | Cada 15 minutos, si el procesador de tareas estuvo detenido |
| Diferir correos | Entre las 21:00 y las 07:00 |

Nada de esto requiere intervención. Todos los plazos se cambian en **Plazos y políticas**.

## 6. Secuencia de pantallas

| Recorrido | Pantallas |
|---|---|
| Visitante agenda | Landing → Agendar (servicio → calendario → datos y política) → Recibimos tu solicitud |
| Paciente paga | Correo de aprobación → Mi portal → Registrar mi pago → Revisando tu pago → Confirmada |
| Recepción aprueba | Bandeja → Detalle → Aprobar |
| Recepción concilia | Conciliación de pagos → Conciliar → Aprobar pago |
| Psicóloga atiende | Citas → Registrar llegada → Nota de la sesión → Sellar nota |
| Psicóloga se ausenta | Bloqueos de agenda → (citas afectadas) → Cancelar por indisponibilidad → Bloquear agenda |

La secuencia completa con los mockups está en `Entregable_2_Final/10` §4.
