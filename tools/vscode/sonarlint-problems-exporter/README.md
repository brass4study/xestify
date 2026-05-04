# Xestify SonarLint Problems Exporter

Extension local de VSCode para exportar exactamente los diagnosticos que
`SonarQube for IDE` publica en el panel Problems.

## Por que hace falta

`SonarQube for IDE` no guarda un reporte JSON estable en el repositorio. Sus
hallazgos se publican en la API de diagnosticos de VSCode y el panel Problems
los muestra desde ahi. Esta extension lee esa misma API:

```js
vscode.languages.getDiagnostics()
```

Despues filtra los diagnosticos cuyo `source` contiene `sonar`.

## Instalar en VSCode

Copiar esta carpeta a las extensiones locales de VSCode:

```powershell
Copy-Item `
  .\tools\vscode\sonarlint-problems-exporter `
  "$env:USERPROFILE\.vscode\extensions\xestify.sonarlint-problems-exporter-0.1.0" `
  -Recurse `
  -Force
```

Despues recargar VSCode.

## Exportar hallazgos

1. Abrir la Command Palette.
2. Ejecutar `Xestify: Export SonarLint Problems`.
3. Leer el reporte en:

```text
var/reports/sonarlint-problems.json
```

El resultado coincide con los diagnosticos Sonar actualmente publicados en
VSCode. Si SonarQube for IDE todavia no ha analizado un archivo, ese archivo no
aparecera hasta que la extension lo analice y publique sus diagnostics.

## Exportar desde terminal

La extension tambien escucha este archivo:

```text
var/reports/sonarlint-problems.request.json
```

Para pedir una exportacion desde terminal:

```powershell
.\tools\vscode\export-sonarlint-problems.ps1
```

El script escribe el trigger, espera a que VSCode regenere el reporte y muestra
el JSON resultante. VSCode debe estar abierto y la extension local debe estar
activa.

## Analizar workspace desde terminal

Para forzar el analisis de todos los archivos soportados del workspace:

```powershell
.\tools\vscode\analyze-sonarlint-workspace.ps1
```

La extension abre todos los archivos `php`, `js` y `html`, ejecuta
`SonarLint.AnalyseOpenFile` y exporta el reporte final. Puede tardar mas que la
exportacion simple porque depende del tiempo de analisis de SonarQube for IDE.

Durante el analisis completo, la extension crea un grupo temporal de editores a
la derecha, abre ahi los archivos necesarios y cierra ese grupo al terminar. Asi
no mezcla las pestanas del analisis con las que ya estaban abiertas.
