<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Transitions;

use GdImage;
use Grafika\ImageInterface;
use Grafika\Grafika;

/**
 * FadeTransition - 淡入淡出转场
 *
 * 通过透明度变化实现场景平滑过渡。
 * 使用 Grafika 抽象层实现，支持 GD 和 Imagick。
 *
 * 使用示例：
 * ```php
 * $fade = new FadeTransition(30);
 * $result = $fade->apply($outImage, $inImage, 0.5);
 * ```
 */
class FadeTransition extends Transition
{
    /**
     * 应用转场效果（Grafika 版本）
     *
     * @param ImageInterface $fromImage 离开的图像
     * @param ImageInterface $toImage 进入的图像
     * @param float $progress 转场进度 (0-1)
     * @return ImageInterface 转场后的图像
     */
    public function applyGrafika(ImageInterface $fromImage, ImageInterface $toImage, float $progress): ImageInterface
    {
        $width = $fromImage->getWidth();
        $height = $fromImage->getHeight();

        // 确保两个图像大小一致
        $toImage = $this->ensureSize($toImage, $width, $height);

        // 创建结果图像（复制 fromImage）
        $result = $this->createBlankImage($width, $height);
        $editor = $this->getEditor();

        // 先绘制 fromImage（从完全不透明到完全透明）
        // 再绘制 toImage（从完全透明到完全不透明）

        // 方法：使用 blend 的 opacity 参数
        // fromImage 透明度 = 1 - progress
        // toImage 透明度 = progress

        // 先绘制底层（fromImage）
        $fromOpacity = 1 - $progress;
        if ($fromOpacity > 0.01) {
            $editor->blend($result, $fromImage, 'normal', $fromOpacity, 'top-left', 0, 0);
        }

        // 再绘制顶层（toImage）
        if ($progress > 0.01) {
            $editor->blend($result, $toImage, 'normal', $progress, 'top-left', 0, 0);
        }

        return $result;
    }

    /**
     * 应用转场效果（GD 兼容版本 - 使用 Grafika）
     *
     * @param GdImage $fromImage 离开的图像
     * @param GdImage $toImage 进入的图像
     * @param float $progress 转场进度 (0-1)
     * @return GdImage 转场后的图像
     */
    public function apply(GdImage $fromImage, GdImage $toImage, float $progress): GdImage
    {
        // 转换为 Grafika 图像
        $fromGrafika = $this->gdToImage($fromImage);
        $toGrafika = $this->gdToImage($toImage);

        // 使用 Grafika 版本处理
        $result = $this->applyGrafika($fromGrafika, $toGrafika, $progress);

        // 转换回 GD
        return $this->imageToGd($result);
    }
}