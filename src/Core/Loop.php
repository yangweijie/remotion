<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Core;

use Yangweijie\Remotion\Contracts\ComponentInterface;
use InvalidArgumentException;

/**
 * Loop 组件
 *
 * 循环播放子组件指定次数。
 * 对标 Remotion 的 <Loop> 组件。
 *
 * 使用示例：
 * ```php
 * $loop = Loop::make(
 *     component: fn($ctx) => $textLayer->render($ctx),
 *     durationInFrames: 30,  // 单次循环的帧数
 *     times: 3,              // 循环次数
 * );
 * ```
 *
 * @see https://www.remotion.dev/docs/loop
 */
class Loop
{
    private readonly ComponentInterface $component;

    public function __construct(
        ComponentInterface|\Closure $component,
        private readonly int $durationInFrames,
        private readonly int $times = 1,
        private readonly ?int $from = 0,  // 起始帧偏移
    ) {
        if ($component instanceof \Closure) {
            $fn = $component;
            $this->component = new class($fn) implements ComponentInterface {
                public function __construct(private readonly \Closure $fn) {}

                public function render(RenderContext $ctx): \GdImage
                {
                    return ($this->fn)($ctx);
                }
            };
        } else {
            $this->component = $component;
        }

        if ($durationInFrames <= 0) {
            throw new InvalidArgumentException('Loop: durationInFrames must be positive');
        }

        if ($times <= 0) {
            throw new InvalidArgumentException('Loop: times must be positive');
        }
    }

    /**
     * 静态工厂方法
     */
    public static function make(
        ComponentInterface|\Closure $component,
        int $durationInFrames,
        int $times = 1,
        ?int $from = 0,
    ): self {
        return new self($component, $durationInFrames, $times, $from);
    }

    /**
     * 获取总帧数
     */
    public function getTotalDuration(): int
    {
        return $this->durationInFrames * $this->times;
    }

    /**
     * 渲染循环
     *
     * @param RenderContext $ctx 全局渲染上下文
     * @param VideoConfig $config 视频配置
     * @return \GdImage|null
     */
    public function render(RenderContext $ctx, VideoConfig $config): ?\GdImage
    {
        $totalDuration = $this->getTotalDuration();
        $globalFrame = $ctx->getCurrentFrame();

        // 考虑起始偏移
        $effectiveFrame = $globalFrame - ($this->from ?? 0);

        // 检查是否在循环范围内
        if ($effectiveFrame < 0 || $effectiveFrame >= $totalDuration) {
            return null;
        }

        // 计算当前循环内的本地帧号
        $localFrame = $effectiveFrame % $this->durationInFrames;

        // 创建本地上下文
        $localCtx = $ctx->withFrameOffset($globalFrame - $localFrame);

        return $this->component->render($localCtx);
    }

    /**
     * 判断给定帧是否在循环范围内
     */
    public function isActive(int $globalFrame): bool
    {
        $totalDuration = $this->getTotalDuration();
        $effectiveFrame = $globalFrame - ($this->from ?? 0);

        return $effectiveFrame >= 0 && $effectiveFrame < $totalDuration;
    }

    /**
     * 获取当前循环次数（从 0 开始）
     */
    public function getCurrentLoopIndex(int $globalFrame): int
    {
        $effectiveFrame = $globalFrame - ($this->from ?? 0);

        if ($effectiveFrame < 0) {
            return -1;
        }

        return (int) floor($effectiveFrame / $this->durationInFrames);
    }

    /**
     * 获取循环内的本地帧号
     */
    public function getLocalFrame(int $globalFrame): int
    {
        $effectiveFrame = $globalFrame - ($this->from ?? 0);

        return $effectiveFrame % $this->durationInFrames;
    }
}
