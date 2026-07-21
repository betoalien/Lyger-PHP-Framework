# Migration from v0.1 to v0.2

`v0.1` is frozen. Use `v0.2` for new work and keep the old directory only for compatibility and historical benchmark comparison.

## Required changes

1. Run Composer with PHP 8.3 or newer.
2. Enable the FFI extension in the CLI configuration.
3. Install the matching library from `libraries/libs` for the host OS and architecture.
4. Use `php rawr serve:php` while the Rust queue end-to-end check is still being completed.
5. Do not copy benchmark claims from v0.1; consult `docs/validation-results.md` and `CHANGELOG.md`.

## Compatibility

The public `Request`, `Response`, routing and fallback APIs remain source-compatible in v0.2. The Always-Alive Rust transport and bounded result streaming are covered by the v0.2 and Dental Clinic contract tests.
