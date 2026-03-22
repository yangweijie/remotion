<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Core;

use Yangweijie\Remotion\Contracts\ComponentInterface;
use InvalidArgumentException;

/**
 * Series 组件
 *
 * 自动排列多个序列，无需手动计算 from 帧号。
 * 每个序列会自动接续前一个序列的结束帧。
 * 对标 Remotion 的 <Series> 组件。
 *
 * 使用示例：
 * ```php
 * $series = Series::make()
 *     ->add('intro', fn($ctx) => $introLayer->render($ctx), 30)
 *     ->add('main', fn($ctx) => $mainLayer->render($ctx), 60)
 *     ->add('outro', fn($ctx) => $outroLayer->render($ctx), 20);
 *
 * // 或者使用数组方式
 * $series = Series::fromArray([
 *     ['id' => 'intro', 'component' => $introComponent, 'durationInFrames' => 30],
 *     ['id' => 'main', 'component' => $mainComponent, 'durationInFrames' => 60],
 * ]);
 * ```
 *
 * @see https://www.remotion.dev/docs/series
 */
class Series
{
    /** @var array<int, array{id: string, component: ComponentInterface, durationInFrames: int, offset: int}> */
    private array $sequences = [];

    private int $currentOffset = 0;

    public function __construct()
    {
    }

    /**
     * 静态工厂方法
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * 从数组创建
     *
     * @param array<int, array{id: string, component: ComponentInterface|\Closure, durationInFrames: int}> $items
     */
    public static function fromArray(array $items): self
    {
        $series = new self();

        foreach ($items as $item) {
            $series->add(
                $item['id'],
                $item['component'],
                $item['durationInFrames']
            );
        }

        return $series;
    }

    /**
     * 添加序列
     *
     * @param string $id 序列标识
     * @param ComponentInterface|\Closure $component 渲染组件
     * @param int $durationInFrames 持续帧数
     * @return self 支持链式调用
     */
    public function add(string $id, ComponentInterface|\Closure $component, int $durationInFrames): self
    {
        if ($durationInFrames <= 0) {
            throw new InvalidArgumentException("Series: durationInFrames must be positive for '{$id}'");
        }

        // 将闭包转换为 ComponentInterface
        if ($component instanceof \Closure) {
            $fn = $component;
            $component = new class($fn) implements ComponentInterface {
                public function __construct(private readonly \Closure $fn) {}

                public function render(RenderContext $ctx): \GdImage
                {
                    return ($this->fn)($ctx);
                }
            };
        }

        $this->sequences[] = [
            'id' => $id,
            'component' => $component,
            'durationInFrames' => $durationInFrames,
            'offset' => $this->currentOffset,
        ];

        $this->currentOffset += $durationInFrames;

        return $this;
    }

    /**
     * 获取总帧数
     */
    public function getTotalDuration(): int
    {
        return $this->currentOffset;
    }

    /**
     * 渲染当前帧对应的序列
     *
     * @param RenderContext $ctx 渲染上下文
     * @param VideoConfig $config 视频配置
     * @return \GdImage|null
     */
    public function render(RenderContext $ctx, VideoConfig $config): ?\GdImage
    {
        $frame = $ctx->getCurrentFrame();

        foreach ($this->sequences as $seq) {
            $start = $seq['offset'];
            $end = $start + $seq['durationInFrames'];

            // 检查当前帧是否在此序列范围内
            if ($frame >= $start && $frame < $end) {
                // 创建本地上下文（帧号从 0 开始）
                $localCtx = $ctx->withFrameOffset($start);
                return $seq['component']->render($localCtx);
            }
        }

        return null;
    }

    /**
     * 获取当前帧所在的序列信息
     *
     * @param int $frame 全局帧号
     * @return array{id: string, durationInFrames: int, offset: int, localFrame: int}|null
     */
    public function getSequenceAtFrame(int $frame): ?array
    {
        foreach ($this->sequences as $seq) {
            $start = $seq['offset'];
            $end = $start + $seq['durationInFrames'];

            if ($frame >= $start && $frame < $end) {
                return [
                    'id' => $seq['id'],
                    'durationInFrames' => $seq['durationInFrames'],
                    'offset' => $seq['offset'],
                    'localFrame' => $frame - $start,
                ];
            }
        }

        return null;
    }

    /**
     * 获取所有序列
     *
     * @return array<int, array{id: string, durationInFrames: int, offset: int}>
     */
    public function getSequences(): array
    {
        return array_map(fn($seq) => [
            'id' => $seq['id'],
            'durationInFrames' => $seq['durationInFrames'],
            'offset' => $seq['offset'],
        ], $this->sequences);
    }

    /**
     * 根据索引获取序列
     */
    public function getSequenceByIndex(int $index): ?Sequence
    {
        if (!isset($this->sequences[$index])) {
            return null;
        }

        $seq = $this->sequences[$index];

        return Sequence::make(
            $seq['id'],
            $seq['component'],
            $seq['offset'],
            $seq['durationInFrames']
        );
    }

    /**
     * 根据 ID 获取序列
     */
    public function getSequenceById(string $id): ?Sequence
    {
        foreach ($this->sequences as $seq) {
            if ($seq['id'] === $id) {
                return Sequence::make(
                    $seq['id'],
                    $seq['component'],
                    $seq['offset'],
                    $seq['durationInFrames']
                );
            }
        }

        return null;
    }
}
