# packvium/native-bridge

Optional native backend for [Packvium](https://packagist.org/packages/packvium/packvium).
It uses a Rust shared library through PHP FFI when one is available, and otherwise uses
the pure-PHP Packvium package.

Full documentation, the constraint reference and benchmarks live at
[packvium.com](https://packvium.com).

## Install

```bash
composer require packvium/native-bridge packvium/packvium
```

PHP 7.4 or later is required. Enable `ext-ffi` and provide a compatible shared library
only when you want the native backend; neither is required for the PHP fallback.

## Quick start

The request is the same plain array `packvium/packvium` reads with `ArrayCodec::pack()` —
the JSON document every Packvium engine accepts. Lengths and weights are strings, parsed
into exact integers, so `'0.1'` is a tenth of a millimetre and never a rounded float.

```php
<?php

use Packvium\Native\NativePacker;

$request = [
    'units' => ['length' => 'mm'],
    'configuration' => [
        // Counted work bounds the search, so both backends stop at a reproducible point;
        // the time limit is only a safety fuse.
        'effort_budget' => ['max_search_nodes' => 20000],
        'time_limit_ms' => 60000,
    ],
    'items' => [
        ['id' => 'mug', 'quantity' => 6, 'weight' => '400 g',
         'dimensions' => ['length' => '120', 'width' => '120', 'height' => '100']],
    ],
    'containers' => [
        ['id' => 'box', 'max_payload' => '20 kg',
         'inner_dimensions' => ['length' => '400', 'width' => '300', 'height' => '250']],
    ],
];

$packer = new NativePacker('/opt/packvium/libpackvium_ffi.so');
$result = $packer->pack($request);

echo $packer->backend(), PHP_EOL; // "rust" or "php"
echo $result['status'], PHP_EOL;  // "feasible"
```

The shared library is the `packvium-ffi` crate's C ABI build, named by platform:
`libpackvium_ffi.so` on Linux, `libpackvium_ffi.dylib` on macOS and `packvium_ffi.dll` on
Windows. If the extension, library or health check is unavailable, `NativePacker` selects
the PHP backend instead of failing the packing request. Log `backend()` once at startup:
it is how you learn that a deployment you believed was accelerated quietly fell back.

Omit the library path (or pass `null`) to always use the pure-PHP backend — useful in a
test suite or on a host that never provisions the shared library:

```php
$packer = new NativePacker();   // no library path: pure PHP, deterministic either way
echo $packer->backend(), PHP_EOL; // "php"
```

The two backends are independent engines held to the same request and result contract.
Each answer is independently validated, but they are not held to identical placements: on
some requests the compiled engine chooses a different arrangement, or one that scores
differently. Pin one backend if you need byte-identical plans across hosts.

## Errors

A malformed request is refused before anything is solved, with the same message from
either backend — `invalid_request: <field>: <detail>`, where `<field>` is a JSON Pointer
into your request. How it reaches you differs:

- **PHP backend:** `pack()` throws `Packvium\Serialization\InvalidRequestException` (an
  `InvalidArgumentException`) with `reason()`, `field()` and `detail()`, or its subclass
  `Packvium\Validation\FixedPlacementException` for `fixed_placements` that cannot hold.
- **Rust backend:** `pack()` returns an array `['status' => 'error', 'error' => <message>]`
  instead of throwing. The message is the same string; there are no separate fields.

Handle both until your deployment runs only one:

```php
use Packvium\Serialization\InvalidRequestException;

try {
    $result = $packer->pack($request);
} catch (InvalidRequestException $e) {
    $result = ['status' => 'error', 'error' => $e->getMessage()];
}
if ($result['status'] === 'error') {
    // show $result['error'] to a person; it names the field and the rule it broke
}
```

A request that is valid but does not fit completely is not an error on either backend: the
result lists what was left out, and why, in `unpacked_items`. A native call that fails at
runtime (a bad pointer, unparseable output) disables the library for the rest of the
`NativePacker`'s life and retries the same request in pure PHP.

## Examples

Runnable, in [`examples/`](https://github.com/toxakara/packvium-php-bridge/tree/main/examples).
Each one is a single file you can read top to bottom and execute without a project around it.

| File | What it shows |
| --- | --- |
| [`basic.php`](https://github.com/toxakara/packvium-php-bridge/blob/main/examples/basic.php) | Pack through the bridge and report which backend answered. |
| [`shapes.php`](https://github.com/toxakara/packvium-php-bridge/blob/main/examples/shapes.php) | Items that are not their box: complementary wedges sharing one crate as `convex_hull`, and a cushion that compresses under load until the crush limit refuses it — the same numbers whichever backend loaded. |

```bash
php examples/basic.php                                  # pure PHP
php examples/basic.php /path/to/libpackvium_ffi.so      # compiled engine
```

Running it both ways is the point: the same request comes back as a valid answer in the
same shape whichever backend served it.

## When to use it

Use this package when your deployment already manages the shared library and you want
the native engine. For a zero-configuration installation, use `packvium/packvium`
directly.

## The Packvium family

One request and result contract, implemented independently in four engines (Rust,
Python, PHP, JavaScript) and held to identical placements on a shared fixture set.
Pick the package for your stack; mixing them in one system is safe.

Documentation, the constraint reference and the benchmarks are at
[packvium.com](https://packvium.com).

| Package | Install | Source |
| --- | --- | --- |
| Python — [`packvium`](https://pypi.org/project/packvium/) | `pip install packvium` | [packvium-python](https://github.com/toxakara/packvium-python) |
| PHP — [`packvium/packvium`](https://packagist.org/packages/packvium/packvium) | `composer require packvium/packvium` | [packvium-php](https://github.com/toxakara/packvium-php) |
| Rust — [`packvium`](https://crates.io/crates/packvium) | `packvium = "1.0"` | [packvium-rust](https://github.com/toxakara/packvium-rust) |
| Node.js — [`@packvium/engine`](https://www.npmjs.com/package/@packvium/engine) | `npm install @packvium/engine` | [packvium-node](https://github.com/toxakara/packvium-node) |
| Browser / WebAssembly — [`@packvium/browser`](https://www.npmjs.com/package/@packvium/browser) | `npm install @packvium/browser` | [packvium-wasm](https://github.com/toxakara/packvium-wasm) |
| PHP FFI bridge — [`packvium/native-bridge`](https://packagist.org/packages/packvium/native-bridge) | `composer require packvium/native-bridge` | [packvium-php-bridge](https://github.com/toxakara/packvium-php-bridge) |
| Python native selector — `packvium-native` | from source until the native wheels ship | [packvium-python-adapter](https://github.com/toxakara/packvium-python-adapter) |

## Citation

If Packvium supports your research, cite it as software. GitHub's **Cite this repository**
button reads [`CITATION.cff`](https://github.com/toxakara/packvium-php-bridge/blob/main/CITATION.cff), and
[`codemeta.json`](https://github.com/toxakara/packvium-php-bridge/blob/main/codemeta.json) carries the same record in
CodeMeta form.

```bibtex
@software{packvium_php_bridge,
  author  = {{Packvium contributors}},
  title   = {Packvium native bridge for PHP},
  version = {1.5.0},
  license = {MIT},
  url     = {https://packvium.com}
}
```

## License

MIT. See [LICENSE](https://github.com/toxakara/packvium-php-bridge/blob/main/LICENSE).
