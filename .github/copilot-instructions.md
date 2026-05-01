# Xestify — Instrucciones de Copilot

## Inicio de sesión obligatorio

Al comenzar cualquier conversación sobre este proyecto, lee **siempre** el archivo
[docs/ia/sesion.md](../docs/ia/sesion.md) antes de responder cualquier pregunta o
realizar cualquier acción. Ese archivo contiene el estado actual del proyecto, las
stories completadas, la story en progreso y las convenciones establecidas.

Después de leerlo, confirma brevemente en qué punto está el proyecto y qué toca hacer a continuación.

---

## ⛔ REGLA OBLIGATORIA: Actualizar docs/ia antes de cada commit

**Esta regla es mandatoria. No hay excepciones. Nunca omitirla.**

Antes de ejecutar `git commit` para cualquier story completada, DEBES actualizar los tres archivos siguientes **en ese orden**:

1. **`docs/ia/sesion.md`** — Marcar la story como completada, añadir commit hash, actualizar "Última actualización" y "Próxima story".
2. **`docs/ia/productividad.md`** — Añadir entrada de la story con: fecha, estimado sin IA, tiempo real con IA, aceleración %, qué hizo la IA, iteraciones, decisión manual.
3. **`docs/ia/prompts.md`** — Añadir el prompt exacto que se usó para la story, resultado e iteraciones.

**Flujo obligatorio para cada story:**
```
1. Implementar código + tests
2. Verificar que los tests pasan
3. Actualizar docs/ia/sesion.md
4. Actualizar docs/ia/productividad.md
5. Actualizar docs/ia/prompts.md
6. git add -A && git commit con el formato obligatorio (ver abajo)
```

**Formato de commit obligatorio:**
- Stories individuales: primera línea `feat: STORY X.X - [Título exacto del backlog]`, seguida de línea en blanco y cuerpo con lista breve de los cambios realizados (archivos creados/modificados + resumen de qué hace cada uno).
- EPICs completos (cuando se commitean en bloque): `feat: EPIC X - [Título del EPIC]` con el mismo cuerpo descriptivo.
- Nunca usar el formato `feat(scope):` — siempre `feat:` sin paréntesis

**Ejemplo de mensaje de commit correcto:**
```
feat: STORY 5.1 - Frontend - Crear página Login

- frontend/src/js/pages/Login.js: nueva página con form email/password, validación, POST /auth/login y callback onSuccess
- frontend/src/js/main.js: flujo bootstrap con render condicional Login/Dashboard y logout
- frontend/src/css/main.css: estilos base login y shell
- frontend/tests/LoginTest.html: 5 tests (render, validación, éxito, error)
- tools/dev/frontend-router.php: proxy local para servir frontend y API en mismo origen
```

---

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
- **Llaves obligatorias en todo bloque condicional** (php:S121) — nunca `if (...) return;` ni `if (...) continue;` en una sola línea; usar siempre llaves `{}`
- **Sin código muerto tras `return`/`exit`/`throw`** (php:S1763) — eliminar cualquier sentencia inalcanzable
- **Newline al final de cada archivo** (php:S113) — el último carácter del archivo debe ser `\n`
- **Sin imports `use` no usados** (php:S1128) — revisa que cada `use` se use en el archivo; dentro del mismo namespace no se necesita `use`
- **Comprobar retorno de funciones que pueden devolver null** (php:S4797) — `preg_replace`, `json_encode`, `file_get_contents`, etc. devuelven `null|false`; usar `?? fallback` o comprobar antes de usar
- **Sin instanciación dinámica con variable** (php:S5992) — evitar `new $class()` y `$obj->$method()`; usar un mapa explícito o factory conocido
- **Cast explícito antes de funciones con tipo estricto** (php:S4423) — si una función devuelve `mixed`, hacer `(string)`, `(int)`, etc. antes de pasarla a funciones que esperan un tipo concreto
- **Sin variables globales** (php:S2188) — nunca `global $var`; encapsular estado en clase o pasar como parámetro
- **Sin código duplicado entre archivos** (php:S1192) — extraer a función o clase compartida
- **Nombres de funciones y métodos en camelCase** (php:S100) — nunca snake_case; `assertEquals` no `assert_equals`
- **Sin lanzar excepciones genéricas** (php:S112) — nunca `throw new RuntimeException` ni `throw new Exception`; usar `\AssertionError` en helpers de test, excepciones de dominio en producción
- **Sin código comentado** (php:S125) — eliminar bloques de código comentado; el historial de git conserva el código antiguo
- **Sin parámetros de función no usados** (php:S1172) — eliminar parámetros que no se usen en el cuerpo; si la firma es fija por interfaz, suprimir con `// NOSONAR`
- **Sin bloques de código vacíos** (php:S108) — `function () {}` dispara el finding; usar `fn() => null` para handlers no-op o añadir un `return;` con comentario
- **Usar clases de caracteres abreviadas en regex** (php:S4784) — `\w` en lugar de `[a-zA-Z0-9_]`, `\d` en lugar de `[0-9]`, `\s` en lugar de `[ \t\n\r]`

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
