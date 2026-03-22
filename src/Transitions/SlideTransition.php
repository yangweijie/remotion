<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Transitions;

use GdImage;
use Grafika\ImageInterface;
use Grafika\Grafika;

/**
 * SlideTransition - 滑动转场
 *
 * 新场景从指定方向滑入，覆盖旧场景。
 * 使用 Grafika 抽象层实现，支持 GD 和 Imagick。
 *
 * 使用示例：
 * ```php
 * $slide = new SlideTransition(30, 'left'); // 从左滑入
 * $result = $slide->apply($outImage, $inImage, 0.5);
 * ```
 */
class SlideTransition extends Transition
{
    public const DIRECTION_LEFT = 'left';
    public const DIRECTION_RIGHT = 'right';
    public const DIRECTION_TOP = 'top';
    public const DIRECTION_BOTTOM = 'bottom';

    public function __construct(
        int $duration = 30,
        private readonly string $direction = self::DIRECTION_LEFT,
    ) {
        parent::__construct($duration);
    }

    /**
     * 获取滑动方向
     */
    public function getDirection(): string
    {
        return $this->direction;
    }

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

        // 创建结果图像
        $result = $this->createBlankImage($width, $height);

        // 根据方向计算偏移
        $offsetX = 0;
        $offsetY = 0;

        switch ($this->direction) {
            case self::DIRECTION_LEFT:
                $offsetX = (int) ($width * (1 - $progress));
                $this->slideLeft($result, $fromImage, $toImage, $offsetX, $width, $height);
                break;

            case self::DIRECTION_RIGHT:
                $offsetX = (int) ($width * $progress);
                $this->slideRight($result, $fromImage, $toImage, $offsetX, $width, $height);
                break;

            case self::DIRECTION_TOP:
                $offsetY = (int) ($height * (1 - $progress));
                $this->slideUp($result, $fromImage, $toImage, $offsetY, $width, $height);
                break;

            case self::DIRECTION_BOTTOM:
                $offsetY = (int) ($height * $progress);
                $this->slideDown($result, $fromImage, $toImage, $offsetY, $width, $height);
                break;
        }

        return $result;
    }

    /**
     * 从左滑入
     */
    private function slideLeft(
        ImageInterface $result,
        ImageInterface $from,
        ImageInterface $to,
        int $offsetX,
        int $width,
        int $height
    ): void {
        $editor = $this->getEditor();

        // fromImage: 从右侧开始，逐渐移出（显示右半部分）
        // fromImage 显示区域：[offsetX, 0] 到 [width, height]
        // 源图像裁剪：[0, 0] 到 [width-offsetX, height]
        if ($offsetX < $width) {
            $srcW = $width - $offsetX;
            $this->copyRegion($result, $from, $offsetX, 0, 0, 0, $srcW, $height);
        }

        // toImage: 从左侧滑入（显示左半部分）
        // toImage 显示区域：[0, 0] 到 [offsetX, height]
        // 源图像裁剪：[width-offsetX, 0] 到 [offsetX, height]
        if ($offsetX > 0) {
            $srcX = $width - $offsetX;
            $this->copyRegion($result, $to, 0, 0, $srcX, 0, $offsetX, $height);
        }
    }

    /**
     * 从右滑入
     */
    private function slideRight(
        ImageInterface $result,
        ImageInterface $from,
        ImageInterface $to,
        int $offsetX,
        int $width,
        int $height
    ): void {
        $editor = $this->getEditor();

        // fromImage: 从左侧开始，逐渐移出（显示左半部分）
        if ($offsetX < $width) {
            $srcW = $width - $offsetX;
            $this->copyRegion($result, $from, 0, 0, $offsetX, 0, $srcW, $height);
        }

        // toImage: 从右侧滑入（显示右半部分）
        if ($offsetX > 0) {
            $srcW = $width - $offsetX;
            $this->copyRegion($result, $to, $offsetX, 0, 0, 0, $srcW, $height);
        }
    }

    /**
     * 从上滑入
     */
    private function slideUp(
        ImageInterface $result,
        ImageInterface $from,
        ImageInterface $to,
        int $offsetY,
        int $width,
        int $height
    ): void {
        // fromImage: 从下侧开始，逐渐移出（显示下半部分）
        if ($offsetY < $height) {
            $srcH = $height - $offsetY;
            $this->copyRegion($result, $from, 0, $offsetY, 0, 0, $width, $srcH);
        }

        // toImage: 从上侧滑入（显示上半部分）
        if ($offsetY > 0) {
            $srcY = $height - $offsetY;
            $this->copyRegion($result, $to, 0, 0, 0, $srcY, $width, $offsetY);
        }
    }

    /**
     * 从下滑入
     */
    private function slideDown(
        ImageInterface $result,
        ImageInterface $from,
        ImageInterface $to,
        int $offsetY,
        int $width,
        int $height
    ): void {
        // fromImage: 从上侧开始，逐渐移出（显示上半部分）
        if ($offsetY < $height) {
            $srcH = $height - $offsetY;
            $this->copyRegion($result, $from, 0, 0, 0, $offsetY, $width, $srcH);
        }

        // toImage: 从下侧滑入（显示下半部分）
        if ($offsetY > 0) {
            $srcH = $height - $offsetY;
            $this->copyRegion($result, $to, 0, $offsetY, 0, 0, $width, $srcH);
        }
    }

    /**
     * 应用转场效果（GD 兼容版本）
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