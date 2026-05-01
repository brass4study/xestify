# Sistema de Actualizaciones

## Objetivo

Actualizar plugins de forma segura y trazable, con opcion manual y automatica.

## Modos

1. Manual
- Usuario abre panel de plugins
- Revisa versiones disponibles
- Ejecuta descarga y aplicacion

2. Automatico
- Tarea programada consulta marketplace cada intervalo
- Descarga paquetes pendientes
- Aplica bajo politica definida

## Flujo seguro

1. Consultar endpoint de versiones
2. Descargar paquete a staging
3. Verificar integridad (checksum)
4. Validar compatibilidad con Core
5. Ejecutar migracion de schema y datos
6. Activar nueva version
7. Registrar log de actualizacion

## Rollback

- Disponible por plugin
- Restaura version anterior
- Restaura metadata previa
- Revierte migraciones reversibles

## Politicas recomendadas

- No auto-aplicar updates major
- Ventana de mantenimiento fuera de horario
- Backup previo obligatorio para cambios incompatibles

## Endpoints internos sugeridos

- GET /api/v1/updates/check
- POST /api/v1/updates/download/{plugin_slug}
- POST /api/v1/updates/apply/{plugin_slug}
- POST /api/v1/updates/rollback/{plugin_slug}

## Logging minimo

- plugin_slug
- from_version
- to_version
- status
- duration_ms
- error_message (si aplica)
