<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Media;

use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Media\Video as FFVideo;
use FFMpeg\Filters\Video\ResizeFilter;
use Grafika\Grafika;
use Grafika\Color;

/**
 * Video - 视频处理组件
 *
 * 提供视频文件的读取、分析和处理功能。
 * 基于 php-ffmpeg/php-ffmpeg 库。
 *
 * 使用示例：
 * ```php
 * use Yangweijie\Remotion\Media\Video;
 *
 * // 加载视频文件
 * $video = Video::fromFile('/path/to/video.mp4');
 *
 * // 获取视频信息
 * $duration = $video->getDuration(); // 秒
 * $width = $video->getWidth();
 * $height = $video->getHeight();
 * $fps = $video->getFps();
 *
 * // 提取视频帧
 * $frame = $video->getFrame(5.0); // 获取 5 秒处的帧
 *
 * // 提取帧序列
 * $frames = $video->extractFrames(0, 10, 30); // 从 0 秒到 10 秒，每秒 30 帧
 * ```
 *
 * @see https://www.remotion.dev/docs/video
 */
class Video
{
    /**
     * FFMpeg 实例
     */
    private ?FFMpeg $ffmpeg = null;

    /**
     * 视频对象
     */
    private ?FFVideo $video = null;

    /**
     * 文件路径
     */
    private string $filePath;

