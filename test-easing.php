<?php
require_once __DIR__ . '/vendor/autoload.php';

use Yangweijie\Remotion\Animation\Easing;

$easingType = $argv[1] ?? 'bounce';

echo "Easing 函数测试: $easingType\n";
echo str_repeat('=', 50) . "\n\n";

$values = [0, 0.25, 0.5, 0.75, 1.0];

foreach ($values as $t) {
    $result = match($easingType) {
        'linear' => Easing::linear($t),
        'ease' => Easing::ease($t),
        'easeIn' => Easing::easeIn($t),
        'easeOut' => Easing::easeOut($t),
        'easeInOut' => Easing::easeInOut($t),
        'quad' => Easing::quad($t),
        'cubic' => Easing::cubic($t),
        'sin' => Easing::sin($t),
        'circle' => Easing::circle($t),
        'exp' => Easing::exp($t),
        'bounce' => Easing::bounce($t),
        'elastic' => Easing::elastic(1.0)($t),
        'back' => Easing::back(1.70158)($t),
        'bezier' => Easing::bezier(0.25, 0.1, 0.25, 1.0)($t),
        default => $t,
    };

    $bar = str_repeat('█', (int) ($result * 20)) . str_repeat('░', 20 - (int) ($result * 20));
    printf("t=%.2f | %s | %.4f\n", $t, $bar, $result);
}
