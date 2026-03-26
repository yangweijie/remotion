# PHP Remotion

PHP 版的 Remotion - 程序化视频/动画合成库。

将 JavaScript [Remotion](https://remotion.dev) 的核心概念移植到 PHP，可以使用 PHP 代码创建 GIF 动画。

## 功能特性

- **Composition** - 视频合成配置（帧率、尺寸、时长）
- **Sequence** - 时间序列，支持图层组合
- **Interpolate** - 数值与颜色插值
- **Spring** - 物理弹簧动画
- **Easing** - 丰富的缓动函数（贝塞尔曲线、弹性、回弹等）
- **Layers** - 颜色层、渐变层、图片层、文字层
- **Grafika 支持** - 支持 GD 和 Imagick 双后端图像处理
- **GIF 优化** - 使用 gifsicle/ffmpeg 优化 GIF 文件大小
- **🆕 帧缓存** - LRU 缓存机制，提升重复渲染性能
- **🆕 取消机制** - 支持取消长时间渲染任务
- **🆕 并行渲染** - 利用多进程并行渲染帧
- **🆕 预设系统** - 快速创建常用分辨率配置

## 安装

```bash
composer install
```

## 快速开始

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Yangweijie\Remotion\Remotion;
use Yangweijie\Remotion\Animation\Easing;
use Yangweijie\Remotion\Core\RenderContext;

// 创建合成
$comp = Remotion::composition(
    id: 'my-first-video',
    renderer: function (RenderContext $ctx): \GdImage {
        $frame  = $ctx->getCurrentFrame();
        $config = $ctx->getVideoConfig();
        
        // 创建画布
        $canvas = Remotion::createCanvas($config->width, $config->height, [20, 20, 50]);
        
        // 淡入动画（使用 RenderContext 内置方法）
        $opacity = $ctx->fadeIn(0, 30) ?? 1.0;
        
        // 绘制文字
        $textLayer = Remotion::textLayer('Hello, PHP!', [
            'fontSize' => 5,
            'r'        => 255,
            'g'        => 255,
            'b'        => 255,
            'align'    => 'center',
        ]);
        $textLayer->setOpacity($opacity);
        $textLayer->drawOn($canvas, 0, (int) ($config->height / 2));
        
        return $canvas;
    },
    durationInFrames: 60,
    fps: 30,
    width: 640,
    height: 360,
);

// 渲染为 GIF（带帧缓存和取消令牌）
$token = new \Yangweijie\Remotion\Core\CancellationToken();

Remotion::registerRoot([$comp]);
$renderer = Remotion::render($comp)
    ->withCache(30)                      // 启用帧缓存
    ->withCancellation($token)            // 绑定取消令牌
    ->withJpegQuality(95)                 // 设置 JPEG 质量
    ->onProgress(function ($frame, $total) {
        echo "进度: $frame/$total\n";
    });

$renderer->renderToGif('output.gif');

// 使用优化渲染（推荐用于长视频）
$renderer->renderToGifOptimized('output.gif', [], [
    'lossy' => 30,      // 有损压缩级别 (0-200)，0=无损
    'colors' => 128,     // 颜色数 (2-256)
    'optimize' => 3,     // 优化级别 (1-3)
]);
```

## 核心 API

### Easing 缓动函数

```php
use Yangweijie\Remotion\Animation\Easing;

// 基础缓动
Easing::linear($t);      // 线性
Easing::ease($t);         // 标准缓动
Easing::easeIn($t);       // 缓入
Easing::easeOut($t);      // 缓出
Easing::easeInOut($t);    // 缓入缓出

// 数学缓动
Easing::quad($t);         // 二次方
Easing::cubic($t);       // 三次方
Easing::sin($t);         // 正弦
Easing::circle($t);      // 圆形
Easing::exp($t);          // 指数

// 弹性效果
Easing::bounce($t);       // 弹跳
$elastic = Easing::elastic(1.0);  // 弹性（可调参数）
$back = Easing::back(1.70158);    // 回弹效果

// 贝塞尔曲线
$bezier = Easing::bezier(0.25, 0.1, 0.25, 1.0);
$bezier($t);

// 缓动转换
$easing = fn($t) => $t * $t;
Easing::out($easing);     // 转缓出
Easing::inOut($easing);   // 转缓入缓出
```

### Interpolate 插值

```php
use Yangweijie\Remotion\Animation\Interpolate;

// 数值插值
$value = Interpolate::interpolate(
    input: 50,           // 当前输入值
    inputRange: [0, 100], // 输入范围
    outputRange: [0, 200], // 输出范围
    options: [
        'easing' => Easing::easeOut(...),
        'extrapolateLeft' => 'clamp',   // 左边界处理
        'extrapolateRight' => 'clamp',  // 右边界处理
    ]
);

// 边界外推模式
// 'extend' - 线性延伸（默认）
// 'clamp'  - 限制在边界值
// 'identity' - 返回输入值本身

// 颜色插值
$color = Interpolate::interpolateColors(
    0.5,                           // 当前帧
    [0, 60],                       // 帧范围
    [[255, 0, 0], [0, 0, 255]]    // 颜色范围
);
// 返回 [r, g, b] 数组
```

### Spring 弹簧动画

```php
use Yangweijie\Remotion\Animation\Spring;

// 弹簧动画值
$scale = Spring::spring(
    frame: $frame,
    fps: 30,
    config: [
        'stiffness' => 100,        // 刚度
        'damping'   => 10,         // 阻尼
        'mass'      => 1,          // 质量
        'from'      => 0.3,        // 起始值
        'to'        => 1.0,        // 目标值
        'overshootClamping' => false, // 禁止过冲
    ],
    durationInFrames: null        // 动画总帧数
);

// 检查动画是否稳定
$isSettled = Spring::isSettled($frame, 30, [
    'from' => 0,
    'to'   => 1,
], 0.005);

// 获取稳定所需帧数
$settledFrame = Spring::getSettledFrame(30, [
    'stiffness' => 100,
    'damping'   => 10,
]);
```

### Color 颜色工具

```php
use Yangweijie\Remotion\Helpers\Color;

// 从不同格式创建颜色
$red   = new Color(255, 0, 0);           // RGB
$blue  = Color::fromHex('#0000FF');       // HEX
$teal  = Color::fromRgb([0, 128, 128]);   // RGB 数组
$green = Color::fromHsl(120.0, 1.0, 0.5); // HSL

// 常用颜色
Color::white();      // #ffffff
Color::black();      // #000000
Color::red();        // #ff0000
Color::green();      // #00ff00
Color::blue();       // #0000ff
Color::transparent(); // 透明色

// 颜色操作
$mixed   = $red->mix($blue, 0.5);    // 混合
$lighter = $color->lighten(1.2);      // 变亮
$alpha   = $color->withAlpha(0.5);    // 设置透明度

// 格式转换
$rgb  = $color->toRgb();             // [r, g, b]
$rgba = $color->toRgba();            // [r, g, b, alpha]
$hex  = $color->toHex();            // "#ff0000"
$gdColor = $color->toGdColor($image); // GD 资源用颜色

// 获取分量
$color->getR();  // 255
$color->getG();  // 0
$color->getB();  // 0
$color->getA();  // 1.0
```

### Sequence 序列

```php
use Yangweijie\Remotion\Core\Sequence;
use Yangweijie\Remotion\Contracts\ComponentInterface;

// 使用闭包创建序列
$sequence = Sequence::make(
    id: 'intro',
    component: function (RenderContext $localCtx) use ($config): \GdImage {
        $localFrame = $localCtx->getCurrentFrame();
        // ... 渲染逻辑
        return $canvas;
    },
    from: 0,              // 开始帧
    durationInFrames: 30, // 持续帧数
    name: 'Intro Sequence'
);

// 检查序列活跃状态
$isActive = $sequence->isActive($globalFrame, $videoConfig);

// 获取本地帧号
$localFrame = $sequence->getLocalFrame($globalFrame);

// 渲染序列
$image = $sequence->render($globalContext, $videoConfig);
// 如果当前帧不在序列范围内，返回 null
```

### VideoConfig 视频配置

```php
use Yangweijie\Remotion\Core\VideoConfig;

// 创建配置
$config = new VideoConfig(
    durationInFrames: 150,  // 总帧数
    fps: 30,               // 帧率
    width: 1920,           // 宽度
    height: 1080           // 高度
);

// 预设配置
$hd = VideoConfig::hd(300, 60);           // 1920x1080
$square = VideoConfig::square(1080);       // 1080x1080
$portrait = VideoConfig::portrait(150, 30); // 1080x1920

// 时间转换
$seconds = $config->getDurationInSeconds();  // 5.0
$frames = $config->secondsToFrames(2.5);      // 75
$seconds = $config->framesToSeconds(30);     // 1.0

// 宽高比
$ratio = $config->getAspectRatio();  // 16/9
```

### RenderContext 渲染上下文

```php
// 获取当前帧
$frame = $ctx->getCurrentFrame();

// 获取视频配置
$config = $ctx->getVideoConfig();

// 获取 Props
$title = $ctx->getProp('title', 'Default');

// 获取进度 (0.0 - 1.0)
$progress = $ctx->getProgress();

// 获取当前时间（秒）
$time = $ctx->getCurrentTime();

// 创建子上下文（帧偏移）
$localCtx = $ctx->withFrameOffset(50);

// 创建子上下文（添加 Props）
$newCtx = $ctx->withProps(['title' => 'New Title']);

// 检查帧范围
$inRange = $ctx->isInRange(10, 30);
```

### RenderContext 增强（🆕 新增）

```php
// 内置插值（基于当前帧）
$value = $ctx->interpolate([0, 30], [0, 100]);
$color = $ctx->interpolateColors([0, 60], [[255, 0, 0], [0, 0, 255]]);

// 弹簧动画
$scale = $ctx->spring(['from' => 0.5, 'to' => 1.0, 'stiffness' => 100]);

// 相对进度
$progress = $ctx->getRelativeProgress(0, 100); // 在 0-100 帧范围内的进度

// 常用动画
$opacity = $ctx->fadeIn(0, 30);        // 0-30帧淡入
$opacity = $ctx->fadeOut(60, 30);      // 60-90帧淡出
$scale    = $ctx->scale(0, 30, 0.5, 1.5); // 缩放
$angle    = $ctx->rotate(0, 60, 0, 360);  // 旋转
$offset   = $ctx->slideIn('left', 0, 30); // 从左侧滑入

// 缓动函数快捷方式
$easing = $ctx::easeIn();      // 缓入
$easing = $ctx::easeOut();     // 缓出
$easing = $ctx::easeInOut();   // 缓入缓出
$easing = $ctx::linear();      // 线性
$easing = $ctx::bezier(0.25, 0.1, 0.25, 1.0); // 贝塞尔
$easing = $ctx::elastic(1);    // 弹性
$easing = $ctx::bounce(1);     // 回弹
```

## 🆕 新功能

### 帧缓存 (FrameCache)

```php
use Yangweijie\Remotion\Core\FrameCache;
use Yangweijie\Remotion\Rendering\Renderer;

// 在 Renderer 中启用缓存
$renderer = new Renderer($composition);
$renderer->withCache(capacity: 30);  // 缓存最近30帧

// 渲染时自动使用缓存
$renderer->renderToFrames($outputDir, 'png');

// 查看缓存统计
$stats = $renderer->getFrameCache()->getStats();
echo "命中率: {$stats['hitRate']}%";  // 40%
```

### 取消机制 (CancellationToken)

```php
use Yangweijie\Remotion\Core\CancellationToken;
use Yangweijie\Remotion\Core\RenderCancelledException;

// 创建取消令牌
$token = new CancellationToken();

// 绑定到渲染器
$renderer = new Renderer($composition);
$renderer->withCancellation($token);

// 在另一个线程/信号处理器中取消
$token->cancel('用户取消');

// 渲染时捕获取消异常
try {
    $renderer->renderToGif('output.gif');
} catch (RenderCancelledException $e) {
    echo "渲染已取消: {$e->getMessage()}";
}

// 超时取消
$timeoutToken = CancellationToken::withTimeout(5.0); // 5秒超时
```

### 并行渲染 (ParallelRenderer)

```php
use Yangweijie\Remotion\Core\CompositionFactory;
use Yangweijie\Remotion\Rendering\ParallelRenderer;

// 使用工厂创建可并行渲染的合成
$comp = CompositionFactory::createParallelizable(
    id: 'parallel-demo',
    template: 'text-fade',
    config: ['text' => 'Hello World'],
    durationInFrames: 120,
    fps: 30,
    width: 640,
    height: 360,
);

// 并行渲染
$renderer = new ParallelRenderer(workerCount: 4);
$files = $renderer->renderToFrames(
    $comp,
    $outputDir,
    'png',
    onProgress: function ($completed, $total) {
        echo "进度: $completed/$total\n";
    }
);
```

### 预设系统 (Preset)

```php
use Yangweijie\Remotion\Core\Preset;
use Yangweijie\Remotion\Remotion;

// 使用预设快速创建合成
$preset = Preset::HD_1080P(durationInFrames: 90);
// 或: Preset::UHD_4K(), Preset::HD_720P(), Preset::STORY_1080P() 等

$comp = Remotion::composition(
    id: 'my-video',
    renderer: $renderer,
    ...$preset->toArray(),  // 展开 width, height, fps, durationInFrames
);

// 修改预设
$preset = Preset::HD_1080P()
    ->withFps(60)
    ->withDurationSeconds(3.0);

// 社交媒体预设
$preset = Preset::YOUTUBE();        // 1920x1080
$preset = Preset::TIKTOK();         // 1080x1920
$preset = Preset::INSTAGRAM();      // 1080x1080
$preset = Preset::YOUTUBE_SHORTS(); // 1080x1920
```

### 命名空间隔离 (CompositionRegistry)

```php
use Yangweijie\Remotion\Core\CompositionRegistry;

// 注册到特定命名空间
CompositionRegistry::register($composition, 'project-a');
CompositionRegistry::register($composition, 'project-b');

// 从命名空间获取
$comp = CompositionRegistry::get('my-video', 'project-a');

// 设置当前活跃命名空间
CompositionRegistry::setActiveNamespace('project-a');
CompositionRegistry::register($composition);  // 自动注册到 project-a

// 查看统计
$stats = CompositionRegistry::getStats();
```

### 渲染质量控制

```php
use Yangweijie\Remotion\Rendering\Renderer;

$renderer = new Renderer($composition);

// 设置 JPEG 质量 (0-100，默认95)
$renderer->withJpegQuality(90);

// 设置 PNG 压缩级别 (-1=默认, 0=无压缩, 9=最大压缩)
$renderer->withPngCompression(6);

// 设置 WebP 质量
$renderer->withWebpQuality(85);

// 批量设置
$renderer->withQuality([
    'jpeg' => 90,
    'png' => 6,
    'webp' => 85,
]);

// 渲染
$renderer->renderToFrames($outputDir, 'jpg');  // 使用90%质量
```

## Grafika 支持

PHP Remotion 支持 [Grafika](https://github.com/kosinix/grafika) 图像处理库，提供 GD 和 Imagick 双后端支持。

### 安装 Grafika

```bash
composer require kosinix/grafika
```

### 创建 Grafika 画布

```php
use Yangweijie\Remotion\Remotion;

// 创建空白画布
$image = Remotion::createImageCanvas(800, 600);

// 创建带背景色的画布
$image = Remotion::createImageCanvas(800, 600, [255, 0, 0]); // 红色背景
```

### Grafika Layer 方法

所有 Layer 类都支持 Grafika 的 `drawOnImage()` 方法：

```php
use Yangweijie\Remotion\Remotion;
use Grafika\Grafika;

$canvas = Remotion::createImageCanvas(800, 600);

// 颜色层
$colorLayer = Remotion::colorLayer(200, 100, 255, 0, 0);
$colorLayer->drawOnImage($canvas, 50, 50);

// 渐变层
$gradient = Remotion::gradientLayer(300, 200,
    ['r' => 255, 'g' => 0, 'b' => 0],
    ['r' => 0, 'g' => 0, 'b' => 255],
    'vertical'
);
$gradient->drawOnImage($canvas, 100, 100);

// 文字层（支持中文 TTF 字体）
$text = Remotion::textLayer('你好世界', [
    'fontSize' => 24,
    'fontPath' => '/path/to/font.ttf',
    'r' => 255, 'g' => 255, 'b' => 255,
]);
$text->drawOnImage($canvas, 200, 300);

// 保存图像
$editor = Grafika::createEditor();
$editor->save($canvas, 'output.png');
```

### 后端自动检测

Grafika 会自动检测可用的图像处理后端：

```php
use Grafika\Grafika;

$editorName = Grafika::detectAvailableEditor();
// 返回 'Imagick' 或 'Gd'
```

**Imagick 后端优势**：
- 更好的渐变质量
- 支持更多图像格式
- 更高效的内存管理

## GIF 优化渲染

使用 `renderToGifOptimized()` 方法可获得更小的 GIF 文件：

### 基础用法

```php
use Yangweijie\Remotion\Remotion;
use Yangweijie\Remotion\Rendering\Renderer;

$composition = Remotion::composition(...);
$renderer = new Renderer($composition);

// 无损优化
$renderer->renderToGifOptimized('output.gif');

// 高压缩（适合长视频）
$renderer->renderToGifOptimized('output.gif', [], [
    'lossy' => 50,       // 有损压缩
    'colors' => 64,      // 减少颜色数
    'optimize' => 3,     // 最高优化级别
]);
```

### 优化选项

| 参数 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `lossy` | int | 0 | 有损压缩级别 (0-200)，0=无损 |
| `colors` | int | 256 | 颜色数 (2-256)，减少可显著降低文件大小 |
| `optimize` | int | 3 | 优化级别 (1-3)，3 为最高 |
| `cleanup` | bool | true | 是否清理临时文件 |

### 工具依赖

优化渲染需要安装外部工具：

```bash
# macOS
brew install gifsicle

# Ubuntu/Debian
sudo apt install gifsicle

# 备选：ffmpeg（已自动检测）
# macOS: brew install ffmpeg
```

**工具优先级**：`gifsicle` > `ffmpeg` > GD 回退

### 性能对比

| 场景 | GD 原生 | 优化后 | 压缩率 |
|------|---------|--------|--------|
| 简单动画 60帧 | 22 KB | 23 KB | -4% |
| 复杂动画 150帧 800x400 | 295 KB | 200 KB | **32%** |
| 高压缩设置 | - | 可达 50%+ | 视内容而定 |

### 流式 GIF 编码 ⭐ 内存敏感场景

使用 `renderToGifStreaming()` 方法实现最低内存占用：

```php
// 流式编码：直接写入文件，不累积帧数据
$renderer->renderToGifStreaming('output.gif');
```

**内存优势**：内存占用恒定，仅相当于一帧的大小。

### 系统内存对比（300帧 1920x1080）

| 方式 | 系统内存峰值 | 文件大小 |
|------|-------------|---------|
| GD 原生 | 741 MB | 13.7 MB |
| 流式编码 | **57.8 MB** | 1.2 MB |
| gifsicle 优化 | **49 MB** | 9.3 MB |

**内存节省：92%+**

### 选择建议

| 场景 | 推荐方法 |
|------|----------|
| 短视频（<60帧） | `renderToGif()` |
| 长视频（>60帧） | `renderToGifOptimized()` |
| 内存敏感场景 | `renderToGifStreaming()` |
| 最小文件体积 | `renderToGifOptimized()` + `lossy` |

## 示例

### 淡入淡出文字动画

```php
$comp = Remotion::composition(
    id: 'fade-text',
    renderer: function (RenderContext $ctx): \GdImage {
        $frame  = $ctx->getCurrentFrame();
        $config = $ctx->getVideoConfig();
        $text   = $ctx->getProp('text', 'Hello!');

        $canvas = Remotion::createCanvas($config->width, $config->height, [20, 20, 50]);

        // 淡入：0-30 帧
        $opacity = Remotion::interpolate($frame, [0, 30], [0.0, 1.0], [
            'extrapolateLeft'  => 'clamp',
            'extrapolateRight' => 'clamp',
        ]);

        // 淡出：60-90 帧
        if ($frame > 60) {
            $opacity = Remotion::interpolate($frame, [60, 90], [1.0, 0.0], [
                'extrapolateLeft'  => 'clamp',
                'extrapolateRight' => 'clamp',
            ]);
        }

        $textLayer = Remotion::textLayer($text, [
            'fontSize' => 5,
            'r' => 255, 'g' => 255, 'b' => 255,
            'align' => 'center',
        ]);
        $textLayer->setOpacity($opacity);
        $textLayer->drawOn($canvas, 0, (int) ($config->height / 2));

        return $canvas;
    },
    durationInFrames: 90,
    fps: 30,
    width: 640,
    height: 360,
);
```

### 弹簧缩放动画

```php
$comp = Remotion::composition(
    id: 'spring-scale',
    renderer: function (RenderContext $ctx): \GdImage {
        $frame  = $ctx->getCurrentFrame();
        $config = $ctx->getVideoConfig();

        // 使用 RenderContext 内置 spring 方法
        $scale = $ctx->spring(['from' => 0.3, 'to' => 1.0, 'stiffness' => 100]);

        $canvas = Remotion::createCanvas($config->width, $config->height);

        // 绘制缩放的圆形
        $radius = (int) ($config->height * 0.2 * $scale);
        $cx = $config->width / 2;
        $cy = $config->height / 2;
        $color = imagecolorallocate($canvas, 255, 200, 50);
        imagefilledellipse($canvas, (int) $cx, (int) $cy, $radius * 2, $radius * 2, $color);

        return $canvas;
    },
    durationInFrames: 60,
    fps: 30,
    width: 640,
    height: 360,
);
```

### 多序列组合

```php
$comp = Remotion::composition(
    id: 'multi-sequence',
    renderer: function (RenderContext $ctx): \GdImage {
        $frame  = $ctx->getCurrentFrame();
        $config = $ctx->getVideoConfig();
        $canvas = Remotion::createCanvas($config->width, $config->height, [10, 10, 10]);

        // Sequence 1: 红色块从左滑入
        $seq1 = Remotion::sequence(
            'slide-in-red',
            function (RenderContext $localCtx) use ($config): \GdImage {
                $localFrame = $localCtx->getCurrentFrame();
                $x = Remotion::interpolate($localFrame, [0, 30], [-200, 50], [
                    'easing' => Easing::easeOut(...),
                ]);
                $img = Remotion::createCanvas($config->width, $config->height);
                $red = Remotion::colorLayer(200, 150, 255, 50, 50);
                $red->drawOn($img, (int) $x, 100);
                return $img;
            },
            from: 0,
            durationInFrames: 45,
        );

        // Sequence 2: 蓝色块从右滑入
        $seq2 = Remotion::sequence(
            'slide-in-blue',
            function (RenderContext $localCtx) use ($config): \GdImage {
                $localFrame = $localCtx->getCurrentFrame();
                $x = Remotion::interpolate($localFrame, [0, 30], 
                    [$config->width, $config->width - 250], [
                    'easing' => Easing::easeOut(...),
                ]);
                $img = Remotion::createCanvas($config->width, $config->height);
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
```

## 完整示例

查看 [example.php](example.php) 获取完整的可运行示例：

```bash
# 渲染淡入淡出文字
php example.php fade-text

# 渲染弹簧动画
php example.php spring-scale

# 渲染多序列组合
php example.php multi-sequence
```

### 新增示例

```bash
# 帧缓存和取消机制演示
php examples/cache-and-cancellation-demo.php

# 并行渲染对比
php examples/parallel-text-fade.php

# RenderContext 增强功能
php examples/enhanced-features-demo.php
```

## 测试

```bash
# 运行所有测试
./vendor/bin/pest

# 运行特定测试组
./vendor/bin/pest --group=easing

# 生成覆盖率报告
./vendor/bin/pest --coverage
```

### 测试覆盖模块

| 模块 | 测试数 | 说明 |
|------|--------|------|
| Easing | 16 | 缓动函数测试 |
| Interpolate | 11 | 插值函数测试 |
| Spring | 10 | 弹簧动画测试 |
| Color | 20 | 颜色工具测试 |
| VideoConfig | 14 | 视频配置测试 |
| RenderContext | 10 | 渲染上下文测试 |
| Sequence | 12 | 序列组件测试 |
| FrameCache | 11 | 帧缓存测试 🆕 |
| CancellationToken | 9 | 取消令牌测试 🆕 |
| CompositionRegistry | 8 | 命名空间隔离测试 🆕 |
| Preset | 13 | 预设系统测试 🆕 |
| RenderContext Enhanced | 13 | 增强功能测试 🆕 |

## 项目结构

```
src/
├── Animation/              # 动画相关
│   ├── Easing.php          # 缓动函数
│   ├── Interpolate.php     # 插值
│   └── Spring.php          # 弹簧动画
├── Core/                   # 核心组件
│   ├── CancellationToken.php  # 🆕 取消令牌
│   ├── Composition.php        # 合成
│   ├── CompositionRegistry.php # 🆕 命名空间隔离
│   ├── FrameCache.php         # 🆕 帧缓存
│   ├── Preset.php             # 🆕 预设系统
│   ├── RenderContext.php      # 渲染上下文
│   ├── Sequence.php           # 序列
│   ├── SequenceWithTransition.php # 🆕 过渡序列
│   ├── Timeline.php           # 时间线
│   └── VideoConfig.php        # 视频配置
├── Helpers/                # 辅助工具
│   ├── Color.php           # 颜色工具
│   └── Pipeline.php        # 管道
├── Layers/                 # 图层
│   ├── AbstractLayer.php
│   ├── ColorLayer.php
│   ├── GradientLayer.php
│   ├── ImageLayer.php
│   └── TextLayer.php
├── Rendering/              # 渲染器
│   ├── AnimatedGifEncoder.php
│   ├── ParallelRenderer.php   # 🆕 并行渲染
│   └── Renderer.php
└── Remotion.php            # 主入口
```

## License

MIT