# PHP Remotion - 项目上下文

## 项目概述

PHP Remotion 是一个将 JavaScript [Remotion](https://remotion.dev) 核心概念移植到 PHP 的程序化视频/动画合成库。使用 PHP 代码创建 GIF 动画，支持时间序列、插值动画、弹簧物理动画、丰富的缓动函数以及多种图层类型。

**主要特性：**
- **Composition** - 视频合成配置（帧率、尺寸、时长）
- **Sequence** - 时间序列，支持图层组合
- **Interpolate** - 数值与颜色插值
- **Spring** - 物理弹簧动画
- **Easing** - 丰富的缓动函数（贝塞尔曲线、弹性、回弹等）
- **Layers** - 颜色层、渐变层、图片层、文字层

---

## 项目结构

```
/Volumes/data/git/php/remotion/
├── bin/remotion              # CLI 入口脚本
├── src/                      # 源代码
│   ├── Remotion.php          # 主门面类（Facade）
│   ├── helpers.php           # 辅助函数
│   ├── Animation/            # 动画模块
│   │   ├── Easing.php        # 缓动函数库
│   │   ├── Interpolate.php   # 数值/颜色插值
│   │   └── Spring.php        # 弹簧动画
│   ├── CLI/                  # 命令行工具
│   │   └── RemotionCLI.php   # 基于 minicli 的 CLI
│   ├── Contracts/            # 接口定义
│   │   ├── ComponentInterface.php
│   │   ├── LayerInterface.php
│   │   └── RendererInterface.php
│   ├── Core/                 # 核心组件
│   │   ├── Composition.php   # 视频合成
│   │   ├── RenderContext.php # 渲染上下文
│   │   ├── Sequence.php      # 时间序列
│   │   ├── Timeline.php      # 时间线管理
│   │   └── VideoConfig.php   # 视频配置
│   ├── Helpers/              # 辅助工具
│   │   ├── Color.php         # 颜色处理
│   │   └── Pipeline.php      # 管道/链式调用
│   ├── Layers/               # 图层类型
│   │   ├── AbstractLayer.php # 抽象图层基类
│   │   ├── ColorLayer.php    # 纯色图层
│   │   ├── GradientLayer.php # 渐变图层
│   │   ├── ImageLayer.php    # 图片图层
│   │   └── TextLayer.php     # 文字图层
│   └── Rendering/            # 渲染引擎
│       ├── AnimatedGifEncoder.php
│       └── Renderer.php
├── tests/                    # 测试目录
│   └── Test.php             # Pest 测试文件
├── example.php              # 完整使用示例
├── advanced-examples.php    # 高级示例
├── composer.json            # Composer 配置
└── phpunit.xml              # PHPUnit 配置
```

---

## 核心 API 速查

### Remotion 门面类

```php
use Yangweijie\Remotion\Remotion;
use Yangweijie\Remotion\Core\RenderContext;

// 创建合成
$comp = Remotion::composition(
    id: 'my-video',
    renderer: function (RenderContext $ctx): \GdImage {
        $frame = $ctx->getCurrentFrame();
        $config = $ctx->getVideoConfig();
        // ... 渲染逻辑
        return $canvas;
    },
    durationInFrames: 60,
    fps: 30,
    width: 640,
    height: 360,
);

// 渲染为 GIF
Remotion::render($comp)->renderToGif('output.gif');
```

### 动画工具

```php
use Yangweijie\Remotion\Animation\Easing;
use Yangweijie\Remotion\Animation\Interpolate;
use Yangweijie\Remotion\Animation\Spring;

// 插值
$value = Remotion::interpolate($frame, [0, 30], [0, 100], [
    'easing' => Easing::easeOut(...),
    'extrapolateLeft' => 'clamp',
]);

// 颜色插值
$color = Remotion::interpolateColors($frame, [0, 30], [
    [255, 0, 0],    // 红色
    [0, 0, 255],    // 蓝色
]);

// 弹簧动画
$scale = Remotion::spring($frame, $fps, [
    'stiffness' => 100,
    'damping' => 12,
    'from' => 0.3,
    'to' => 1.0,
]);
```

### 图层

```php
// 纯色图层
$colorLayer = Remotion::colorLayer($width, $height, 255, 0, 0);

// 渐变图层
$gradient = Remotion::gradientLayer(
    $width, $height,
    ['r' => 255, 'g' => 0, 'b' => 0],
    ['r' => 0, 'g' => 0, 'b' => 255],
    'vertical'  // 或 'horizontal'
);

// 文字图层
$text = Remotion::textLayer('Hello World!', [
    'fontSize' => 5,      // GD 内置字体 1-5
    'r' => 255, 'g' => 255, 'b' => 255,
    'align' => 'center',
]);

// 图片图层
$image = Remotion::imageLayer('/path/to/image.png', $width, $height);
```

### 序列（Sequence）

```php
$seq = Remotion::sequence(
    id: 'intro',
    renderer: function (RenderContext $ctx) use ($config): \GdImage {
        $localFrame = $ctx->getCurrentFrame(); // 从 0 开始
        // ... 渲染
        return $canvas;
    },
    from: 0,              // 全局开始帧
    durationInFrames: 30, // 持续帧数
);

// 在合成中使用
$seqImage = $seq->render($globalCtx, $config);
if ($seqImage !== null) {
    imagecopy($canvas, $seqImage, 0, 0, 0, 0, $width, $height);
    imagedestroy($seqImage);
}
```

---

## 常用命令

### 安装依赖

```bash
composer install
```

### 运行示例

```bash
# 渲染淡入淡出文字动画
php example.php fade-text

# 渲染弹簧动画
php example.php spring-scale

# 渲染多序列组合
php example.php multi-sequence

# 自定义输出文件名
php example.php fade-text my-animation.gif
```

### CLI 工具

```bash
# 查看帮助
php bin/remotion help

# 列出所有已注册的合成
php bin/remotion list --root=example.php

# 查看合成详情
php bin/remotion info <compositionId> --root=example.php

# 渲染合成
php bin/remotion render <compositionId> --output=output.gif --root=example.php

# 渲染为图片序列
php bin/remotion render-frames <compositionId> --dir=frames/ --format=png --root=example.php
```

### 运行测试

```bash
# 运行所有测试
./vendor/bin/pest

# 运行特定测试组
./vendor/bin/pest --group=easing

# 生成覆盖率报告
./vendor/bin/pest --coverage
```

### 静态分析

```bash
./vendor/bin/phpstan analyse
```

---

## 技术栈

- **PHP**: >= 8.0
- **依赖库**:
  - `kosinix/grafika`: 图像处理
  - `sybio/gif-creator`: GIF 生成
  - `minicli/minicli`: CLI 框架
  - `php-standard-library/php-standard-library`: PSL 标准库
- **开发依赖**:
  - `pestphp/pest`: 测试框架
  - `phpstan/phpstan`: 静态分析

---

## 开发规范

1. **命名规范**: 使用英文驼峰/蛇式，禁止拼音；常量全大写加下划线
2. **函数规范**: 单行长度 ≤ 80；圈复杂度 ≤ 5；纯函数优先
3. **类规范**: 单文件单类；职责单一（SRP）
4. **注释规范**: 公共 API 必须包含行内文档（docstring）
5. **异常处理**: 禁止裸 `except:`；自定义异常继承自标准异常类

---

## 关键文件说明

| 文件 | 说明 |
|------|------|
| `src/Remotion.php` | 主门面类，提供统一入口 |
| `src/Core/Composition.php` | 视频合成核心类 |
| `src/Core/RenderContext.php` | 渲染上下文，包含帧号、配置、Props |
| `src/Animation/Easing.php` | 缓动函数库（贝塞尔、弹性、回弹等） |
| `src/Animation/Interpolate.php` | 数值与颜色插值 |
| `src/Animation/Spring.php` | 物理弹簧动画计算 |
| `src/Layers/*.php` | 各种图层类型实现 |
| `example.php` | 完整使用示例 |
| `bin/remotion` | CLI 入口脚本 |

---

## 参考资料

- [Remotion 官方文档](https://www.remotion.dev/docs)
- [Composer 文档](https://getcomposer.org/doc/)
- [Pest PHP 测试框架](https://pestphp.com/)
