<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Contracts;

use Yangweijie\Remotion\Core\RenderContext;

/**
 * ComponentInterface
 *
 * 对标 remotion 中的 React 组件概念。
 * 每个 Component 接收一个 RenderContext（包含当前帧、视频配置等），
 * 并返回渲染好的 GD 图像资源或 Grafika Editor 实例。
 */
interface ComponentInterface
{
    /**
     * 渲染当前帧
     *
     * @param RenderContext $ctx 当前渲染上下文（帧号、视频配置、Props）
     * @return \GdImage|resource 返回 GD 图像资源
     */
    public function render(RenderContext $ctx): \GdImage;
}
