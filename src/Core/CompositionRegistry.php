<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Core;

/**
 * 合成注册表（命名空间隔离）
 *
 * 提供命名空间隔离的合成注册表，避免不同项目/模块之间的静态注册表冲突。
 *
 * 使用示例：
 * ```php
 * // 使用默认命名空间
 * CompositionRegistry::register($comp);
 *
 * // 使用自定义命名空间
 * CompositionRegistry::register($comp, 'my-project');
 * CompositionRegistry::get('my-comp', 'my-project');
 *
 * // 清空特定命名空间
 * CompositionRegistry::clear('my-project');
 * ```
 */
class CompositionRegistry
{
    /** @var array<string, array<string, Composition>> 命名空间隔离的注册表 */
    private static array $namespaces = [];

    /** @var string 默认命名空间 */
    private static string $defaultNamespace = 'default';

    /** @var string|null 当前激活的命名空间 */
    private static ?string $activeNamespace = null;

    /**
     * 注册合成到指定命名空间
     */
    public static function register(Composition $composition, ?string $namespace = null): void
    {
        $ns = $namespace ?? self::$activeNamespace ?? self::$defaultNamespace;

        if (!isset(self::$namespaces[$ns])) {
            self::$namespaces[$ns] = [];
        }

        self::$namespaces[$ns][$composition->getId()] = $composition;
    }

    /**
     * 从指定命名空间获取合成
     */
    public static function get(string $id, ?string $namespace = null): ?Composition
    {
        $ns = $namespace ?? self::$activeNamespace ?? self::$defaultNamespace;

        return self::$namespaces[$ns][$id] ?? null;
    }

    /**
     * 检查合成是否存在
     */
    public static function has(string $id, ?string $namespace = null): bool
    {
        $ns = $namespace ?? self::$activeNamespace ?? self::$defaultNamespace;

        return isset(self::$namespaces[$ns][$id]);
    }

    /**
     * 获取指定命名空间的所有合成
     *
     * @return array<string, Composition>
     */
    public static function all(?string $namespace = null): array
    {
        $ns = $namespace ?? self::$activeNamespace ?? self::$defaultNamespace;

        return self::$namespaces[$ns] ?? [];
    }

    /**
     * 从指定命名空间移除合成
     */
    public static function remove(string $id, ?string $namespace = null): bool
    {
        $ns = $namespace ?? self::$activeNamespace ?? self::$defaultNamespace;

        if (!isset(self::$namespaces[$ns][$id])) {
            return false;
        }

        unset(self::$namespaces[$ns][$id]);
        return true;
    }

    /**
     * 清空指定命名空间（或所有命名空间）
     */
    public static function clear(?string $namespace = null): void
    {
        if ($namespace === null) {
            self::$namespaces = [];
        } else {
            unset(self::$namespaces[$namespace]);
        }
    }

    /**
     * 设置当前激活的命名空间
     *
     * 设置后，所有不带命名空间参数的操作都会使用此命名空间
     */
    public static function setActiveNamespace(string $namespace): void
    {
        self::$activeNamespace = $namespace;

        // 确保命名空间存在
        if (!isset(self::$namespaces[$namespace])) {
            self::$namespaces[$namespace] = [];
        }
    }

    /**
     * 获取当前激活的命名空间
     */
    public static function getActiveNamespace(): ?string
    {
        return self::$activeNamespace;
    }

    /**
     * 清除当前激活的命名空间（恢复使用默认）
     */
    public static function clearActiveNamespace(): void
    {
        self::$activeNamespace = null;
    }

    /**
     * 设置默认命名空间
     */
    public static function setDefaultNamespace(string $namespace): void
    {
        self::$defaultNamespace = $namespace;
    }

    /**
     * 获取默认命名空间
     */
    public static function getDefaultNamespace(): string
    {
        return self::$defaultNamespace;
    }

    /**
     * 获取所有命名空间名称
     *
     * @return string[]
     */
    public static function getNamespaces(): array
    {
        return array_keys(self::$namespaces);
    }

    /**
     * 检查命名空间是否存在
     */
    public static function hasNamespace(string $namespace): bool
    {
        return isset(self::$namespaces[$namespace]);
    }

    /**
     * 获取命名空间统计信息
     */
    public static function getStats(): array
    {
        $stats = [
            'totalNamespaces' => count(self::$namespaces),
            'namespaces' => [],
        ];

        foreach (self::$namespaces as $ns => $compositions) {
            $stats['namespaces'][$ns] = [
                'compositionCount' => count($compositions),
                'compositionIds' => array_keys($compositions),
            ];
        }

        return $stats;
    }

    /**
     * 在命名空间之间移动合成
     */
    public static function move(string $id, string $fromNamespace, string $toNamespace): bool
    {
        if (!isset(self::$namespaces[$fromNamespace][$id])) {
            return false;
        }

        $composition = self::$namespaces[$fromNamespace][$id];

        // 从源命名空间移除
        unset(self::$namespaces[$fromNamespace][$id]);

        // 添加到目标命名空间
        if (!isset(self::$namespaces[$toNamespace])) {
            self::$namespaces[$toNamespace] = [];
        }
        self::$namespaces[$toNamespace][$id] = $composition;

        return true;
    }

    /**
     * 复制合成到另一个命名空间
     */
    public static function copy(string $id, string $fromNamespace, string $toNamespace): bool
    {
        if (!isset(self::$namespaces[$fromNamespace][$id])) {
            return false;
        }

        $composition = self::$namespaces[$fromNamespace][$id];

        if (!isset(self::$namespaces[$toNamespace])) {
            self::$namespaces[$toNamespace] = [];
        }
        self::$namespaces[$toNamespace][$id] = $composition;

        return true;
    }
}
