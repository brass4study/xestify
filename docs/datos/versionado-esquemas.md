# Versionado de Esquemas

## Objetivo

Permitir evolucion de campos por plugin sin romper registros existentes.

## Conceptos

- schema_version: version declarada en metadata
- migration_step: unidad de cambio incremental
- compatibility_mode: lectura de versiones antiguas

## Reglas

1. Nunca borrar campos en caliente sin migracion
2. Cambios incompatibles requieren version major
3. Cada update de plugin debe traer plan de migracion
4. Se mantiene historial de schema_json por version

## Estrategia de migracion

1. Descargar plugin nuevo en staging local
2. Validar compatibilidad con Core
3. Ejecutar pre-check sobre entity_data
4. Aplicar migraciones (estructura logica y datos)
5. Marcar schema_version vigente
6. Habilitar plugin actualizado

## Tabla sugerida: plugin_migrations

- id (uuid)
- plugin_slug (text)
- from_version (text)
- to_version (text)
- status (text)
- executed_at (timestamp)
- log (text)

## Politica de rollback

- Si falla una migracion: desactivar version nueva
- Restaurar version previa del plugin
- Restaurar snapshot de metadata
- Si aplica, restaurar backup transaccional de datos

## Ejemplo de cambio compatible

- Se agrega campo opcional "notas" tipo string
- No se requiere reescritura masiva de registros

## Ejemplo de cambio incompatible

- Campo "graduacion" pasa de string a objeto complejo
- Requiere script de transformacion de contenido JSONB