    /**
     * 创建 Video 实例
     */
    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $this->ffmpeg = FFMpeg::create();
        $this->video = $this->ffmpeg->open($filePath);
    }

    /**
     * 从文件创建视频实例
     */
    public static function fromFile(string $filePath): self
    {
        return new self($filePath);
    }

    /**
     * 获取视频时长（秒）
     */
    public function getDuration(): float
    {
        if ($this->video === null) {
            return 0.0;
        }

        $format = $this->video->getStreams()->first()->get('duration');
        
        if (is_numeric($format)) {
            return (float) $format;
        }

        return 0.0;
    }

    /**
     * 获取视频宽度
     */
    public function getWidth(): int
    {
        if ($this->video === null) {
            return 0;
        }

        $stream = $this->video->getStreams()->first();
        return (int) $stream->get('width', 0);
    }

    /**
     * 获取视频高度
     */
    public function getHeight(): int
    {
        if ($this->video === null) {
            return 0;
        }

        $stream = $this->video->getStreams()->first();
        return (int) $stream->get('height', 0);
    }

    /**
     * 获取帧率
     */
    public function getFps(): float
    {
        if ($this->video === null) {
            return 0.0;
        }

        $stream = $this->video->getStreams()->first();
        $fps = $stream->get('r_frame_rate', '30/1');
        
        if (is_string($fps) && strpos($fps, '/') !== false) {
            [$num, $den] = explode('/', $fps);
            if ((float) $den > 0) {
                return (float) $num / (float) $den;
            }
        }

        return (float) $fps;
    }

    /**
     * 获取总帧数
     */
    public function getTotalFrames(): int
    {
        return (int) round($this->getDuration() * $this->getFps());
    }

    /**
     * 获取编解码器
     */
    public function getCodec(): string
    {
        if ($this->video === null) {
            return '';
        }

        $stream = $this->video->getStreams()->first();
        return (string) $stream->get('codec_name', 'unknown');
    }

    /**
     * 获取比特率
     */
    public function getBitRate(): int
    {
        if ($this->video === null) {
            return 0;
        }

        $stream = $this->video->getStreams()->first();
        return (int) $stream->get('bit_rate', 0);
    }

    /**
     * 获取文件路径
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * 获取宽高比
     */
    public function getAspectRatio(): float
    {
        $width = $this->getWidth();
        $height = $this->getHeight();

        if ($height === 0) {
            return 1.0;
        }

        return $width / $height;
    }

    /**
     * 获取原始视频对象
     */
    public function getRaw(): ?FFVideo
    {
        return $this->video;
    }

    /**
     * 获取指定时间的帧
     *
     * @param float $timeInSeconds 时间（秒）
     * @return \GdImage|null
     */
    public function getFrame(float $timeInSeconds): ?\GdImage
    {
        if ($this->video === null) {
            return null;
        }

        try {
            $frame = $this->video->frame(TimeCode::fromSeconds($timeInSeconds));
            
            // 保存到临时文件
            $tempPath = sys_get_temp_dir() . '/video_frame_' . uniqid() . '.png';
            $frame->save($tempPath);

            // 使用 GD 加载
            $image = imagecreatefrompng($tempPath);
            
            // 删除临时文件
            @unlink($tempPath);

            return $image;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 获取指定帧号的帧
     *
     * @param int $frameNumber 帧号
     * @return \GdImage|null
     */
    public function getFrameByNumber(int $frameNumber): ?\GdImage
    {
        $fps = $this->getFps();
        if ($fps <= 0) {
            return null;
        }

        $timeInSeconds = $frameNumber / $fps;
        return $this->getFrame($timeInSeconds);
    }

    /**
     * 提取帧序列
     *
     * @param float $startTime 开始时间（秒）
     * @param float $endTime 结束时间（秒）
     * @param int $targetFps 目标帧率
     * @return \Generator<\GdImage>
     */
    public function extractFrames(float $startTime, float $endTime, int $targetFps): \Generator
    {
        $frameInterval = 1.0 / $targetFps;
        
        for ($time = $startTime; $time < $endTime; $time += $frameInterval) {
            $frame = $this->getFrame($time);
            if ($frame !== null) {
                yield $frame;
            }
        }
    }

    /**
     * 检查视频是否在指定时间范围内
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
     * 计算视频在指定帧的播放进度
     *
     * @param int $frame 当前帧
     * @param int $fps 帧率
     * @param float $startTime 开始时间（秒）
     * @param float|null $duration 持续时间（秒），null 表示整个视频
     * @return float|null 0-1 的进度值，如果不在播放范围则返回 null
     */
    public function getProgressAtFrame(int $frame, int $fps, float $startTime = 0.0, ?float $duration = null): ?float
    {
        $currentTime = self::frameToTime($frame, $fps);
        $videoDuration = $duration ?? $this->getDuration();

        if ($currentTime < $startTime) {
            return null;
        }

        $elapsed = $currentTime - $startTime;

        if ($elapsed > $videoDuration) {
            return null;
        }

        return $elapsed / $videoDuration;
    }

    /**
     * 缩放视频帧到指定尺寸
     * 使用 Grafika 抽象层（支持 GD 和 Imagick）
     *
     * @param float $timeInSeconds 时间（秒）
     * @param int $width 目标宽度
     * @param int $height 目标高度
     * @param string $mode 缩放模式：'fit', 'fill', 'exact'
     * @return \GdImage|null
     */
    public function getFrameScaled(float $timeInSeconds, int $width, int $height, string $mode = 'fit'): ?\GdImage
    {
        $frame = $this->getFrame($timeInSeconds);
        if ($frame === null) {
            return null;
        }

        // 转换为 Grafika 图像
        $image = $this->gdToImage($frame);
        $editor = Grafika::createEditor();
        
        $srcWidth = $image->getWidth();
        $srcHeight = $image->getHeight();

        // 计算缩放比例
        $ratio = $srcWidth / $srcHeight;
        $targetRatio = $width / $height;

        switch ($mode) {
            case 'fit':
                // 保持比例，适应目标尺寸
                if ($ratio > $targetRatio) {
                    $newWidth = $width;
                    $newHeight = (int) ($width / $ratio);
                } else {
                    $newHeight = $height;
                    $newWidth = (int) ($height * $ratio);
                }
                // 先缩放
                $editor->resizeExact($image, $newWidth, $newHeight);
                break;
            case 'fill':
                // 保持比例，填充目标尺寸（可能裁剪）
                if ($ratio > $targetRatio) {
                    $newHeight = $height;
                    $newWidth = (int) ($height * $ratio);
                } else {
                    $newWidth = $width;
                    $newHeight = (int) ($width / $ratio);
                }
                // 先缩放，再居中裁剪
                $editor->resizeExact($image, $newWidth, $newHeight);
                break;
            case 'exact':
            default:
                $newWidth = $width;
                $newHeight = $height;
                // 直接缩放
                $editor->resizeExact($image, $width, $height);
                break;
        }

        // 创建目标画布（黑色背景）
        $result = Grafika::createBlankImage($width, $height);
        $bgColor = new Color('#000000');
        $editor->fill($result, $bgColor);

        // 计算居中偏移
        $offsetX = (int) (($width - $newWidth) / 2);
        $offsetY = (int) (($height - $newHeight) / 2);

        // 将缩放后的图像合并到结果画布
        $editor->blend($result, $image, 'normal', 100, 'top-left', $offsetX, $offsetY);

        // 转换回 GD 图像
        return $this->imageToGd($result);
    }
    
    /**
     * 将 GD 图像转换为 Grafika 图像
     */
    private function gdToImage(\GdImage $gd): \Grafika\ImageInterface
    {
        $editorName = Grafika::detectAvailableEditor();
        
        if ($editorName === 'Imagick') {
            ob_start();
            imagepng($gd);
            $blob = ob_get_clean();
            $imagick = new \Imagick();
            $imagick->readImageBlob($blob);
            return \Grafika\Imagick\Image::createFromCore($imagick);
        }
        
        return \Grafika\Gd\Image::createFromCore($gd);
    }
    
    /**
     * 将 Grafika 图像转换为 GD 图像
     */
    private function imageToGd(\Grafika\ImageInterface $image): \GdImage
    {
        $core = $image->getCore();
        
        if ($core instanceof \GdImage) {
            return $core;
        }
        
        // Imagick 转 GD
        $blob = $core->getImageBlob();
        $gd = imagecreatefromstring($blob);
        
        if ($gd === false) {
            throw new \RuntimeException('Failed to convert Imagick to GD');
        }
        
        return $gd;
    }

    /**
     * 获取视频信息数组
     */
    public function getInfo(): array
    {
        return [
            'file' => $this->filePath,
            'duration' => $this->getDuration(),
            'width' => $this->getWidth(),
            'height' => $this->getHeight(),
            'fps' => $this->getFps(),
            'total_frames' => $this->getTotalFrames(),
            'aspect_ratio' => $this->getAspectRatio(),
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
            'Video(%s, %.2fs, %dx%d, %.2f fps)',
            basename($this->filePath),
            $this->getDuration(),
            $this->getWidth(),
            $this->getHeight(),
            $this->getFps()
        );
    }
}
