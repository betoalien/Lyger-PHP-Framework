# Lyger v0.2 validation results

These measurements were executed locally on 2026-07-21 using PHP 8.3.30 and the updated Rust library in `libraries/libs`.

## Static checks

- `php -l` over `Lyger`, `App`, `routes`, and `database`: all files passed.
- `composer validate --no-check-publish`: passed.
- Composer authoritative autoload generation: passed without warnings.

## Framework benchmark (`benchmark/run.php`)

One run completed with exit code 0:

| Test | Measured result |
|---|---:|
| Hello World loop (1,000 iterations) | 0.01 ms / 123,361,882 loop iterations/s |
| Heavy computation (10M iterations) | PHP 368 ms; Rust FFI 73 ms; measured ratio 5.1x |
| JSON serialization (1,000 objects × 100) | PHP 20.59 ms; reported Rust estimate 6.86 ms |
| SQLite FFI query + JSON | 1.68 ms |
| Memory allocation sample | 16 MB used; 18 MB peak |

The benchmark's JSON Rust value is currently a calculated estimate (`$phpTime / 3`), not a Rust execution measurement. The Hello World loop is also a PHP in-process loop, not HTTP throughput. These values must not be presented as end-to-end framework or network benchmarks until dedicated reproducible harnesses are added.

## Database services

PostgreSQL and MySQL containers are defined in the repository-level `docker-servers/` compose file. Rust-core integration tests against both services passed separately; PHP driver parity remains a framework gap.

## Two-track validation

The framework is validated in two independent contexts:

1. `v0.2/tests/`: framework contracts and HTTP E2E.
2. `Dental_Clinic/tests/`: the real consumer application using the same v0.2 libraries.

Both contexts pass PHP lint, Composer autoload generation, database, health, streaming and HTTP contracts when their local server is running.

The Rust PHP driver contract now passes through the actual FFI library for all three environments:

- v0.2 → SQLite: PASS
- v0.2 → PostgreSQL 15: PASS
- v0.2 → MySQL 8.0: PASS
- Dental Clinic → SQLite: PASS
- Dental Clinic → PostgreSQL 15: PASS
- Dental Clinic → MySQL 8.0: PASS

`QueryBuilder` still defaults to its legacy PDO implementation; the new `DatabaseManager`/`RustDatabaseDriver` path is validated independently and must be made the configured default before claiming full ORM Rust-driver parity.

## v0.2 streaming validation

Using the ARM64 library from `v0.2/libraries/libs/Mac/`:

- v0.2 streaming contract: PASS
- Dental Clinic streaming contract: PASS
- Result chunks preserve ordering and are bounded to the requested page size.
- `memoryMetrics()` returns `used_bytes` and `budget_bytes`.
- Handles are released in `finally` blocks after consumption.

## Installer validation

The installer was executed in isolated temporary copies (the source tree was not modified):

- Vue.js selector: PASS
- React selector: PASS
- Svelte selector: PASS
- `core:download-binaries` verifies the platform artifact: PASS
- Generated frontend template and `.lyger_installed` marker: PASS

## Always-Alive HTTP status

The first attempt timed out because PHP encoded empty headers as `[]`, while Rust requires a JSON object. After normalizing headers to `(object)`, local curl validation passed: `/api/health` returned 200 and `/missing` returned 404 with JSON body. The same checks passed in `Dental_Clinic` after synchronizing v0.2.
