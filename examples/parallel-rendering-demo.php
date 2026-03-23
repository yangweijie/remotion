<?php
/**
 * 并行渲染演示
 * 展示如何使用 PSL Async 进行并行渲染
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Psl\Async;
use Yangweijie\Remotion\Remotion;
use Yangweijie\Remotion\Core\RenderContext;
use Yangweijie\Remotion\Rendering\ParallelRenderer;
use Yangweijie\Remotion\Animation\Easing;

echo "🎬 PHP Remotion 并行渲染演示\n";
echo "==============================\n\n";

// 检查 PSL Async 是否可用
if (!function_exists('Psl\Async\concurrently')) {
    echo "❌ 错误: PSL Async 扩展未安装\n";
    echo "请运行: composer require php-standard-library/async\n";
    exit(1);
}

// 创建一个测试合成
$composition = Remotion::composition(
    id: 'parallel-demo',
    renderer: function (RenderContext $ctx): \GdImage {
        $frame = $ctx->getCurrentFrame();
        $config = $ctx->getVideoConfig();
        
        $canvas = imagecreatetruecolor($config->width, $config->height);
        
        // 动态背景
        $hue = ($frame * 2) % 360;
        $rgb = hslToRgb($hue / 360, 0.5, 0.3);
        $bgColor = imagecolorallocate($canvas, $rgb[0], $rgb[1], $rgb[2]);
        imagefill($canvas, 0, 0, $bgColor);
        
        // 动画文字
        $progress = $frame / $config->durationInFrames;
        $x = Easing::easeInOut($progress) * ($config->width - 100);
        $y = $config->height / 2;
        
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagestring($canvas, 5, (int)$x, (int)$y, "Frame: {$frame}", $white);
        
        return $canvas;
    },
    durationInFrames: 120, // 4秒 @ 30fps
    fps: 30,
    width: 640,
    height: 360,
);

// 方案1: 串行渲染（对比基准）
echo "方案1: 串行渲染\n";
$startTime = microtime(true);

$outputDir = __DIR__ . '/../output/serial_frames';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$config = $composition->getVideoConfig();
for ($frame = 0; $frame < $config->durationInFrames; $frame++) {
    $image = $composition->renderFrame($frame);
    imagepng($image, sprintf('%s/frame_%04d.png', $outputDir, $frame));
    imagedestroy($image);
    
    if ($frame % 30 === 0) {
        echo "  渲染帧 {$frame}/{$config->durationInFrames}\n";
    }
}

$serialTime = microtime(true) - $startTime;
echo "✅ 串行渲染完成: " . round($serialTime, 2) . " 秒\n\n";

// 方案2: 并行渲染（使用 PSL Async）
echo "方案2: 并行渲染 (4 workers)\n";
$startTime = microtime(true);

$outputDir = __DIR__ . '/../output/parallel_frames';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

try {
    $renderer = new ParallelRenderer(workerCount: 4);
    
    $files = $renderer->renderToFrames(
        composition: $composition,
        outputDir: $outputDir,
        format: 'png',
        onProgress: function($current, $total) {
            if ($current % 10 === 0 || $current === $total) {
                $percent = round($current / $total * 100);
                echo "  进度: {$current}/{$total} ({$percent}%)\n";
            }
        }
    );
    
    $parallelTime = microtime(true) - $startTime;
    echo "✅ 并行渲染完成: " . round($parallelTime, 2) . " 秒\n";
    echo "📁 生成文件数: " . count($files) . "\n";
    
    // 性能对比
    $speedup = round($serialTime / $parallelTime, 2);
    echo "\n📊 性能对比:\n";
    echo "  串行: " . round($serialTime, 2) . " 秒\n";
    echo "  并行: " . round($parallelTime, 2) . " 秒\n";
    echo "  加速比: {$speedup}x\n";
    
} catch (\Throwable $e) {
    echo "❌ 并行渲染失败: " . $e->getMessage() . "\n";
    echo "注意: 并行渲染需要可序列化的 Composition\n";
}

// 方案3: 带取消功能的渲染
echo "\n方案3: 可取消的渲染\n";

// 创建取消令牌
$cancellation = new Async\NullCancellationToken();

// 模拟: 5秒后取消
Async\run(function() use (&$cancellation) {
    Async\sleep(5.0);
    echo "  模拟: 发送取消信号\n";
    // 实际取消需要通过 CancellationToken 实现
});

// HSL 转 RGB 辅助函数
function hslToRgb(float $h, float $s, float $l): array
{
    $r = $l;
    $g = $l;
    $b = $l;
    
    if ($s !== 0.0) {
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $r = hueToRgb($p, $q, $h + 1/3);
        $g = hueToRgb($p, $q, $h);
        $b = hueToRgb($p, $q, $h - 1/3);
    }
    
    return [
        (int) round($r * 255),
        (int) round($g * 255),
        (int) round($b * 255),
    ];
}

function hueToRgb(float $p, float $q, float $t): float
{
    if ($t < 0) $t += 1;
    if ($t > 1) $t -= 1;
    if ($t < 1/6) return $p + ($q - $p) * 6 * $t;
    if ($t < 1/2) return $q;
    if ($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6;
    return $p;
}

echo "\n✅ 演示完成!\n";
echo "提示: 并行渲染效果取决于 CPU 核心数和任务复杂度\n";
