<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Transitions;

use GdImage;
use Grafika\Grafika;
use Grafika\ImageInterface;

/**
 * Transition - 转场效果基类
 *
 * 提供场景切换时的过渡动画。
 * 对标 Remotion 的 @remotion/transitions 包。
 *
 * 支持 Grafika 抽象层（自动检测 GD 或 Imagick）
 *
 * @see https://www.remotion.dev/docs/transitions
 */
abstract class Transition
{
    public function __construct(
        protected readonly int $duration = 30,  // 转场持续帧数
    ) {}

    /**
     * 应用转场效果（Grafika 版本 - 推荐）
     *
     * @param ImageInterface $fromImage 离开的图像
     * @param ImageInterface $toImage 进入的图像
     * @param float $progress 转场进度 (0-1)
     * @return ImageInterface 转场后的图像
     */
    abstract public function applyGrafika(ImageInterface $fromImage, ImageInterface $toImage, float $progress): ImageInterface;

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

    /**
     * 获取转场持续时间
     */
    public function getDuration(): int
    {
        return $this->duration;
    }

    /**
     * 创建空白 Grafika 图像
     */
    protected function createBlankImage(int $width, int $height): ImageInterface
    {
        return Grafika::createBlankImage($width, $height);
    }

    /**
     * 获取 Grafika 编辑器
     */
    protected function getEditor()
    {
        return Grafika::createEditor();
    }

    /**
     * 将 GD 图像转换为 Grafika 图像
     */
    protected function gdToImage(GdImage $gd): ImageInterface
    {
        $editorName = Grafika::detectAvailableEditor();

        if ($editorName === 'Imagick') {
            // GD 转 Imagick
            ob_start();
            imagepng($gd);
            $blob = ob_get_clean();
            $imagick = new \Imagick();
            $imagick->readImageBlob($blob);
            return \Grafika\Imagick\Image::createFromCore($imagick);
        } else {
            return \Grafika\Gd\Image::createFromCore($gd);
        }
    }

    /**
     * 将 Grafika 图像转换为 GD 图像
     */
    protected function imageToGd(ImageInterface $image): GdImage
    {
        $core = $image->getCore();

        if ($core instanceof GdImage) {
            return $core;
        }

        // Imagick 转 GD
        $imagick = $core;
        $blob = $imagick->getImageBlob();
        $gd = imagecreatefromstring($blob);

        if ($gd === false) {
            throw new \RuntimeException('Failed to convert Imagick to GD');
        }

        return $gd;
    }

    /**
     * 确保图像尺寸一致（使用 Grafika）
     *
     * @param ImageInterface $image 需要调整的图像
     * @param int $width 目标宽度
     * @param int $height 目标高度
     * @return ImageInterface 调整后的图像
     */
    protected function ensureSize(ImageInterface $image, int $width, int $height): ImageInterface
    {
        if ($image->getWidth() !== $width || $image->getHeight() !== $height) {
            $editor = $this->getEditor();
            $editor->resizeExact($image, $width, $height);
        }
        return $image;
    }

    /**
     * 复制图像区域到目标位置（使用 Grafika blend）
     *
     * @param ImageInterface $dest 目标图像
     * @param ImageInterface $src 源图像
     * @param int $destX 目标 X 坐标
     * @param int $destY 目标 Y 坐标
     * @param int $srcX 源 X 坐标（GD 兼容，需要先裁剪）
     * @param int $srcY 源 Y 坐标（GD 兼容，需要先裁剪）
     * @param int $srcW 源宽度
     * @param int $srcH 源高度
     */
    protected function copyRegion(
        ImageInterface $dest,
        ImageInterface $src,
        int $destX,
        int $destY,
        int $srcX,
        int $srcY,
        int $srcW,
        int $srcH
    ): void {
        $editor = $this->getEditor();

        // 如果需要从源图像的非零位置开始复制，先裁剪
        if ($srcX > 0 || $srcY > 0 || $srcW < $src->getWidth() || $srcH < $src->getHeight()) {
            // 创建裁剪后的副本
            $cropped = $this->createBlankImage($srcW, $srcH);
            $croppedCore = $cropped->getCore();
            $srcCore = $src->getCore();

            if ($croppedCore instanceof GdImage && $srcCore instanceof GdImage) {
                imagecopy($croppedCore, $srcCore, 0, 0, $srcX, $srcY, $srcW, $srcH);
            } elseif ($croppedCore instanceof \Imagick && $srcCore instanceof \Imagick) {
                $croppedCore->compositeImage(
                    $srcCore,
                    \Imagick::COMPOSITE_OVER,
                    -$srcX,
                    -$srcY
                );
            }

            $editor->blend($dest, $cropped, 'normal', 1.0, 'top-left', $destX, $destY);
        } else {
            // 直接 blend
            $editor->blend($dest, $src, 'normal', 1.0, 'top-left', $destX, $destY);
        }
    }

    /**
     * 混合两个图像（带透明度）
     *
     * @param ImageInterface $base 底层图像
     * @param ImageInterface $top 顶层图像
     * @param float $opacity 顶层透明度 (0-1)
     * @return ImageInterface 混合后的图像
     */
    protected function blendImages(ImageInterface $base, ImageInterface $top, float $opacity): ImageInterface
    {
        $editor = $this->getEditor();
        $editor->blend($base, $top, 'normal', $opacity, 'top-left', 0, 0);
        return $base;
    }
}