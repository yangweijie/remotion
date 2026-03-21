<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Helpers;

/**
 * Pipeline
 *
 * 函数管道工具，模拟 PHP 8.0+ 提议的管道操作符（|>）行为。
 * 允许将一系列转换函数以链式方式应用于一个值。
 *
 * 使用示例：
 * ```php
 * $result = Pipeline::make(0)
 *     ->pipe(fn($x) => $x + 1)         // 1
 *     ->pipe(fn($x) => $x * 2)         // 2
 *     ->pipe(fn($x) => "Value: {$x}")  // "Value: 2"
 *     ->get();
 *
 * // 简写：使用全局函数
 * $result = pipe(0)
 *     ->pipe(fn($x) => $x + 10)
 *     ->get();
 * ```
 *
 * @template T
 */
class Pipeline
{
    /**
     * @param mixed $value 初始值
     */
    private function __construct(private mixed $value) {}

    /**
     * 创建管道（对标管道操作符 value |> fn）
     *
     * @param mixed $value 初始值
     * @return static<mixed>
     */
    public static function make(mixed $value): static
    {
        return new static($value);
    }

    /**
     * 应用一个转换函数到当前值
     *
     * @param callable $transform 转换函数，接收当前值并返回新值
     * @return static<mixed>
     */
    public function pipe(callable $transform): static
    {
        return new static($transform($this->value));
    }

    /**
     * 条件性应用转换（仅当条件为真时）
     */
    public function pipeIf(bool $condition, callable $transform): static
    {
        return $condition ? $this->pipe($transform) : $this;
    }

    /**
     * 调试：打印当前值（不修改值）
     */
    public function tap(callable $sideEffect): static
    {
        $sideEffect($this->value);
        return $this;
    }

    /**
     * 获取最终结果
     */
    public function get(): mixed
    {
        return $this->value;
    }

    /**
     * 将管道中的值传递给函数并返回结果（终止管道）
     */
    public function into(callable $transform): mixed
    {
        return $transform($this->value);
    }

    /**
     * 创建图像处理管道（专为 GD 图像设计）
     */
    public static function image(\GdImage $image): static
    {
        return new static($image);
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
