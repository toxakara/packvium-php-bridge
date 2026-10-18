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

    // /: verify that a refused request throws InvalidRequestException
    // with matching code, reason, and field on both backends, and that refusal does
    // not disable the native FFI backend.
    $invalidRequest = [
        'items' => [['id' => 'a', 'quantity' => 1, 'dimensions' => ['length' => '-10', 'width' => '10', 'height' => '10']]],
        'containers' => [['id' => 'c', 'inner_dimensions' => ['length' => '20', 'width' => '20', 'height' => '20']]],
    ];
    try {
        $packer->pack($invalidRequest);
        throw new RuntimeException('PHP backend should have refused invalid request');
    } catch (\Packvium\Serialization\InvalidRequestException $e) {
        if ($e->errorCode() !== 'invalid_request' || $e->reason() !== 'negative_measure' || $e->field() !== '/items/0/dimensions/length') {
            throw new RuntimeException("Unexpected refusal on PHP backend: {$e->errorCode()} {$e->reason()} {$e->field()}");
        }
    }
    try {
        $native->pack($invalidRequest);
        throw new RuntimeException('Native backend should have refused invalid request');
    } catch (\Packvium\Serialization\InvalidRequestException $e) {
        if ($e->errorCode() !== 'invalid_request' || $e->reason() !== 'negative_measure' || $e->field() !== '/items/0/dimensions/length') {
            throw new RuntimeException("Unexpected refusal on Native backend: {$e->errorCode()} {$e->reason()} {$e->field()}");
        }
    }
    if ($native->backend() !== 'rust') {
        throw new RuntimeException('Native backend should not be disabled by an application refusal');
    }
    echo "OK native bridge request refusal parity across both backends\n";

    // Every refusal the core can name reaches the caller as the class pure PHP
    // throws for the same request, with the same code, reason, field and detail.
    $side = ['length' => '100', 'width' => '100', 'height' => '100'];
    $fixed = ['item_type' => 'cube', 'container_type' => 'box', 'container_instance' => 1,
        'position' => ['x' => '0', 'y' => '0', 'z' => '0'], 'orientation' => 'LWH'];
    $refused = [
        'fixed placement collision' => [
            'items' => [['id' => 'cube', 'quantity' => 2, 'dimensions' => $side]],
            'containers' => [['id' => 'box', 'inner_dimensions' => ['length' => '200', 'width' => '100', 'height' => '100']]],
            'fixed_placements' => [$fixed, $fixed],
        ],
        'unsupported feature' => [
            'items' => [['id' => 'cube', 'quantity' => 1, 'dimensions' => $side]],
            'containers' => [['id' => 'box', 'inner_dimensions' => $side, 'pallet_overhang_limit' => '1']],
        ],
        'policy without as_of' => [
            'items' => [['id' => 'cube', 'quantity' => 1, 'dimensions' => $side]],
            'containers' => [['id' => 'box', 'inner_dimensions' => $side]],
            'policy' => ['rules' => [['kind' => 'nope']]],
        ],
    ];
    $describe = static function (callable $pack): array {
        try {
            $pack();
        } catch (\Throwable $e) {
            return [get_class($e), method_exists($e, 'errorCode') ? $e->errorCode() : null,
                method_exists($e, 'reason') ? $e->reason() : null,
                method_exists($e, 'field') ? $e->field() : null,
                method_exists($e, 'detail') ? $e->detail() : null];
        }
        return ['accepted'];
    };
    foreach ($refused as $name => $case) {
        $expected = $describe(static function () use ($packer, $case) { return $packer->pack($case); });
        $observed = $describe(static function () use ($native, $case) { return $native->pack($case); });
        if ($expected === ['accepted'] || $observed !== $expected) {
            throw new RuntimeException("$name: native threw " . json_encode($observed) . ', pure PHP ' . json_encode($expected));
        }
    }
    if ($native->backend() !== 'rust') {
        throw new RuntimeException('A named refusal must not disable the native backend');
    }
    echo "OK native bridge raises pure PHP's class for every named refusal\n";
}

// A payload without a code this bridge can reproduce is the library's failure --
// a caught panic, a transport error -- and must be answered from pure PHP, never thrown at
// the caller as their mistake. Checked on the payloads directly: no library fails on demand.
$unnamed = [
    ['status' => 'error', 'error' => 'internal panic while solving (caught at the FFI boundary)'],
    ['status' => 'error', 'error' => 'null request'],
    ['status' => 'error', 'error' => 'solver result failed independent validation: x', 'code' => 'solution_failed_validation'],
    ['status' => 'error', 'error' => 'invalid_request: x', 'code' => 'invalid_request'],
];
foreach ($unnamed as $payload) {
    if (Packvium\Native\NativePacker::refusalFor($payload) !== null) {
        throw new RuntimeException('Treated as the caller\'s refusal: ' . json_encode($payload));
    }
}
$named = [
    'Packvium\Serialization\InvalidRequestException' => ['code' => 'invalid_request', 'error' => 'invalid_request: /a: b', 'reason' => 'wrong_type', 'field' => '/a', 'detail' => 'b'],
    'Packvium\Validation\FixedPlacementException' => ['code' => 'invalid_fixed_placement', 'error' => 'invalid_fixed_placement: d', 'reason' => 'cannot_hold', 'field' => '/fixed_placements', 'detail' => 'd'],
    'Packvium\Serialization\UnsupportedFeatureException' => ['code' => 'unsupported_feature', 'error' => 'unsupported_feature: the Rust core does not yet implement x; ...'],
];
foreach ($named as $class => $payload) {
    $refusal = Packvium\Native\NativePacker::refusalFor($payload + ['status' => 'error']);
    if (!$refusal instanceof $class || $refusal->getMessage() !== $payload['error']) {
        throw new RuntimeException("{$payload['code']} did not become $class with the core's message");
    }
}
$model = Packvium\Native\NativePacker::refusalFor(['status' => 'error', 'code' => 'invalid_input', 'error' => 'invalid input: policy must be an object']);
if (!$model instanceof Packvium\Serialization\InvalidRequestException || $model->reason() !== 'invalid_value'
    || $model->field() !== '' || $model->detail() !== 'policy must be an object') {
    throw new RuntimeException('An untyped model error must read as pure PHP\'s invalid_value refusal');
}
echo "OK native bridge maps each code to pure PHP's class and falls back on an unnamed failure\n";

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
