<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Layers;

/**
 * GradientLayer
 *
 * 渐变色图层，支持线性渐变，丰富背景效果。
 * 对标 remotion 中通过 CSS linear-gradient 实现的背景效果。
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

    public function drawOn(\GdImage $canvas, int $x = 0, int $y = 0): void
    {
        $layer = $this->createCanvas($this->width, $this->height);

        match ($this->direction) {
            'horizontal' => $this->drawHorizontalGradient($layer),
            'diagonal'   => $this->drawDiagonalGradient($layer),
            default      => $this->drawVerticalGradient($layer),
        };

        $this->blendOnto($canvas, $layer, $x + $this->x, $y + $this->y);
        imagedestroy($layer);
    }

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

    private function drawVerticalGradient(\GdImage $layer): void
    {
        for ($py = 0; $py < $this->height; $py++) {
            $t     = $py / max(1, $this->height - 1);
            $color = $this->interpolateColor($t);
            $c     = imagecolorallocate($layer, $color['r'], $color['g'], $color['b']);
            imageline($layer, 0, $py, $this->width - 1, $py, $c);
        }
    }

    private function drawHorizontalGradient(\GdImage $layer): void
    {
        for ($px = 0; $px < $this->width; $px++) {
            $t     = $px / max(1, $this->width - 1);
            $color = $this->interpolateColor($t);
            $c     = imagecolorallocate($layer, $color['r'], $color['g'], $color['b']);
            imageline($layer, $px, 0, $px, $this->height - 1, $c);
        }
    }

    private function drawDiagonalGradient(\GdImage $layer): void
    {
        for ($py = 0; $py < $this->height; $py++) {
            for ($px = 0; $px < $this->width; $px++) {
                $t     = ($px / max(1, $this->width - 1) + $py / max(1, $this->height - 1)) / 2;
                $color = $this->interpolateColor($t);
                $c     = imagecolorallocate($layer, $color['r'], $color['g'], $color['b']);
                imagesetpixel($layer, $px, $py, $c);
            }
        }
    }
}
