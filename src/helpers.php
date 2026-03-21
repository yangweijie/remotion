<?php

declare(strict_types=1);

use Yangweijie\Remotion\Animation\Easing;
use Yangweijie\Remotion\Animation\Interpolate;
use Yangweijie\Remotion\Animation\Spring;
use Yangweijie\Remotion\Helpers\Pipeline;

/**
 * 全局助手函数
 *
 * 提供类似 remotion 顶级函数的 PHP 版本：
 * - interpolate()  对标 remotion 的 interpolate()
 * - spring()       对标 remotion 的 spring()
 * - pipe()         管道操作符语法糖
 * - clamp()        数值截断
 */

if (!function_exists('interpolate')) {
    /**
     * 值插值（对标 remotion 的 interpolate()）
     *
     * @param float   $input       当前输入值（通常是帧号）
     * @param float[] $inputRange  输入范围
     * @param float[] $outputRange 输出范围
     * @param array   $options     选项（easing, extrapolateLeft, extrapolateRight）
     */
    function interpolate(float $input, array $inputRange, array $outputRange, array $options = []): float
    {
        return Interpolate::interpolate($input, $inputRange, $outputRange, $options);
    }
}

if (!function_exists('spring')) {
    /**
     * 弹簧动画（对标 remotion 的 spring()）
     *
     * @param int   $frame  当前帧号
     * @param int   $fps    帧率
     * @param array $config 弹簧配置（stiffness, damping, mass, from, to）
     */
    function spring(int $frame, int $fps, array $config = []): float
    {
        return Spring::spring($frame, $fps, $config);
    }
}

if (!function_exists('pipe')) {
    /**
     * 创建管道（对标管道操作符 |>）
     *
     * @param mixed $value 初始值
     */
    function pipe(mixed $value): Pipeline
    {
        return Pipeline::make($value);
    }
}

if (!function_exists('clamp')) {
    /**
     * 将值限制在 [min, max] 范围内
     */
    function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }
}

if (!function_exists('ease_in_out')) {
    /**
     * 快速缓入缓出插值
     */
    function ease_in_out(float $input, array $inputRange, array $outputRange): float
    {
        return interpolate($input, $inputRange, $outputRange, [
            'easing'           => Easing::easeInOut(...),
            'extrapolateLeft'  => 'clamp',
            'extrapolateRight' => 'clamp',
        ]);
    }
}

if (!function_exists('fade_in')) {
    /**
     * 快速淡入插值（从 0 到 1 的不透明度）
     *
     * @param int $frame     当前帧
     * @param int $startFrame 开始帧
     * @param int $endFrame   结束帧
     */
    function fade_in(int $frame, int $startFrame = 0, int $endFrame = 20): float
    {
        return clamp(
            interpolate($frame, [$startFrame, $endFrame], [0.0, 1.0]),
            0.0,
            1.0
        );
    }
}

if (!function_exists('fade_out')) {
    /**
     * 快速淡出插值（从 1 到 0 的不透明度）
     */
    function fade_out(int $frame, int $startFrame, int $endFrame): float
    {
        return clamp(
            interpolate($frame, [$startFrame, $endFrame], [1.0, 0.0]),
            0.0,
            1.0
        );
    }
}
