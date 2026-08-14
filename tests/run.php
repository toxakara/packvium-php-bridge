<?php declare(strict_types=1);
$composerAutoload = dirname(__DIR__).'/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
} else {
    // The monorepo release gate deliberately runs from a clean workspace where the
    // dependency directory has been removed. Keep that gate network-free while the
    // exported package continues to exercise the normal Composer path in standalone CI.
    require dirname(__DIR__, 4).'/packvium-php/autoload.php';
    require dirname(__DIR__).'/src/NativePacker.php';
}

$packer = new Packvium\Native\NativePacker();
if ($packer->backend() !== 'php') { throw new RuntimeException('Expected PHP fallback'); }
echo "OK native bridge fallback selection\n";

// Small, self-contained request -- this test only checks that pack() returns a
// well-formed result, not any particular placement, so it does not need the shared
// cross-language fixture corpus (which is not part of this package).
$request = [
    'items' => [['id' => 'a', 'quantity' => 1, 'dimensions' => ['length' => '10', 'width' => '10', 'height' => '10']]],
    'containers' => [['id' => 'c', 'inner_dimensions' => ['length' => '20', 'width' => '20', 'height' => '20']]],
];

// The fallback check above never loads the Rust shared library, so it cannot
// catch a header/ABI mismatch between NativePacker's FFI::cdef() string and what
// packvium-ffi actually exports. Exercise the real library when it has been built
// (`cargo build --release -p packvium-ffi`); skip cleanly otherwise so this stays
// runnable without a Rust toolchain.
$root = dirname(__DIR__, 2) . '/packvium-rust';
$candidates = [
    "$root/target/release/libpackvium_ffi.dylib",
    "$root/target/release/libpackvium_ffi.so",
    "$root/target/release/packvium_ffi.dll",
];
$library = null;
foreach ($candidates as $candidate) {
    if (is_file($candidate)) { $library = $candidate; break; }
}

if (!extension_loaded('ffi')) {
    echo "SKIP native bridge FFI check (ext-ffi not loaded)\n";
} elseif ($library === null) {
    echo "SKIP native bridge FFI check (packvium-ffi not built; run "
        . "'cargo build --release -p packvium-ffi' from the Rust workspace)\n";
} else {
    $native = new Packvium\Native\NativePacker($library);
    if ($native->backend() !== 'rust') {
        throw new RuntimeException('Expected the rust backend once a library path is given');
    }
    $result = $native->pack($request);
    if (!isset($result['status']) || !is_string($result['status'])) {
        throw new RuntimeException('Native pack() result has no string status field');
    }
    echo "OK native bridge FFI call against $library (status: {$result['status']})\n";
}

// "selects native only when healthy and always falls back safely" is only
// proven if an unhealthy library -- one that doesn't exist, or that exists but doesn't
// actually export this ABI -- cannot escape the constructor as an uncaught exception.
if (!extension_loaded('ffi')) {
    echo "SKIP native bridge unhealthy-library fallback check (ext-ffi not loaded)\n";
} else {
    $missing = new Packvium\Native\NativePacker('/nonexistent/libpackvium_ffi.so');
    if ($missing->backend() !== 'php') {
        throw new RuntimeException('Expected PHP fallback for a nonexistent library path');
    }
    $fallbackResult = $missing->pack($request);
    if (!isset($fallbackResult['status']) || !is_string($fallbackResult['status'])) {
        throw new RuntimeException('PHP fallback pack() result has no string status field');
    }

    // A real shared library that does not export the packvium_* symbols: any
    // libc/libSystem the process already has loaded satisfies FFI::cdef() itself (no
    // load error) but must fail the health check inside the constructor.
    $wrongAbi = PHP_OS_FAMILY === 'Darwin' ? '/usr/lib/libSystem.B.dylib' : '/lib/x86_64-linux-gnu/libc.so.6';
    if (is_file($wrongAbi)) {
        $unhealthy = new Packvium\Native\NativePacker($wrongAbi);
        if ($unhealthy->backend() !== 'php') {
            throw new RuntimeException('Expected PHP fallback when the library lacks the packvium ABI');
        }
    }
    echo "OK native bridge falls back safely and remains callable for missing/unhealthy libraries\n";
}
