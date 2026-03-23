<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Core;

use Yangweijie\Remotion\Animation\Easing;
use Yangweijie\Remotion\Animation\Interpolate;
use Yangweijie\Remotion\Animation\Spring;

/**
 * RenderContext
 *
 * 对标 remotion 中每个组件渲染时的上下文环境。
 * 提供 useCurrentFrame() 和 useVideoConfig() 的 PHP 等价物。
 * 新增：内置插值、缓动、弹簧动画等工具方法
 *
 * @see https://www.remotion.dev/docs/use-current-frame
 * @see https://www.remotion.dev/docs/use-video-config
 */
class RenderContext
{
    /**
     * 当前帧号（从 0 开始，对标 remotion 的 useCurrentFrame()）
     */
    public readonly int $frame;

    /**
     * 视频配置（对标 remotion 的 useVideoConfig()）
     */
    public readonly VideoConfig $videoConfig;

    /**
     * 组件 Props（对标 remotion 的 defaultProps）
     */
    public readonly array $props;

    /**
     * 父合成 ID（用于嵌套合成）
     */
    public readonly ?string $compositionId;

    public function __construct(
        int $frame,
        VideoConfig $videoConfig,
        array $props = [],
        ?string $compositionId = null,
    ) {
        $this->frame         = $frame;
        $this->videoConfig   = $videoConfig;
        $this->props         = $props;
        $this->compositionId = $compositionId;
    }

    /**
     * 获取当前帧号（对标 useCurrentFrame()）
     */
    public function getCurrentFrame(): int
    {
        return $this->frame;
    }

    /**
     * 获取视频配置（对标 useVideoConfig()）
     */
    public function getVideoConfig(): VideoConfig
    {
        return $this->videoConfig;
    }

    /**
     * 获取 Prop 值
     */
    public function getProp(string $key, mixed $default = null): mixed
    {
        return $this->props[$key] ?? $default;
    }

    /**
     * 获取当前帧占总帧数的进度比例 (0.0 - 1.0)
     */
    public function getProgress(): float
    {
        $total = $this->videoConfig->durationInFrames - 1;
        if ($total <= 0) {
            return 1.0;
        }
        return $this->frame / $total;
    }

    /**
     * 获取当前时间（秒）
     */
    public function getCurrentTime(): float
    {
        return $this->frame / $this->videoConfig->fps;
    }

    /**
     * 创建一个帧偏移后的子上下文（用于 Sequence 组件）
     */
    public function withFrameOffset(int $offset): self
    {
        return new self(
            frame: $this->frame - $offset,
            videoConfig: $this->videoConfig,
            props: $this->props,
            compositionId: $this->compositionId,
        );
    }

    /**
     * 创建一个包含额外 Props 的新上下文
     */
    public function withProps(array $props): self
    {
        return new self(
            frame: $this->frame,
            videoConfig: $this->videoConfig,
            props: array_merge($this->props, $props),
            compositionId: $this->compositionId,
        );
    }

    /**
     * 创建一个使用新 VideoConfig 的上下文（用于子序列）
     */
    public function withVideoConfig(VideoConfig $videoConfig): self
    {
        return new self(
            frame: $this->frame,
            videoConfig: $videoConfig,
            props: $this->props,
            compositionId: $this->compositionId,
        );
    }

    /**
     * 判断当前帧是否在给定范围内
     */
    public function isInRange(int $from, int $durationInFrames): bool
    {
        return $this->frame >= $from && $this->frame < ($from + $durationInFrames);
    }

    // ============================================================
    // 插值和动画工具方法
    // ============================================================

    /**
     * 基于当前帧进行插值计算
     *
     * @param float[] $inputRange  输入范围，如 [0, 30]
     * @param float[] $outputRange 输出范围，如 [0, 100]
     * @param array{
     *     easing?: \Closure,
     *     extrapolateLeft?: 'extend'|'clamp'|'identity',
     *     extrapolateRight?: 'extend'|'clamp'|'identity',
     * } $options 插值选项
     */
    public function interpolate(
        array $inputRange,
        array $outputRange,
        array $options = [],
    ): float {
        return Interpolate::interpolate($this->frame, $inputRange, $outputRange, $options);
    }

    /**
     * 基于当前帧进行颜色插值
     *
     * @param float[] $inputRange  输入范围
     * @param int[][] $colorRange  颜色范围，如 [[255,0,0], [0,0,255]]
     * @return int[] 插值颜色 [r, g, b]
     */
    public function interpolateColors(
        array $inputRange,
        array $colorRange,
        array $options = [],
    ): array {
        return Interpolate::interpolateColors($this->frame, $inputRange, $colorRange, $options);
    }

    /**
     * 基于当前帧计算弹簧动画值
     *
     * @param array{
     *     stiffness?: float,
     *     damping?: float,
     *     mass?: float,
     *     from?: float,
     *     to?: float,
     * } $options 弹簧选项
     */
    public function spring(array $options = []): float
    {
        return Spring::spring($this->frame, $this->videoConfig->fps, $options);
    }

