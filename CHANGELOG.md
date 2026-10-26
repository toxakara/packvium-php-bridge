# Changelog

What changed in `packvium/native-bridge` on Packagist, release by release. The format follows
[Keep a Changelog](https://keepachangelog.com/1.1.0/) and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.8.0]

No changes of its own. Both backends, `packvium/packvium` 1.8.0 and the 1.8.0 C library,
answer the same way: results carry `optimality.lower_bound` and the gap, an effort budget
names the stop that cut the search, and a non-boolean `keep_upright`, `stackable`,
`must_be_on_floor` or `require_placement_coordinates` is refused as `wrong_type`.

## [1.7.0]

The Rust backend runs the 1.7.0 core, through `packvium/packvium` 1.7.0: `quality` looks ahead
in its block solver and, on our benchmark, finishes inside its time limit; every schema enum
value outside its list, `ticks` as an output unit included, is refused as `not_allowed`.

### Fixed

- **A Rust-backend refusal throws the PHP backend's exception.** `NativePacker` maps each native
  code onto the class the PHP backend throws, where it threw `invalid_request` for every native
  error.
- **A native failure falls back to the PHP backend.** A missing or unmapped code, or a caught
  panic, was reported as the caller's fault; the PHP backend now answers the request.

## [1.6.0]

The Rust backend gains the 1.6.0 request fields — boxes cut to measure, flat bags, stacking
groups and `maximum_volume` — through `packvium/packvium` 1.6.0.

### Fixed

- **Small orders with a `convex_hull` item no longer run out the clock on the Rust backend.**
  A five-item order with one jar spent the whole time limit; it now finishes in a handful of
  search nodes.

## [1.5.0]

A refused request now reads the same on both backends (see *Fixed*).

### Fixed

- **`quality` with a minimum support ratio answers on the Rust backend.** It failed with
  `solution_failed_validation` where the PHP backend answered.
- **A refused request reads the same on both backends.** On the Rust backend `NativePacker::pack()`
  returned `['status' => 'error', 'error' => ...]` with no code; it now throws
  `InvalidRequestException` with the code, reason and field, as the PHP backend does.

## [1.4.0]

A version-alignment release. The bridge itself is unchanged; nothing breaks 1.3.0.

Upgrade `packvium/packvium` to `1.4.0` to get fixed placements and `Packvium\Revisions`
through the fallback.

## [1.3.0]

A version-alignment release. The bridge itself is unchanged; nothing breaks 1.2.0.

Upgrade `packvium/packvium` to `1.3.0` to get `Packvium\Artifacts` through the fallback.

## [1.2.0]

A version-alignment release. The bridge itself is unchanged; nothing breaks 1.1.0.

It keeps loading a native shared library when one is provided and falling back to
`packvium/packvium` otherwise — upgrade that package to `1.2.0` to get its new
`Packvium\Execution\Plan` through the fallback.

## Earlier releases

Up to 1.1.0 one changelog covered every Packvium language. Those entries are kept in
this repository's GitHub Releases for each tag.
