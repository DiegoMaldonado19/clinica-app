# BPMS · Clínica de psicología

Sistema de gestión de procesos para una clínica de psicología de una sola profesional:
agendamiento en línea con aprobación por SLA, pagos con conciliación humana, cancelación con
política escalonada, expediente clínico con nota SOAP inmutable y bitácora de accesos.

Laravel 13 · Filament 5 · Livewire 4 · MariaDB 11.4 · Redis 7 · MinIO (S3) · Mailpit.
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

### Cuentas de demostración

Solo con `APP_ENV=local`. La contraseña de las tres es `password`.

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

## Estructura

```
src/<Contexto>/Domain          reglas de negocio, sin Illuminate\* (Deptrac lo verifica)
src/<Contexto>/Application     orquestación de los casos de uso
src/<Contexto>/Infrastructure  adaptadores: base de datos, colas, correo, candados
app/                           Laravel: paneles Filament, Livewire, modelos de lectura
```

Contextos: `Scheduling`, `Billing`, `ClinicalRecords`, `Identity`, `Notifications`, `Shared`.
Se comunican solo por eventos de dominio.

## Documentación

| Documento | Para |
|---|---|
| [`docs/fase-2/01`](docs/fase-2/01-fases-de-avance.md) | Plan por fases F-01…F-12 |
| [`docs/fase-2/02`](docs/fase-2/02-configuracion-de-infraestructura.md) | Infraestructura local y CI |
| [`docs/fase-2/03`](docs/fase-2/03-requisitos-de-la-fase.md) | Qué exige la fase y dónde quedó cada punto |
| [`docs/fase-2/04`](docs/fase-2/04-manual-de-usuario.md) | Manual de usuario |
| [`docs/fase-2/05`](docs/fase-2/05-documentacion-de-usuario.md) | Qué ve y qué puede hacer cada perfil |