    /**
     * 获取当前帧在指定范围内的相对进度 (0.0 - 1.0)
     *
     * @param int $from  范围起始帧
     * @param int $to    范围结束帧（不包含）
     */
    public function getRelativeProgress(int $from, int $to): float
    {
        if ($to <= $from) {
            return $this->frame >= $from ? 1.0 : 0.0;
        }

        $progress = ($this->frame - $from) / ($to - $from);
        return max(0.0, min(1.0, $progress));
    }

    /**
     * 获取当前帧在合成内的相对进度 (0.0 - 1.0)
     * 等同于 getProgress()，但语义更清晰
     */
    public function getCompositionProgress(): float
    {
        return $this->getProgress();
    }

    /**
     * 检查当前帧是否在动画范围内，并返回插值进度
     *
     * @param int   $from     动画起始帧
     * @param int   $duration 动画持续帧数
     * @param float $fromVal  起始值
     * @param float $toVal    结束值
     * @param array $options  插值选项
     * @return float|null 如果不在范围内返回 null，否则返回插值结果
     */
    public function animate(
        int $from,
        int $duration,
        float $fromVal,
        float $toVal,
        array $options = [],
    ): ?float {
        if (!$this->isInRange($from, $duration)) {
            return null;
        }

        return $this->interpolate(
            [$from, $from + $duration],
            [$fromVal, $toVal],
            $options
        );
    }

    /**
     * 淡入动画（0 -> 1）
     *
     * @param int   $from     淡入起始帧
     * @param int   $duration 淡入持续帧数
     * @param array $options  插值选项
     * @return float|null 如果不在淡入范围内返回 null
     */
    public function fadeIn(int $from, int $duration, array $options = []): ?float
    {
        return $this->animate($from, $duration, 0.0, 1.0, $options);
    }

    /**
     * 淡出动画（1 -> 0）
     *
     * @param int   $from     淡出起始帧
     * @param int   $duration 淡出持续帧数
     * @param array $options  插值选项
     * @return float|null 如果不在淡出范围内返回 null
     */
    public function fadeOut(int $from, int $duration, array $options = []): ?float
    {
        return $this->animate($from, $duration, 1.0, 0.0, $options);
    }

    /**
     * 滑入动画（从屏幕外滑入）
     *
     * @param string $direction 滑入方向: 'left'|'right'|'top'|'bottom'
     * @param int    $from      动画起始帧
     * @param int    $duration  动画持续帧数
     * @param array  $options   插值选项
     * @return array{x: float, y: float}|null 偏移量
     */
    public function slideIn(
        string $direction,
        int $from,
        int $duration,
        array $options = [],
    ): ?array {
        $progress = $this->animate($from, $duration, 0.0, 1.0, $options);
        if ($progress === null) {
            return null;
        }

        $width = $this->videoConfig->width;
        $height = $this->videoConfig->height;

        return match ($direction) {
            'left'   => ['x' => -$width * (1 - $progress), 'y' => 0],
            'right'  => ['x' => $width * (1 - $progress), 'y' => 0],
            'top'    => ['x' => 0, 'y' => -$height * (1 - $progress)],
            'bottom' => ['x' => 0, 'y' => $height * (1 - $progress)],
            default  => ['x' => 0, 'y' => 0],
        };
    }

    /**
     * 缩放动画
     *
     * @param int   $from      动画起始帧
     * @param int   $duration  动画持续帧数
     * @param float $fromScale 起始缩放比例
     * @param float $toScale   结束缩放比例
     * @param array $options   插值选项
     * @return float|null 当前缩放比例
     */
    public function scale(
        int $from,
        int $duration,
        float $fromScale = 0.0,
        float $toScale = 1.0,
        array $options = [],
    ): ?float {
        return $this->animate($from, $duration, $fromScale, $toScale, $options);
    }

    /**
     * 旋转动画
     *
     * @param int   $from      动画起始帧
     * @param int   $duration  动画持续帧数
     * @param float $fromAngle 起始角度（度）
     * @param float $toAngle   结束角度（度）
     * @param array $options   插值选项
     * @return float|null 当前角度
     */
    public function rotate(
        int $from,
        int $duration,
        float $fromAngle = 0.0,
        float $toAngle = 360.0,
        array $options = [],
    ): ?float {
        return $this->animate($from, $duration, $fromAngle, $toAngle, $options);
    }

    /**
     * 获取常用缓动函数
     */
    public static function easeIn(): \Closure
    {
        return Easing::easeIn(...);
    }

    public static function easeOut(): \Closure
    {
        return Easing::easeOut(...);
    }

    public static function easeInOut(): \Closure
    {
        return Easing::easeInOut(...);
    }

    public static function linear(): \Closure
    {
        return fn ($t) => $t;
    }

    /**
     * 获取贝塞尔缓动函数
     */
    public static function bezier(float $x1, float $y1, float $x2, float $y2): \Closure
    {
        return Easing::bezier($x1, $y1, $x2, $y2);
    }

    /**
     * 获取弹性缓动函数
     */
    public static function elastic(int $bounciness = 1): \Closure
    {
        return Easing::elastic($bounciness);
    }

    /**
     * 获取回弹缓动函数
     */
    public static function bounce(int $bounciness = 1): \Closure
    {
        return Easing::bounce($bounciness);
    }
}
