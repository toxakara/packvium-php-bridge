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

## How your feedback is handled

Every issue and discussion is read by a maintainer. This is what happens next:

1. **New issues are labelled `needs-triage`.** A maintainer answers with one of: a question
   back to you, a confirmation, or a reason it will not be taken on.
2. **A confirmed bug** gets the `bug` label and is reproduced from your request. A wrong result
   in one package is checked against the others, because all of them implement the same
   contract and must agree.
3. **An accepted feature request or idea** is added to the project's backlog, and the issue is
   labelled `accepted` with a comment saying so. An idea from Discussions is first turned into
   an issue and linked back to the discussion.
4. **When the change ships**, the issue is closed with the release it shipped in. A change to
   the request or result format can only ship in a release that is allowed to change it, so
   it may take more than one release.
5. **Declined** requests are closed with the reason, so you are never left waiting on a
   silent issue.

Questions go to **Discussions → Q&A**; security problems go to **Report a vulnerability**
under the Security tab, never to a public issue.
