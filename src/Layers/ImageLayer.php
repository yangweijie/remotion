<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Layers;

/**
 * ImageLayer
 *
 * 图像图层，对标 remotion 中的 <Img> 组件。
 * 支持从文件路径加载图像，并可进行缩放、裁剪等操作。
 */
class ImageLayer extends AbstractLayer
{
    private ?\GdImage $image = null;
    private int $width;
    private int $height;

    /**
     * @param string $filePath 图像文件路径
     * @param int|null $width  缩放宽度（null 保持原始）
     * @param int|null $height 缩放高度（null 保持原始）
     */
    public function __construct(
        private readonly string $filePath,
        ?int $width = null,
        ?int $height = null,
    ) {
        $this->loadImage($width, $height);
    }

    /**
     * 从 GD 图像资源直接创建（不从文件加载）
     */
    public static function fromGdImage(\GdImage $image): self
    {
        $instance = new self('');
        $instance->image  = $image;
        $instance->width  = imagesx($image);
        $instance->height = imagesy($image);
        return $instance;
    }

    private function loadImage(?int $targetWidth, ?int $targetHeight): void
    {
        if (empty($this->filePath) || !file_exists($this->filePath)) {
            // 占位空白图像
            $this->width  = $targetWidth  ?? 100;
            $this->height = $targetHeight ?? 100;
            $this->image  = imagecreatetruecolor($this->width, $this->height);
            return;
        }

        $mime = mime_content_type($this->filePath);
        $src  = match ($mime) {
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($this->filePath),
            'image/png'               => imagecreatefrompng($this->filePath),
            'image/gif'               => imagecreatefromgif($this->filePath),
            'image/webp'              => imagecreatefromwebp($this->filePath),
            default                   => throw new \RuntimeException("Unsupported image type: {$mime}"),
        };

        if ($src === false) {
            throw new \RuntimeException("Failed to load image: {$this->filePath}");
        }

        $origW = imagesx($src);
        $origH = imagesy($src);

        // 计算目标尺寸
        if ($targetWidth !== null && $targetHeight !== null) {
            $this->width  = $targetWidth;
            $this->height = $targetHeight;
        } elseif ($targetWidth !== null) {
            $this->width  = $targetWidth;
            $this->height = (int) round($origH * $targetWidth / $origW);
        } elseif ($targetHeight !== null) {
            $this->height = $targetHeight;
            $this->width  = (int) round($origW * $targetHeight / $origH);
        } else {
            $this->width  = $origW;
            $this->height = $origH;
        }

        if ($this->width === $origW && $this->height === $origH) {
            $this->image = $src;
        } else {
            // 缩放
            $dst = imagecreatetruecolor($this->width, $this->height);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $this->width, $this->height, $origW, $origH);
            imagedestroy($src);
            $this->image = $dst;
        }
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getImage(): ?\GdImage
    {
        return $this->image;
    }

    public function drawOn(\GdImage $canvas, int $x = 0, int $y = 0): void
    {
        if ($this->image === null) {
            return;
        }

        $this->blendOnto($canvas, $this->image, $x + $this->x, $y + $this->y);
    }

    public function __destruct()
    {
        if ($this->image !== null) {
            imagedestroy($this->image);
        }
    }
}
