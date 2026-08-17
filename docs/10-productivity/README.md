# Productividad e IA

Esta carpeta contiene documentación sobre prompts, plantillas, métricas y seguimiento de productividad asistida por IA en Xestify.

---

## Flujo obligatorio de registro

1. Al completar cada story, actualiza inmediatamente:
	- [sesion.md](sesion.md): Marca la story como completada, añade commit hash, actualiza "Última actualización" y "Próxima story".
	- [productividad.md](productividad.md): Añade entrada con fecha, estimado sin IA, tiempo real con IA, aceleración, qué hizo la IA, iteraciones y decisión manual.
	- [prompts.md](prompts.md): Añade el prompt exacto usado, resultado e iteraciones.
2. No esperar a batch ni dejar para el final: la actualización debe ser inmediata y detallada.

---

## Uso de los archivos

- **ia-productivity-template.md**: Plantilla para análisis académico, copiar y rellenar por tarea.
- **productividad.md**: Registro real de métricas y aceleración por story.
- **prompts.md**: Historial de prompts exactos, resultados e iteraciones.
- **sesion.md**: Estado de avance, stories completadas y próximas, trazabilidad de cambios.

---

## Skills locales (Claude Code)

Las skills de `skills/` son la parte ejecutable de la productividad asistida
por IA en Xestify: automatizan tareas repetitivas en vez de repetir el mismo
prompt largo cada vez. Índice completo (descripción y frase que dispara cada
una) en [`skills/README.md`](../../skills/README.md); convención estructural
en `AGENTS.md`, sección "Skills locales del proyecto".

| Skill | Qué hace |
|---|---|
| [`audit-technical-debt`](../../skills/audit-technical-debt/SKILL.md) | Genera auditorías de deuda técnica (completa/incremental/acotada) |
| [`fix-technical-debt`](../../skills/fix-technical-debt/SKILL.md) | Corrige hallazgos ya auditados, fase a fase |
| [`review-sonarqube-clean-code`](../../skills/review-sonarqube-clean-code/SKILL.md) | Revisa/corrige findings de SonarQube for IDE/SonarLint |
| [`seed-business-data`](../../skills/seed-business-data/SKILL.md) | Siembra datos de negocio de demostración (STORY 10.6) |

---

## Recomendaciones para análisis académico

- Documentar tanto éxitos como fallos o iteraciones necesarias.
- Analizar qué aceleró más la IA y qué requirió intervención manual.
- Mantener trazabilidad entre story, commit y prompt usado.

---

Consulta estos documentos para mejorar y analizar la productividad con IA en el proyecto.