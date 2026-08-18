# Guia de Contribucion

Este documento centraliza las convenciones de calidad de codigo y verificacion
para Xestify. `AGENTS.md` define el comportamiento de los agentes; este archivo
define los estandares tecnicos que deben aplicar personas, agentes y herramientas.

## Principios generales

- Mantener los cambios acotados al objetivo de la tarea.
- Preferir los patrones existentes del proyecto antes de introducir nuevas
  abstracciones.
- Evitar refactors no relacionados con la tarea en curso.
- Añadir o ajustar tests cuando se corrija una regresion o se toque un flujo con
  riesgo funcional.
- No dejar codigo de depuracion, comentarios obsoletos ni archivos generados de
  runtime en control de versiones.

## Convenciones del proyecto

- Sin Composer ni autoload PSR-4; usar unicamente el autoloader manual de
  `bootstrap.php`.
- Sin frameworks PHP; PHP 8.1+ nativo con `Container` y `Router` propios.
- Sin build step en frontend; Vanilla JS ES2020+, sin npm, sin bundlers.
- Tests standalone; scripts PHP puros sin PHPUnit:
  `php tests/unit/FooTest.php`. En la estructura actual del repo, usar
  `php backend/tests/unit/FooTest.php`.
- Namespace raiz: `Xestify\` mapea a `backend/src/`.
- Para scripts sueltos de verificacion puntual o utilidades ad-hoc de sesion
  (servir ficheros estaticos, probar un endpoint, inspeccionar datos, un
  servidor HTTP de usar y tirar) usar PHP CLI (`php -r`, `php -S`, scripts en
  `tools/`), no Python ni Node: PHP es el lenguaje real del stack y evita
  introducir un runtime ajeno solo para una comprobacion de una sesion. Las
  excepciones ya asumidas son `node --check` para sintaxis JS (ver
  Verificacion) y `frontend/tests/e2e/` (Playwright, dependencia ya
  instalada). Esto no aplica a servir la SPA o los tests HTML de
  `frontend/tests/integration/`: eso pasa siempre por el Apache+PHP
  same-origin real, nunca por un servidor alternativo (ver "Desarrollo
  local" en `AGENTS.md`).

## Herramientas CLI (`tools/`)

Los scripts de `tools/` viajan en el artefacto de release dentro del
`DocumentRoot`, asi que deben ser imposibles de ejecutar por web:

- `tools/setup/bootstrap.php` empieza con el guard `if (PHP_SAPI !== 'cli')`
  (403 + exit) antes de cualquier otra sentencia.
- Todo `tools/**/*.php` requiere ese bootstrap como **primera sentencia** tras
  `declare(strict_types=1)`: `require_once __DIR__ . '/bootstrap.php';` en
  `tools/setup/`, `require_once dirname(__DIR__) . '/setup/bootstrap.php';` en
  `tools/dev/` u otras subcarpetas. Los helpers compartidos de consola viven en
  `tools/setup/cli-helpers.php` (prompts, secretos, parseo de opciones).
- Nunca aceptar contrasenas ni secretos como flags de linea de comandos:
  prompt (oculto cuando el terminal lo permite) o variables de entorno
  `XESTIFY_*`.
- `tools/setup/` es lo que un operador necesita (instalacion, admin, seeds,
  sync, comprobacion); `tools/dev/` es QA/desarrollo y queda fuera del ZIP de
  release (`skills/publish-release`).
- `backend/tests/unit/ToolsCliGuardTest.php` verifica estas reglas de forma
  estatica; falla si se añade un script sin el guard o con un flag de
  contrasena.

## Verificacion

- Al tocar backend, ejecutar la suite relevante.
- Si el cambio es transversal, ejecutar:

```powershell
php backend\tests\run.php all
```

- Al tocar frontend sin runner automatizado, verificar al menos sintaxis cuando
  aplique:

```powershell
node --check frontend\src\js\main.js
node --check frontend\src\js\pages\EntityEdit.js
```

- Documentar en la respuesta cualquier test que no se haya podido ejecutar.
- Listado completo de los tests existentes (qué verifica cada uno, cómo
  ejecutarlos): `docs/06-backend/testing.md` (backend) y
  `docs/05-frontend/testing.md` (frontend, integración + E2E).

## SonarQube for IDE

- La extension de VSCode publica sus hallazgos como diagnostics de VSCode.
- Para exportar exactamente los hallazgos Sonar visibles en Problems, usar la
  extension local `skills/review-sonarqube-clean-code/assets/vscode-extension`.
- El reporte se genera en `var/reports/sonarlint-problems.json`.
- Los agentes pueden pedir la exportacion ejecutando:

```powershell
.\skills\review-sonarqube-clean-code\scripts\export-sonarlint-problems.ps1
```

- Para forzar un analisis completo de archivos `php`, `js` y `html` del
  workspace antes de exportar, ejecutar:

```powershell
.\skills\review-sonarqube-clean-code\scripts\analyze-sonarlint-workspace.ps1
```

- El reporte depende del estado actual de VSCode: si SonarQube for IDE no ha
  analizado un archivo, no habra diagnostics que exportar para ese archivo.

## Calidad de codigo PHP

Aplicar estas reglas siempre al generar o modificar codigo PHP. No esperar a que
SonarQube lo detecte.

- Complejidad ciclomatica <= 10 por funcion; extraer metodos privados si se supera.
- Comparacion estricta: usar siempre `===` y `!==`, nunca `==` ni `!=`.
- Sin `@` para suprimir errores; manejar las condiciones explicitamente.
- Sin variables no usadas; eliminarlas antes de terminar.
- Sin bloques `catch` vacios; logear o relanzar siempre la excepcion.
- Constantes en `UPPER_SNAKE_CASE`; evitar magic numbers sueltos.
- Parametros <= 5 por funcion; agrupar en array u objeto si hacen falta mas.
- Lineas <= 120 caracteres.
- Sin `var_dump`, `print_r`, `die`, `exit` en codigo de produccion.
- SQL siempre con parametros preparados PDO; nunca interpolar variables.
- Sin `eval()`.
- Declarar tipos en firmas (`string $foo`, `: bool`, etc.); el proyecto ya usa
  `declare(strict_types=1)`.
- Llaves obligatorias en todo bloque condicional; nunca `if (...) return;` ni
  `if (...) continue;` en una sola linea.
- Sin codigo muerto tras `return`, `exit` o `throw`.
- Newline al final de cada archivo.
- Sin imports `use` no usados.
- Comprobar retorno de funciones que pueden devolver `null` o `false`:
  `preg_replace`, `json_encode`, `file_get_contents`, etc.
- Sin instanciacion dinamica con variable; evitar `new $class()` y
  `$obj->$method()`. Usar un mapa explicito o factory conocido.
- Excepcion controlada: `PluginClassLoader` y `PluginHookRegistrar` pueden usar
  instanciacion o despacho dinamico porque cargan clases declaradas
  dinamicamente por los propios plugins (manifest/schema), un dato externo que
  no se conoce en tiempo de escritura del codigo. Esa excepcion debe quedar
  marcada con `// NOSONAR`, estar cubierta por tests y no extenderse a
  servicios, modelos, controladores ni logica de negocio. `Router` registra
  sus rutas mediante closures explicitas con el metodo literal en el codigo
  fuente (ver `config/routes.php`) y no necesita esta excepcion.
