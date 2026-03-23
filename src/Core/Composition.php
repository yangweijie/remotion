<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Core;

use Yangweijie\Remotion\Contracts\ComponentInterface;

/**
 * Composition
 *
 * PHP 版 remotion Composition 的核心实现。
 * 对标 <Composition> 组件，用于注册一个可渲染的视频/动画合成。
 *
 * 使用示例：
 * ```php
 * $composition = Composition::make(
 *     id: 'my-video',
 *     component: new MyComponent(),
 *     durationInFrames: 90,
 *     fps: 30,
 *     width: 1920,
 *     height: 1080,
 * );
 * ```
 *
 * @see https://www.remotion.dev/docs/composition
 */
class Composition
{
    /** @var array<string, self> 全局合成注册表 */
    private static array $registry = [];

    private readonly VideoConfig $videoConfig;

    /** @var array<int, Sequence> 子序列列表 */
    private array $sequences = [];

    /** @var CompositionConfig|null 可序列化配置（用于并行渲染） */
    private ?CompositionConfig $config = null;

    public function __construct(
        public readonly string $id,
        private readonly ComponentInterface $component,
        int $durationInFrames,
        int $fps,
        int $width,
        int $height,
        public readonly array $defaultProps = [],
    ) {
        // 验证 ID 格式（只允许字母、数字、连字符）
        if (!preg_match('/^[a-zA-Z0-9\-]+$/', $id)) {
            throw new \InvalidArgumentException(
                "Composition ID '{$id}' can only contain letters, numbers and hyphens"
            );
        }

        $this->videoConfig = new VideoConfig($durationInFrames, $fps, $width, $height);
    }

    /**
     * 静态工厂方法（流式 API）
     */
    public static function make(
        string $id,
        ComponentInterface $component,
        int $durationInFrames,
        int $fps,
        int $width,
        int $height,
        array $defaultProps = [],
    ): self {
        return new self($id, $component, $durationInFrames, $fps, $width, $height, $defaultProps);
    }

    /**
     * 使用闭包快速创建合成（无需实现 ComponentInterface）
     *
     * @param string   $id
     * @param \Closure $renderer function(RenderContext $ctx): \GdImage
     */
    public static function fromClosure(
        string $id,
        \Closure $renderer,
        int $durationInFrames,
        int $fps,
        int $width,
        int $height,
        array $defaultProps = [],
    ): self {
        $component = new class($renderer) implements ComponentInterface {
            public function __construct(private readonly \Closure $fn) {}

            public function render(RenderContext $ctx): \GdImage
            {
                return ($this->fn)($ctx);
            }
        };

        return new self($id, $component, $durationInFrames, $fps, $width, $height, $defaultProps);
    }

    /**
     * 注册合成到全局注册表（对标 registerRoot()）
     */
    public function register(): self
    {
        self::$registry[$this->id] = $this;
        return $this;
    }

    /**
     * 从全局注册表获取合成
     */
    public static function get(string $id): ?self
    {
        return self::$registry[$id] ?? null;
    }

    /**
     * 获取所有已注册的合成
     *
     * @return array<string, self>
     */
    public static function all(): array
    {
        return self::$registry;
    }

    /**
     * 清空全局注册表
     */
    public static function clearRegistry(): void
    {
        self::$registry = [];
    }

    /**
     * 添加一个子序列（时间轴片段）
     */
    public function addSequence(Sequence $sequence): self
    {
        $this->sequences[] = $sequence;
        return $this;
    }

    /**
     * 渲染指定帧号的图像
     *
     * @param int   $frame 帧号（0 到 durationInFrames-1）
     * @param array $props 覆盖 defaultProps 的运行时 Props
     * @return \GdImage
     */
    public function renderFrame(int $frame, array $props = []): \GdImage
    {
        if ($frame < 0 || $frame >= $this->videoConfig->durationInFrames) {
            throw new \OutOfRangeException(
                "Frame {$frame} is out of range [0, {$this->videoConfig->durationInFrames})"
            );
        }

        $mergedProps = array_merge($this->defaultProps, $props);
        $ctx = new RenderContext($frame, $this->videoConfig, $mergedProps, $this->id);

        return $this->component->render($ctx);
    }

    /**
     * 构建该合成的时间轴
     */
    public function buildTimeline(): Timeline
    {
        $timeline = new Timeline($this->videoConfig);

        foreach ($this->sequences as $seq) {
            $timeline->register($seq->getId(), $seq->getFrom(), $seq->getDurationInFrames());
        }

        return $timeline;
    }

    /**
     * 获取合成的视频配置
     */
    public function getVideoConfig(): VideoConfig
    {
        return $this->videoConfig;
    }

    /**
     * 获取组件实例
     */
    public function getComponent(): ComponentInterface
    {
        return $this->component;
    }

    /**
     * 获取子序列列表
     *
     * @return Sequence[]
     */
    public function getSequences(): array
    {
        return $this->sequences;
    }

    /**
     * 获取合成ID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * 设置可序列化配置
     */
    public function setConfig(CompositionConfig $config): self
    {
        $this->config = $config;
        return $this;
    }

    /**
     * 获取可序列化配置
     */
    public function getConfig(): ?CompositionConfig
    {
        return $this->config;
    }

    /**
     * 检查是否支持并行渲染
     */
    public function isParallelizable(): bool
    {
        return $this->config !== null;
    }

    /**
     * 获取合成摘要信息
     */
    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'durationInFrames' => $this->videoConfig->durationInFrames,
            'fps'              => $this->videoConfig->fps,
            'width'            => $this->videoConfig->width,
            'height'           => $this->videoConfig->height,
            'defaultProps'     => $this->defaultProps,
            'sequences'        => count($this->sequences),
        ];
    }

    public function __toString(): string
    {
        return sprintf(
            'Composition{id="%s", %s}',
            $this->id,
            $this->videoConfig
        );
    }
}
