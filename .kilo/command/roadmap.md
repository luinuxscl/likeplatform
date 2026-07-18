---
description: Estado y actualización del roadmap de monetización SaaS
---

# /roadmap — Gestión del roadmap

Subcomando: `$1` (status | update). Argumentos: `$ARGUMENTS`.

## Localizar el roadmap
El glob tool excluye directorios ocultos (`.kilo/`, `.git/`, etc.), así que no lo busques ahí. Probá estos patrones en orden y usá el primer archivo que matchee (si hay más de uno, preferí el más reciente por mtime):
1. `**/roadmap*.md` (ubicación canónica recomendada: `docs/roadmap/saas-monetization.md` o similar)
2. `**/plans/*.md` (carpeta `plans/` no oculta)
3. `**/docs/roadmap/*.md` (subcarpeta dedicada)

Fallback con shell (si el glob tool falla en todo): `find . -type f -name "*.md" -not -path "./node_modules/*" -not -path "./vendor/*" -not -path "./.git/*" | xargs grep -l -i "roadmap\|fase 0\|fase 1" 2>/dev/null | head -5`. Si aparece algún candidato, usá el primero.

Si no hay ningún match ni con find, detenete y avisá al usuario sugiriendo crear el archivo en `docs/roadmap/`.

## Si $1 es "status" (o vacío)
1. Lee el archivo completo del roadmap.
2. Cruzá con `git log --oneline -10` y `git status` para detectar trabajo no reflejado.
3. Respondé en español con este formato (máx ~20 líneas, sin modificar archivos):
   - **Archivo del roadmap**: ruta usada.
   - **Fase actual**: primera fase sin cerrar.
   - **Última tarea cerrada**: ID + commit hash corto (si está anotado en el doc).
   - **Siguiente tarea**: ID, descripción de 1 línea, archivos afectados, criterio de aceptación.
   - **Resto de la fase**: lista compacta de IDs pendientes en orden.
   - **Notas de sesión anterior**: si existe una sección "Estado actual" al inicio del documento, resumila.
4. No modifiques archivos en este modo.

## Si $1 es "update"
1. Lee el archivo completo del roadmap.
2. Cruzá con `git log --oneline` desde la fecha del documento y `git status` para detectar trabajo nuevo.
3. Modificá el archivo (usá Edit, no Write, para preservar formato):
   - Marcá ✅ las tareas detectadas como completadas (commits, código o tests presentes).
   - Al inicio del documento, mantené o actualizá una sección `## Estado actual` con: fecha de hoy (ISO), última fase cerrada, commit hash corto, resultado de tests, siguiente tarea pendiente.
   - Si una tarea está parcialmente hecha, agregale una sub-nota `> En progreso: <qué falta>` sin marcarla ✅.
   - No reordenes fases ni reescribas tareas no tocadas.
4. Al final, mostra un resumen de los cambios: fases tocadas, tareas marcadas, líneas agregadas o modificadas.

## Nota
El roadmap se considera la fuente de verdad para retomar trabajo entre sesiones. Mantenelo actualizado al cierre de cada tarea.
