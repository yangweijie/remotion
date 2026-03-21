<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Core;

use Yangweijie\Remotion\Contracts\ComponentInterface;

/**
 * Sequence
 *
 * PHP 版 remotion <Sequence> 的实现。
 * Sequence 代表合成中一个有起始帧和持续时长的时间段，
 * 它封装一个子组件，并将全局帧号偏移为该片段内的本地帧号。
 *
 * 使用示例：
 * ```php
 * $sequence = new Sequence(
 *     id: 'intro',
 *     component: new IntroComponent(),
 *     from: 0,
 *     durationInFrames: 60,
 * );
 * ```
 *
 * @see https://www.remotion.dev/docs/sequence
 */
class Sequence
{
    private readonly ComponentInterface $component;

    public function __construct(
        private readonly string $id,
        ComponentInterface|\Closure $component,
        private readonly int $from = 0,
        private readonly ?int $durationInFrames = null,
        private readonly string $name = '',
        private readonly bool $layout = true,  // 对标 remotion 的 layout="none" 选项
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

        if ($from < 0) {
            throw new \InvalidArgumentException("Sequence '{$id}': from cannot be negative");
        }
    }

    /**
     * 静态工厂方法
     */
    public static function make(
        string $id,
        ComponentInterface|\Closure $component,
        int $from = 0,
        ?int $durationInFrames = null,
        string $name = '',
    ): self {
        return new self($id, $component, $from, $durationInFrames, $name);
    }

    /**
     * 在给定全局帧上渲染此序列。
     * 如果当前帧不在此序列范围内，返回 null（表示不渲染）。
     *
     * @param RenderContext $ctx 全局渲染上下文
     * @param VideoConfig   $compositionConfig 父合成的视频配置
     * @return \GdImage|null
     */
    public function render(RenderContext $ctx, VideoConfig $compositionConfig): ?\GdImage
    {
        $totalDuration = $this->durationInFrames ?? $compositionConfig->durationInFrames - $this->from;

        // 判断当前帧是否在此序列的活跃范围内
        if ($ctx->frame < $this->from || $ctx->frame >= $this->from + $totalDuration) {
            return null;
        }

        // 构建本地帧上下文（帧号从 0 开始，对应此序列内部）
        $localCtx = $ctx->withFrameOffset($this->from);

        return $this->component->render($localCtx);
    }

    /**
     * 判断给定全局帧是否在此序列的活跃范围内
     */
    public function isActive(int $globalFrame, VideoConfig $compositionConfig): bool
    {
        $totalDuration = $this->durationInFrames ?? $compositionConfig->durationInFrames - $this->from;
        return $globalFrame >= $this->from && $globalFrame < $this->from + $totalDuration;
    }

    /**
     * 获取在给定全局帧的本地帧号
     */
    public function getLocalFrame(int $globalFrame): int
    {
        return $globalFrame - $this->from;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getFrom(): int
    {
        return $this->from;
    }

    public function getDurationInFrames(): ?int
    {
        return $this->durationInFrames;
    }

    public function getName(): string
    {
        return $this->name ?: $this->id;
    }

    public function getComponent(): ComponentInterface
    {
        return $this->component;
    }

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'from'             => $this->from,
            'durationInFrames' => $this->durationInFrames,
            'name'             => $this->name,
        ];
    }

    public function __toString(): string
    {
        $duration = $this->durationInFrames !== null ? $this->durationInFrames : 'auto';
        return sprintf('Sequence{id="%s", from=%d, duration=%s}', $this->id, $this->from, $duration);
    }
}
