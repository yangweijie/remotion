<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Layers;

use Yangweijie\Remotion\Contracts\LayerInterface;
use Grafika\Grafika;

/**
 * AbstractLayer
 *
 * 图层基类，封装通用属性（透明度、位置、尺寸）。
 * 对标 remotion 中 AbsoluteFill、Img、Video 等视觉元素。
 * 
 * 支持 Grafika 抽象层（自动检测 GD 或 Imagick）
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
     * 将当前图层合并到目标画布，考虑透明度（Grafika 版本）
     * 
     * @param \Grafika\ImageInterface $canvas 目标画布
     * @param \Grafika\ImageInterface $layer  图层
     * @param int $x X 坐标
     * @param int $y Y 坐标
     */
    protected function blendOntoImage(
        \Grafika\ImageInterface $canvas,
        \Grafika\ImageInterface $layer,
        int $x,
        int $y
    ): void {
        try {
            $editor = Grafika::createEditor();
            
            // Grafika blend 签名: blend($image1, $image2, $type, $opacity, $position, $offsetX, $offsetY)
            if ($this->opacity >= 1.0) {
                $editor->blend($canvas, $layer, 'normal', 1.0, 'top-left', $x, $y);
            } else {
                $editor->blend($canvas, $layer, 'normal', $this->opacity, 'top-left', $x, $y);
            }
        } catch (\Throwable $e) {
            // Grafika/Imagick 兼容性问题，回退到 GD 实现
            $this->blendOntoImageFallback($canvas, $layer, $x, $y);
        }
    }

    /**
     * GD 回退方法：将图层合并到画布
     */
    private function blendOntoImageFallback(
        \Grafika\ImageInterface $canvas,
        \Grafika\ImageInterface $layer,
        int $x,
        int $y
    ): void {
        $canvasCore = $canvas->getCore();
        $layerCore = $layer->getCore();
        
        // 如果都是 GD 资源，直接使用 GD 函数
        if ($canvasCore instanceof \GdImage && $layerCore instanceof \GdImage) {
            $this->blendOnto($canvasCore, $layerCore, $x, $y);
            return;
        }
        
        // Imagick 转 GD 处理
        if ($canvasCore instanceof \Imagick) {
            $gdCanvas = imagecreatefromstring($canvasCore->getImageBlob());
        } else {
            $gdCanvas = $canvasCore;
        }
        
        if ($layerCore instanceof \Imagick) {
            $gdLayer = imagecreatefromstring($layerCore->getImageBlob());
        } else {
            $gdLayer = $layerCore;
        }
        
        $this->blendOnto($gdCanvas, $gdLayer, $x, $y);
        
        // 将结果写回 canvas（仅当原始是 Imagick 时需要）
        if ($canvasCore instanceof \Imagick && $gdCanvas instanceof \GdImage) {
            ob_start();
            imagepng($gdCanvas);
            $blob = ob_get_clean();
            $canvasCore->readImageBlob($blob);
        }
    }

    /**
     * 创建支持透明通道的画布（Grafika 版本）
     * 
     * @param int $width 宽度
     * @param int $height 高度
     * @return \Grafika\ImageInterface 图像对象
     */
    protected function createImageCanvas(int $width, int $height): \Grafika\ImageInterface
    {
        return Grafika::createBlankImage($width, $height);
    }

    /**
     * 将当前图层合并到目标画布，考虑透明度（GD 兼容版本）
     * @deprecated 请使用 blendOntoImage() 代替
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
     * 创建支持透明通道的画布（GD 兼容版本）
     * @deprecated 请使用 createImageCanvas() 代替
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
