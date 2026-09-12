---
description: Revisión previa al Pull Request, con las verificaciones y los tres subagentes
---

Revisión previa al Pull Request de la rama actual.

## 1. Verificaciones automáticas

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G --no-progress
vendor/bin/deptrac analyse --no-progress --fail-on-uncovered
vendor/bin/pest
```

Si alguna falla, repórtalo y detente. **No relajes una regla para que pase el código.**

## 2. Subagentes

Lanza `revisor-arquitectura` y `revisor-consultas` sobre `git diff main...HEAD`.

## 3. Revisión manual

- ¿Hay algún secreto en el diff?
- ¿Alguna migración es destructiva o incompatible hacia atrás?
- ¿Se cacheó algo que no puede perderse? (ADR-008)
- ¿Las pruebas cubren los casos frontera, no solo el camino feliz?
- ¿Se puede explicar cada línea generada por un agente? Si no, no se fusiona.

## 4. Salida

Rellena la plantilla de `.github/pull_request_template.md` con la trazabilidad real
(fase, HU, RF, RN, `to-be`) y el apartado de aporte de agentes de IA.
