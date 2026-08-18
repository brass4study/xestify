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

## Superficie web y herramientas CLI

La aplicación se sirve con el `DocumentRoot` en la raíz del repositorio, así
que `backend/.env`, los scripts de `tools/` y las clases PHP de `plugins/`
están físicamente dentro del árbol servible. Capas que lo protegen (detalle y
verificación en la sección "Modelo de seguridad de la instalación" de
[INSTALL.md](../../INSTALL.md)):

- `tools/setup/bootstrap.php` rechaza cualquier SAPI distinto de `cli` (403);
  todo `tools/**/*.php` lo requiere como primera sentencia y
  `backend/tests/unit/ToolsCliGuardTest.php` lo vigila.
- `.htaccess` raíz: solo reescribe rutas públicas y devuelve 403 (case-insensitive)
  para `backend/`, `docs/`, `frontend/`, `skills/`, `tools/`, `var/`, `.md`
  raíz y dotfiles.
- `.htaccess` por directorio: `Require all denied` en `tools/`, `backend/`,
  `docs/`, `skills/`; `backend/public/` re-permite el entrypoint; `plugins/`
  deniega `*.php` y `*.json`.
- Sin secretos en flags de CLI (prompt o `XESTIFY_*`), `backend/.env` con
  permisos `0640`, usuarios seed con contraseña conocida solo en `APP_DEBUG=true`
  (y bloqueados en login fuera de debug); el primer administrador real lo crea
  `tools/setup/install.php` / `create-admin-user.php`.
- `tools/setup/check-install.php --url=` comprueba la exposición real tras
  desplegar.

Deuda conocida: `DocumentRoot` en subcarpeta pública (`public/`) como solución
definitiva — DECISION 11 en
[docs/09-history/decisiones-tecnicas.md](../09-history/decisiones-tecnicas.md).

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