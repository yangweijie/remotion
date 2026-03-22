<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Core;

use Yangweijie\Remotion\Contracts\ComponentInterface;
use InvalidArgumentException;

/**
 * Freeze 组件
 *
 * 冻结子组件到指定帧，在指定时间段内持续显示该帧。
 * 对标 Remotion 的 <Freeze> 组件。
 *
 * 使用示例：
 * ```php
 * $freeze = Freeze::make(
 *     component: fn($ctx) => $animation->render($ctx),
 *     frame: 30,            // 冻结到第 30 帧
 *     duration: 60,         // 冻结持续 60 帧
 *     from: 0,              // 从第 0 帧开始冻结（可选）
 * );
 * ```
 *
 * @see https://www.remotion.dev/docs/freeze
 */
class Freeze
{
    private readonly ComponentInterface $component;

    public function __construct(
        ComponentInterface|\Closure $component,
        private readonly int $frame,
        private readonly int $duration,
        private readonly int $from = 0,
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

        if ($frame < 0) {
            throw new InvalidArgumentException('Freeze: frame cannot be negative');
        }

        if ($duration <= 0) {
            throw new InvalidArgumentException('Freeze: duration must be positive');
        }

        if ($from < 0) {
            throw new InvalidArgumentException('Freeze: from cannot be negative');
        }
    }

    /**
     * 静态工厂方法
     */
    public static function make(
        ComponentInterface|\Closure $component,
        int $frame,
        int $duration,
        int $from = 0,
    ): self {
        return new self($component, $frame, $duration, $from);
    }

    /**
     * 获取总帧数
     */
    public function getTotalDuration(): int
    {
        return $this->duration;
    }

    /**
     * 获取结束帧
     */
    public function getEndFrame(): int
    {
        return $this->from + $this->duration;
    }

    /**
     * 渲染冻结帧
     *
     * @param RenderContext $ctx 全局渲染上下文
     * @param VideoConfig $config 视频配置
     * @return \GdImage|null
     */
    public function render(RenderContext $ctx, VideoConfig $config): ?\GdImage
    {
        $globalFrame = $ctx->getCurrentFrame();

        // 检查是否在冻结范围内
        if ($globalFrame < $this->from || $globalFrame >= $this->from + $this->duration) {
            return null;
        }

        // 创建冻结帧上下文
        // 无论当前是哪一帧，都渲染指定的冻结帧
        $frozenCtx = $ctx->withFrameOffset($globalFrame - $this->frame);

        return $this->component->render($frozenCtx);
    }

    /**
     * 判断给定帧是否在冻结范围内
     */
    public function isActive(int $globalFrame): bool
    {
        return $globalFrame >= $this->from && $globalFrame < $this->from + $this->duration;
    }

    /**
     * 获取冻结的目标帧号
     */
    public function getFrozenFrame(): int
    {
        return $this->frame;
    }

    /**
     * 获取冻结进度（0.0 - 1.0）
     */
    public function getProgress(int $globalFrame): float
    {
        if (!$this->isActive($globalFrame)) {
            return $this->frame < $this->from ? 1.0 : 0.0;
        }

        return ($globalFrame - $this->from) / $this->duration;
    }
}
