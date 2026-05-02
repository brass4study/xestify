# Renderizado Dinamico en Frontend

## Objetivo

Construir UI de entidades sin hardcodear formularios por negocio.

## Flujo

1. Cargar schema de entidad
2. Traducir campos a componentes de UI
3. Cargar datos existentes
4. Aplicar extensiones (tabs, acciones)
5. Enviar payload al backend

## Componentes sugeridos

- DynamicForm
- DynamicTable
- DynamicTabs
- EntityDetail

## Mapeo de tipos

- string -> input text
- number -> input number
- boolean -> switch box
- date -> date picker
- select -> select
- object -> subform
- array -> repeater
- timestamp -> date time picker

## Estructura de schema (ejemplo)

```json
{
  "entity": "client",
  "version": "1.0.0",
  "fields": [
    {"name": "nombre", "type": "string", "required": true},
    {"name": "telefono", "type": "string", "required": true},
    {"name": "activo", "type": "boolean", "required": false}
  ]
}
```

## Carga de extensiones UI

- Backend expone hooks registerTabs y registerActions
- Frontend consulta extensiones activas por entidad
- Frontend monta componente remoto/local por plugin

## Validacion

- Validacion UX en cliente (rapida)
- Validacion final en backend (autoritativa)

## Criterios de calidad

- Sin bloqueos por campos desconocidos
- Mensajes de error por campo
- Compatibilidad movil escritorio
