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
        
        // 淡入动画
        $opacity = Remotion::interpolate($frame, [0, 30], [0.0, 1.0], [
            'extrapolateLeft'  => 'clamp',
            'extrapolateRight' => 'clamp',
        ]);
        
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

// 渲染为 GIF
Remotion::registerRoot([$comp]);
$renderer = Remotion::render($comp);
$renderer->renderToGif('output.gif');
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

        // 弹簧动画
        $scale = Spring::spring($frame, $config->fps, [
            'stiffness' => 100,
            'damping'   => 12,
            'from'      => 0.3,
            'to'        => 1.0,
        ]);

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

## 项目结构

```
src/
├── Animation/           # 动画相关
│   ├── Easing.php       # 缓动函数
│   ├── Interpolate.php  # 插值
│   └── Spring.php       # 弹簧动画
├── Core/               # 核心组件
│   ├── Composition.php  # 合成
│   ├── RenderContext.php # 渲染上下文
│   ├── Sequence.php     # 序列
│   ├── Timeline.php     # 时间线
│   └── VideoConfig.php  # 视频配置
├── Helpers/            # 辅助工具
│   ├── Color.php        # 颜色工具
│   └── Pipeline.php     # 管道
├── Layers/             # 图层
│   ├── AbstractLayer.php
│   ├── ColorLayer.php
│   ├── GradientLayer.php
│   ├── ImageLayer.php
│   └── TextLayer.php
├── Rendering/          # 渲染器
│   ├── AnimatedGifEncoder.php
│   └── Renderer.php
└── Remotion.php        # 主入口
```

## License

MIT
