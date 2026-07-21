# Lyger release order

## Canonical lines

- `v0.1/`: frozen compatibility baseline and historical measurements.
- `v0.2/`: active PHP framework development line.
- `lyger_framework_rust-/`: canonical Rust core repository; GitHub Actions produces platform libraries.

## Workflow

1. Complete and validate a gap in `v0.2`.
2. Run PHP lint, Composer validation, framework tests and the reproducible benchmark.
3. Validate the Rust core with PostgreSQL and MySQL containers.
4. Record actual results in `v0.2/docs/validation-results.md` and `v0.2/CHANGELOG.md`.
5. Update `v0.2/README.md` only with claims backed by those results.
6. Publish the PHP framework and Rust core changes from their respective repositories.

No new feature should be implemented directly in `v0.1`.
