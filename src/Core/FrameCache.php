<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Core;

/**
 * 帧缓存 (LRU Cache)
 *
 * 用于缓存渲染过的帧图像，避免重复渲染。
 * 特别适用于包含循环、重复序列或需要随机访问帧的场景。
 *
 * 使用示例：
 * ```php
 * $cache = new FrameCache(capacity: 30); // 缓存最近30帧
 *
 * // 获取或渲染帧
 * $frame = $cache->get($frameNumber, function($frame) use ($comp) {
 *     return $comp->renderFrame($frame);
 * });
 * ```
 */
class FrameCache
{
    /** @var array<int, \GdImage> 缓存存储 */
    private array $cache = [];

    /** @var array<int, int> 访问顺序（帧号 => 时间戳） */
    private array $accessOrder = [];

    /** @var int 当前缓存大小 */
    private int $currentSize = 0;

    /** @var int 统计：命中次数 */
    private int $hits = 0;

    /** @var int 统计：未命中次数 */
    private int $misses = 0;

    public function __construct(
        private readonly int $capacity = 30,
    ) {
        if ($capacity < 1) {
            throw new \InvalidArgumentException('Cache capacity must be at least 1');
        }
    }

    /**
     * 获取帧（如果缓存中不存在则通过回调渲染）
     *
     * @param int      $frame    帧号
     * @param callable $renderer function(int $frame): \GdImage
     * @return \GdImage
     */
    public function get(int $frame, callable $renderer): \GdImage
    {
        // 检查缓存
        if (isset($this->cache[$frame])) {
            $this->hits++;
            $this->updateAccessOrder($frame);
            return $this->cache[$frame];
        }

        // 缓存未命中，渲染帧
        $this->misses++;
        $image = $renderer($frame);

        // 存储到缓存
        $this->put($frame, $image);

        return $image;
    }

    /**
     * 直接存储帧到缓存
     */
    public function put(int $frame, \GdImage $image): void
    {
        // 如果缓存已满，淘汰最久未使用的帧
        if ($this->currentSize >= $this->capacity && !isset($this->cache[$frame])) {
            $this->evictLRU();
        }

        // 存储新帧（如果是替换已有帧，需要先销毁旧图像）
        if (isset($this->cache[$frame])) {
            imagedestroy($this->cache[$frame]);
        } else {
            $this->currentSize++;
        }

        $this->cache[$frame] = $image;
        $this->updateAccessOrder($frame);
    }

    /**
     * 检查帧是否在缓存中
     */
    public function has(int $frame): bool
    {
        return isset($this->cache[$frame]);
    }

    /**
     * 从缓存中移除帧
     */
    public function remove(int $frame): bool
    {
        if (!isset($this->cache[$frame])) {
            return false;
        }

        imagedestroy($this->cache[$frame]);
        unset($this->cache[$frame], $this->accessOrder[$frame]);
        $this->currentSize--;

        return true;
    }

    /**
     * 清空缓存
     */
    public function clear(): void
    {
        foreach ($this->cache as $image) {
            imagedestroy($image);
        }

        $this->cache = [];
        $this->accessOrder = [];
        $this->currentSize = 0;
    }

    /**
     * 获取缓存统计信息
     */
    public function getStats(): array
    {
        $total = $this->hits + $this->misses;
        $hitRate = $total > 0 ? ($this->hits / $total) * 100 : 0;

        return [
            'capacity'    => $this->capacity,
            'currentSize' => $this->currentSize,
            'hits'        => $this->hits,
            'misses'      => $this->misses,
            'hitRate'     => round($hitRate, 2),
        ];
    }

    /**
     * 重置统计信息
     */
    public function resetStats(): void
    {
        $this->hits = 0;
        $this->misses = 0;
    }

    /**
     * 获取缓存容量
     */
    public function getCapacity(): int
    {
        return $this->capacity;
    }

    /**
     * 获取当前缓存大小
     */
    public function getSize(): int
    {
        return $this->currentSize;
    }

    /**
     * 更新访问顺序
     */
    private function updateAccessOrder(int $frame): void
    {
        $this->accessOrder[$frame] = hrtime(true);
    }

    /**
     * 淘汰最久未使用的帧
     */
    private function evictLRU(): void
    {
        // 找到最久未访问的帧
        $oldestFrame = null;
        $oldestTime = PHP_INT_MAX;

        foreach ($this->accessOrder as $frame => $time) {
            if ($time < $oldestTime) {
                $oldestTime = $time;
                $oldestFrame = $frame;
            }
        }

        if ($oldestFrame !== null) {
            $this->remove($oldestFrame);
        }
    }

    /**
     * 析构时清理资源
     */
    public function __destruct()
    {
        $this->clear();
    }
}
