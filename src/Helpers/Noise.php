<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Helpers;

/**
 * Noise - Perlin/Simplex 噪声生成器
 *
 * 提供确定性噪声生成，用于程序化动画和纹理。
 * 对标 Remotion 的 noise 功能。
 *
 * 使用示例：
 * ```php
 * use Yangweijie\Remotion\Helpers\Noise;
 *
 * // 2D 噪声（用于纹理）
 * $value = Noise::noise2D($x * 0.01, $y * 0.01, $seed);
 *
 * // 3D 噪声（用于动画纹理）
 * $value = Noise::noise3D($x * 0.01, $y * 0.01, $frame * 0.1, $seed);
 *
 * // 4D 噪声（用于 3D 动画）
 * $value = Noise::noise4D($x, $y, $z, $time, $seed);
 * ```
 *
 * @see https://www.remotion.dev/docs/noise
 */
class Noise
{
    /**
     * 排列表（用于 Perlin 噪声）
     */
    private static ?array $permutation = null;

    /**
     * 生成 2D Perlin 噪声
     *
     * @param float $x X 坐标
     * @param float $y Y 坐标
     * @param int $seed 随机种子
     * @return float -1 到 1 之间的噪声值
     */
    public static function noise2D(float $x, float $y, int $seed = 0): float
    {
        $p = self::getPermutation($seed);

        $xi = (int) floor($x) & 255;
        $yi = (int) floor($y) & 255;

        $xf = $x - floor($x);
        $yf = $y - floor($y);

        $u = self::fade($xf);
        $v = self::fade($yf);

        $aa = $p[$p[$xi] + $yi];
        $ab = $p[$p[$xi] + $yi + 1];
        $ba = $p[$p[$xi + 1] + $yi];
        $bb = $p[$p[$xi + 1] + $yi + 1];

        $x1 = self::lerp(
            self::grad($aa, $xf, $yf),
            self::grad($ba, $xf - 1, $yf),
            $u
        );

        $x2 = self::lerp(
            self::grad($ab, $xf, $yf - 1),
            self::grad($bb, $xf - 1, $yf - 1),
            $u
        );

        return self::lerp($x1, $x2, $v);
    }

    /**
     * 生成 3D Perlin 噪声
     *
     * @param float $x X 坐标
     * @param float $y Y 坐标
     * @param float $z Z 坐标
     * @param int $seed 随机种子
     * @return float -1 到 1 之间的噪声值
     */
    public static function noise3D(float $x, float $y, float $z, int $seed = 0): float
    {
        $p = self::getPermutation($seed);

        $xi = (int) floor($x) & 255;
        $yi = (int) floor($y) & 255;
        $zi = (int) floor($z) & 255;

        $xf = $x - floor($x);
        $yf = $y - floor($y);
        $zf = $z - floor($z);

        $u = self::fade($xf);
        $v = self::fade($yf);
        $w = self::fade($zf);

        $aaa = $p[$p[$p[$xi] + $yi] + $zi];
        $aab = $p[$p[$p[$xi] + $yi] + $zi + 1];
        $aba = $p[$p[$p[$xi] + $yi + 1] + $zi];
        $abb = $p[$p[$p[$xi] + $yi + 1] + $zi + 1];
        $baa = $p[$p[$p[$xi + 1] + $yi] + $zi];
        $bab = $p[$p[$p[$xi + 1] + $yi] + $zi + 1];
        $bba = $p[$p[$p[$xi + 1] + $yi + 1] + $zi];
        $bbb = $p[$p[$p[$xi + 1] + $yi + 1] + $zi + 1];

        $x1 = self::lerp(
            self::grad3D($aaa, $xf, $yf, $zf),
            self::grad3D($baa, $xf - 1, $yf, $zf),
            $u
        );
        $x2 = self::lerp(
            self::grad3D($aba, $xf, $yf - 1, $zf),
            self::grad3D($bba, $xf - 1, $yf - 1, $zf),
            $u
        );
        $y1 = self::lerp($x1, $x2, $v);

        $x1 = self::lerp(
            self::grad3D($aab, $xf, $yf, $zf - 1),
            self::grad3D($bab, $xf - 1, $yf, $zf - 1),
            $u
        );
        $x2 = self::lerp(
            self::grad3D($abb, $xf, $yf - 1, $zf - 1),
            self::grad3D($bbb, $xf - 1, $yf - 1, $zf - 1),
            $u
        );
        $y2 = self::lerp($x1, $x2, $v);

        return self::lerp($y1, $y2, $w);
    }

    /**
     * 生成 4D Perlin 噪声
     *
     * @param float $x X 坐标
     * @param float $y Y 坐标
     * @param float $z Z 坐标
     * @param float $w W 坐标（第四维度）
     * @param int $seed 随机种子
     * @return float -1 到 1 之间的噪声值
     */
    public static function noise4D(float $x, float $y, float $z, float $w, int $seed = 0): float
    {
        // 4D 噪声使用 3D 噪声的组合
        // 这是一种近似方法，但性能更好
        $n1 = self::noise3D($x, $y, $z, $seed);
        $n2 = self::noise3D($w, $x + 31.416, $y + 47.853, $seed);

        return ($n1 + $n2) / 2;
    }

