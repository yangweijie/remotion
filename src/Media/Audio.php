<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Media;

use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Media\Audio as FFAudio;

/**
 * Audio - 音频处理组件
 *
 * 提供音频文件的读取、分析和处理功能。
 * 基于 php-ffmpeg/php-ffmpeg 库。
 *
 * 使用示例：
 * ```php
 * use Yangweijie\Remotion\Media\Audio;
 *
 * // 加载音频文件
 * $audio = Audio::fromFile('/path/to/audio.mp3');
 *
 * // 获取音频信息
 * $duration = $audio->getDuration(); // 秒
 * $sampleRate = $audio->getSampleRate();
 * $channels = $audio->getChannels();
 *
 * // 提取音频片段
 * $clip = $audio->clip(5.0, 10.0); // 从 5 秒到 10 秒
 *
 * // 保存音频
 * $audio->save('/path/to/output.mp3');
 * ```
 *
 * @see https://www.remotion.dev/docs/audio
 */
class Audio
{
    /**
     * FFMpeg 实例
     */
    private ?FFMpeg $ffmpeg = null;

    /**
     * 音频对象
     */
    private ?FFAudio $audio = null;

    /**
     * 文件路径
     */
    private string $filePath;

    /**
     * 创建 Audio 实例
     */
    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $this->ffmpeg = FFMpeg::create();
        $this->audio = $this->ffmpeg->open($filePath);
    }

    /**
     * 从文件创建音频实例
     */
    public static function fromFile(string $filePath): self
    {
        return new self($filePath);
    }

    /**
     * 获取音频时长（秒）
     */
    public function getDuration(): float
    {
        if ($this->audio === null) {
            return 0.0;
        }

        $format = $this->audio->getStreams()->first()->get('duration');
        
        if (is_numeric($format)) {
            return (float) $format;
        }

        return 0.0;
    }

    /**
     * 获取采样率
     */
    public function getSampleRate(): int
    {
        if ($this->audio === null) {
            return 0;
        }

        $stream = $this->audio->getStreams()->first();
        return (int) $stream->get('sample_rate', 44100);
    }

    /**
     * 获取声道数
     */
    public function getChannels(): int
    {
        if ($this->audio === null) {
            return 0;
        }

        $stream = $this->audio->getStreams()->first();
        return (int) $stream->get('channels', 2);
    }

    /**
     * 获取比特率
     */
    public function getBitRate(): int
    {
        if ($this->audio === null) {
            return 0;
        }

        $stream = $this->audio->getStreams()->first();
        return (int) $stream->get('bit_rate', 0);
    }

    /**
     * 获取编解码器
     */
    public function getCodec(): string
    {
        if ($this->audio === null) {
            return '';
        }

        $stream = $this->audio->getStreams()->first();
        return (string) $stream->get('codec_name', 'unknown');
    }

    /**
     * 获取文件路径
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * 获取原始音频对象
     */
    public function getRaw(): ?FFAudio
    {
        return $this->audio;
    }

    /**
     * 检查音频是否在指定时间范围内
     */
    public function isInRange(float $timeInSeconds, float $startTime, float $duration): bool
    {
        $endTime = $startTime + $duration;
        return $timeInSeconds >= $startTime && $timeInSeconds < $endTime;
    }

    /**
     * 将帧号转换为时间（秒）
     */
    public static function frameToTime(int $frame, int $fps): float
    {
        return $frame / $fps;
    }

    /**
     * 将时间（秒）转换为帧号
     */
    public static function timeToFrame(float $time, int $fps): int
    {
        return (int) round($time * $fps);
    }

    /**
     * 计算音频在指定帧的播放进度
     *
     * @param int $frame 当前帧
     * @param int $fps 帧率
     * @param float $startTime 开始时间（秒）
     * @param float|null $duration 持续时间（秒），null 表示整个音频
     * @return float|null 0-1 的进度值，如果不在播放范围则返回 null
     */
    public function getProgressAtFrame(int $frame, int $fps, float $startTime = 0.0, ?float $duration = null): ?float
    {
        $currentTime = self::frameToTime($frame, $fps);
        $audioDuration = $duration ?? $this->getDuration();

        if ($currentTime < $startTime) {
            return null;
        }

        $elapsed = $currentTime - $startTime;

        if ($elapsed > $audioDuration) {
            return null;
        }

        return $elapsed / $audioDuration;
    }

    /**
     * 获取音频信息数组
     */
    public function getInfo(): array
    {
        return [
            'file' => $this->filePath,
            'duration' => $this->getDuration(),
            'sample_rate' => $this->getSampleRate(),
            'channels' => $this->getChannels(),
            'bit_rate' => $this->getBitRate(),
            'codec' => $this->getCodec(),
        ];
    }

    /**
     * 转换为字符串（用于调试）
     */
    public function __toString(): string
    {
        return sprintf(
            'Audio(%s, duration: %.2fs, %d channels, %d Hz)',
            basename($this->filePath),
            $this->getDuration(),
            $this->getChannels(),
            $this->getSampleRate()
        );
    }
}
