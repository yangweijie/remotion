<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Contracts;

use Yangweijie\Remotion\Core\RenderContext;

/**
 * RendererInterface
 *
 * 渲染器接口，负责将合成导出为 GIF 或图像序列。
 */
interface RendererInterface
{
    /**
     * 渲染单帧
     *
     * @param RenderContext $ctx 渲染上下文
     * @return \GdImage 渲染结果图像
     */
    public function renderFrame(RenderContext $ctx): \GdImage;

    /**
     * 渲染整个合成为 GIF 文件
     *
     * @param string $outputPath 输出文件路径
     * @return bool 是否成功
     */
    public function renderToGif(string $outputPath): bool;

    /**
     * 渲染整个合成为图像序列
     *
     * @param string $outputDir 输出目录
     * @param string $format    图像格式 (png/jpeg)
     * @return bool 是否成功
     */
    public function renderToFrames(string $outputDir, string $format = 'png'): bool;
}
