---
description: Andamiaje de una historia HU-xx desde su ficha en el Entregable 2
argument-hint: HU-04
---

Vas a preparar el andamiaje de la historia **$1**.

## Pasos

1. Lee la ficha de $1 en `../Entregable_2_Final/07-casos-de-uso-e-historias.md` §3 y sus
   criterios Gherkin. Lee las reglas `RN-xx` que menciona en
   `../Entregable_1_Final/04-procesos-to-be.md` §Reglas.
2. Identifica a qué contexto acotado pertenece y qué fase `F-xx` la construye
   (`docs/fase-2/01-fases-de-avance.md`).
3. Crea la rama: `git checkout -b feature/$1-descripcion-corta` desde `main`.
4. Invoca al subagente `generador-pruebas` con el Gherkin de $1. **Las pruebas se escriben
   antes que la implementación**, y desde los criterios, no desde el código.
5. Lista lo que hay que crear —comando, handler, cambios en el agregado, migración, adaptador,
   pantalla— y **para antes de implementar**. Espera confirmación.

## No hagas

- No decidas reglas de negocio. Si la ficha es ambigua, pregunta.
- No modifiques la máquina de estados de `Appointment` sin decisión explícita.
- No añadas dependencias.
