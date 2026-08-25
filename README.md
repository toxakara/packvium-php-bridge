# packvium/native-bridge

Optional native backend for [Packvium](https://packagist.org/packages/packvium/packvium).
It uses a Rust shared library through PHP FFI when one is available, and otherwise uses
the pure-PHP Packvium package.

## Install

```bash
composer require packvium/native-bridge packvium/packvium
```

PHP 7.4 or later is required. Enable `ext-ffi` and provide a compatible shared library
only when you want the native backend; neither is required for the PHP fallback.

## Quick start

```php
<?php

use Packvium\Native\NativePacker;

$packer = new NativePacker('/opt/packvium/libpackvium_ffi.so');
$result = $packer->pack($request);

echo $packer->backend(), PHP_EOL; // "rust" or "php"
echo $result['status'], PHP_EOL;
```

Pass the same request format used by `packvium/packvium`. If the extension, library or
health check is unavailable, `NativePacker` selects the PHP backend instead of failing
the packing request.

## Examples

Runnable, in [`examples/`](examples). Each one is a single file you can read top to bottom
and execute without a project around it.

| File | What it shows |
| --- | --- |
| [`basic.php`](examples/basic.php) | Pack through the bridge and report which backend answered. |

```bash
php examples/basic.php                            # pure PHP
php examples/basic.php /path/to/libpackvium.so    # compiled engine
```

Running it both ways is the point: the same request must come back with the same answer
whichever backend served it.

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
| Rust — [`packvium`](https://crates.io/crates/packvium) | `packvium = "0.1"` | [packvium-rust](https://github.com/toxakara/packvium-rust) |
| Node.js — [`@packvium/engine`](https://www.npmjs.com/package/@packvium/engine) | `npm install @packvium/engine` | [packvium-node](https://github.com/toxakara/packvium-node) |
| Browser / WebAssembly — [`@packvium/browser`](https://www.npmjs.com/package/@packvium/browser) | `npm install @packvium/browser` | [packvium-wasm](https://github.com/toxakara/packvium-wasm) |
| PHP FFI bridge — [`packvium/native-bridge`](https://packagist.org/packages/packvium/native-bridge) | `composer require packvium/native-bridge` | [packvium-php-bridge](https://github.com/toxakara/packvium-php-bridge) |
| Python native selector — `packvium-native` | from source until the native wheels ship | [packvium-python-adapter](https://github.com/toxakara/packvium-python-adapter) |

## License

MIT. See [LICENSE](LICENSE).
