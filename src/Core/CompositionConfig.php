<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Core;

/**
 * 可序列化的 Composition 配置
 * 
 * 用于并行渲染时传递配置给子进程
 */
class CompositionConfig
{
    public function __construct(
        public readonly string $id,
        public readonly int $durationInFrames,
        public readonly int $fps,
        public readonly int $width,
        public readonly int $height,
        public readonly string $rendererType = 'closure', // 'closure' | 'template' | 'layer'
        public readonly array $rendererConfig = [], // 用于重建 renderer 的配置
        public readonly array $defaultProps = [],
    ) {}
    
    /**
     * 从现有 Composition 创建配置
     */
    public static function fromComposition(Composition $composition): self
    {
        $config = $composition->getVideoConfig();
        
        return new self(
            id: $composition->getId(),
            durationInFrames: $config->durationInFrames,
            fps: $config->fps,
            width: $config->width,
            height: $config->height,
            defaultProps: $composition->getProps(),
        );
    }
    
    /**
     * 重建 VideoConfig
     */
    public function toVideoConfig(): VideoConfig
    {
        return new VideoConfig(
            durationInFrames: $this->durationInFrames,
            fps: $this->fps,
            width: $this->width,
            height: $this->height,
        );
    }
    
    /**
     * 序列化为数组
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'durationInFrames' => $this->durationInFrames,
            'fps' => $this->fps,
            'width' => $this->width,
            'height' => $this->height,
            'rendererType' => $this->rendererType,
            'rendererConfig' => $this->rendererConfig,
            'defaultProps' => $this->defaultProps,
        ];
    }
    
    /**
     * 从数组创建
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            durationInFrames: $data['durationInFrames'],
            fps: $data['fps'],
            width: $data['width'],
            height: $data['height'],
            rendererType: $data['rendererType'] ?? 'closure',
            rendererConfig: $data['rendererConfig'] ?? [],
            defaultProps: $data['defaultProps'] ?? [],
        );
    }
}
