# Changelog

All notable changes to the validated v0.2 release line are documented here.

## [0.2.0] - 2026-07-21

Rust production checklist and the v0.3 PardoX/Big Data roadmap: `../Changes/RUST_CORE_PRODUCTION_AND_COMPETITIVE_ROADMAP.md`.

### Added

- Rust queue bridge declarations and PHP request/response transport support.
- `Request::fromServerPayload()` for Rust-originated HTTP requests.
- Composer authoritative classmap to support the framework's multi-class source files.
- Validation notes and reproducibility caveats in `docs/validation-results.md`.
- `DatabaseDriver`, `RustDatabaseDriver`, `PdoDatabaseDriver` and `DatabaseManager` contracts.
- Bounded result streaming through `Engine::streamResult()` and Rust memory metrics.
- ABI verification with an explicit PHP fallback for unavailable/incompatible binaries.
- Installer binary verification via `php rawr core:download-binaries`.

### Fixed

- PHP syntax error in the controller fixture.
- Console command name collision and incorrect error variable.
- Deprecated JavaScript template interpolation in the admin view.
- Composer PHP platform and lock metadata mismatch.
- `rawr serve` now validates ports and targets the Rust Always-Alive path.
- Empty response headers are serialized as a JSON object (`{}`), matching the Rust response contract; this fixed the HTTP response timeout.
- PHP→Rust driver CRUD verified against SQLite, PostgreSQL 15 and MySQL 8.0.

### Validation

- PHP 8.3 lint: all canonical files pass.
- Composer validation and authoritative autoload generation pass without warnings.
- Rust core PostgreSQL/MySQL integration tests: 13/13 passed.
- HTTP queue end-to-end validation: `/api/health` 200 and `/missing` 404 verified through curl.
- Dental Clinic consumer validation: `/api/health` 200 and `/missing` 404 verified through curl.
- Installer selector validation: Vue.js, React and Svelte paths pass in isolated copies.
- Streaming and memory contracts pass in v0.2 and Dental Clinic.

## [0.1.0] - Frozen

The v0.1 tree is retained unchanged as the compatibility baseline. New development must target v0.2.
