<!-- file generated with AI assistance: Claude Code - 2026-06-14 -->

# CLAUDE.md

This is a public open-source package maintained under github.com/dmstr.

## Language policy

**Everything in this repository is in English** — commit messages, code,
comments, and documentation. Do not write German here, even though related
company/customer projects (e.g. the consuming application) use German. These
packages are public and English-only.

## Testing

PHPUnit (^12). Run locally with the bundled CLI-only Docker Compose
(no host PHP required):

    docker compose run --rm php

This installs dependencies and runs `vendor/bin/phpunit`. The same runs in CI
via `.github/workflows/tests.yml`.

## Requirements

PHP >= 8.4.
