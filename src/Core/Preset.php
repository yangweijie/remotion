<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Core;

/**
 * 预设配置
 *
 * 提供常用分辨率、帧率、质量预设，快速创建视频配置。
 *
 * 使用示例：
 * ```php
 * // 使用预设
 * $config = Preset::HD_1080P(); // 1920x1080, 30fps
 * $config = Preset::HD_720P();  // 1280x720, 30fps
 *
 * // 使用预设创建合成
 * $comp = Remotion::composition(
 *     id: 'my-video',
 *     renderer: $renderer,
 *     ...Preset::HD_1080P()->toArray(),
 * );
 *
 * // 自定义预设
 * $preset = Preset::create(
 *     width: 1920,
 *     height: 1080,
 *     fps: 60,
 *     durationInFrames: 180,
 * );
 * ```
 */
class Preset
{
    /**
     * @param int $width 宽度（像素）
     * @param int $height 高度（像素）
     * @param int $fps 帧率
     * @param int $durationInFrames 默认持续帧数
     */
    public function __construct(
        public readonly int $width,
        public readonly int $height,
        public readonly int $fps = 30,
        public readonly int $durationInFrames = 90,
    ) {}

    /**
     * 创建自定义预设
     */
    public static function create(
        int $width,
        int $height,
        int $fps = 30,
        int $durationInFrames = 90,
    ): self {
        return new self($width, $height, $fps, $durationInFrames);
    }

    // ============================================================
    // 标准预设 - 横向视频 (16:9)
    // ============================================================

    /**
     * 4K UHD - 3840x2160, 30fps
     */
    public static function UHD_4K(int $durationInFrames = 90): self
    {
        return new self(3840, 2160, 30, $durationInFrames);
    }

    /**
     * 4K UHD - 3840x2160, 60fps
     */
    public static function UHD_4K_60FPS(int $durationInFrames = 180): self
    {
        return new self(3840, 2160, 60, $durationInFrames);
    }

    /**
     * 1080p Full HD - 1920x1080, 30fps
     */
    public static function HD_1080P(int $durationInFrames = 90): self
    {
        return new self(1920, 1080, 30, $durationInFrames);
    }

    /**
     * 1080p Full HD - 1920x1080, 60fps
     */
    public static function HD_1080P_60FPS(int $durationInFrames = 180): self
    {
        return new self(1920, 1080, 60, $durationInFrames);
    }

    /**
     * 720p HD - 1280x720, 30fps
     */
    public static function HD_720P(int $durationInFrames = 90): self
    {
        return new self(1280, 720, 30, $durationInFrames);
    }

    /**
     * 480p SD - 854x480, 30fps
     */
    public static function SD_480P(int $durationInFrames = 90): self
    {
        return new self(854, 480, 30, $durationInFrames);
    }

    /**
     * 360p - 640x360, 30fps
     */
    public static function LD_360P(int $durationInFrames = 90): self
    {
        return new self(640, 360, 30, $durationInFrames);
    }

    // ============================================================
    // 标准预设 - 竖向视频 (9:16)
    // ============================================================

    /**
     * 竖屏 1080x1920, 30fps (抖音/Instagram Stories)
     */
    public static function STORY_1080P(int $durationInFrames = 90): self
    {
        return new self(1080, 1920, 30, $durationInFrames);
    }

    /**
     * 竖屏 1080x1920, 60fps
     */
    public static function STORY_1080P_60FPS(int $durationInFrames = 180): self
    {
        return new self(1080, 1920, 60, $durationInFrames);
    }

    /**
     * 竖屏 720x1280, 30fps
     */
    public static function STORY_720P(int $durationInFrames = 90): self
    {
        return new self(720, 1280, 30, $durationInFrames);
    }

    // ============================================================
    // 标准预设 - 方形视频 (1:1)
    // ============================================================

    /**
     * 方形 1080x1080, 30fps (Instagram)
     */
    public static function SQUARE_1080P(int $durationInFrames = 90): self
    {
        return new self(1080, 1080, 30, $durationInFrames);
    }

    /**
     * 方形 720x720, 30fps
     */
    public static function SQUARE_720P(int $durationInFrames = 90): self
    {
        return new self(720, 720, 30, $durationInFrames);
    }

    // ============================================================
    // 标准预设 - 其他常见比例
    // ============================================================

    /**
     * 21:9 超宽屏 - 2560x1080, 30fps
     */
    public static function ULTRAWIDE_21_9(int $durationInFrames = 90): self
    {
        return new self(2560, 1080, 30, $durationInFrames);
    }

    /**
     * 21:9 超宽屏 4K - 3840x1600, 30fps
     */
    public static function ULTRAWIDE_4K(int $durationInFrames = 90): self
    {
        return new self(3840, 1600, 30, $durationInFrames);
    }

