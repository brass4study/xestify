# Xestify — Instrucciones de Copilot

## Inicio de sesión obligatorio

Al comenzar cualquier conversación sobre este proyecto, lee **siempre** el archivo
[docs/ia/sesion.md](../docs/ia/sesion.md) antes de responder cualquier pregunta o
realizar cualquier acción. Ese archivo contiene el estado actual del proyecto, las
stories completadas, la story en progreso y las convenciones establecidas.

Después de leerlo, confirma brevemente en qué punto está el proyecto y qué toca hacer a continuación.

## Convenciones del proyecto

- **Sin Composer ni autoload PSR-4** — usar únicamente el autoloader manual de `bootstrap.php`
- **Sin frameworks PHP** — PHP 8.1+ nativo con Container/Router propios
- **Sin build step en frontend** — Vanilla JS ES2020+, sin npm, sin bundlers
- **Tests standalone** — scripts PHP puros sin PHPUnit (`php tests/unit/FooTest.php`)
- **Namespace raíz:** `Xestify\` → mapea a `backend/src/`

## Referencias clave

- Estado del proyecto: [docs/ia/sesion.md](../docs/ia/sesion.md)
- Backlog: [docs/mvp/backlog.md](../docs/mvp/backlog.md)
- Decisiones técnicas: [docs/mvp/decisiones-tecnicas.md](../docs/mvp/decisiones-tecnicas.md)
