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

## Calidad de código (reglas SonarQube)

Aplica estas reglas **siempre** al generar o modificar código PHP o JavaScript. No esperes a que SonarQube lo detecte.

### PHP
- **Complejidad ciclomática ≤ 10** por función — extrae métodos privados si superas ese límite
- **Comparación estricta** — usar siempre `===` y `!==`, nunca `==` ni `!=`
- **Sin `@` para suprimir errores** — maneja las condiciones explícitamente
- **Sin variables no usadas** — elimínalas antes de terminar
- **Sin bloques `catch` vacíos** — logea o relanza siempre la excepción
- **Constantes en UPPER_SNAKE_CASE** — nunca magic numbers sueltos
- **Parámetros ≤ 5 por función** — agrupa en array u objeto si necesitas más
- **Líneas ≤ 120 caracteres**
- **Sin `var_dump`, `print_r`, `die`, `exit` en código de producción**
- **SQL siempre con parámetros preparados (PDO)** — nunca interpolación de variables en queries
- **Sin `eval()`**
- **Declara tipos en firmas** (`string $foo`, `: bool`, etc.) — el proyecto ya usa `declare(strict_types=1)`

### JavaScript
- **`const` por defecto**, `let` cuando necesites reasignar, nunca `var`
- **Sin `console.log` en código de producción**
- **Comparación estricta** — siempre `===`
- **Sin funciones anónimas inline de más de 5 líneas** — extrae a función nombrada
- **Sin `innerHTML` con datos de usuario** — usar `textContent` o sanitizar

## Referencias clave

- Estado del proyecto: [docs/ia/sesion.md](../docs/ia/sesion.md)
- Backlog: [docs/mvp/backlog.md](../docs/mvp/backlog.md)
- Decisiones técnicas: [docs/mvp/decisiones-tecnicas.md](../docs/mvp/decisiones-tecnicas.md)
