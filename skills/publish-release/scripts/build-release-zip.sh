#!/usr/bin/env bash
# Construye el artefacto instalable de Xestify desde un ref de git y
# verifica su contenido antes de darlo por bueno.
#
# Uso: bash skills/publish-release/scripts/build-release-zip.sh <ref>
#   <ref>: tag vX.Y.Z (release real) o HEAD u otro ref (dry-run).
#
# Salida: var/dist/xestify-vX.Y.Z.zip (o xestify-dev-<hash>.zip en dry-run).
set -euo pipefail

REF="${1:?Uso: build-release-zip.sh <ref (vX.Y.Z | HEAD)>}"

# ============================================================
# FUENTE DE VERDAD de la whitelist del artefacto de release.
# Si cambia lo que la aplicacion necesita para funcionar o
# instalarse, se actualiza AQUI (y solo aqui).
# ============================================================
WHITELIST=(
  .htaccess
  backend/.htaccess
  backend/public
  backend/src
  backend/database
  backend/.env.example
  frontend/src
  plugins
  tools/setup
  INSTALL.md
  LICENSE.md
  README.md
)

# Rutas que JAMAS deben aparecer en el artefacto (verificacion negativa).
FORBIDDEN=(
  docs/
  skills/
  tools/dev/
  backend/tests/
  frontend/tests/
  frontend/tailwind.config.cjs
  .claude/
  .github/
  .vscode/
  AGENTS.md
  CLAUDE.md
  CONTRIBUTING.md
  .editorconfig
  .gitattributes
  .gitignore
  var/
  debug.log
)

# Ficheros clave cuya presencia se exige (verificacion positiva).
REQUIRED=(
  .htaccess
  INSTALL.md
  LICENSE.md
  README.md
  backend/.env.example
  backend/public/index.php
  frontend/src/index.html
  frontend/src/css/tailwind.generated.css
  backend/.htaccess
  backend/public/.htaccess
  backend/database/schema/001_users.sql
  plugins/.htaccess
  tools/setup/.htaccess
  tools/setup/bootstrap.php
  tools/setup/cli-helpers.php
  tools/setup/install.php
  tools/setup/create-admin-user.php
  tools/setup/check-install.php
)

ROOT="$(git rev-parse --show-toplevel)"

if [[ "$REF" == v* ]]; then
  VERSION="${REF#v}"
  ZIP_NAME="xestify-$REF.zip"
else
  VERSION="dev-$(git -C "$ROOT" rev-parse --short "$REF")"
  ZIP_NAME="xestify-$VERSION.zip"
fi
PREFIX="xestify-$VERSION/"
OUT_DIR="$ROOT/var/dist"
mkdir -p "$OUT_DIR"
ZIP="$OUT_DIR/$ZIP_NAME"

git -C "$ROOT" archive --format=zip --prefix="$PREFIX" -o "$ZIP" "$REF" -- "${WHITELIST[@]}"

# Listado equivalente al contenido del zip: mismo ref y mismos pathspecs en
# formato tar (portable: no depende de tener unzip instalado).
LISTING="$(git -C "$ROOT" archive --format=tar --prefix="$PREFIX" "$REF" -- "${WHITELIST[@]}" | tar -tf -)"

fail=0
for f in "${FORBIDDEN[@]}"; do
  if grep -qF "${PREFIX}${f}" <<<"$LISTING"; then
    echo "ERROR: ruta prohibida en el artefacto: $f" >&2
    fail=1
  fi
done
for f in "${REQUIRED[@]}"; do
  if ! grep -qxF "${PREFIX}${f}" <<<"$LISTING"; then
    echo "ERROR: falta fichero clave en el artefacto: $f" >&2
    fail=1
  fi
done

if [[ $fail -ne 0 ]]; then
  rm -f "$ZIP"
  echo "Verificacion fallida: artefacto descartado." >&2
  exit 1
fi

echo "OK: $ZIP"
echo "Entradas: $(wc -l <<<"$LISTING" | tr -d ' ') | Tamano: $(du -h "$ZIP" | cut -f1)"
