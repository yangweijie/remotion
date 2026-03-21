<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Core;

/**
 * RenderContext
 *
 * 对标 remotion 中每个组件渲染时的上下文环境。
 * 提供 useCurrentFrame() 和 useVideoConfig() 的 PHP 等价物。
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
}
