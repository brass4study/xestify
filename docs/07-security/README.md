# Seguridad y modelo local

Esta carpeta contiene la documentación sobre el modelo de seguridad local, estrategias de autenticación/autorización y mejores prácticas para desarrolladores de plugins y core.

---

## Resumen de controles y principios

- Zero trust entre plugins y menor privilegio
- Validación server-side obligatoria (nunca confiar solo en frontend)
- Auditoría de acciones críticas y registro de eventos
- Roles mínimos y permisos por entidad/acción
- Seguridad de datos: SQL parametrizado, validación de payload, soft delete, backup cifrado

---

## Recomendaciones para desarrolladores

- Nunca ejecutar lógica sensible en hooks sin validar origen y permisos
- Usar siempre validación de datos en backend (ver ejemplo en [plugins/persons/Hooks.php](../../plugins/persons/Hooks.php))
- Declarar dependencias y compatibilidad en manifest.json
- Evitar exponer endpoints de plugin sin autenticación
- Revisar logs y auditar acciones de plugins

---

## Ejemplos y referencia

- [modelo-seguridad-local.md](modelo-seguridad-local.md): Modelo de seguridad local y recomendaciones
- [plugins/persons/Hooks.php](../../plugins/persons/Hooks.php): Validación de unicidad y control de acceso
- [plugins/comments/Hooks.php](../../plugins/comments/Hooks.php): Inyección controlada de UI