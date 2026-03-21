<?php
require 'vendor/autoload.php';

use Yangweijie\Remotion\Remotion;
use Yangweijie\Remotion\Core\RenderContext;
use Yangweijie\Remotion\Animation\Spring;

// 创建一个简单的弹簧动画合成（20 帧，低分辨率，快速测试）
$comp = Remotion::composition(
    id: 'quick-test',
    renderer: function(RenderContext $ctx): \GdImage {
        $frame = $ctx->getCurrentFrame();
        $cfg   = $ctx->getVideoConfig();

        // 弹簧缩放（对标 remotion spring()）
        $scale = Spring::spring($frame, $cfg->fps, ['stiffness' => 200, 'damping' => 15]);

        // 颜色插值（对标 remotion interpolateColors()）
        $bgRgb = Remotion::interpolateColors($frame, [0, 20], [[20, 20, 80], [80, 20, 80]]);

        $canvas = Remotion::createCanvas($cfg->width, $cfg->height, $bgRgb);

        // 绘制黄色圆（使用弹簧缩放）
        $r = (int) ($cfg->height * 0.3 * $scale);
        $c = imagecolorallocate($canvas, 255, 220, 50);
        imagefilledellipse($canvas, (int) ($cfg->width / 2), (int) ($cfg->height / 2), $r * 2, $r * 2, $c);

        return $canvas;
    },
    durationInFrames: 20,
    fps: 10,
    width: 160,
    height: 120,
);

$out = 'test-output.gif';
$renderer = Remotion::render($comp);
$renderer->renderToGif($out);
echo "GIF saved to: {$out} (" . filesize($out) . " bytes)\n";
echo "SUCCESS!\n";
