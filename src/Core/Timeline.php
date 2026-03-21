<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Core;

/**
 * Timeline
 *
 * 时间轴控制器，管理合成中各个 Sequence 的播放时间。
 * 对标 remotion 中的时间轴概念，支持帧级别的精确控制。
 */
class Timeline
{
    /** @var array<string, array{from: int, durationInFrames: int}> */
    private array $segments = [];

    public function __construct(
        private readonly VideoConfig $videoConfig
    ) {}

    /**
     * 注册一个时间段（对标 Sequence 的 from 和 durationInFrames）
     *
     * @param string $id              时间段 ID
     * @param int    $from            开始帧（相对于合成开始）
     * @param int    $durationInFrames 持续帧数（null 表示到合成结束）
     */
    public function register(string $id, int $from, ?int $durationInFrames = null): self
    {
        $duration = $durationInFrames ?? ($this->videoConfig->durationInFrames - $from);

        if ($from < 0) {
            throw new \InvalidArgumentException("Sequence '{$id}': from cannot be negative");
        }
        if ($duration <= 0) {
            throw new \InvalidArgumentException("Sequence '{$id}': durationInFrames must be > 0");
        }

        $this->segments[$id] = [
            'from'             => $from,
            'durationInFrames' => $duration,
        ];

        return $this;
    }

    /**
     * 判断给定帧号是否处于某个时间段内
     */
    public function isActive(string $id, int $frame): bool
    {
        if (!isset($this->segments[$id])) {
            return false;
        }

        $seg = $this->segments[$id];
        return $frame >= $seg['from'] && $frame < ($seg['from'] + $seg['durationInFrames']);
    }

    /**
     * 获取某帧在特定时间段内的本地帧号（帧偏移）
     * 等同于 remotion Sequence 中的 useCurrentFrame()
     */
    public function getLocalFrame(string $id, int $globalFrame): ?int
    {
        if (!$this->isActive($id, $globalFrame)) {
            return null;
        }

        return $globalFrame - $this->segments[$id]['from'];
    }

    /**
     * 获取所有在给定帧激活的时间段 ID
     *
     * @return string[]
     */
    public function getActiveSegments(int $frame): array
    {
        return array_keys(array_filter(
            $this->segments,
            fn ($seg) => $frame >= $seg['from'] && $frame < ($seg['from'] + $seg['durationInFrames'])
        ));
    }

    /**
     * 获取某个时间段的进度比例 (0.0 - 1.0)
     */
    public function getProgress(string $id, int $frame): ?float
    {
        if (!$this->isActive($id, $frame)) {
            return null;
        }

        $seg  = $this->segments[$id];
        $local = $frame - $seg['from'];
        $total = $seg['durationInFrames'] - 1;

        return $total > 0 ? $local / $total : 1.0;
    }

    /**
     * 构建帧序列（从 0 到 durationInFrames-1）
     *
     * @return int[]
     */
    public function getFrameRange(): array
    {
        return range(0, $this->videoConfig->durationInFrames - 1);
    }

    /**
     * 获取所有已注册时间段
     */
    public function getSegments(): array
    {
        return $this->segments;
    }

    /**
     * 获取视频配置
     */
    public function getVideoConfig(): VideoConfig
    {
        return $this->videoConfig;
    }
}
