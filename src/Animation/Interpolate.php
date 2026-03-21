<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Animation;

/**
 * Interpolate
 *
 * PHP 版 remotion interpolate() 的实现。
 * 将输入值从一个范围映射到另一个范围，支持缓动函数和边界外推。
 *
 * 使用示例：
 * ```php
 * // 将帧 0-30 的不透明度从 0 插值到 1（淡入效果）
 * $opacity = Interpolate::interpolate($frame, [0, 30], [0, 1]);
 *
 * // 使用缓动函数
 * $scale = Interpolate::interpolate($frame, [0, 60], [0.5, 1.0], [
 *     'easing' => Easing::easeOut(...),
 * ]);
 * ```
 *
 * @see https://www.remotion.dev/docs/interpolate
 */
class Interpolate
{
    /**
     * 核心插值函数（对标 remotion 的 interpolate()）
     *
     * @param float     $input        当前输入值（通常是帧号）
     * @param float[]   $inputRange   输入范围，必须单调递增，如 [0, 30, 60]
     * @param float[]   $outputRange  输出范围，与 inputRange 等长，如 [0, 0.5, 1]
     * @param array{
     *     easing?: \Closure,
     *     extrapolateLeft?: 'extend'|'clamp'|'identity',
     *     extrapolateRight?: 'extend'|'clamp'|'identity',
     * } $options 插值选项
     * @return float 插值结果
     */
    public static function interpolate(
        float $input,
        array $inputRange,
        array $outputRange,
        array $options = [],
    ): float {
        if (count($inputRange) !== count($outputRange)) {
            throw new \InvalidArgumentException(
                'inputRange and outputRange must have the same length'
            );
        }
        if (count($inputRange) < 2) {
            throw new \InvalidArgumentException(
                'inputRange and outputRange must have at least 2 elements'
            );
        }

        $easing           = $options['easing'] ?? null;
        $extrapolateLeft  = $options['extrapolateLeft']  ?? 'extend';
        $extrapolateRight = $options['extrapolateRight'] ?? 'extend';

        // 找到输入值所在的区间
        $segment = self::findSegment($input, $inputRange);

        $inputLo  = $inputRange[$segment];
        $inputHi  = $inputRange[$segment + 1];
        $outputLo = $outputRange[$segment];
        $outputHi = $outputRange[$segment + 1];

        // 计算区间内的归一化进度 (0~1)
        $inputDiff = $inputHi - $inputLo;

        if ($inputDiff === 0.0) {
            $progress = 1.0;
        } else {
            $progress = ($input - $inputLo) / $inputDiff;
        }

        // 处理边界外推
        if ($input < $inputRange[0]) {
            return self::handleExtrapolate($extrapolateLeft, $input, $inputLo, $inputHi, $outputLo, $outputHi);
        }

        if ($input > $inputRange[count($inputRange) - 1]) {
            return self::handleExtrapolate($extrapolateRight, $input, $inputLo, $inputHi, $outputLo, $outputHi);
        }

        // 应用缓动函数
        if ($easing !== null) {
            $progress = $easing($progress);
        }

        // 线性混合输出值
        return $outputLo + $progress * ($outputHi - $outputLo);
    }

    /**
     * 颜色插值（在两个 RGB 颜色之间插值）
     *
     * @param float     $input       当前帧号
     * @param float[]   $inputRange  输入范围
     * @param int[][]   $colorRange  颜色范围，每个元素为 [r, g, b]，如 [[255,0,0], [0,0,255]]
     * @return int[] 插值颜色 [r, g, b]
     */
    public static function interpolateColors(
        float $input,
        array $inputRange,
        array $colorRange,
        array $options = [],
    ): array {
        $r = (int) round(self::interpolate($input, $inputRange, array_column($colorRange, 0), $options));
        $g = (int) round(self::interpolate($input, $inputRange, array_column($colorRange, 1), $options));
        $b = (int) round(self::interpolate($input, $inputRange, array_column($colorRange, 2), $options));

        return [
            max(0, min(255, $r)),
            max(0, min(255, $g)),
            max(0, min(255, $b)),
        ];
    }

    /**
     * 找到输入值所在的区间索引
     */
    private static function findSegment(float $input, array $inputRange): int
    {
        $len = count($inputRange);

        // 超出左边界，使用第一个区间
        if ($input <= $inputRange[0]) {
            return 0;
        }

        // 超出右边界，使用最后一个区间
        if ($input >= $inputRange[$len - 1]) {
            return $len - 2;
        }

        // 找到对应区间
        for ($i = 0; $i < $len - 1; $i++) {
            if ($input >= $inputRange[$i] && $input <= $inputRange[$i + 1]) {
                return $i;
            }
        }

        return 0;
    }

    /**
     * 处理边界外推逻辑
     */
    private static function handleExtrapolate(
        string $mode,
        float $input,
        float $inputLo,
        float $inputHi,
        float $outputLo,
        float $outputHi,
    ): float {
        return match ($mode) {
            'clamp'    => $input < $inputLo ? $outputLo : $outputHi,
            'identity' => $input,
            // 'extend'（默认）：线性延伸
            default => $inputHi === $inputLo
                ? $outputLo
                : $outputLo + ($input - $inputLo) / ($inputHi - $inputLo) * ($outputHi - $outputLo),
        };
    }
}