- Cast explicito antes de funciones con tipo estricto cuando el valor venga de
  una funcion que devuelve `mixed`.
- Sin variables globales; nunca `global $var`.
- Sin codigo duplicado entre archivos; extraer a funcion o clase compartida.
- Nombres de funciones y metodos en camelCase; nunca snake_case.
- Sin lanzar excepciones genericas en produccion; usar excepciones de dominio.
  En helpers de test, usar `\AssertionError` cuando aplique.
- Sin codigo comentado; el historial de git conserva el codigo antiguo.
- Sin parametros de funcion no usados; si la firma es fija por interfaz, suprimir
  con `// NOSONAR`.
- Sin bloques de codigo vacios; usar `fn() => null` para handlers no-op o añadir
  un `return;` con comentario.
- Usar clases de caracteres abreviadas en regex cuando aplique: `\w`, `\d`, `\s`.

## Calidad de codigo JavaScript

Aplicar estas reglas siempre al generar o modificar codigo JavaScript.

- `const` por defecto, `let` cuando haga falta reasignar, nunca `var`.
- Sin `console.log` en codigo de produccion.
- Comparacion estricta: usar siempre `===` y `!==`.
- Sin funciones anonimas inline de mas de 5 lineas; extraer a funcion nombrada.
- Sin `innerHTML` con datos de usuario; usar `textContent` o sanitizar.
- La UI de paginas, modulos y componentes compuestos debe crearse exclusivamente
  mediante `component.create` y los componentes registrados en
  `ComponentFactory.js`, unica entrada publica del sistema UI. Los nombres no
  registrados deben lanzar error; no usar tags personalizados como fallback.
  No construir controles, tablas, formularios ni
  estructuras visuales con `document.createElement`, `createElement`,
  `createComponentInstance` o HTML manual. Estas primitivas quedan reservadas a
  la infraestructura de bajo nivel de `BaseComponent` y `ComponentFactory`.
- Si falta una capacidad visual, ampliar la API del componente propietario o
  proponer y registrar un componente nuevo. No crear una implementacion paralela
  dentro de una pagina o modulo consumidor.

## Herramientas recomendadas

Siempre que encaje con la restriccion del proyecto de no introducir Composer ni
npm sin decision explicita, reflejar estas reglas en herramientas ejecutables:

- `.editorconfig` para formato basico comun.
- SonarQube/SonarCloud para quality gate y reglas transversales.
- Scripts standalone en `tools/` para verificaciones repetibles.
- Linters o analizadores estaticos solo si se aprueba introducirlos en el stack.
