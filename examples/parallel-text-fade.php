<?php
/**
 * 并行渲染示例 - 文本淡入动画
 * 
 * 展示如何使用 PSL Async 和 CompositionFactory 进行并行渲染
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Psl\Async;
use Yangweijie\Remotion\Core\CompositionFactory;
use Yangweijie\Remotion\Rendering\ParallelRenderer;

echo "🎬 PHP Remotion 并行渲染示例\n";
echo "==============================\n\n";

// 注册默认模板
CompositionFactory::registerDefaultTemplates();

// 创建支持并行渲染的 Composition
$composition = CompositionFactory::createParallelizable(
    id: 'text-fade-parallel',
    template: 'text-fade',
    templateParams: [
        'text' => 'Hello Parallel World!',
        'fontSize' => 5,
    ],
    durationInFrames: 120, // 4秒 @ 30fps
    fps: 30,
    width: 640,
    height: 360,
);

echo "✅ Composition 创建成功\n";
echo "   ID: {$composition->getId()}\n";
echo "   可并行渲染: " . ($composition->isParallelizable() ? '是' : '否') . "\n\n";

// 方案1: 串行渲染（对比基准）
echo "方案1: 串行渲染\n";
$outputDir = __DIR__ . '/../output/serial_frames';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$startTime = microtime(true);
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
$outputDir = __DIR__ . '/../output/parallel_frames';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

try {
    $startTime = microtime(true);
    
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
    $speedup = $serialTime / $parallelTime;
    echo "\n📊 性能对比:\n";
    echo "  串行: " . round($serialTime, 2) . " 秒\n";
    echo "  并行: " . round($parallelTime, 2) . " 秒\n";
    echo "  加速比: " . round($speedup, 2) . "x\n";
    
    // 生成GIF
    echo "\n🎬 生成 GIF...\n";
    $gifPath = __DIR__ . '/../output/parallel-demo.gif';
    
    // 使用 GifCreator
    $gifCreator = new \GifCreator\GifCreator();
    $durations = array_fill(0, count($files), 3); // 3/100秒 = 30fps
    $gifCreator->create($files, $durations, 0);
    file_put_contents($gifPath, $gifCreator->getGif());
    
    echo "✅ GIF 生成完成: {$gifPath}\n";
    echo "📦 文件大小: " . round(filesize($gifPath) / 1024, 2) . " KB\n";
    
} catch (\Throwable $e) {
    echo "❌ 并行渲染失败: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}

// 方案3: 带取消功能的渲染
echo "\n方案3: 可取消的渲染\n";
echo "（演示代码，实际取消需要 CancellationToken）\n";

// 创建取消令牌
$cancellation = new Async\NullCancellationToken();

$renderer = new ParallelRenderer(workerCount: 2);
$renderer->withCancellation($cancellation);

echo "✅ 已配置取消令牌\n";
echo "提示: 可以通过信号或外部事件触发取消\n";

echo "\n✅ 演示完成!\n";
echo "提示: 并行渲染效果取决于 CPU 核心数和任务复杂度\n";
echo "      对于简单动画，并行开销可能超过收益\n";

