<?php
require 'vendor/autoload.php';

use Yangweijie\Remotion\Core\VideoConfig;
use Yangweijie\Remotion\Core\Composition;
use Yangweijie\Remotion\Animation\Interpolate;
use Yangweijie\Remotion\Animation\Easing;
use Yangweijie\Remotion\Animation\Spring;
use Yangweijie\Remotion\Helpers\Pipeline;
use Yangweijie\Remotion\Helpers\Color;
use Yangweijie\Remotion\Core\RenderContext;
use Yangweijie\Remotion\Remotion;

echo "=== PHP Remotion - Verification Test ===\n\n";

// 1. VideoConfig
$config = VideoConfig::hd(60, 30);
echo "1. VideoConfig: " . $config . "\n";
assert($config->width === 1920);
assert($config->fps === 30);
assert($config->getDurationInSeconds() === 2.0);
echo "   Duration: " . $config->getDurationInSeconds() . "s [OK]\n";

// 2. Interpolate
$val = Interpolate::interpolate(15, [0, 30], [0, 1]);
echo "\n2. interpolate(15, [0,30], [0,1]) = " . $val . "\n";
assert(abs($val - 0.5) < 0.001);
echo "   [OK] expected: 0.5\n";

// 3. Easing
$eased = Easing::easeOut(0.5);
echo "\n3. Easing::easeOut(0.5) = " . $eased . "\n";
assert($eased > 0.5); // easeOut 在中点应该大于线性值
echo "   [OK] > 0.5 (ease-out curve)\n";

// 4. Spring
$springVal = Spring::spring(frame: 30, fps: 30, config: ['stiffness' => 100, 'damping' => 10]);
echo "\n4. Spring(frame=30, fps=30) = " . $springVal . "\n";
assert($springVal > 0 && $springVal <= 1.5); // 弹簧可能略微超过 1
echo "   [OK] in range (0, 1.5)\n";

// 5. Pipeline
$result = Pipeline::make(0)
    ->pipe(fn($x) => $x + 1)
    ->pipe(fn($x) => $x * 10)
    ->tap(fn($x) => null)
    ->get();
echo "\n5. Pipeline(0 +1 *10) = " . $result . "\n";
assert($result === 10);
echo "   [OK] expected: 10\n";

// 6. Color
$color = Color::fromHex('#ff6600');
echo "\n6. Color::fromHex('#ff6600') = " . $color . " [" . implode(',', $color->toRgb()) . "]\n";
assert($color->getR() === 255);
assert($color->getG() === 102);
echo "   [OK]\n";

// 7. Composition
$comp = Remotion::composition(
    id: 'test-comp',
    renderer: function(RenderContext $ctx): \GdImage {
        $img = imagecreatetruecolor($ctx->videoConfig->width, $ctx->videoConfig->height);
        $bg  = imagecolorallocate($img, 30, 30, 80);
        imagefill($img, 0, 0, $bg);
        return $img;
    },
    durationInFrames: 30,
    fps: 30,
    width: 320,
    height: 240,
);
echo "\n7. Composition: " . $comp . "\n";

// 8. RenderContext
$ctx = new RenderContext(15, new VideoConfig(30, 30, 320, 240));
echo "\n8. RenderContext:\n";
echo "   frame=" . $ctx->getCurrentFrame() . "\n";
echo "   progress=" . $ctx->getProgress() . "\n";
assert($ctx->getCurrentFrame() === 15);
assert(abs($ctx->getProgress() - (15/29)) < 0.001);
echo "   [OK]\n";

// 9. Render single frame
$frame = $comp->renderFrame(0);
echo "\n9. Single frame render: " . imagesx($frame) . "x" . imagesy($frame) . "\n";
assert(imagesx($frame) === 320);
assert(imagesy($frame) === 240);
imagedestroy($frame);
echo "   [OK]\n";

// 10. Global helpers
$helpVal = interpolate(10, [0, 20], [0.0, 1.0]);
echo "\n10. Global interpolate(10, [0,20], [0,1]) = " . $helpVal . "\n";
assert(abs($helpVal - 0.5) < 0.001);
echo "    [OK]\n";

echo "\n=== All Tests Passed! ===\n";
echo "\nProject structure:\n";
$dirs = [
    'src/Contracts',
    'src/Core',
    'src/Animation',
    'src/Layers',
    'src/Rendering',
    'src/Helpers',
    'src/CLI',
];
foreach ($dirs as $dir) {
    $files = glob($dir . '/*.php');
    echo "  {$dir}/  (" . count($files) . " files)\n";
    foreach ($files as $f) {
        echo "    - " . basename($f) . "\n";
    }
}