    /**
     * 4:3 传统比例 - 1024x768, 30fps
     */
    public static function CLASSIC_4_3(int $durationInFrames = 90): self
    {
        return new self(1024, 768, 30, $durationInFrames);
    }

    /**
     * GIF 优化尺寸 - 640x360, 15fps
     */
    public static function GIF_OPTIMIZED(int $durationInFrames = 45): self
    {
        return new self(640, 360, 15, $durationInFrames);
    }

    /**
     * 缩略图尺寸 - 320x180, 10fps
     */
    public static function THUMBNAIL(int $durationInFrames = 30): self
    {
        return new self(320, 180, 10, $durationInFrames);
    }

    // ============================================================
    // 社交媒体预设
    // ============================================================

    /**
     * YouTube 标准 - 1920x1080, 30fps
     */
    public static function YOUTUBE(): self
    {
        return self::HD_1080P(90);
    }

    /**
     * YouTube Shorts - 1080x1920, 30fps
     */
    public static function YOUTUBE_SHORTS(): self
    {
        return self::STORY_1080P(60);
    }

    /**
     * TikTok/抖音 - 1080x1920, 30fps
     */
    public static function TIKTOK(): self
    {
        return self::STORY_1080P(90);
    }

    /**
     * Instagram Feed - 1080x1080, 30fps
     */
    public static function INSTAGRAM(): self
    {
        return self::SQUARE_1080P(90);
    }

    /**
     * Instagram Reels - 1080x1920, 30fps
     */
    public static function INSTAGRAM_REELS(): self
    {
        return self::STORY_1080P(90);
    }

    /**
     * Twitter/X - 1280x720, 30fps
     */
    public static function TWITTER(): self
    {
        return self::HD_720P(90);
    }

    /**
     * Facebook - 1280x720, 30fps
     */
    public static function FACEBOOK(): self
    {
        return self::HD_720P(90);
    }

    // ============================================================
    // 转换方法
    // ============================================================

    /**
     * 转换为数组（用于展开到合成参数）
     */
    public function toArray(): array
    {
        return [
            'width'            => $this->width,
            'height'           => $this->height,
            'fps'              => $this->fps,
            'durationInFrames' => $this->durationInFrames,
        ];
    }

    /**
     * 转换为 VideoConfig
     */
    public function toVideoConfig(): VideoConfig
    {
        return new VideoConfig(
            $this->durationInFrames,
            $this->fps,
            $this->width,
            $this->height,
        );
    }

    /**
     * 修改宽度
     */
    public function withWidth(int $width): self
    {
        return new self($width, $this->height, $this->fps, $this->durationInFrames);
    }

    /**
     * 修改高度
     */
    public function withHeight(int $height): self
    {
        return new self($this->width, $height, $this->fps, $this->durationInFrames);
    }

    /**
     * 修改帧率
     */
    public function withFps(int $fps): self
    {
        return new self($this->width, $this->height, $fps, $this->durationInFrames);
    }

    /**
     * 修改持续帧数
     */
    public function withDuration(int $durationInFrames): self
    {
        return new self($this->width, $this->height, $this->fps, $durationInFrames);
    }

    /**
     * 修改持续时间（秒）
     */
    public function withDurationSeconds(float $seconds): self
    {
        return new self(
            $this->width,
            $this->height,
            $this->fps,
            (int) ($seconds * $this->fps),
        );
    }

    /**
     * 缩放尺寸
     */
    public function scale(float $factor): self
    {
        return new self(
            (int) ($this->width * $factor),
            (int) ($this->height * $factor),
            $this->fps,
            $this->durationInFrames,
        );
    }

    // ============================================================
    // 辅助方法
    // ============================================================

    /**
     * 获取长宽比
     */
    public function getAspectRatio(): float
    {
        return $this->width / $this->height;
    }

    /**
     * 获取长宽比字符串（如 "16:9"）
     */
    public function getAspectRatioString(): string
    {
        $gcd = $this->gcd($this->width, $this->height);
        return ($this->width / $gcd) . ':' . ($this->height / $gcd);
    }

    /**
     * 获取总时长（秒）
     */
    public function getDurationSeconds(): float
    {
        return $this->durationInFrames / $this->fps;
    }

    /**
     * 获取总像素数
     */
    public function getTotalPixels(): int
    {
        return $this->width * $this->height;
    }

    /**
     * 获取预设名称描述
     */
    public function getDescription(): string
    {
        $resolution = "{$this->width}x{$this->height}";
        $fps = "{$this->fps}fps";
        $duration = round($this->getDurationSeconds(), 1) . 's';
        $aspect = $this->getAspectRatioString();

        return "{$resolution} @ {$fps} ({$aspect}, {$duration})";
    }

    /**
     * 计算最大公约数
     */
    private function gcd(int $a, int $b): int
    {
        return $b === 0 ? $a : $this->gcd($b, $a % $b);
    }

    public function __toString(): string
    {
        return "Preset{{$this->getDescription()}}";
    }
}
