#!/usr/bin/env bash
# Static analysis runner (larastan / PHPStan).
#
# WHY THIS WRAPPER: the only PHP on this machine is a ZTS (thread-safe) build,
# and PHPStan segfaults silently (exit 1, no output) when handed more than one
# file at once on ZTS. Analysing a single file at a time is stable, so we loop.
# Cross-file types still resolve via reflection, so coverage is unaffected.
#
# Usage:  bash scripts/phpstan.sh            # analyse everything under app/
#         bash scripts/phpstan.sh app/Models # analyse a subtree
set -u

PHP="${PHP_BIN:-php}"
TARGET="${1:-app}"

fail=0
count=0
while IFS= read -r f; do
    count=$((count + 1))
    out=$("$PHP" vendor/bin/phpstan analyse "$f" \
        --no-progress --memory-limit=1G --error-format=raw 2>/dev/null)
    if [ -n "$out" ]; then
        printf '%s\n' "$out"
        fail=1
    fi
done < <(find "$TARGET" -name '*.php')

if [ "$fail" -eq 0 ]; then
    echo "PHPStan: $count files analysed, no errors."
fi
exit "$fail"
