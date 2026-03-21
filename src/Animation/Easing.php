<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Animation;

/**
 * Easing
 *
 * 缓动函数集合，对标 remotion 的 Easing 模块。
 * 提供常见的贝塞尔曲线和数学缓动函数。
 *
 * @see https://www.remotion.dev/docs/easing
 */
class Easing
{
    /**
     * 线性（无缓动）
     */
    public static function linear(float $t): float
    {
        return $t;
    }

    /**
     * ease（等同于 CSS ease）
     * 对应三次贝塞尔 cubic-bezier(0.25, 0.1, 0.25, 1.0)
     */
    public static function ease(float $t): float
    {
        return self::bezier(0.25, 0.1, 0.25, 1.0)($t);
    }

    /**
     * ease-in（缓入）
     */
    public static function easeIn(float $t): float
    {
        return self::bezier(0.42, 0.0, 1.0, 1.0)($t);
    }

    /**
     * ease-out（缓出）
     */
    public static function easeOut(float $t): float
    {
        return self::bezier(0.0, 0.0, 0.58, 1.0)($t);
    }

    /**
     * ease-in-out（缓入缓出）
     */
    public static function easeInOut(float $t): float
    {
        return self::bezier(0.42, 0.0, 0.58, 1.0)($t);
    }

    /**
     * 二次方缓入
     */
    public static function quad(float $t): float
    {
        return $t * $t;
    }

    /**
     * 三次方缓入
     */
    public static function cubic(float $t): float
    {
        return $t * $t * $t;
    }

    /**
     * 四次方缓入
     */
    public static function poly(int $n): \Closure
    {
        return fn (float $t): float => $t ** $n;
    }

    /**
     * 正弦缓动
     */
    public static function sin(float $t): float
    {
        return 1.0 - cos($t * M_PI / 2);
    }

    /**
     * 圆形缓动
     */
    public static function circle(float $t): float
    {
        return 1.0 - sqrt(1.0 - $t * $t);
    }

    /**
     * 指数缓动
     */
    public static function exp(float $t): float
    {
        return $t === 0.0 ? 0.0 : 2 ** (10 * ($t - 1));
    }

    /**
     * 弹性缓动
     */
    public static function elastic(float $bounciness = 1.0): \Closure
    {
        $p = $bounciness * M_PI;
        return function (float $t) use ($p): float {
            if ($t === 0.0 || $t === 1.0) {
                return $t;
            }
            return 1 - pow(cos($t * M_PI / 2), 3) * cos($t * $p);
        };
    }

    /**
     * 回弹效果（先超出再回到终点）
     */
    public static function back(float $s = 1.70158): \Closure
    {
        return fn (float $t): float => $t * $t * (($s + 1) * $t - $s);
    }

    /**
     * 弹跳缓动
     */
    public static function bounce(float $t): float
    {
        if ($t < 1 / 2.75) {
            return 7.5625 * $t * $t;
        } elseif ($t < 2 / 2.75) {
            $t -= 1.5 / 2.75;
            return 7.5625 * $t * $t + 0.75;
        } elseif ($t < 2.5 / 2.75) {
            $t -= 2.25 / 2.75;
            return 7.5625 * $t * $t + 0.9375;
        } else {
            $t -= 2.625 / 2.75;
            return 7.5625 * $t * $t + 0.984375;
        }
    }

    /**
     * 创建三次贝塞尔曲线缓动函数
     * 对标 Easing.bezier() 和 CSS cubic-bezier()
     *
     * @param float $x1 控制点1 X
     * @param float $y1 控制点1 Y
     * @param float $x2 控制点2 X
     * @param float $y2 控制点2 Y
     */
    public static function bezier(float $x1, float $y1, float $x2, float $y2): \Closure
    {
        // 使用二分法近似求解贝塞尔曲线 t 值
        return function (float $x) use ($x1, $y1, $x2, $y2): float {
            if ($x <= 0.0) return 0.0;
            if ($x >= 1.0) return 1.0;

            // 二分查找对应 x 的参数 t
            $lo = 0.0;
            $hi = 1.0;
            $t  = $x;

            for ($i = 0; $i < 30; $i++) {
                $xGuess = self::bezierX($t, $x1, $x2);
                $diff   = $xGuess - $x;

                if (abs($diff) < 1e-7) break;

                if ($diff > 0) {
                    $hi = $t;
                } else {
                    $lo = $t;
                }
                $t = ($lo + $hi) / 2;
            }

            return self::bezierY($t, $y1, $y2);
        };
    }

    /**
     * 反转缓动函数（in -> out）
     */
    public static function in(\Closure $easing): \Closure
    {
        return $easing;
    }

    /**
     * 将缓动函数转换为缓出
     */
    public static function out(\Closure $easing): \Closure
    {
        return fn (float $t): float => 1 - $easing(1 - $t);
    }

    /**
     * 将缓动函数转换为缓入缓出
     */
    public static function inOut(\Closure $easing): \Closure
    {
        return function (float $t) use ($easing): float {
            if ($t < 0.5) {
                return $easing($t * 2) / 2;
            }
            return 1 - $easing((1 - $t) * 2) / 2;
        };
    }

    // ---- 内部贝塞尔计算 ----

    private static function bezierX(float $t, float $x1, float $x2): float
    {
        return 3 * (1 - $t) * (1 - $t) * $t * $x1
            + 3 * (1 - $t) * $t * $t * $x2
            + $t * $t * $t;
    }

    private static function bezierY(float $t, float $y1, float $y2): float
    {
        return 3 * (1 - $t) * (1 - $t) * $t * $y1
            + 3 * (1 - $t) * $t * $t * $y2
            + $t * $t * $t;
    }
}
