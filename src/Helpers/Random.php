<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Helpers;

/**
 * Random - 确定性伪随机数生成器
 *
 * 基于种子的伪随机数生成，确保多线程/多次渲染结果一致。
 * 对标 Remotion 的 random() 函数。
 *
 * 使用示例：
 * ```php
 * use Yangweijie\Remotion\Helpers\Random;
 *
 * // 在帧渲染中使用确定性随机
 * $x = Random::get('particle-x', $frame, 0, $config->width);
 * $y = Random::get('particle-y', $frame, 0, $config->height);
 * $size = Random::range('particle-size', $frame, 5, 20);
 * ```
 *
 * @see https://www.remotion.dev/docs/random
 */
class Random
{
    /**
     * 生成确定性伪随机数
     *
     * @param string $seed 随机种子标识
     * @param int $frame 帧号
     * @param float|null $min 最小值
     * @param float|null $max 最大值
     * @return float 0-1 之间的随机数，或 min-max 范围内的值
     */
    public static function get(string $seed, int $frame, ?float $min = null, ?float $max = null): float
    {
        // 组合种子和帧号生成唯一哈希
        $combined = $seed . '-' . $frame;
        $hash = self::hash($combined);

        // 归一化到 0-1 (CRC32 最大值是 4294967295)
        $value = $hash / 4294967295.0;

        // 如果指定了范围，映射到该范围
        if ($min !== null && $max !== null) {
            return $min + $value * ($max - $min);
        }

        return $value;
    }

    /**
     * 生成确定性整数随机数
     *
     * @param string $seed 随机种子标识
     * @param int $frame 帧号
     * @param int $min 最小值（包含）
     * @param int $max 最大值（包含）
     * @return int
     */
    public static function range(string $seed, int $frame, int $min, int $max): int
    {
        $value = self::get($seed, $frame);
        return (int) floor($min + $value * ($max - $min + 1));
    }

    /**
     * 生成随机布尔值
     *
     * @param string $seed 随机种子标识
     * @param int $frame 帧号
     * @param float $probability 为 true 的概率 (0-1)
     * @return bool
     */
    public static function bool(string $seed, int $frame, float $probability = 0.5): bool
    {
        return self::get($seed, $frame) < $probability;
    }

    /**
     * 从数组中随机选择一个元素
     *
     * @template T
     * @param string $seed 随机种子标识
     * @param int $frame 帧号
     * @param array<T> $array 候选数组
     * @return T
     */
    public static function pick(string $seed, int $frame, array $array): mixed
    {
        if (empty($array)) {
            throw new \InvalidArgumentException('Cannot pick from empty array');
        }

        $index = self::range($seed, $frame, 0, count($array) - 1);
        return $array[$index];
    }

    /**
     * 生成随机颜色（RGB）
     *
     * @param string $seed 随机种子标识
     * @param int $frame 帧号
     * @return array{r: int, g: int, b: int}
     */
    public static function color(string $seed, int $frame): array
    {
        return [
            'r' => self::range($seed . '-r', $frame, 0, 255),
            'g' => self::range($seed . '-g', $frame, 0, 255),
            'b' => self::range($seed . '-b', $frame, 0, 255),
        ];
    }

    /**
     * 生成 2D 随机坐标
     *
     * @param string $seed 随机种子标识
     * @param int $frame 帧号
     * @param int $width 宽度
     * @param int $height 高度
     * @return array{x: int, y: int}
     */
    public static function position(string $seed, int $frame, int $width, int $height): array
    {
        return [
            'x' => self::range($seed . '-x', $frame, 0, $width - 1),
            'y' => self::range($seed . '-y', $frame, 0, $height - 1),
        ];
    }

    /**
     * 使用 xxHash 算法的简化实现
     * 确保跨平台一致性
     */
    private static function hash(string $input): int
    {
        // 使用 CRC32 作为简单但一致的哈希
        // CRC32 返回 32 位无符号整数 (0 - 4294967295)
        $hash = crc32($input);

        // 确保返回正整数
        return abs($hash);
    }

    /**
     * 创建随机数生成器实例（用于多次调用）
     */
    public static function create(string $seed, int $frame): RandomGenerator
    {
        return new RandomGenerator($seed, $frame);
    }
}

/**
 * 随机数生成器实例
 * 支持链式调用
 */
class RandomGenerator
{
    private int $counter = 1;

    public function __construct(
        private readonly string $seed,
        private readonly int $frame
    ) {}

    /**
     * 获取下一个随机值
     */
    public function next(?float $min = null, ?float $max = null): float
    {
        $value = Random::get($this->seed . '-' . $this->counter, $this->frame, $min, $max);
        $this->counter++;
        return $value;
    }

    /**
     * 获取下一个整数
     */
    public function nextInt(int $min, int $max): int
    {
        return Random::range($this->seed . '-' . $this->counter++, $this->frame, $min, $max);
    }

    /**
     * 获取下一个布尔值
     */
    public function nextBool(float $probability = 0.5): bool
    {
        return Random::bool($this->seed . '-' . $this->counter++, $this->frame, $probability);
    }

    /**
     * 重置计数器
     */
    public function reset(): self
    {
        $this->counter = 1;
        return $this;
    }
}
