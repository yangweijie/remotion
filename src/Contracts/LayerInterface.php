<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Contracts;

/**
 * LayerInterface
 *
 * 图层接口，对应 remotion 中的视觉元素（AbsoluteFill、序列片段等）。
 * 每个图层可以被组合进合成中，支持层叠、透明度、位置等属性。
 */
interface LayerInterface
{
    /**
     * 将图层绘制到目标画布上
     *
     * @param \GdImage $canvas 目标画布
     * @param int      $x      X 坐标
     * @param int      $y      Y 坐标
     * @return void
     */
    public function drawOn(\GdImage $canvas, int $x = 0, int $y = 0): void;

    /**
     * 获取图层宽度
     */
    public function getWidth(): int;

    /**
     * 获取图层高度
     */
    public function getHeight(): int;

    /**
     * 获取图层透明度 (0.0 - 1.0)
     */
    public function getOpacity(): float;

    /**
     * 设置图层透明度
     */
    public function setOpacity(float $opacity): static;
}
