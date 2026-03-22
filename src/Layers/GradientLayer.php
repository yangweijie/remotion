<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Layers;

use Grafika\Grafika;
use Grafika\Color;

/**
 * GradientLayer
 *
 * 渐变色图层，支持线性渐变，丰富背景效果。
 * 对标 remotion 中通过 CSS linear-gradient 实现的背景效果。
 * 
 * 支持 Grafika 抽象层（自动检测 GD 或 Imagick）
 */
class GradientLayer extends AbstractLayer
{
    /**
     * @param int   $width  图层宽度
     * @param int   $height 图层高度
     * @param array $stops  渐变色标，格式：[['r'=>255,'g'=>0,'b'=>0], ['r'=>0,'g'=>0,'b'=>255]]
     * @param string $direction 渐变方向：horizontal|vertical|diagonal
     */
    public function __construct(
        private int $width,
        private int $height,
        private array $stops,
        private string $direction = 'vertical',
    ) {
        if (count($stops) < 2) {
            throw new \InvalidArgumentException('GradientLayer requires at least 2 color stops');
        }
    }

    public static function make(
        int $width,
        int $height,
        array $startColor,
        array $endColor,
        string $direction = 'vertical',
    ): self {
        return new self($width, $height, [$startColor, $endColor], $direction);
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
     * 绘制到 Grafika 图像（新版本）
     */
    public function drawOnImage(\Grafika\ImageInterface $canvas, int $x = 0, int $y = 0): void
    {
        $editor = Grafika::createEditor();
        $layer = $this->createImageCanvas($this->width, $this->height);
        
        // 检测编辑器类型
        $editorName = Grafika::detectAvailableEditor();
        
        if ($editorName === 'Imagick') {
            $this->drawGradientImagick($layer);
        } else {
            $this->drawGradientGd($layer);
        }
        
        // 混合到目标画布
        $this->blendOntoImage($canvas, $layer, $x + $this->x, $y + $this->y);
    }

    /**
     * 使用 Imagick 绘制渐变（高效）
     */
    private function drawGradientImagick(\Grafika\ImageInterface $image): void
    {
        $imagick = $image->getCore();
        $draw = new \ImagickDraw();
        
        match ($this->direction) {
            'horizontal' => $this->drawHorizontalGradientImagick($imagick, $draw),
            'diagonal'   => $this->drawDiagonalGradientImagick($imagick, $draw),
            default      => $this->drawVerticalGradientImagick($imagick, $draw),
        };
    }

    private function drawVerticalGradientImagick(\Imagick $imagick, \ImagickDraw $draw): void
    {
        for ($py = 0; $py < $this->height; $py++) {
            $t = $py / max(1, $this->height - 1);
            $color = $this->interpolateColor($t);
            $draw->setFillColor(sprintf('#%02x%02x%02x', $color['r'], $color['g'], $color['b']));
            $draw->line(0, $py, $this->width, $py);
        }
        $imagick->drawImage($draw);
    }

    private function drawHorizontalGradientImagick(\Imagick $imagick, \ImagickDraw $draw): void
    {
        for ($px = 0; $px < $this->width; $px++) {
            $t = $px / max(1, $this->width - 1);
            $color = $this->interpolateColor($t);
            $draw->setFillColor(sprintf('#%02x%02x%02x', $color['r'], $color['g'], $color['b']));
            $draw->line($px, 0, $px, $this->height);
        }
        $imagick->drawImage($draw);
    }

    private function drawDiagonalGradientImagick(\Imagick $imagick, \ImagickDraw $draw): void
    {
        for ($py = 0; $py < $this->height; $py++) {
            for ($px = 0; $px < $this->width; $px++) {
                $t = ($px / max(1, $this->width - 1) + $py / max(1, $this->height - 1)) / 2;
                $color = $this->interpolateColor($t);
                $draw->setFillColor(sprintf('#%02x%02x%02x', $color['r'], $color['g'], $color['b']));
                $draw->point($px, $py);
            }
        }
        $imagick->drawImage($draw);
    }

    /**
     * 使用 GD 绘制渐变
     */
    private function drawGradientGd(\Grafika\ImageInterface $image): void
    {
        $gd = $image->getCore();
        
        match ($this->direction) {
            'horizontal' => $this->drawHorizontalGradientGd($gd),
            'diagonal'   => $this->drawDiagonalGradientGd($gd),
            default      => $this->drawVerticalGradientGd($gd),
        };
    }

    private function drawVerticalGradientGd(\GdImage $gd): void
    {
        for ($py = 0; $py < $this->height; $py++) {
            $t = $py / max(1, $this->height - 1);
            $color = $this->interpolateColor($t);
            $c = imagecolorallocate($gd, $color['r'], $color['g'], $color['b']);
            imageline($gd, 0, $py, $this->width - 1, $py, $c);
        }
    }

    private function drawHorizontalGradientGd(\GdImage $gd): void
    {
        for ($px = 0; $px < $this->width; $px++) {
            $t = $px / max(1, $this->width - 1);
            $color = $this->interpolateColor($t);
            $c = imagecolorallocate($gd, $color['r'], $color['g'], $color['b']);
            imageline($gd, $px, 0, $px, $this->height - 1, $c);
        }
    }

    private function drawDiagonalGradientGd(\GdImage $gd): void
    {
        for ($py = 0; $py < $this->height; $py++) {
            for ($px = 0; $px < $this->width; $px++) {
                $t = ($px / max(1, $this->width - 1) + $py / max(1, $this->height - 1)) / 2;
                $color = $this->interpolateColor($t);
                $c = imagecolorallocate($gd, $color['r'], $color['g'], $color['b']);
                imagesetpixel($gd, $px, $py, $c);
            }
        }
    }

    /**
     * 插值计算颜色
     */
    private function interpolateColor(float $t): array
    {
        $n = count($this->stops) - 1;
        $segment = (int) min($t * $n, $n - 1);
        $localT  = $t * $n - $segment;

        $c1 = $this->stops[$segment];
        $c2 = $this->stops[$segment + 1];

        return [
            'r' => (int) round($c1['r'] + ($c2['r'] - $c1['r']) * $localT),
            'g' => (int) round($c1['g'] + ($c2['g'] - $c1['g']) * $localT),
            'b' => (int) round($c1['b'] + ($c2['b'] - $c1['b']) * $localT),
        ];
    }

    /**
     * 绘制到 GD 画布（兼容版本）
     * @deprecated 请使用 drawOnImage() 代替
     */
    public function drawOn(\GdImage $canvas, int $x = 0, int $y = 0): void
    {
        $layer = $this->createCanvas($this->width, $this->height);

        match ($this->direction) {
            'horizontal' => $this->drawHorizontalGradientGd($layer),
            'diagonal'   => $this->drawDiagonalGradientGd($layer),
            default      => $this->drawVerticalGradientGd($layer),
        };

        $this->blendOnto($canvas, $layer, $x + $this->x, $y + $this->y);
        imagedestroy($layer);
    }
}
