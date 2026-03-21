<?php

/**
 * PHP Remotion 高级示例
 *
 * 展示更复杂的动画效果和高级用法。
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Yangweijie\Remotion\Remotion;
use Yangweijie\Remotion\Animation\Easing;
use Yangweijie\Remotion\Animation\Interpolate;
use Yangweijie\Remotion\Animation\Spring;
use Yangweijie\Remotion\Helpers\Color;
use Yangweijie\Remotion\Core\RenderContext;
use Yangweijie\Remotion\Core\Sequence;

// ============================================================
// 示例 1: 彩虹渐变动画
// ============================================================
$rainbowComp = Remotion::composition(
    id: 'rainbow-animation',
    renderer: function (RenderContext $ctx): \GdImage {
        $frame  = $ctx->getCurrentFrame();
        $config = $ctx->getVideoConfig();

        $canvas = Remotion::createCanvas($config->width, $config->height, [0, 0, 0]);

        // 彩虹颜色数组
        $rainbowColors = [
            [255, 0, 0],     // 红
            [255, 127, 0],   // 橙
            [255, 255, 0],   // 黄
            [0, 255, 0],     // 绿
            [0, 0, 255],     // 蓝
            [75, 0, 130],    // 靛
            [148, 0, 211],   // 紫
        ];

        $bandHeight = $config->height / count($rainbowColors);

        foreach ($rainbowColors as $i => $rgb) {
            // 每条彩虹带根据帧数左右摆动
            $offset = sin(($frame + $i * 10) / 20.0 * M_PI) * 30;
            $startX = (int) ($config->width / 2 - 200 + $offset);
            $endX = (int) ($config->width / 2 + 200 + $offset);

            $color = imagecolorallocate($canvas, $rgb[0], $rgb[1], $rgb[2]);
            imagefilledrectangle(
                $canvas,
                $startX,
                (int) ($i * $bandHeight),
                $endX,
                (int) (($i + 1) * $bandHeight),
                $color
            );
        }

        return $canvas;
    },
    durationInFrames: 120,
    fps: 30,
    width: 640,
    height: 360,
);

// ============================================================
// 示例 2: 脉冲波纹效果
// ============================================================
$pulseComp = Remotion::composition(
    id: 'pulse-effect',
    renderer: function (RenderContext $ctx): \GdImage {
        $frame  = $ctx->getCurrentFrame();
        $config = $ctx->getVideoConfig();

        $canvas = Remotion::createCanvas($config->width, $config->height, [10, 10, 20]);
        $cx = $config->width / 2;
        $cy = $config->height / 2;

        // 多层波纹
        for ($i = 0; $i < 5; $i++) {
            $delay = $i * 8;
            $localFrame = max(0, $frame - $delay);

            // 波纹半径随时间扩大
            $baseRadius = 30 + $localFrame * 5;
            $alpha = max(0, 1 - $localFrame / 60.0);

            $gdColor = imagecolorallocatealpha(
                $canvas,
                100 + $i * 30,
                200,
                255,
                (int) ((1 - $alpha) * 127)
            );

            imagesetthickness($canvas, 3 - $i / 2);
            imageellipse(
                $canvas,
                (int) $cx,
                (int) $cy,
                (int) ($baseRadius * 2),
                (int) ($baseRadius * 2),
                $gdColor
            );
        }

        // 中心发光球
        $pulse = sin($frame / 10.0 * M_PI) * 0.3 + 0.7;
        $radius = (int) (30 * $pulse);

        $gradient = imagecreatetruecolor($radius * 2, $radius * 2);
        imagealphablending($gradient, false);
        imagesavealpha($gradient, true);

        $centerColor = imagecolorallocatealpha($gradient, 255, 255, 255, 0);
        $edgeColor = imagecolorallocatealpha($gradient, 100, 200, 255, 127);
        imagefilledellipse($gradient, $radius, $radius, $radius * 2, $radius * 2, $centerColor);

        // 简单的径向渐变
        for ($r = $radius; $r > 0; $r--) {
            $a = (int) ((1 - $r / $radius) * 127);
            $c = imagecolorallocatealpha($gradient, 255, 255, 255, max(0, min(127, $a)));
            imageellipse($gradient, $radius, $radius, $r * 2, $r * 2, $c);
        }

        imagecopy($canvas, $gradient, (int) ($cx - $radius), (int) ($cy - $radius), 0, 0, $radius * 2, $radius * 2);
        imagedestroy($gradient);

        return $canvas;
    },
    durationInFrames: 90,
    fps: 30,
    width: 640,
    height: 360,
);

// ============================================================
// 示例 3: 文字打字机效果
// ============================================================
$typewriterComp = Remotion::composition(
    id: 'typewriter',
    renderer: function (RenderContext $ctx): \GdImage {
        $frame  = $ctx->getCurrentFrame();
        $config = $ctx->getVideoConfig();
        $text   = $ctx->getProp('text', 'Hello, PHP Remotion!');

        $canvas = Remotion::createCanvas($config->width, $config->height, [30, 30, 30]);

        // 计算显示的字符数
        $charsToShow = (int) Interpolate::interpolate(
            $frame,
            [0, 30, strlen($text) + 30],
            [0, strlen($text), strlen($text)],
            ['extrapolateRight' => 'clamp']
        );

        $visibleText = substr($text, 0, $charsToShow);

        // 光标闪烁
        $cursorVisible = ($frame % 30) < 20;

        $textLayer = Remotion::textLayer($visibleText . ($cursorVisible ? '_' : ' '), [
            'fontSize' => 4,
            'r'        => 0,
            'g'        => 255,
            'b'        => 0,
            'align'    => 'left',
        ]);
        $textLayer->drawOn($canvas, 50, (int) ($config->height / 2));

        return $canvas;
    },
    durationInFrames: 150,
    fps: 30,
    width: 640,
    height: 360,
    defaultProps: ['text' => 'Typewriter Effect Demo'],
);

// ============================================================
// 示例 4: 进度条动画
// ============================================================
$progressBarComp = Remotion::composition(
    id: 'progress-bar',
    renderer: function (RenderContext $ctx): \GdImage {
        $frame  = $ctx->getCurrentFrame();
        $config = $ctx->getVideoConfig();

        $canvas = Remotion::createCanvas($config->width, $config->height, [20, 20, 20]);

        // 进度值
        $progress = Interpolate::interpolate(
            $frame,
            [0, 60, 120],
            [0.0, 1.0, 0.0],
            [
                'easing' => Easing::easeInOut(...),
            ]
        );

        $barWidth = 400;
        $barHeight = 40;
        $barX = ($config->width - $barWidth) / 2;
        $barY = ($config->height - $barHeight) / 2;

        // 背景条
        $bgColor = imagecolorallocate($canvas, 50, 50, 50);
        imagefilledrectangle($canvas, $barX - 2, $barY - 2, $barX + $barWidth + 2, $barY + $barHeight + 2, $bgColor);

        // 进度条背景
        $trackColor = imagecolorallocate($canvas, 30, 30, 30);
        imagefilledrectangle($canvas, $barX, $barY, $barX + $barWidth, $barY + $barHeight, $trackColor);

        // 渐变进度
        $progressWidth = (int) ($barWidth * $progress);
        if ($progressWidth > 0) {
            // 使用多层颜色创建渐变效果
            $steps = 20;
            for ($i = 0; $i < $steps; $i++) {
                $t = $i / $steps;
                $r = (int) Interpolate::interpolate($t, [0, 1], [0, 100]);
                $g = (int) Interpolate::interpolate($t, [0, 1], [200, 255]);
                $b = (int) Interpolate::interpolate($t, [0, 1], [0, 200]);

                $segmentWidth = $progressWidth / $steps;
                $c = imagecolorallocate($canvas, $r, $g, $b);
                imagefilledrectangle(
                    $canvas,
                    (int) ($barX + $i * $segmentWidth),
                    $barY,
                    (int) ($barX + ($i + 1) * $segmentWidth),
                    $barY + $barHeight,
                    $c
                );
            }
        }

        // 百分比文字
        $percentText = sprintf('%d%%', (int) ($progress * 100));
        $textLayer = Remotion::textLayer($percentText, [
            'fontSize' => 4,
            'r'        => 255,
            'g'        => 255,
            'b'        => 255,
            'align'    => 'center',
        ]);
        $textLayer->drawOn($canvas, 0, (int) ($config->height / 2));

        return $canvas;
    },
    durationInFrames: 150,
    fps: 30,
    width: 640,
    height: 360,
);

// ============================================================
// 示例 5: 图片轮播 + 过渡效果
// ============================================================
$slideshowComp = Remotion::composition(
    id: 'slideshow',
    renderer: function (RenderContext $ctx): \GdImage {
        $frame  = $ctx->getCurrentFrame();
        $config = $ctx->getVideoConfig();

        $canvas = Remotion::createCanvas($config->width, $config->height, [0, 0, 0]);

        // 每张幻灯片持续时间
        $slideDuration = 60;
        $currentSlide = (int) ($frame / $slideDuration) % 3;
        $localFrame = $frame % $slideDuration;

        // 创建假彩色背景代替真实图片
        $slideColors = [
            [255, 100, 100],  // 红色调
            [100, 255, 100],  // 绿色调
            [100, 100, 255],  // 蓝色调
        ];

        $nextSlide = ($currentSlide + 1) % 3;
        $currentColor = $slideColors[$currentSlide];
        $nextColor = $slideColors[$nextSlide];

        // 淡入淡出过渡
        $transitionProgress = Interpolate::interpolate(
            $localFrame,
            [$slideDuration - 15, $slideDuration],
            [0.0, 1.0],
            ['easing' => Easing::easeInOut(...)]
        );

        for ($y = 0; $y < $config->height; $y++) {
            $t = $y / $config->height;
            $r1 = $currentColor[0] * (1 - $t) + $currentColor[1] * $t;
            $g1 = $currentColor[1] * (1 - $t) + $currentColor[2] * $t;
            $b1 = $currentColor[2] * (1 - $t) + $currentColor[0] * $t;

            $r2 = $nextColor[0] * (1 - $t) + $nextColor[1] * $t;
            $g2 = $nextColor[1] * (1 - $t) + $nextColor[2] * $t;
            $b2 = $nextColor[2] * (1 - $t) + $nextColor[0] * $t;

            $r = (int) ($r1 * (1 - $transitionProgress) + $r2 * $transitionProgress);
            $g = (int) ($g1 * (1 - $transitionProgress) + $g2 * $transitionProgress);
            $b = (int) ($b1 * (1 - $transitionProgress) + $b2 * $transitionProgress);

            $color = imagecolorallocate($canvas, max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
            imageline($canvas, 0, $y, $config->width, $y, $color);
        }

        // 页码指示器
        $indicatorY = $config->height - 30;
        $indicatorSpacing = 20;
        $startX = ($config->width - (3 - 1) * $indicatorSpacing) / 2;

        for ($i = 0; $i < 3; $i++) {
            $x = (int) ($startX + $i * $indicatorSpacing);
            $isActive = ($i === $currentSlide);

            $color = $isActive
                ? imagecolorallocate($canvas, 255, 255, 255)
                : imagecolorallocate($canvas, 100, 100, 100);

            imagefilledellipse($canvas, $x, $indicatorY, $isActive ? 12 : 8, $isActive ? 12 : 8, $color);
        }

        // Slide 标题
        $title = "Slide " . ($currentSlide + 1);
        $textLayer = Remotion::textLayer($title, [
            'fontSize' => 5,
            'r'        => 255,
            'g'        => 255,
            'b'        => 255,
            'align'    => 'center',
        ]);
        $textLayer->setOpacity($transitionProgress < 0.5 ? 1 : 1 - ($transitionProgress - 0.5) * 2);
        $textLayer->drawOn($canvas, 0, 50);

        return $canvas;
    },
    durationInFrames: 180,
    fps: 30,
    width: 640,
    height: 360,
);

// ============================================================
// 注册并渲染
// ============================================================
Remotion::registerRoot([
    $rainbowComp,
    $pulseComp,
    $typewriterComp,
    $progressBarComp,
    $slideshowComp,
]);

$targetId = $argv[1] ?? 'progress-bar';
$output = $argv[2] ?? ($targetId . '.gif');

$comp = \Yangweijie\Remotion\Core\Composition::get($targetId);
if (!$comp) {
    echo "Available compositions:\n";
    foreach (\Yangweijie\Remotion\Core\Composition::all() as $id => $c) {
        echo "  - {$id}\n";
    }
    echo "\nUsage: php advanced-examples.php <compositionId> [output.gif]\n";
    exit(1);
}

echo "\nPHP Remotion - Rendering '{$targetId}'\n";
echo str_repeat('=', 50) . "\n";

$renderer = Remotion::render($comp);
$renderer->onProgress(function (int $frame, int $total): void {
    $pct = (int) round($frame / $total * 100);
    $bar = str_repeat('█', (int) ($pct / 5)) . str_repeat('░', 20 - (int) ($pct / 5));
    echo "\r  [{$bar}] {$pct}% ({$frame}/{$total})";
});

$start = microtime(true);

use Yangweijie\Remotion\Helpers\Pipeline;

Pipeline::make($renderer)
    ->tap(fn ($r) => print("  Starting render...\n"))
    ->pipe(fn ($r) => $r->renderToGif($output))
    ->tap(fn ($success) => print("\n\n  Render " . ($success ? 'completed!' : 'failed!') . "\n"))
    ->get();

$elapsed = round(microtime(true) - $start, 2);
echo "  Output: {$output} ({$elapsed}s)\n\n";
