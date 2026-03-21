<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Core;

/**
 * VideoConfig
 *
 * 对标 remotion 的视频配置（fps, width, height, durationInFrames）。
 * 对应 useVideoConfig() hook 返回的数据结构。
 *
 * @see https://www.remotion.dev/docs/use-video-config
 */
class VideoConfig
{
    public readonly int $durationInFrames;
    public readonly int $fps;
    public readonly int $width;
    public readonly int $height;

    public function __construct(
        int $durationInFrames,
        int $fps,
        int $width,
        int $height,
    ) {
        if ($durationInFrames <= 0) {
            throw new \InvalidArgumentException('durationInFrames must be greater than 0');
        }
        if ($fps <= 0) {
            throw new \InvalidArgumentException('fps must be greater than 0');
        }
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException('width and height must be greater than 0');
        }

        $this->durationInFrames = $durationInFrames;
        $this->fps              = $fps;
        $this->width            = $width;
        $this->height           = $height;
    }

    /**
     * 获取视频总时长（秒）
     */
    public function getDurationInSeconds(): float
    {
        return $this->durationInFrames / $this->fps;
    }

    /**
     * 获取视频宽高比
     */
    public function getAspectRatio(): float
    {
        return $this->width / $this->height;
    }

    /**
     * 将秒数转换为帧数
     */
    public function secondsToFrames(float $seconds): int
    {
        return (int) round($seconds * $this->fps);
    }

    /**
     * 将帧数转换为秒数
     */
    public function framesToSeconds(int $frames): float
    {
        return $frames / $this->fps;
    }

    /**
     * 创建标准 1080p 配置
     */
    public static function hd(int $durationInFrames = 150, int $fps = 30): self
    {
        return new self($durationInFrames, $fps, 1920, 1080);
    }

    /**
     * 创建正方形配置（适合社交媒体）
     */
    public static function square(int $size = 1080, int $durationInFrames = 150, int $fps = 30): self
    {
        return new self($durationInFrames, $fps, $size, $size);
    }

    /**
     * 创建竖屏配置（适合手机/Shorts）
     */
    public static function portrait(int $durationInFrames = 150, int $fps = 30): self
    {
        return new self($durationInFrames, $fps, 1080, 1920);
    }

    public function toArray(): array
    {
        return [
            'durationInFrames' => $this->durationInFrames,
            'fps'              => $this->fps,
            'width'            => $this->width,
            'height'           => $this->height,
        ];
    }

    public function __toString(): string
    {
        return sprintf(
            'VideoConfig{%dx%d @ %dfps, %d frames (%.2fs)}',
            $this->width,
            $this->height,
            $this->fps,
            $this->durationInFrames,
            $this->getDurationInSeconds()
        );
    }
}
