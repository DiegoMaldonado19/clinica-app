# BPMS Clínica Psicológica

Sistema de gestión de procesos para una clínica de psicología de una sola profesional.
El diseño completo vive en `../Entregable_2_Final/`; el plan de ejecución en `docs/fase-2/`.

## Arquitectura — no negociable

- El dominio vive en `src/<Contexto>/Domain/` y **NO importa `Illuminate\*`**. Deptrac lo verifica.
- Laravel es un adaptador. La lógica de negocio nunca va en modelos ni en controladores.
- La regla de dependencia: `Infrastructure → Application → Domain`. Nunca al revés.
- Las reglas RN-01 a RN-20 son **parámetros en `business_rule_settings`**, no constantes en código.
- Los contextos se comunican **solo por eventos de dominio**. Cero uniones SQL entre ellos.

## Reglas de datos

- Dinero: `INT` de centavos más código de moneda. **Nunca `FLOAT`**.
- Fechas: **UTC** en la base; `America/Guatemala` solo en presentación.
- Claves primarias: UUID v7 (`Str::uuid7()`) en entidades de dominio, `BIGINT` en bitácoras y catálogos.
- Claves foráneas con `ON DELETE RESTRICT`. Nada clínico ni financiero se borra en cascada.
- **NO existe `tenant_id` en esta fase** (ADR-007). No lo añadas.

## Caché — ADR-008

> Si perder Redis pierde algo, no va en Redis.

- Sesiones y colas: driver `database`.
- Se cachea: agregados del tablero, catálogos, tarifas, reglas, disponibilidad (horarios, **no personas**).
- **NUNCA se cachea contenido clínico descifrado**, ni la ficha del paciente, ni resultados de pruebas.

## Nomenclatura

- **Identificadores de código en inglés**, como los diagramas de clases del doc 08:
  `Appointment`, `TieredCancellationPolicy`, `CancellationOutcome`.
- Documentación y comentarios en español. Comentarios escasos: el código se autoexplica.
- PSR-12, aplicado por Pint. No pelees con el formateador.

## Prohibiciones absolutas

- Ningún dato real de paciente en un prompt. **Solo datos sintéticos.**
- No generar la lógica de cifrado ni de control de acceso: requiere revisión humana deliberada.
- No modificar la máquina de estados de la cita sin decisión explícita.
- No añadir dependencias sin justificarlo en el Pull Request.
- No relajar una verificación de la tubería para que pase código generado. Si Deptrac rechaza
  el código, el código está mal; no la regla.

## Antes de dar por terminado

```bash
vendor/bin/pint && \
vendor/bin/phpstan analyse --memory-limit=1G && \
vendor/bin/deptrac analyse --fail-on-uncovered && \
vendor/bin/pest
```

## Entorno

```bash
docker compose up -d                                    # 10 servicios
docker compose exec app php artisan migrate --seed
```

Landing `http://localhost` · panel `/admin` · correo `:8025` · MinIO `:9001` · Vite `:5173`.

La recarga en caliente está activa: editar un `.php` surte efecto en la siguiente petición y
Vite recarga el front solo. **No reconstruyas la imagen para ver un cambio.**
