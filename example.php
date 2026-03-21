<?php

/**
 * PHP Remotion 使用示例
 *
 * 演示如何使用 PHP Remotion 库创建动画合成。
 * 对应 remotion 原版的以下概念：
 *   - Composition      合成
 *   - Sequence         序列
 *   - interpolate()    插值
 *   - spring()         弹簧动画
 *   - useCurrentFrame  当前帧
 *   - useVideoConfig   视频配置
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Yangweijie\Remotion\Animation\Easing;
use Yangweijie\Remotion\Core\RenderContext;
use Yangweijie\Remotion\Remotion;

// ============================================================
// 示例 1：淡入淡出文字动画
// 对标 remotion 中 interpolate + useCurrentFrame 的典型用法
// ============================================================
$fadeTextComp = Remotion::composition(
    id: 'fade-text',
    renderer: function (RenderContext $ctx): \GdImage {
        $frame  = $ctx->getCurrentFrame();
        $config = $ctx->getVideoConfig();
        $text   = $ctx->getProp('text', 'Hello, PHP Remotion!');

        // 创建画布（深色背景）
        $canvas = Remotion::createCanvas($config->width, $config->height, [20, 20, 50]);

        // 淡入：0-30 帧，不透明度从 0 到 1
        // 等同于 remotion: interpolate(frame, [0, 30], [0, 1], { extrapolateRight: 'clamp' })
        $opacity = Remotion::interpolate($frame, [0, 30], [0.0, 1.0], [
            'extrapolateLeft'  => 'clamp',
            'extrapolateRight' => 'clamp',
        ]);

        // 淡出：60-90 帧，不透明度从 1 到 0
        if ($frame > 60) {
            $opacity = Remotion::interpolate($frame, [60, 90], [1.0, 0.0], [
                'extrapolateLeft'  => 'clamp',
                'extrapolateRight' => 'clamp',
            ]);
        }

        // Y 轴位移动画（从下方滑入）
        $translateY = Remotion::interpolate($frame, [0, 30], [50, 0], [
            'easing'           => Easing::easeOut(...),
            'extrapolateLeft'  => 'clamp',
            'extrapolateRight' => 'clamp',
        ]);

        // 绘制文字图层
        $textLayer = Remotion::textLayer($text, [
            'fontSize' => 5,          // GD 内置字体大小 (1-5)
            'r'        => 255,
            'g'        => 255,
            'b'        => 255,
            'align'    => 'center',
            'maxWidth' => $config->width,
        ]);
        $textLayer->setOpacity($opacity);
        $textLayer->drawOn($canvas, 0, (int) ($config->height / 2 + $translateY));

        return $canvas;
    },
    durationInFrames: 90,
    fps: 30,
    width: 640,
    height: 360,
    defaultProps: ['text' => 'Hello, PHP Remotion!'],
);

// ============================================================
// 示例 2：弹簧缩放动画 + 颜色渐变背景
// 对标 remotion 中 spring() 的用法
// ============================================================
$springComp = Remotion::composition(
    id: 'spring-scale',
    renderer: function (RenderContext $ctx): \GdImage {
        $frame  = $ctx->getCurrentFrame();
        $config = $ctx->getVideoConfig();

        // 弹簧动画：对标 remotion 的 spring({ frame, fps, config: { stiffness: 100, damping: 12 } })
        $scale = Remotion::spring($frame, $config->fps, [
            'stiffness' => 100,
            'damping'   => 12,
            'mass'      => 1,
            'from'      => 0.3,
            'to'        => 1.0,
        ]);

        // 颜色插值背景（从蓝色渐变到紫色）
        $bgColor = Remotion::interpolateColors($frame, [0, 60], [
            [20, 20, 120],   // 深蓝
            [100, 20, 100],  // 紫色
        ]);

        $canvas = Remotion::createCanvas($config->width, $config->height);

        // 渐变背景
        $gradient = Remotion::gradientLayer(
            $config->width,
            $config->height,
            ['r' => $bgColor[0], 'g' => $bgColor[1], 'b' => $bgColor[2]],
            ['r' => max(0, $bgColor[0] - 50), 'g' => max(0, $bgColor[1] - 20), 'b' => $bgColor[2]],
            'vertical',
        );
        $gradient->drawOn($canvas);

        // 中心圆（使用弹簧缩放）
        $radius = (int) ($config->height * 0.2 * $scale);
        $cx     = $config->width  / 2;
        $cy     = $config->height / 2;
        $color  = imagecolorallocate($canvas, 255, 200, 50);
        imagefilledellipse($canvas, (int) $cx, (int) $cy, $radius * 2, $radius * 2, $color);

        return $canvas;
    },
    durationInFrames: 60,
    fps: 30,
    width: 640,
    height: 360,
);

// ============================================================
// 示例 3：多 Sequence 合成（对标 remotion 的 <Sequence> 组合）
// ============================================================
$multiSeqComp = Remotion::composition(
    id: 'multi-sequence',
    renderer: function (RenderContext $ctx): \GdImage {
        $frame  = $ctx->getCurrentFrame();
        $config = $ctx->getVideoConfig();

        $canvas = Remotion::createCanvas($config->width, $config->height, [10, 10, 10]);

        // Sequence 1：0-45 帧，红色块从左滑入
        $seq1 = Remotion::sequence(
            'slide-in-red',
            function (RenderContext $localCtx) use ($config): \GdImage {
                $localFrame = $localCtx->getCurrentFrame(); // 从 0 开始的本地帧
                $x          = Remotion::interpolate($localFrame, [0, 30], [-200, 50], [
                    'easing'           => Easing::easeOut(...),
                    'extrapolateRight' => 'clamp',
                ]);
                $img = Remotion::createCanvas($config->width, $config->height);
                $red = Remotion::colorLayer(200, 150, 255, 50, 50);
                $red->drawOn($img, (int) $x, 100);
                return $img;
            },
            from: 0,
            durationInFrames: 45,
        );

        // Sequence 2：30-90 帧，蓝色块从右滑入
        $seq2 = Remotion::sequence(
            'slide-in-blue',
            function (RenderContext $localCtx) use ($config): \GdImage {
                $localFrame = $localCtx->getCurrentFrame();
                $x          = Remotion::interpolate($localFrame, [0, 30], [$config->width, $config->width - 250], [
                    'easing'           => Easing::easeOut(...),
                    'extrapolateRight' => 'clamp',
                ]);
                $img  = Remotion::createCanvas($config->width, $config->height);
                $blue = Remotion::colorLayer(200, 150, 50, 50, 255);
                $blue->drawOn($img, (int) $x, 200);
                return $img;
            },
            from: 30,
            durationInFrames: 60,
        );

        // 渲染所有激活的 Sequence
        foreach ([$seq1, $seq2] as $seq) {
            $seqImage = $seq->render($ctx, $config);
            if ($seqImage !== null) {
                imagecopy($canvas, $seqImage, 0, 0, 0, 0, $config->width, $config->height);
                imagedestroy($seqImage);
            }
        }

        return $canvas;
    },
    durationInFrames: 90,
    fps: 30,
    width: 640,
    height: 360,
);

// ============================================================
// 注册所有合成（对标 remotion 的 registerRoot()）
// ============================================================
Remotion::registerRoot([
    $fadeTextComp,
    $springComp,
    $multiSeqComp,
]);

// ============================================================
// 渲染示例（根据命令行参数决定渲染哪个合成）
// ============================================================

$targetId = $argv[1] ?? 'spring-scale';
$output   = $argv[2] ?? ($targetId . '.gif');

$comp = \Yangweijie\Remotion\Core\Composition::get($targetId);
if (!$comp) {
    echo "Available compositions: " . implode(', ', array_keys(\Yangweijie\Remotion\Core\Composition::all())) . "\n";
    echo "Usage: php example.php <compositionId> [output.gif]\n";
    exit(1);
}

echo "\nPHP Remotion - Rendering '{$targetId}'\n";
echo str_repeat('=', 50) . "\n";
echo (string) $comp . "\n\n";

$renderer = Remotion::render($comp);
$renderer->onProgress(function (int $frame, int $total): void {
    $pct   = (int) round($frame / $total * 100);
    $bar   = str_repeat('█', (int) ($pct / 5)) . str_repeat('░', 20 - (int) ($pct / 5));
    echo "\r  [{$bar}] {$pct}% ({$frame}/{$total})";
});

$start = microtime(true);

// 使用 Pipeline 链式处理渲染流程
use Yangweijie\Remotion\Helpers\Pipeline;

Pipeline::make($renderer)
    ->tap(fn ($r) => print("  Starting render...\n"))
    ->pipe(fn ($r) => $r->renderToGif($output))
    ->tap(fn ($success) => print("\n\n  Render " . ($success ? 'completed!' : 'failed!') . "\n"))
    ->get();

$elapsed = round(microtime(true) - $start, 2);
echo "  Output: {$output} ({$elapsed}s)\n\n";
