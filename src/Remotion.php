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
use Yangweijie\Remotion\Helpers\Pipeline;
use Yangweijie\Remotion\Layers\ColorLayer;
use Yangweijie\Remotion\Layers\GradientLayer;
use Yangweijie\Remotion\Layers\ImageLayer;
use Yangweijie\Remotion\Layers\TextLayer;
use Yangweijie\Remotion\Rendering\Renderer;

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
     * 快速创建画布（带透明支持）
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
}
