<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Helpers;

/**
 * Color
 *
 * 颜色工具类，提供颜色解析、转换和操作功能。
 * 支持 HEX、RGB、HSL 等颜色格式，方便在动画中进行颜色插值。
 */
class Color
{
    private int $r;
    private int $g;
    private int $b;
    private float $a; // 0.0 (完全透明) ~ 1.0 (不透明)

    public function __construct(int $r, int $g, int $b, float $a = 1.0)
    {
        $this->r = max(0, min(255, $r));
        $this->g = max(0, min(255, $g));
        $this->b = max(0, min(255, $b));
        $this->a = max(0.0, min(1.0, $a));
    }

    // ---- 工厂方法 ----

    /**
     * 从十六进制颜色创建（支持 #RGB, #RGBA, #RRGGBB, #RRGGBBAA）
     */
    public static function fromHex(string $hex): self
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) === 4) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2].$hex[3].$hex[3];
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $a = strlen($hex) >= 8 ? hexdec(substr($hex, 6, 2)) / 255 : 1.0;

        return new self((int) $r, (int) $g, (int) $b, $a);
    }

    /**
     * 从 RGB 数组创建
     *
     * @param int[] $rgb [r, g, b] 或 [r, g, b, a]
     */
    public static function fromRgb(array $rgb): self
    {
        return new self($rgb[0], $rgb[1], $rgb[2], isset($rgb[3]) ? $rgb[3] / 255 : 1.0);
    }

    /**
     * 从 HSL 创建（h: 0-360, s: 0-1, l: 0-1）
     */
    public static function fromHsl(float $h, float $s, float $l): self
    {
        $h = fmod($h / 360, 1.0);

        if ($s === 0.0) {
            $v = (int) round($l * 255);
            return new self($v, $v, $v);
        }

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;

        $r = self::hueToRgb($p, $q, $h + 1 / 3);
        $g = self::hueToRgb($p, $q, $h);
        $b = self::hueToRgb($p, $q, $h - 1 / 3);

        return new self(
            (int) round($r * 255),
            (int) round($g * 255),
            (int) round($b * 255),
        );
    }

    // ---- 常用颜色常量 ----

    public static function white(): self  { return new self(255, 255, 255); }
    public static function black(): self  { return new self(0, 0, 0); }
    public static function red(): self    { return new self(255, 0, 0); }
    public static function green(): self  { return new self(0, 255, 0); }
    public static function blue(): self   { return new self(0, 0, 255); }
    public static function transparent(): self { return new self(0, 0, 0, 0.0); }

    // ---- 颜色操作 ----

    /**
     * 与另一个颜色混合（线性插值）
     *
     * @param Color $other 目标颜色
     * @param float $t     混合比例 (0.0 = 当前色, 1.0 = 目标色)
     */
    public function mix(self $other, float $t): self
    {
        return new self(
            r: (int) round($this->r + ($other->r - $this->r) * $t),
            g: (int) round($this->g + ($other->g - $this->g) * $t),
            b: (int) round($this->b + ($other->b - $this->b) * $t),
            a: $this->a + ($other->a - $this->a) * $t,
        );
    }

    /**
     * 调整亮度（系数 > 1 变亮，< 1 变暗）
     */
    public function lighten(float $factor): self
    {
        return new self(
            r: min(255, (int) round($this->r * $factor)),
            g: min(255, (int) round($this->g * $factor)),
            b: min(255, (int) round($this->b * $factor)),
            a: $this->a,
        );
    }

    /**
     * 设置透明度
     */
    public function withAlpha(float $alpha): self
    {
        return new self($this->r, $this->g, $this->b, $alpha);
    }

    // ---- 转换 ----

    public function toRgb(): array
    {
        return [$this->r, $this->g, $this->b];
    }

    public function toRgba(): array
    {
        return [$this->r, $this->g, $this->b, (int) round($this->a * 255)];
    }

    public function toHex(): string
    {
        return sprintf('#%02x%02x%02x', $this->r, $this->g, $this->b);
    }

    /**
     * 转换为 GD 颜色整数
     * @deprecated 请使用 toGrafikaColor() 代替
     */
    public function toGdColor(\GdImage $image): int
    {
        // GD 的 alpha 范围：0（不透明）~ 127（完全透明）
        $gdAlpha = (int) round((1 - $this->a) * 127);
        return imagecolorallocatealpha($image, $this->r, $this->g, $this->b, $gdAlpha);
    }

    /**
     * 转换为 Grafika Color 对象
     */
    public function toGrafikaColor(): \Grafika\Color
    {
        return new \Grafika\Color($this->toHex(), $this->a);
    }

    public function getR(): int { return $this->r; }
    public function getG(): int { return $this->g; }
    public function getB(): int { return $this->b; }
    public function getA(): float { return $this->a; }

    public function __toString(): string
    {
        if ($this->a < 1.0) {
            return sprintf('rgba(%d,%d,%d,%.2f)', $this->r, $this->g, $this->b, $this->a);
        }
        return $this->toHex();
    }

    // ---- 内部工具 ----

    private static function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) $t += 1;
        if ($t > 1) $t -= 1;

        if ($t < 1 / 6) return $p + ($q - $p) * 6 * $t;
        if ($t < 1 / 2) return $q;
        if ($t < 2 / 3) return $p + ($q - $p) * (2 / 3 - $t) * 6;

        return $p;
    }
}
