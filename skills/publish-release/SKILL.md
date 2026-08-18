---
name: publish-release
description: Publica una release de Xestify en GitHub con gh release create; sugiere la siguiente version SemVer a partir de los tags existentes (v1.0.0 si no hay ninguno) pero siempre confirma la version con el usuario, compone las notas en espanol desde el historial de commits agrupando por stories/EPICs, crea y empuja el tag anotado vX.Y.Z tras confirmacion, y construye automaticamente un ZIP instalable limpio (solo runtime e instalacion; sin docs, tests, skills ni config de agentes) con scripts/build-release-zip.sh que adjunta como asset del release. Usar cuando el usuario pida "publica una release", "crea la release vX.Y.Z", "sube la release a GitHub", "saca una nueva version" o "prepara el zip de la release". No usar para desplegar en un servidor ni para commits o push ordinarios.
---

# Publish Release

Publica una release oficial de Xestify en el repositorio de GitHub
(`gh release create`): tag anotado `vX.Y.Z`, notas en espanol y un ZIP
instalable limpio como asset. El ZIP lo construye y verifica siempre esta
skill mediante `scripts/build-release-zip.sh`; el usuario no prepara ni
adjunta nada a mano. Sus unicas intervenciones son las 3 confirmaciones
marcadas en los pasos (version, notas y tag).

## Que contiene el artefacto

Solo lo estrictamente necesario para instalar y ejecutar la aplicacion:
runtime PHP (`backend/public`, `backend/src`, esquema SQL inicial en
`backend/database/schema/` y `.env.example`), frontend servido tal cual
(`frontend/src`), los plugins completos (`plugins/`), los scripts de
instalacion CLI (`tools/setup/`: `install.php`, `create-admin-user.php`,
`check-install.php`, seeds y sync — nunca `tools/dev/`, que es QA), los
`.htaccess` (raiz y por directorio: `backend/`, `backend/public/`,
`plugins/`, `tools/setup/`) y las guias `INSTALL.md`, `LICENSE.md` y
`README.md`.

La lista canonica (whitelist, rutas prohibidas y ficheros obligatorios)
vive en `scripts/build-release-zip.sh`. No duplicarla aqui ni en otro
sitio: si cambia la estructura del proyecto, se actualiza el script.

Nota aceptada: dentro del ZIP, los enlaces de `INSTALL.md` hacia `docs/` y
`skills/` quedan sin destino local; apuntan al repositorio en GitHub y los
pasos nucleo de instalacion son autocontenidos.

## Prerrequisitos

1. `gh` instalado y autenticado contra github.com (`gh auth status`).
2. Rama `main`, arbol de trabajo limpio y sincronizado con `origin/main`.
   No se ejecutan suites de tests: la verificacion previa del codigo es
   responsabilidad del flujo normal de desarrollo.
3. En Windows, Git Bash disponible (el script de empaquetado es bash y se
   invoca siempre como `bash script.sh`, sin depender del bit de ejecucion).

## Pasos

### 1. Checks previos (abortar con mensaje claro si alguno falla)

```bash
git rev-parse --abbrev-ref HEAD      # debe ser "main"
git status --porcelain               # debe estar vacio
git fetch origin
git rev-list --left-right --count main...origin/main   # debe ser "0	0"
gh auth status                       # autenticado contra github.com
```

### 2. Version (**CONFIRMACION 1, obligatoria**)

```bash
git tag --sort=-v:refname            # ultimo tag publicado
```

- Sin tags: sugerir `v1.0.0`.
- Con tags: sugerir el bump SemVer segun el rango desde el ultimo tag
  (`feat` nuevos -> minor; solo `fix`/`docs` -> patch; ruptura de
  compatibilidad -> major).
- Preguntar SIEMPRE al usuario la version definitiva antes de continuar,
  aunque la haya dado ya en su peticion.

### 3. Notas del release (**CONFIRMACION 2, obligatoria**)

```bash
git log --reverse --format='%s' <ultimo-tag>..HEAD   # todo el historico si es el primer release
```

Reglas de composicion:

- En espanol.
- Primer release: resumen por EPICs/funcionalidades del producto, no un
  volcado de commits.
- Releases siguientes: agrupar los `feat: STORY X.Y - ...` por EPIC,
  resumir los `fix:` en una seccion "Correcciones" y los `docs:` en
  "Documentacion". Omitir ruido interno (chore de tooling, etc.).
- Linea final con enlace al repositorio para la documentacion completa.
- Guardar en `var/dist/release-notes-vX.Y.Z.md`, mostrar el borrador
  completo al usuario y aplicar sus cambios hasta que lo apruebe.

### 4. Tag (**CONFIRMACION 3, obligatoria y explicita**)

```bash
git tag -a vX.Y.Z -m "Xestify vX.Y.Z"
git push origin vX.Y.Z
```

### 5. Artefacto (automatico)

```bash
bash skills/publish-release/scripts/build-release-zip.sh vX.Y.Z
```

Construye y verifica `var/dist/xestify-vX.Y.Z.zip` desde el tag. Si la
verificacion falla, el script descarta el zip y sale con error: NO
continuar (el tag ya empujado no estorba; se reintenta tras corregir).

### 6. Publicar y verificar (automatico)

```bash
gh release create vX.Y.Z "var/dist/xestify-vX.Y.Z.zip#Xestify vX.Y.Z - paquete instalable" \
  --verify-tag --title "Xestify vX.Y.Z" \
  --notes-file var/dist/release-notes-vX.Y.Z.md --latest
gh release view vX.Y.Z               # comprobar titulo, notas y asset
```

`--verify-tag` impide que `gh` cree un tag implicito si el push del paso 4
hubiera fallado. Ofrecer al usuario `gh release view vX.Y.Z --web` para la
inspeccion final.

## Manejo de errores

| Situacion | Accion |
|---|---|
| El tag `vX.Y.Z` ya existe | Comparar `git rev-parse vX.Y.Z^{commit}` con `git rev-parse HEAD`: si coinciden y el usuario confirma, reutilizarlo y saltar el paso 4; si no, proponer otra version o (solo con confirmacion explicita) `git tag -d vX.Y.Z && git push origin :refs/tags/vX.Y.Z` y recrear |
| `gh release create` fallo a mitad (release sin asset) | `gh release upload vX.Y.Z var/dist/xestify-vX.Y.Z.zip --clobber` y reverificar con `gh release view` |
| Notas equivocadas ya publicadas | `gh release edit vX.Y.Z --notes-file var/dist/release-notes-vX.Y.Z.md` |
| Rehacer el asset | Reconstruir el zip (paso 5) + `gh release upload ... --clobber` |
| Deshacer todo | `gh release delete vX.Y.Z --yes`; borrar el tag solo si el usuario lo pide expresamente |
| La verificacion del zip falla | No publicar; revisar si la whitelist del script quedo desactualizada respecto a la estructura real del repo |

## Dry-run (probar sin publicar)

```bash
bash skills/publish-release/scripts/build-release-zip.sh HEAD
```

Genera `var/dist/xestify-dev-<hash>.zip` con la misma verificacion, sin
crear tag ni release. Usar cuando el usuario solo quiera el zip o revisar
el contenido del artefacto.

## Test Prompts

Ver `evals/evals.json`.
