<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Animation;

/**
 * Spring
 *
 * PHP 版 remotion spring() 的实现。
 * 基于物理弹簧模型的动画，产生自然的弹性效果。
 *
 * 使用示例：
 * ```php
 * // 弹簧动画（从第 20 帧开始）
 * $scale = Spring::spring(frame: $frame, fps: 30, config: [
 *     'stiffness' => 100,
 *     'damping'   => 10,
 *     'mass'      => 1,
 * ]);
 * ```
 *
 * @see https://www.remotion.dev/docs/spring
 */
class Spring
{
    /**
     * 计算弹簧动画的当前值
     *
     * @param int   $frame  当前帧号（从动画起点算起）
     * @param int   $fps    视频帧率
     * @param array $config 弹簧配置
     *   - stiffness (float): 弹簧刚度，默认 100
     *   - damping   (float): 阻尼系数，默认 10
     *   - mass      (float): 质量，默认 1
     *   - from      (float): 起始值，默认 0
     *   - to        (float): 目标值，默认 1
     *   - overshootClamping (bool): 是否禁止过冲，默认 false
     * @param float $durationInFrames 动画总帧数（null = 无限）
     * @return float 当前动画值
     */
    public static function spring(
        int $frame,
        int $fps,
        array $config = [],
        ?float $durationInFrames = null,
    ): float {
        $stiffness        = $config['stiffness']        ?? 100.0;
        $damping          = $config['damping']          ?? 10.0;
        $mass             = $config['mass']             ?? 1.0;
        $from             = $config['from']             ?? 0.0;
        $to               = $config['to']               ?? 1.0;
        $overshootClamping = $config['overshootClamping'] ?? false;

        if ($frame <= 0) {
            return $from;
        }

        // 如果设置了总帧数并且当前帧超出范围
        if ($durationInFrames !== null && $frame >= $durationInFrames) {
            return $to;
        }

        // 弹簧方程（解析解）
        $t = $frame / $fps;

        $w0   = sqrt($stiffness / $mass);               // 自然角频率
        $zeta = $damping / (2 * sqrt($stiffness * $mass)); // 阻尼比

        $amplitude = $to - $from;

        if ($zeta < 1.0) {
            // 欠阻尼（会振荡）
            $wd    = $w0 * sqrt(1 - $zeta * $zeta);
            $value = 1 - exp(-$zeta * $w0 * $t) * (cos($wd * $t) + ($zeta * $w0 / $wd) * sin($wd * $t));
        } elseif ($zeta === 1.0) {
            // 临界阻尼
            $value = 1 - exp(-$w0 * $t) * (1 + $w0 * $t);
        } else {
            // 过阻尼
            $r1    = -$w0 * ($zeta - sqrt($zeta * $zeta - 1));
            $r2    = -$w0 * ($zeta + sqrt($zeta * $zeta - 1));
            $c2    = ($r1) / ($r1 - $r2);
            $c1    = 1 - $c2;
            $value = 1 - ($c1 * exp($r1 * $t) + $c2 * exp($r2 * $t));
        }

        $result = $from + $amplitude * $value;

        // 过冲限制
        if ($overshootClamping) {
            if ($to > $from) {
                $result = min($result, $to);
            } else {
                $result = max($result, $to);
            }
        }

        return $result;
    }

    /**
     * 判断弹簧动画是否已经稳定（收敛到目标值）
     *
     * @param int   $frame         当前帧号
     * @param int   $fps           帧率
     * @param array $config        弹簧配置
     * @param float $threshold     收敛阈值，默认 0.005
     */
    public static function isSettled(
        int $frame,
        int $fps,
        array $config = [],
        float $threshold = 0.005,
    ): bool {
        $current  = self::spring($frame, $fps, $config);
        $next     = self::spring($frame + 1, $fps, $config);
        $to       = $config['to'] ?? 1.0;

        return abs($current - $to) < $threshold && abs($next - $current) < $threshold;
    }

    /**
     * 获取弹簧动画稳定所需的帧数（近似值）
     */
    public static function getSettledFrame(int $fps, array $config = [], float $threshold = 0.005): int
    {
        $frame = 0;
        while ($frame < $fps * 30) { // 最多检查 30 秒
            if (self::isSettled($frame, $fps, $config, $threshold)) {
                return $frame;
            }
            $frame++;
        }
        return $frame;
    }
}
