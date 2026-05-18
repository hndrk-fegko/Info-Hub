#!/usr/bin/env sh

set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
ROOT_DIR=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)
RUNNER="$ROOT_DIR/tests/run.php"

if [ -n "${PHP_BIN:-}" ]; then
	PHP_EXECUTABLE="$PHP_BIN"
elif command -v php >/dev/null 2>&1; then
	PHP_EXECUTABLE="$(command -v php)"
elif [ -x '/c/xampp/php/php.exe' ]; then
	PHP_EXECUTABLE='/c/xampp/php/php.exe'
elif [ -x '/mnt/c/xampp/php/php.exe' ]; then
	PHP_EXECUTABLE='/mnt/c/xampp/php/php.exe'
else
	echo 'Kein PHP-Interpreter gefunden. Setze PHP_BIN oder installiere php in PATH.' >&2
	exit 2
fi

exec "$PHP_EXECUTABLE" "$RUNNER" "$@"