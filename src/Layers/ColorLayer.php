<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Layers;

use Grafika\Grafika;
use Grafika\Color;

/**
 * ColorLayer
 *
 * 纯色填充图层，对标 remotion 中的 AbsoluteFill 背景色。
 * 可用于创建背景、色块、渐变效果等。
 * 
 * 支持 Grafika 抽象层（自动检测 GD 或 Imagick）
 */
class ColorLayer extends AbstractLayer
{
    /**
     * @param int $width  图层宽度
     * @param int $height 图层高度
     * @param int $r      红色分量 (0-255)
     * @param int $g      绿色分量 (0-255)
     * @param int $b      蓝色分量 (0-255)
     * @param int $alpha  透明度 (0=不透明, 127=完全透明)
     */
    public function __construct(
        private int $width,
        private int $height,
        private int $r = 0,
        private int $g = 0,
        private int $b = 0,
        private int $alpha = 0,
    ) {}

    /**
     * 工厂方法：从十六进制颜色创建
     */
    public static function fromHex(int $width, int $height, string $hex): self
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return new self($width, $height, (int) $r, (int) $g, (int) $b);
    }

    /**
     * 工厂方法：从 RGB 数组创建
     */
    public static function fromRgb(int $width, int $height, array $rgb): self
    {
        return new self($width, $height, $rgb[0], $rgb[1], $rgb[2]);
    }

    /**
     * 创建透明图层（用于合成基底）
     */
    public static function transparent(int $width, int $height): self
    {
        return new self($width, $height, 0, 0, 0, 127);
    }

    /**
     * 修改颜色（可用于动画中每帧改变颜色）
     */
    public function setColor(int $r, int $g, int $b, int $alpha = 0): self
    {
        $this->r = $r;
        $this->g = $g;
        $this->b = $b;
        $this->alpha = $alpha;
        return $this;
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
     * 获取 Grafika Color 对象
     */
    protected function getGrafikaColor(): Color
    {
        // Grafika alpha: 0-1 (1=不透明), GD alpha: 0-127 (0=不透明)
        $opacity = $this->alpha === 0 ? 1.0 : 1.0 - ($this->alpha / 127);
        return new Color(
            sprintf('#%02x%02x%02x', $this->r, $this->g, $this->b),
            $opacity
        );
    }

    /**
     * 绘制到 Grafika 图像（新版本）
     */
    public function drawOnImage(\Grafika\ImageInterface $canvas, int $x = 0, int $y = 0): void
    {
        $editor = Grafika::createEditor();
        
        // 创建纯色图层
        $layer = $this->createImageCanvas($this->width, $this->height);
        $editor->fill($layer, $this->getGrafikaColor());
        
        // 混合到目标画布
        $this->blendOntoImage($canvas, $layer, $x + $this->x, $y + $this->y);
    }

    /**
     * 绘制到 GD 画布（兼容版本）
     * @deprecated 请使用 drawOnImage() 代替
     */
    public function drawOn(\GdImage $canvas, int $x = 0, int $y = 0): void
    {
        $layer = $this->createCanvas($this->width, $this->height);
        $color = imagecolorallocatealpha($layer, $this->r, $this->g, $this->b, $this->alpha);
        imagefilledrectangle($layer, 0, 0, $this->width - 1, $this->height - 1, $color);

        $this->blendOnto($canvas, $layer, $x + $this->x, $y + $this->y);
        imagedestroy($layer);
    }
}
