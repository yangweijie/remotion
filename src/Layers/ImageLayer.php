<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Layers;

use Grafika\Grafika;
use Grafika\Color;

/**
 * ImageLayer
 *
 * 图像图层，对标 remotion 中的 <Img> 组件。
 * 支持从文件路径加载图像，并可进行缩放、裁剪等操作。
 * 
 * 支持 Grafika 抽象层（自动检测 GD 或 Imagick）
 */
class ImageLayer extends AbstractLayer
{
    private ?\Grafika\ImageInterface $imageObj = null;
    private ?\GdImage $gdImage = null;
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
     * 从 Grafika 图像对象创建
     */
    public static function fromImage(\Grafika\ImageInterface $image): self
    {
        $instance = new self('');
        $instance->imageObj = $image;
        $instance->width    = $image->getWidth();
        $instance->height   = $image->getHeight();
        return $instance;
    }

    /**
     * 从 GD 图像资源直接创建（不从文件加载）
     * @deprecated 请使用 fromImage() 代替
     */
    public static function fromGdImage(\GdImage $image): self
    {
        $instance = new self('');
        $instance->gdImage = $image;
        $instance->width   = imagesx($image);
        $instance->height  = imagesy($image);
        return $instance;
    }

    /**
     * 使用 Grafika 加载图像
     */
    private function loadImage(?int $targetWidth, ?int $targetHeight): void
    {
        if (empty($this->filePath) || !file_exists($this->filePath)) {
            // 占位空白图像
            $this->width  = $targetWidth  ?? 100;
            $this->height = $targetHeight ?? 100;
            $this->imageObj = Grafika::createBlankImage($this->width, $this->height);
            return;
        }

        try {
            // 使用 Grafika 加载图像
            $this->imageObj = Grafika::createImage($this->filePath);
            
            $origW = $this->imageObj->getWidth();
            $origH = $this->imageObj->getHeight();

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

            // 需要缩放时使用 Grafika
            if ($this->width !== $origW || $this->height !== $origH) {
                $editor = Grafika::createEditor();
                $editor->resize($this->imageObj, $this->width, $this->height, 'exact');
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException("Failed to load image: {$this->filePath} - " . $e->getMessage(), 0, $e);
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

    /**
     * 获取 Grafika 图像对象
     */
    public function getImage(): ?\Grafika\ImageInterface
    {
        return $this->imageObj;
    }

    /**
     * 获取 GD 图像（兼容方法）
     * @deprecated 请使用 getImage() 代替
     */
    public function getGdImage(): ?\GdImage
    {
        if ($this->gdImage !== null) {
            return $this->gdImage;
        }
        if ($this->imageObj !== null) {
            return $this->imageObj->getCore();
        }
        return null;
    }

    /**
     * 绘制到 Grafika 图像（新版本）
     */
    public function drawOnImage(\Grafika\ImageInterface $canvas, int $x = 0, int $y = 0): void
    {
        if ($this->imageObj === null) {
            return;
        }
        
        $this->blendOntoImage($canvas, $this->imageObj, $x + $this->x, $y + $this->y);
    }

    /**
     * 绘制到 GD 画布（兼容版本）
     * @deprecated 请使用 drawOnImage() 代替
     */
    public function drawOn(\GdImage $canvas, int $x = 0, int $y = 0): void
    {
        if ($this->imageObj === null && $this->gdImage === null) {
            return;
        }

        // 获取 GD 资源
        $gd = $this->gdImage ?? $this->imageObj->getCore();
        
        if ($gd instanceof \GdImage) {
            $this->blendOnto($canvas, $gd, $x + $this->x, $y + $this->y);
        }
    }

    public function __destruct()
    {
        // Grafika 图像由 Grafika 管理，无需手动销毁
        if ($this->gdImage !== null) {
            imagedestroy($this->gdImage);
        }
    }
}
