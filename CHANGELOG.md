# Changelog

What changed in `packvium/native-bridge` on Packagist, release by release. The format follows
[Keep a Changelog](https://keepachangelog.com/1.1.0/) and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
