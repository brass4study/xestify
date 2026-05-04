# Guia de Contribucion

Este documento centraliza las convenciones de calidad de codigo y verificacion
para Xestify. `AGENTS.md` define el comportamiento de los agentes; este archivo
define los estandares tecnicos que deben aplicar personas, agentes y herramientas.

## Principios generales

- Mantener los cambios acotados al objetivo de la tarea.
- Preferir los patrones existentes del proyecto antes de introducir nuevas
  abstracciones.
- Evitar refactors no relacionados con la tarea en curso.
- Anadir o ajustar tests cuando se corrija una regresion o se toque un flujo con
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

## SonarQube for IDE

- La extension de VSCode publica sus hallazgos como diagnostics de VSCode.
- Para exportar exactamente los hallazgos Sonar visibles en Problems, usar la
  extension local `tools/vscode/sonarlint-problems-exporter`.
- El reporte se genera en `var/reports/sonarlint-problems.json`.
- Los agentes pueden pedir la exportacion ejecutando:

```powershell
.\tools\vscode\export-sonarlint-problems.ps1
```

- Para forzar un analisis completo de archivos `php`, `js` y `html` del
  workspace antes de exportar, ejecutar:

```powershell
.\tools\vscode\analyze-sonarlint-workspace.ps1
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
- Excepcion controlada: `Router` y `PluginLoader` pueden usar instanciacion o
  despacho dinamico porque son los puntos arquitectonicos que convierten rutas y
  plugins declarados en ejecucion real. Esa excepcion debe quedar marcada con
  `// NOSONAR`, estar cubierta por tests y no extenderse a servicios, modelos,
  controladores ni logica de negocio.
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
- Sin bloques de codigo vacios; usar `fn() => null` para handlers no-op o anadir
  un `return;` con comentario.
- Usar clases de caracteres abreviadas en regex cuando aplique: `\w`, `\d`, `\s`.

## Calidad de codigo JavaScript

Aplicar estas reglas siempre al generar o modificar codigo JavaScript.

- `const` por defecto, `let` cuando haga falta reasignar, nunca `var`.
- Sin `console.log` en codigo de produccion.
- Comparacion estricta: usar siempre `===` y `!==`.
- Sin funciones anonimas inline de mas de 5 lineas; extraer a funcion nombrada.
- Sin `innerHTML` con datos de usuario; usar `textContent` o sanitizar.

## Herramientas recomendadas

Siempre que encaje con la restriccion del proyecto de no introducir Composer ni
npm sin decision explicita, reflejar estas reglas en herramientas ejecutables:

- `.editorconfig` para formato basico comun.
- SonarQube/SonarCloud para quality gate y reglas transversales.
- Scripts standalone en `tools/` para verificaciones repetibles.
- Linters o analizadores estaticos solo si se aprueba introducirlos en el stack.
