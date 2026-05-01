# Modelo de Seguridad Local

## Objetivo

Reducir superficie de ataque manteniendo versatilidad de plugins.

## Principios

- Zero trust entre plugins
- Menor privilegio
- Validacion server-side obligatoria
- Auditoria de acciones criticas

## Controles de autenticacion

- Login local con hash fuerte de password
- Sesion o token con expiracion
- Rotacion de credenciales admin

## Controles de autorizacion

- Roles minimos: admin, operador, lectura
- Permisos por entidad y accion
- Permisos especiales para gestion de plugins

## Seguridad de plugins

- Solo instalar desde fuentes confiables
- Verificar checksum y metadatos
- Bloquear plugin con incompatibilidad declarada
- Sandbox logico para hooks

## Seguridad de datos

- Parametrizar todas las consultas SQL
- Validar payload contra schema_json
- Soft delete para trazabilidad
- Backup cifrado cuando salga del dispositivo

## Auditoria

Registrar eventos:

- Login y logout
- Cambios de permisos
- Instalacion y actualizacion de plugins
- Acciones de borrado y rollback

## Incidentes

- Desactivar plugin comprometido
- Forzar cierre de sesiones
- Restaurar backup verificado
- Revisar logs antes de reactivar
