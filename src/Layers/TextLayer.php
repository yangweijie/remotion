<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Layers;

use Grafika\Grafika;
use Grafika\Color;

/**
 * TextLayer
 *
 * 文字图层，对标 remotion 中的文字渲染（通过 CSS 控制的 div 元素）。
 * 支持自定义字体、大小、颜色、对齐等样式。
 * 
 * 支持 Grafika 抽象层（自动检测 GD 或 Imagick）
 */
class TextLayer extends AbstractLayer
{
    private int $width;
    private int $height;

    /**
     * @param string $text      显示文本
     * @param int    $fontSize  字体大小（像素，GD 使用点数）
     * @param int    $r         文字颜色 R
     * @param int    $g         文字颜色 G
     * @param int    $b         文字颜色 B
     * @param string $fontPath  TTF 字体文件路径（空字符串使用内置字体）
     * @param string $align     对齐方式：left|center|right
     * @param int    $maxWidth  文字最大宽度（像素），0 = 不限制
     * @param int    $maxHeight 文字最大高度（像素），0 = 不限制
     */
    public function __construct(
        private string $text,
        private int $fontSize = 24,
        private int $r = 255,
        private int $g = 255,
        private int $b = 255,
        private string $fontPath = '',
        private string $align = 'left',
        int $maxWidth = 0,
        int $maxHeight = 0,
    ) {
        $this->width  = $maxWidth  > 0 ? $maxWidth  : 800;
        $this->height = $maxHeight > 0 ? $maxHeight : 200;
    }

    /**
     * 静态工厂方法（流式 API）
     */
    public static function make(string $text, array $styles = []): self
    {
        return new self(
            text:      $text,
            fontSize:  $styles['fontSize']  ?? 24,
            r:         $styles['r']         ?? 255,
            g:         $styles['g']         ?? 255,
            b:         $styles['b']         ?? 255,
            fontPath:  $styles['fontPath']  ?? '',
            align:     $styles['align']     ?? 'left',
            maxWidth:  $styles['maxWidth']  ?? 0,
            maxHeight: $styles['maxHeight'] ?? 0,
        );
    }

    public function setText(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    public function setColor(int $r, int $g, int $b): self
    {
        $this->r = $r;
        $this->g = $g;
        $this->b = $b;
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
     * 绘制到 Grafika 图像（新版本）
     */
    public function drawOnImage(\Grafika\ImageInterface $canvas, int $x = 0, int $y = 0): void
    {
        $editor = Grafika::createEditor();
        $color = new Color(sprintf('#%02x%02x%02x', $this->r, $this->g, $this->b));
        
        $drawX = $x + $this->x;
        $drawY = $y + $this->y;

        if (!empty($this->fontPath) && file_exists($this->fontPath)) {
            // 使用 Grafika text() 方法（支持 TTF 字体）
            $this->drawTtfTextGrafika($canvas, $editor, $color, $drawX, $drawY);
        } else {
            // 使用 GD 内置字体（需要回退到 GD）
            $this->drawBuiltinTextGd($canvas, $drawX, $drawY);
        }
    }

    /**
     * 使用 Grafika 绘制 TTF 字体文字
     */
    private function drawTtfTextGrafika(\Grafika\ImageInterface $canvas, $editor, Color $color, int $x, int $y): void
    {
        // 计算文字边界框
        $bbox = imagettfbbox($this->fontSize, 0, $this->fontPath, $this->text);
        if ($bbox === false) {
            return;
        }

        $textWidth  = $bbox[2] - $bbox[0];

        // 根据对齐方式调整 X
        $drawX = match ($this->align) {
            'center' => (int) ($x + ($this->width - $textWidth) / 2),
            'right'  => (int) ($x + $this->width - $textWidth),
            default  => $x,
        };

        // Y 坐标基线调整（imagettftext 使用基线坐标）
        $drawY = $y + $this->fontSize;

        // 使用 Grafika text 方法
        $editor->text($canvas, $this->text, $this->fontSize, $drawX, $drawY, $color, $this->fontPath);
    }

    /**
     * 使用 GD 绘制内置字体文字（Grafika 不支持内置字体）
     */
    private function drawBuiltinTextGd(\Grafika\ImageInterface $canvas, int $x, int $y): void
    {
        $core = $canvas->getCore();
        
        if (!($core instanceof \GdImage)) {
            // Imagick 不支持 GD 内置字体，需要转换
            return;
        }

        $font = min(5, max(1, (int) ($this->fontSize / 10)));
        $color = imagecolorallocate($core, $this->r, $this->g, $this->b);

        $charWidth = imagefontwidth($font);
        $textWidth = strlen($this->text) * $charWidth;

        $drawX = match ($this->align) {
            'center' => $x + (int) (($this->width - $textWidth) / 2),
            'right'  => $x + $this->width - $textWidth,
            default  => $x,
        };

        imagestring($core, $font, $drawX, $y, $this->text, $color);
    }

    /**
     * 绘制到 GD 画布（兼容版本）
     * @deprecated 请使用 drawOnImage() 代替
     */
    public function drawOn(\GdImage $canvas, int $x = 0, int $y = 0): void
    {
        $layer = $this->createCanvas($this->width, $this->height);
        $color = imagecolorallocate($layer, $this->r, $this->g, $this->b);

        $drawX = $x + $this->x;
        $drawY = $y + $this->y;

        if (!empty($this->fontPath) && file_exists($this->fontPath)) {
            // 使用 TTF 字体
            $this->drawTtfText($canvas, $color, $drawX, $drawY);
        } else {
            // 使用 GD 内置字体
            $this->drawBuiltinText($canvas, $drawX, $drawY);
        }

        imagedestroy($layer);
    }

    /**
     * 使用 TTF 字体绘制文字（GD 版本）
     */
    private function drawTtfText(\GdImage $canvas, int $color, int $x, int $y): void
    {
        $bbox = imagettfbbox($this->fontSize, 0, $this->fontPath, $this->text);
        if ($bbox === false) {
            return;
        }

        $textWidth  = $bbox[2] - $bbox[0];

        $drawX = match ($this->align) {
            'center' => (int) ($x + ($this->width - $textWidth) / 2),
            'right'  => (int) ($x + $this->width - $textWidth),
            default  => $x,
        };

        $drawY = $y + $this->fontSize;

        imagettftext($canvas, $this->fontSize, 0, $drawX, $drawY, $color, $this->fontPath, $this->text);
    }

    /**
     * 使用 GD 内置字体绘制文字
     */
    private function drawBuiltinText(\GdImage $canvas, int $x, int $y): void
    {
        $font = min(5, max(1, (int) ($this->fontSize / 10)));
        $color = imagecolorallocate($canvas, $this->r, $this->g, $this->b);

        $charWidth  = imagefontwidth($font);
        $textWidth  = strlen($this->text) * $charWidth;

        $drawX = match ($this->align) {
            'center' => $x + (int) (($this->width - $textWidth) / 2),
            'right'  => $x + $this->width - $textWidth,
            default  => $x,
        };

        imagestring($canvas, $font, $drawX, $y, $this->text, $color);
    }
}
