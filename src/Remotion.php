<?php

declare(strict_types=1);

namespace Yangweijie\Remotion;

use Yangweijie\Remotion\Animation\Easing;
use Yangweijie\Remotion\Animation\Interpolate;
use Yangweijie\Remotion\Animation\Spring;
use Yangweijie\Remotion\Core\Composition;
use Yangweijie\Remotion\Core\RenderContext;
use Yangweijie\Remotion\Core\Sequence;
use Yangweijie\Remotion\Core\Timeline;
use Yangweijie\Remotion\Core\VideoConfig;
use Yangweijie\Remotion\Helpers\Color;
use Yangweijie\Remotion\Helpers\Noise;
use Yangweijie\Remotion\Helpers\Pipeline;
use Yangweijie\Remotion\Helpers\Random;
use Yangweijie\Remotion\Layers\ColorLayer;
use Yangweijie\Remotion\Layers\GradientLayer;
use Yangweijie\Remotion\Layers\ImageLayer;
use Yangweijie\Remotion\Layers\TextLayer;
use Yangweijie\Remotion\Media\Audio;
use Yangweijie\Remotion\Media\Video;
use Yangweijie\Remotion\Rendering\Renderer;
use Yangweijie\Remotion\Transitions\FadeTransition;
use Yangweijie\Remotion\Transitions\SlideTransition;

/**
 * Remotion
 *
 * PHP 版 remotion 库的主门面类（Facade）。
 * 提供统一的入口点，快速访问所有核心功能。
 *
 * 基本使用：
 * ```php
 * use Yangweijie\Remotion\Remotion;
 *
 * // 创建合成
 * $comp = Remotion::composition(
 *     id: 'my-animation',
 *     renderer: function(RenderContext $ctx) {
 *         $frame  = $ctx->getCurrentFrame();
 *         $config = $ctx->getVideoConfig();
 *
 *         // 创建画布
 *         $canvas = imagecreatetruecolor($config->width, $config->height);
 *
 *         // 插值背景颜色
 *         $bgColor = Remotion::interpolateColors($frame, [0, 30], [
 *             [20, 20, 80],   // 深蓝
 *             [80, 20, 80],   // 紫色
 *         ]);
 *
 *         // 绘制背景
 *         $bg = Remotion::colorLayer($config->width, $config->height, ...$bgColor);
 *         $bg->drawOn($canvas);
 *
 *         return $canvas;
 *     },
 *     durationInFrames: 90,
 *     fps: 30,
 *     width: 640,
 *     height: 360,
 * );
 *
 * // 渲染为 GIF
 * Remotion::render($comp)->renderToGif('output.gif');
 * ```
 *
 * @see https://www.remotion.dev/docs
 */
class Remotion
{
    // ---- 合成 ----

    /**
     * 创建一个新的合成（对标 <Composition>）
     *
     * @param string   $id             合成 ID
     * @param callable $renderer       渲染函数 function(RenderContext $ctx): \GdImage
     * @param int      $durationInFrames 总帧数
     * @param int      $fps            帧率
     * @param int      $width          宽度
     * @param int      $height         高度
     * @param array    $defaultProps   默认 Props
     */
    public static function composition(
        string $id,
        callable $renderer,
        int $durationInFrames,
        int $fps,
        int $width,
        int $height,
        array $defaultProps = [],
    ): Composition {
        return Composition::fromClosure(
            $id,
            \Closure::fromCallable($renderer),
            $durationInFrames,
            $fps,
            $width,
            $height,
            $defaultProps,
        );
    }

    /**
     * 创建视频配置
     */
    public static function videoConfig(
        int $durationInFrames,
        int $fps,
        int $width,
        int $height,
    ): VideoConfig {
        return new VideoConfig($durationInFrames, $fps, $width, $height);
    }

    /**
     * 创建序列（对标 <Sequence>）
     */
    public static function sequence(
        string $id,
        callable $renderer,
        int $from = 0,
        ?int $durationInFrames = null,
        string $name = '',
    ): Sequence {
        return Sequence::make($id, \Closure::fromCallable($renderer), $from, $durationInFrames, $name);
    }

