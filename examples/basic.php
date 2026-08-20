<?php
/**
 * Pack through the FFI bridge, with or without the compiled library.
 *
 * Run it:
 *
 *     php examples/basic.php                       # pure PHP
 *     php examples/basic.php /path/to/libpackvium.dylib   # compiled engine
 *
 * This package is a thin selector, not an engine. Hand the constructor a path to the
 * compiled Rust shared library and it will use it; hand it nothing, or a path that does
 * not load, and it uses the pure-PHP `packvium/packvium` package instead. Both answer
 * the same shared JSON contract, so your code never branches on which one is present.
 *
 * The fallback is deliberately silent: a missing library, an ABI mismatch or any other
 * load-time failure leaves you with a working packer rather than a fatal error. That is
 * the right default for a shipping application and the wrong default for a deployment
 * you believed was accelerated -- so log `backend()` once at startup. It is the
 * difference between "we are running the compiled engine" and "we quietly fell back",
 * and you want that from a log line rather than from a latency graph.
 */
declare(strict_types=1);

// Installed normally, Composer's autoloader supplies both this package and the
// `packvium/packvium` library the pure-PHP path falls back to. Inside the source
// workspace there is no vendor directory, so wire the two up by hand.
$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
} else {
    require dirname(__DIR__, 4) . '/packvium-php/autoload.php';
    require dirname(__DIR__) . '/src/NativePacker.php';
}

use Packvium\Native\NativePacker;

$packer = new NativePacker($argv[1] ?? null);

printf("bridge using the %s backend\n\n", $packer->backend());

$request = [
    'items' => [
        // Lengths and weights are strings on purpose. They are parsed into exact
        // integers, so '0.1' means a tenth of a millimetre and never
        // 0.09999999999999999. Plain integers and fractions like '3/16' work too.
        [
            'id' => 'mug',
            'quantity' => 6,
            'dimensions' => ['length' => '120', 'width' => '120', 'height' => '100'],
            'weight' => '400 g',
        ],
        [
            'id' => 'plate',
            'quantity' => 8,
            'dimensions' => ['length' => '260', 'width' => '260', 'height' => '20'],
            'weight' => '600 g',
        ],
        // Too long for the box in every orientation, so it cannot be placed.
        [
            'id' => 'ladder',
            'quantity' => 1,
            'dimensions' => ['length' => '1800', 'width' => '300', 'height' => '100'],
            'weight' => '6 kg',
        ],
    ],
    'containers' => [
        [
            'id' => 'box',
            'inner_dimensions' => ['length' => '400', 'width' => '400', 'height' => '400'],
            'max_payload' => '15 kg',
            'cost_minor' => 180,
        ],
    ],
];

$result = $packer->pack($request);

printf("status: %s\n", $result['status']);
printf("containers opened: %d\n", count($result['containers']));

foreach ($result['containers'] as $index => $container) {
    printf("\nbox #%d: %d placement(s)\n", $index + 1, count($container['placements']));
    foreach ($container['placements'] as $placement) {
        // Every measurement arrives as ['ticks', 'value', 'unit']: `ticks` is the exact
        // integer the engine reasoned about, `value` is that number written for a human.
        $position = $placement['position'];
        printf(
            "  %-8s at (%s, %s, %s) %s  orientation %s\n",
            $placement['item_type'],
            $position['x']['value'],
            $position['y']['value'],
            $position['z']['value'],
            $position['x']['unit'],
            $placement['orientation'],
        );
    }
}

// A refusal is an answer, not an error.
if ($result['unpacked_items'] !== []) {
    echo "\nnot packed:\n";
    foreach ($result['unpacked_items'] as $unpacked) {
        printf("  %-10s %s\n", $unpacked['item_id'], $unpacked['reason']);
    }
}