    /**
     * 生成分形噪声（FBm - Fractional Brownian Motion）
     * 多层噪声叠加，产生更自然的纹理
     *
     * @param float $x X 坐标
     * @param float $y Y 坐标
     * @param int $seed 随机种子
     * @param int $octaves 叠加层数（默认 4）
     * @param float $lacunarity 频率增长因子（默认 2.0）
     * @param float $persistence 振幅衰减因子（默认 0.5）
     * @return float -1 到 1 之间的噪声值
     */
    public static function fbm(
        float $x,
        float $y,
        int $seed = 0,
        int $octaves = 4,
        float $lacunarity = 2.0,
        float $persistence = 0.5
    ): float {
        $value = 0.0;
        $amplitude = 1.0;
        $frequency = 1.0;
        $maxValue = 0.0;

        for ($i = 0; $i < $octaves; $i++) {
            $value += $amplitude * self::noise2D($x * $frequency, $y * $frequency, $seed + $i);
            $maxValue += $amplitude;
            $amplitude *= $persistence;
            $frequency *= $lacunarity;
        }

        return $value / $maxValue;
    }

    /**
     * 生成分形 3D 噪声
     */
    public static function fbm3D(
        float $x,
        float $y,
        float $z,
        int $seed = 0,
        int $octaves = 4,
        float $lacunarity = 2.0,
        float $persistence = 0.5
    ): float {
        $value = 0.0;
        $amplitude = 1.0;
        $frequency = 1.0;
        $maxValue = 0.0;

        for ($i = 0; $i < $octaves; $i++) {
            $value += $amplitude * self::noise3D($x * $frequency, $y * $frequency, $z * $frequency, $seed + $i);
            $maxValue += $amplitude;
            $amplitude *= $persistence;
            $frequency *= $lacunarity;
        }

        return $value / $maxValue;
    }

    /**
     * 湍流噪声（Turbulence）
     * 取噪声的绝对值，产生更锐利的纹理
     */
    public static function turbulence(
        float $x,
        float $y,
        int $seed = 0,
        int $octaves = 4
    ): float {
        $value = 0.0;
        $amplitude = 1.0;
        $frequency = 1.0;
        $maxValue = 0.0;

        for ($i = 0; $i < $octaves; $i++) {
            $value += $amplitude * abs(self::noise2D($x * $frequency, $y * $frequency, $seed + $i));
            $maxValue += $amplitude;
            $amplitude *= 0.5;
            $frequency *= 2.0;
        }

        return $value / $maxValue;
    }

    /**
     * 将噪声值映射到指定范围
     *
     * @param float $noiseValue -1 到 1 的噪声值
     * @param float $min 最小值
     * @param float $max 最大值
     * @return float
     */
    public static function mapToRange(float $noiseValue, float $min, float $max): float
    {
        $normalized = ($noiseValue + 1) / 2; // -1~1 -> 0~1
        return $min + $normalized * ($max - $min);
    }

    /**
     * 获取或创建排列表
     */
    private static function getPermutation(int $seed): array
    {
        if (self::$permutation === null || $seed !== 0) {
            self::$permutation = self::createPermutation($seed);
        }

        return self::$permutation;
    }

    /**
     * 创建排列表
     */
    private static function createPermutation(int $seed): array
    {
        // 基础排列表
        $p = [];
        for ($i = 0; $i < 256; $i++) {
            $p[$i] = $i;
        }

        // 使用种子进行 Fisher-Yates 洗牌
        mt_srand($seed);
        for ($i = 255; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            $temp = $p[$i];
            $p[$i] = $p[$j];
            $p[$j] = $temp;
        }

        // 复制数组以避免溢出
        for ($i = 0; $i < 256; $i++) {
            $p[256 + $i] = $p[$i];
        }

        return $p;
    }

    /**
     * 平滑函数（Quintic interpolation curve）
     */
    private static function fade(float $t): float
    {
        return $t * $t * $t * ($t * ($t * 6 - 15) + 10);
    }

    /**
     * 线性插值
     */
    private static function lerp(float $a, float $b, float $t): float
    {
        return $a + $t * ($b - $a);
    }

    /**
     * 2D 梯度函数
     */
    private static function grad(int $hash, float $x, float $y): float
    {
        $h = $hash & 7;
        $u = $h < 4 ? $x : $y;
        $v = $h < 4 ? $y : $x;

        return (($h & 1) === 0 ? $u : -$u) + (($h & 2) === 0 ? $v : -$v);
    }

    /**
     * 3D 梯度函数
     */
    private static function grad3D(int $hash, float $x, float $y, float $z): float
    {
        $h = $hash & 15;
        $u = $h < 8 ? $x : $y;
        $v = $h < 4 ? $y : ($h === 12 || $h === 14 ? $x : $z);

        return (($h & 1) === 0 ? $u : -$u) + (($h & 2) === 0 ? $v : -$v);
    }
}