    /**
     * 创建 Loop 组件
     *
     * 循环播放子组件指定次数。
     *
     * @param callable $renderer 渲染函数
     * @param int $durationInFrames 单次循环的帧数
     * @param int $times 循环次数
     * @param int|null $from 起始帧偏移
     */
    public static function loop(
        callable $renderer,
        int $durationInFrames,
        int $times = 1,
        ?int $from = 0,
    ): Core\Loop {
        return Core\Loop::make(\Closure::fromCallable($renderer), $durationInFrames, $times, $from);
    }

    /**
     * 创建 Series 组件
     *
     * 自动排列多个序列。
     */
    public static function series(): Core\Series
    {
        return Core\Series::make();
    }

    /**
     * 创建 Freeze 组件
     *
     * 冻结子组件到指定帧。
     *
     * @param callable $renderer 渲染函数
     * @param int $frame 冻结到的帧号
     * @param int $duration 冻结持续的帧数
     * @param int $from 从哪一帧开始冻结
     */
    public static function freeze(
        callable $renderer,
        int $frame,
        int $duration,
        int $from = 0,
    ): Core\Freeze {
        return Core\Freeze::make(\Closure::fromCallable($renderer), $frame, $duration, $from);
    }

    // ---- 渲染 ----

    /**
     * 创建渲染器
     */
    public static function render(Composition $composition): Renderer
    {
        return new Renderer($composition);
    }

    // ---- 动画工具 ----

    /**
     * 数值插值（对标 remotion 的 interpolate()）
     */
    public static function interpolate(
        float $input,
        array $inputRange,
        array $outputRange,
        array $options = [],
    ): float {
        return Interpolate::interpolate($input, $inputRange, $outputRange, $options);
    }

    /**
     * 颜色插值
     *
     * @return int[] [r, g, b]
     */
    public static function interpolateColors(
        float $input,
        array $inputRange,
        array $colorRange,
        array $options = [],
    ): array {
        return Interpolate::interpolateColors($input, $inputRange, $colorRange, $options);
    }

    /**
     * 弹簧动画（对标 remotion 的 spring()）
     */
    public static function spring(int $frame, int $fps, array $config = []): float
    {
        return Spring::spring($frame, $fps, $config);
    }

    /**
     * 获取缓动函数（Easing）
     */
    public static function easing(): Easing
    {
        // 静态工具类，作为命名空间入口
        return new Easing();
    }

    // ---- 图层工厂 ----

    /**
     * 创建纯色图层
     */
    public static function colorLayer(int $width, int $height, int $r, int $g, int $b): ColorLayer
    {
        return new ColorLayer($width, $height, $r, $g, $b);
    }

    /**
     * 从 HEX 颜色创建图层
     */
    public static function colorLayerFromHex(int $width, int $height, string $hex): ColorLayer
    {
        return ColorLayer::fromHex($width, $height, $hex);
    }

    /**
     * 创建渐变图层
     */
    public static function gradientLayer(
        int $width,
        int $height,
        array $startColor,
        array $endColor,
        string $direction = 'vertical',
    ): GradientLayer {
        return GradientLayer::make($width, $height, $startColor, $endColor, $direction);
    }

    /**
     * 创建图像图层
     */
    public static function imageLayer(string $path, ?int $width = null, ?int $height = null): ImageLayer
    {
        return new ImageLayer($path, $width, $height);
    }

    /**
     * 创建文字图层
     */
    public static function textLayer(string $text, array $styles = []): TextLayer
    {
        return TextLayer::make($text, $styles);
    }

    // ---- 工具 ----

    /**
     * 创建管道
     */
    public static function pipe(mixed $value): Pipeline
    {
        return Pipeline::make($value);
    }

    /**
     * 创建颜色对象
     */
    public static function color(int $r, int $g, int $b, float $a = 1.0): Color
    {
        return new Color($r, $g, $b, $a);
    }

    /**
     * 从 HEX 字符串创建颜色
     */
    public static function colorFromHex(string $hex): Color
    {
        return Color::fromHex($hex);
    }

