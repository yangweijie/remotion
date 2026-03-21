<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Layers;

use Yangweijie\Remotion\Contracts\LayerInterface;

/**
 * AbstractLayer
 *
 * 图层基类，封装通用属性（透明度、位置、尺寸）。
 * 对标 remotion 中 AbsoluteFill、Img、Video 等视觉元素。
 */
abstract class AbstractLayer implements LayerInterface
{
    protected float $opacity = 1.0;
    protected int $x        = 0;
    protected int $y        = 0;

    public function getOpacity(): float
    {
        return $this->opacity;
    }

    public function setOpacity(float $opacity): static
    {
        $this->opacity = max(0.0, min(1.0, $opacity));
        return $this;
    }

    public function getX(): int
    {
        return $this->x;
    }

    public function getY(): int
    {
        return $this->y;
    }

    public function setPosition(int $x, int $y): static
    {
        $this->x = $x;
        $this->y = $y;
        return $this;
    }

    /**
     * 将当前图层合并到目标画布，考虑透明度
     */
    protected function blendOnto(\GdImage $canvas, \GdImage $layer, int $x, int $y): void
    {
        $w = imagesx($layer);
        $h = imagesy($layer);

        if ($this->opacity >= 1.0) {
            // 完全不透明：直接复制（支持 alpha 通道）
            imagecopy($canvas, $layer, $x, $y, 0, 0, $w, $h);
        } else {
            // 带透明度合并
            imagecopymerge($canvas, $layer, $x, $y, 0, 0, $w, $h, (int) ($this->opacity * 100));
        }
    }

    /**
     * 创建支持透明通道的画布
     */
    protected function createCanvas(int $width, int $height): \GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);
        imagealphablending($image, true);
        return $image;
    }
}
