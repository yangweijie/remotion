<?php

/**
 * 帧缓存和取消机制演示
 *
 * 演示功能：
 * 1. 帧缓存 (LRU Cache) - 缓存最近渲染的帧，提升重复访问性能
 * 2. 取消令牌 (CancellationToken) - 安全取消长时间渲染
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Yangweijie\Remotion\Remotion;
use Yangweijie\Remotion\Core\CancellationToken;
use Yangweijie\Remotion\Core\Composition;
use Yangweijie\Remotion\Core\RenderCancelledException;
use Yangweijie\Remotion\Rendering\Renderer;

// 创建测试合成
$comp = Composition::fromClosure(
    id: 'cache-demo',
    renderer: function ($ctx) {
        $config = $ctx->getVideoConfig();
        $frame = $ctx->getCurrentFrame();

        // 创建画布
        $canvas = imagecreatetruecolor($config->width, $config->height);

        // 背景渐变（基于帧号）
        $progress = $frame / $config->durationInFrames;
        $r = (int) (255 * (1 - $progress));
        $g = (int) (128 + 127 * sin($progress * M_PI));
        $b = (int) (255 * $progress);

        $bgColor = imagecolorallocate($canvas, $r, $g, $b);
        imagefill($canvas, 0, 0, $bgColor);

        // 添加文字
        $textColor = imagecolorallocate($canvas, 255, 255, 255);
        $text = "Frame: {$frame}";
        imagestring($canvas, 5, 50, 50, $text, $textColor);

        return $canvas;
    },
    durationInFrames: 60,
    fps: 30,
    width: 320,
    height: 240,
);

echo "🎬 PHP Remotion 帧缓存与取消机制演示\n";
echo "=====================================\n\n";

// ========== 演示 1: 帧缓存 ==========
echo "📦 演示 1: 帧缓存 (LRU Cache)\n";
echo "------------------------------\n";

$outputDir = __DIR__ . '/../output/cache-demo';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// 启用帧缓存（容量为10帧）
$renderer = new Renderer($comp);
$renderer->withCache(capacity: 10);

$startTime = microtime(true);

// 模拟随机访问帧（测试缓存效果）
$randomFrames = [5, 10, 15, 5, 10, 20, 25, 5, 30, 10]; // 5和10会重复访问

foreach ($randomFrames as $frame) {
    echo "访问帧 {$frame}...\n";
    $image = $renderer->renderFrameWithCache($frame);

    // 保存帧
    $filename = $outputDir . sprintf('/frame_%04d.png', $frame);
    imagepng($image, $filename);
}

$cache = $renderer->getFrameCache();
$stats = $cache->getStats();

$elapsed = round(microtime(true) - $startTime, 3);

echo "\n✅ 缓存统计:\n";
echo "  缓存容量: {$stats['capacity']}\n";
echo "  当前大小: {$stats['currentSize']}\n";
echo "  命中次数: {$stats['hits']}\n";
echo "  未命中次数: {$stats['misses']}\n";
echo "  命中率: {$stats['hitRate']}%\n";
echo "  耗时: {$elapsed} 秒\n";

// 清理缓存
$cache->clear();
echo "  缓存已清空\n\n";

// ========== 演示 2: 取消机制 ==========
echo "🛑 演示 2: 取消机制 (CancellationToken)\n";
echo "----------------------------------------\n";

// 创建一个慢速渲染的合成（每帧延迟50ms）
$slowComp = Composition::fromClosure(
    id: 'cancel-demo',
    renderer: function ($ctx) {
        $config = $ctx->getVideoConfig();
        $frame = $ctx->getCurrentFrame();

        // 模拟耗时操作
        usleep(50000); // 50ms 延迟

        $canvas = imagecreatetruecolor($config->width, $config->height);
        $bgColor = imagecolorallocate($canvas, 50, 50, 50);
        imagefill($canvas, 0, 0, $bgColor);

        $textColor = imagecolorallocate($canvas, 255, 255, 255);
        $text = "Frame: {$frame}";
        imagestring($canvas, 5, 50, 50, $text, $textColor);

        return $canvas;
    },
    durationInFrames: 20,
    fps: 30,
    width: 320,
    height: 240,
);

$cancelOutputDir = __DIR__ . '/../output/cancel-demo';
if (!is_dir($cancelOutputDir)) {
    mkdir($cancelOutputDir, 0755, true);
}

// 创建取消令牌
$token = new CancellationToken();

// 创建渲染器并绑定取消令牌
$renderer2 = new Renderer($slowComp);
$renderer2->withCancellation($token);

// 演示：手动取消
echo "演示手动取消...\n";
echo "  1. 先渲染几帧\n";

// 手动渲染几帧
for ($i = 0; $i < 3; $i++) {
    $image = $renderer2->renderFrameWithCache($i);
    imagedestroy($image);
    echo "    渲染帧 {$i}\n";
}

// 手动取消
echo "  2. 发送取消信号\n";
$token->cancel('用户手动取消');

try {
    echo "  3. 继续渲染（应该触发异常）\n";
    $image = $renderer2->renderFrameWithCache(4);
    imagedestroy($image);
    echo "❌ 应该触发取消异常\n";
} catch (RenderCancelledException $e) {
    echo "\n✅ 渲染成功取消!\n";
    echo "  原因: {$e->getMessage()}\n";
}

// ========== 演示 3: 取消监听器 ==========
echo "\n📢 演示 3: 取消监听器\n";
echo "---------------------\n";

$token2 = new CancellationToken();
$listenerCalled = false;

// 注册取消监听器
$listenerId = $token2->registerListener(function (?string $reason) use (&$listenerCalled) {
    $listenerCalled = true;
    echo "  监听器被调用! 原因: {$reason}\n";
});

echo "注册取消监听器...\n";
echo "发送取消信号...\n";
$token2->cancel('测试监听器');

if ($listenerCalled) {
    echo "✅ 监听器正常工作!\n";
} else {
    echo "❌ 监听器未被调用\n";
}

// ========== 演示 4: 完整功能集成 ==========
echo "\n🚀 演示 4: 完整功能集成（缓存 + 进度回调）\n";
echo "-------------------------------------------\n";

$fullDir = __DIR__ . '/../output/full-demo';
if (!is_dir($fullDir)) {
    mkdir($fullDir, 0755, true);
}

// 创建新的令牌（不取消，完整渲染）
$normalToken = new CancellationToken();

$renderer4 = new Renderer($comp);
$renderer4
    ->withCache(capacity: 20)
    ->withCancellation($normalToken)
    ->onProgress(function ($frame, $total) {
        if ($frame % 10 === 0) {
            echo "进度: {$frame}/{$total}\n";
        }
    });

$startTime = microtime(true);
$success = $renderer4->renderToFrames($fullDir, 'png');
$elapsed = round(microtime(true) - $startTime, 3);

if ($success) {
    $finalStats = $renderer4->getFrameCache()?->getStats();
    echo "\n✅ 完整渲染完成!\n";
    echo "  耗时: {$elapsed} 秒\n";
    if ($finalStats) {
        echo "  缓存命中率: {$finalStats['hitRate']}%\n";
    }
}

// ========== 演示 5: 链接令牌 ==========
echo "\n🔗 演示 5: 链接多个取消令牌\n";
echo "--------------------------\n";

$tokenA = new CancellationToken();
$tokenB = new CancellationToken();

$linkedToken = CancellationToken::linked([$tokenA, $tokenB]);

// 取消 tokenA
echo "取消 tokenA...\n";
$tokenA->cancel('Token A 被取消');

if ($linkedToken->isCancelled()) {
    echo "✅ 链接令牌也被取消! 原因: {$linkedToken->getReason()}\n";
} else {
    echo "❌ 链接令牌未被取消\n";
}

echo "\n✨ 所有演示完成!\n";
echo "提示:\n";
echo "  - 帧缓存对循环动画或随机访问帧特别有用\n";
echo "  - 取消机制可用于用户交互式应用\n";
echo "  - 取消监听器可用于清理资源或通知UI\n";
echo "  - 链接令牌可用于组合多个取消源\n";