    /**
     * 快速创建画布（带透明支持）- GD 版本
     * @deprecated 请使用 createImageCanvas() 代替
     */
    public static function createCanvas(int $width, int $height, ?array $backgroundColor = null): \GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);

        if ($backgroundColor !== null) {
            [$r, $g, $b] = $backgroundColor;
            $bg = imagecolorallocate($canvas, $r, $g, $b);
            imagefill($canvas, 0, 0, $bg);
        } else {
            // 透明背景
            imagealphablending($canvas, false);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefill($canvas, 0, 0, $transparent);
            imagealphablending($canvas, true);
        }

        return $canvas;
    }

    /**
     * 创建 Grafika 图像画布（推荐使用，支持 GD/Imagick 自动检测）
     *
     * @param int         $width         宽度
     * @param int         $height        高度
     * @param array|null  $backgroundColor 背景色 [R, G, B] 或 null 表示透明
     * @return \Grafika\ImageInterface 图像对象
     */
    public static function createImageCanvas(int $width, int $height, ?array $backgroundColor = null): \Grafika\ImageInterface
    {
        $image = \Grafika\Grafika::createBlankImage($width, $height);
        
        if ($backgroundColor !== null) {
            $editor = \Grafika\Grafika::createEditor();
            $color = new \Grafika\Color(sprintf('#%02x%02x%02x', $backgroundColor[0], $backgroundColor[1], $backgroundColor[2]));
            $editor->fill($image, $color);
        }
        
        return $image;
    }

    /**
     * 注册合成（对标 registerRoot()）
     * 批量注册多个合成
     *
     * @param Composition[] $compositions
     */
    public static function registerRoot(array $compositions): void
    {
        foreach ($compositions as $comp) {
            $comp->register();
        }
    }

    // ---- P1: Random ----

    /**
     * 生成确定性伪随机数
     *
     * @param string $seed 随机种子
     * @param int $frame 帧号
     * @param float|null $min 最小值
     * @param float|null $max 最大值
     * @return float 0-1 或指定范围内的随机数
     */
    public static function random(string $seed, int $frame, ?float $min = null, ?float $max = null): float
    {
        return Random::get($seed, $frame, $min, $max);
    }

    /**
     * 生成确定性随机整数
     */
    public static function randomRange(string $seed, int $frame, int $min, int $max): int
    {
        return Random::range($seed, $frame, $min, $max);
    }

    /**
     * 生成随机颜色
     */
    public static function randomColor(string $seed, int $frame): array
    {
        return Random::color($seed, $frame);
    }

    // ---- P1: Transitions ----

    /**
     * 创建淡入淡出转场
     */
    public static function fadeTransition(int $durationInFrames): Transitions\FadeTransition
    {
        return new Transitions\FadeTransition($durationInFrames);
    }

    /**
     * 创建滑动转场
     */
    public static function slideTransition(int $durationInFrames, string $direction = 'left'): Transitions\SlideTransition
    {
        return new Transitions\SlideTransition($durationInFrames, $direction);
    }

    // ---- P2: Noise ----

    /**
     * 生成 2D Perlin 噪声
     */
    public static function noise2D(float $x, float $y, int $seed = 0): float
    {
        return Noise::noise2D($x, $y, $seed);
    }

    /**
     * 生成 3D Perlin 噪声
     */
    public static function noise3D(float $x, float $y, float $z, int $seed = 0): float
    {
        return Noise::noise3D($x, $y, $z, $seed);
    }

    /**
     * 生成 4D Perlin 噪声
     */
    public static function noise4D(float $x, float $y, float $z, float $w, int $seed = 0): float
    {
        return Noise::noise4D($x, $y, $z, $w, $seed);
    }

    /**
     * 生成分形噪声（FBm）
     */
    public static function fbm(float $x, float $y, int $seed = 0, int $octaves = 4): float
    {
        return Noise::fbm($x, $y, $seed, $octaves);
    }

    // ---- P2: Media ----

    /**
     * 创建音频实例
     */
    public static function audio(string $filePath): Media\Audio
    {
        return Media\Audio::fromFile($filePath);
    }

    /**
     * 创建视频实例
     */
    public static function video(string $filePath): Media\Video
    {
        return Media\Video::fromFile($filePath);
    }
}
