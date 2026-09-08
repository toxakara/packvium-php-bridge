<?php
/**
 * Shapes through the bridge: the same answer from whichever backend loaded.
 *
 * Run it:
 *
 *     php examples/shapes.php                              # pure PHP
 *     php examples/shapes.php /path/to/libpackvium.dylib   # compiled engine
 *
 * `basic.php` shows that the bridge picks a backend for you, silently, and that you
 * should log which one. This example answers the question that follows: does the
 * accelerated path support everything the pure one does?
 *
 * It does, and that is worth showing with the newest capability rather than the oldest.
 * `shape_type` says an item is not simply its declared box -- `convex_hull` narrows it in
 * space, `compressible` in height under load -- and both belong to the shared JSON
 * contract rather than to any one engine. The bridge forwards the request untouched, so
 * the numbers below are the same either way. Run it twice, once with a library path and
 * once without, and diff the output: it should be identical apart from the backend line.
 *
 * If it ever is not, that is a bug worth reporting rather than a difference to design
 * around.
 */
declare(strict_types=1);

$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
} else {
    require dirname(__DIR__, 4) . '/packvium-php/autoload.php';
    require dirname(__DIR__) . '/src/NativePacker.php';
}

use Packvium\Native\NativePacker;

$packer = new NativePacker($argv[1] ?? null);
printf("answered by the %s backend\n\n", $packer->backend());

const MM = ['units' => ['length' => 'mm']];

function crate(string $length, string $width, string $height): array
{
    return [['id' => 'crate',
             'inner_dimensions' => ['length' => $length, 'width' => $width, 'height' => $height]]];
}

/** Print only what the shape changed: containers, placements and unused volume. */
function summarise(NativePacker $packer, string $label, array $request): void
{
    $result = $packer->pack($request);
    $placed = 0;
    foreach ($result['containers'] as $container) {
        $placed += count($container['placements']);
    }
    printf("  %-22s %d container(s), %d placed, unused volume %d ppm\n",
        $label, count($result['containers']), $placed, $result['score'][3]);
}

// ------------------------------------------------------------------ convex_hull
//
// Two triangular prisms cut from the same cube along its diagonal. Their bounding boxes
// are identical and each fills the crate alone, so as cuboids the second has nowhere to
// go. As hulls they are complementary halves and share the crate exactly: collisions are
// decided by an exact integer separating-axis test on the vertices, not a box overlap.

const LOWER_WEDGE = [
    ['x' => '0', 'y' => '0', 'z' => '0'], ['x' => '100', 'y' => '0', 'z' => '0'],
    ['x' => '0', 'y' => '100', 'z' => '0'], ['x' => '0', 'y' => '0', 'z' => '100'],
    ['x' => '100', 'y' => '0', 'z' => '100'], ['x' => '0', 'y' => '100', 'z' => '100'],
];
const UPPER_WEDGE = [
    ['x' => '100', 'y' => '100', 'z' => '0'], ['x' => '100', 'y' => '0', 'z' => '0'],
    ['x' => '0', 'y' => '100', 'z' => '0'], ['x' => '100', 'y' => '100', 'z' => '100'],
    ['x' => '100', 'y' => '0', 'z' => '100'], ['x' => '0', 'y' => '100', 'z' => '100'],
];

function wedge(string $id, ?array $vertices): array
{
    $item = ['id' => $id, 'quantity' => 1,
             'dimensions' => ['length' => '100', 'width' => '100', 'height' => '100'],
             'weight' => ['value' => '1', 'unit' => 'kg']];
    if ($vertices !== null) {
        $item['shape_type'] = 'convex_hull';
        $item['hull_vertices'] = $vertices;
    }
    return $item;
}

echo "convex_hull -- two complementary wedges cut from one cube\n";
summarise($packer, 'as cuboids', MM + [
    'items' => [wedge('wedge-lower', null), wedge('wedge-upper', null)],
    'containers' => crate('100', '100', '100'),
]);
summarise($packer, 'as hulls', MM + [
    'items' => [wedge('wedge-lower', LOWER_WEDGE), wedge('wedge-upper', UPPER_WEDGE)],
    'containers' => crate('100', '100', '100'),
]);

// ----------------------------------------------------------------- compressible
//
// `compression_ratio` is the fraction of its own height an item may lose under load;
// `max_compression_pressure_kpa` is where yielding becomes crushing and the load is
// refused instead. `must_be_on_floor` is not decoration -- without it the solver may put
// the brick underneath, nothing bears on the cushion, and the feature never engages.

$cushion = ['id' => 'cushion', 'quantity' => 1,
            'dimensions' => ['length' => '100', 'width' => '100', 'height' => '100'],
            'weight' => ['value' => '2', 'unit' => 'kg'],
            'must_be_on_floor' => true,
            'shape_type' => 'compressible',
            'compression_ratio' => 0.25,
            'max_compression_pressure_kpa' => 100];

$brick = static fn (int $kilograms): array => [
    'id' => 'brick', 'quantity' => 1,
    'dimensions' => ['length' => '100', 'width' => '100', 'height' => '100'],
    'weight' => ['value' => (string) $kilograms, 'unit' => 'kg'],
];

// The crate is 100x100x200 and both items are 100 mm cubes, so rigidly they fill it and
// nothing is unused. Under 101 kg the cushion gives up part of its quarter. One more
// kilogram crosses 100 kPa over its 0.01 m^2 face and the stack is refused instead.
echo "\ncompressible -- a cushion that yields to the load above it\n";
foreach ([101, 102] as $kilograms) {
    summarise($packer, "brick {$kilograms} kg", MM + [
        'items' => [$cushion, $brick($kilograms)],
        'containers' => crate('100', '100', '200'),
    ]);
}
