# Contributing

Thanks for looking. This package is small, additive and deliberately conservative about
what can go wrong when the native library is unhealthy — please keep it that way.

## Getting set up

```bash
composer install
composer test
```

`composer test` runs without a Rust toolchain or `ext-ffi` — the parts of the suite that
need either skip cleanly and say so.

## The rules that matter here

**A missing or broken native library must never surface as an exception from the
caller's perspective.** `NativePacker`'s constructor runs a health check
(`packvium_version()`) before trusting a loaded library, and every FFI call is wrapped
so a runtime failure disables the native path and falls back to `packvium/packvium`
instead of propagating. A change that lets a native-library problem escape as an
uncaught exception is a regression, however unlikely the failure mode seems.

**No new required dependencies.** `require` stays `{"php": ">=7.4"}`. `ext-ffi` and
`packvium/packvium` are both optional (`suggest`, or `require-dev` for testing only) —
this package must install and be usable without either.

**Determinism.** The native and PHP-fallback paths must agree on every field that
`packvium/packvium` documents. A behavioural difference between them is a bug in
whichever one is wrong, not a documented quirk.

## Pull requests

- One logical change per pull request.
- Commit messages in imperative mood, under 72 characters:
  `type(scope): description` with `feat`, `fix`, `refactor`, `chore`, `docs` or `test`.
- Add or update tests. `composer test` must pass on PHP 7.4 through 8.4, with and
  without `ext-ffi` loaded.
- Do not bump the version; releases are cut separately.

## Reporting a bug

Please include the full request that reproduces it, whether `ext-ffi` was loaded, and
`backend()`'s return value at the time. For anything with security implications, follow
[SECURITY.md](SECURITY.md) rather than opening a public issue.
