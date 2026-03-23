<?php

/**
 * RenderContext 增强和渲染质量控制演示
 *
 * 演示功能：
 * 1. RenderContext 增强 - 内置插值、缓动、弹簧动画
 * 2. 渲染质量控制 - JPEG/PNG/WebP/AVIF 质量配置
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Yangweijie\Remotion\Core\Composition;
use Yangweijie\Remotion\Rendering\Renderer;

echo "🎬 PHP Remotion 增强功能演示\n";
echo "==============================\n\n";

// ========== 演示 1: RenderContext 增强 ==========
echo "🎨 演示 1: RenderContext 动画工具\n";
echo "----------------------------------\n";

$animComp = Composition::fromClosure(
    id: 'animation-demo',
    renderer: function ($ctx) {
        $config = $ctx->getVideoConfig();
        $frame = $ctx->getCurrentFrame();

        // 创建画布
        $canvas = imagecreatetruecolor($config->width, $config->height);

        // 背景色（随时间变化）
        $bgColor = $ctx->interpolateColors(
            [0, $config->durationInFrames - 1],
            [[20, 20, 40], [40, 20, 60]]
        );
        $bg = imagecolorallocate($canvas, $bgColor[0], $bgColor[1], $bgColor[2]);
        imagefill($canvas, 0, 0, $bg);

        // 1. 淡入动画（前30帧）
        $opacity = $ctx->fadeIn(0, 30);
        if ($opacity !== null) {
            $textColor = imagecolorallocatealpha($canvas, 255, 255, 255, (int)(127 * (1 - $opacity)));
            imagestring($canvas, 5, 20, 20, "Fade In", $textColor);
        }

        // 2. 缩放动画（20-50帧）
        $scale = $ctx->scale(20, 30, 0.5, 1.5, ['easing' => $ctx->easeOut()]);
        if ($scale !== null) {
            $size = (int)(50 * $scale);
            $halfSize = (int)($size / 2);
            $boxColor = imagecolorallocate($canvas, 255, 100, 100);
            imagefilledrectangle($canvas, 150 - $halfSize, 100 - $halfSize, 150 + $halfSize, 100 + $halfSize, $boxColor);
        }

        // 3. 旋转动画（使用弹簧效果）
        $springProgress = $ctx->spring([
            'stiffness' => 100,
            'damping' => 10,
            'from' => 0,
            'to' => 1,
        ]);
        // 使用弹簧值控制位置
        $x = (int)(100 + $springProgress * 200);
        $circleColor = imagecolorallocate($canvas, 100, 255, 100);
        imagefilledellipse($canvas, $x, 180, 30, 30, $circleColor);

        // 4. 滑入动画（40-60帧）
        $offset = $ctx->slideIn('right', 40, 20, ['easing' => $ctx->easeInOut()]);
        if ($offset !== null) {
            $slideColor = imagecolorallocate($canvas, 100, 100, 255);
            imagestring($canvas, 4, 250 + (int)$offset['x'], 50, "Slide In", $slideColor);
        }

        // 5. 相对进度指示器
        $progress = $ctx->getRelativeProgress(0, $config->durationInFrames);
        $barWidth = (int)(300 * $progress);
        $barBg = imagecolorallocate($canvas, 60, 60, 80);
        $barFg = imagecolorallocate($canvas, 100, 200, 100);
        imagefilledrectangle($canvas, 20, 220, 320, 230, $barBg);
        imagefilledrectangle($canvas, 20, 220, 20 + $barWidth, 230, $barFg);

        // 显示帧信息
        $infoColor = imagecolorallocate($canvas, 200, 200, 200);
        $info = "Frame: {$frame} / {$config->durationInFrames}";
        imagestring($canvas, 2, 20, 200, $info, $infoColor);

        return $canvas;
    },
    durationInFrames: 90,
    fps: 30,
    width: 400,
    height: 250,
);

$animDir = __DIR__ . '/../output/animation-demo';
if (!is_dir($animDir)) {
    mkdir($animDir, 0755, true);
}

$renderer = new Renderer($animComp);
$renderer->onProgress(function ($frame, $total) {
    if ($frame % 30 === 0) {
        echo "  渲染进度: {$frame}/{$total}\n";
    }
});

echo "渲染动画帧...\n";
$renderer->renderToFrames($animDir, 'png');
echo "✅ 动画渲染完成!\n\n";

// ========== 演示 2: 渲染质量控制 ==========
echo "📊 演示 2: 渲染质量控制\n";
echo "-----------------------\n";

// 创建一个简单的测试合成
$qualityComp = Composition::fromClosure(
    id: 'quality-demo',
    renderer: function ($ctx) {
        $config = $ctx->getVideoConfig();
        $canvas = imagecreatetruecolor($config->width, $config->height);

        // 创建渐变背景（测试色彩质量）
        for ($y = 0; $y < $config->height; $y++) {
            $progress = $y / $config->height;
            $r = (int)(255 * $progress);
            $g = (int)(128 + 127 * sin($progress * M_PI));
            $b = (int)(255 * (1 - $progress));
            $color = imagecolorallocate($canvas, $r, $g, $b);
            imageline($canvas, 0, $y, $config->width, $y, $color);
        }

        // 添加文字
        $textColor = imagecolorallocate($canvas, 255, 255, 255);
        imagestring($canvas, 5, 50, 50, "Quality Test", $textColor);

        return $canvas;
    },
    durationInFrames: 1, // 只渲染一帧
    fps: 30,
    width: 400,
    height: 300,
);

$qualityDir = __DIR__ . '/../output/quality-demo';
if (!is_dir($qualityDir)) {
    mkdir($qualityDir, 0755, true);
}

// 测试不同质量设置
$qualityTests = [
    ['format' => 'jpeg', 'quality' => 50, 'name' => 'low'],
    ['format' => 'jpeg', 'quality' => 85, 'name' => 'medium'],
    ['format' => 'jpeg', 'quality' => 95, 'name' => 'high'],
    ['format' => 'png', 'quality' => 0, 'name' => 'uncompressed'],
    ['format' => 'png', 'quality' => 6, 'name' => 'compressed'],
];

echo "测试不同质量设置:\n";
foreach ($qualityTests as $test) {
    $renderer = new Renderer($qualityComp);

    if ($test['format'] === 'jpeg') {
        $renderer->withJpegQuality($test['quality']);
    } elseif ($test['format'] === 'png') {
        $renderer->withPngCompression($test['quality']);
    }

    $filename = $qualityDir . '/test_' . $test['format'] . '_' . $test['name'] . '.' . $test['format'];
    $renderer->renderFrameToFile(0, $filename, $test['format']);

    $size = filesize($filename);
    $sizeKb = round($size / 1024, 2);
    echo "  {$test['format']} ({$test['name']}): {$sizeKb} KB\n";
}

// 批量设置质量
echo "\n批量设置质量参数:\n";
$renderer = new Renderer($qualityComp);
$renderer->withQuality([
    'jpeg' => 90,
    'png' => 9,
    'webp' => 85,
]);

$quality = $renderer->getQuality();
echo "  JPEG 质量: {$quality['jpeg']}\n";
echo "  PNG 压缩: {$quality['png']}\n";
echo "  WebP 质量: {$quality['webp']}\n";
echo "  AVIF 质量: {$quality['avif']}\n";

echo "\n✅ 渲染质量控制演示完成!\n";
echo "\n提示:\n";
echo "  - RenderContext 现在内置了 interpolate()、spring() 等方法\n";
echo "  - fadeIn/fadeOut/scale/rotate/slideIn 等动画方法可直接使用\n";
echo "  - 使用 withJpegQuality()、withPngCompression() 控制输出质量\n";
echo "  - 支持 WebP 和 AVIF 格式（如果 PHP 扩展可用）\n";
