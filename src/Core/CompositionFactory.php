<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Core;

use Yangweijie\Remotion\Contracts\ComponentInterface;

/**
 * Composition 工厂
 * 
 * 用于从配置重建 Composition，支持并行渲染
 */
class CompositionFactory
{
    private static array $templateRegistry = [];
    
    /**
     * 注册可复用的模板
     */
    public static function registerTemplate(string $name, callable $factory): void
    {
        self::$templateRegistry[$name] = $factory;
    }
    
    /**
     * 从配置创建 Composition
     */
    public static function createFromConfig(CompositionConfig $config): Composition
    {
        // 根据 renderer 类型重建
        return match ($config->rendererType) {
            'template' => self::createFromTemplate($config),
            'layer' => self::createFromLayers($config),
            default => throw new \InvalidArgumentException(
                "Cannot recreate Composition with renderer type: {$config->rendererType}. " .
                "Use 'template' or 'layer' type for parallel rendering."
            ),
        };
    }
    
    /**
     * 从模板创建
     */
    private static function createFromTemplate(CompositionConfig $config): Composition
    {
        $templateName = $config->rendererConfig['template'] ?? null;
        
        if (!isset(self::$templateRegistry[$templateName])) {
            throw new \InvalidArgumentException("Unknown template: {$templateName}");
        }
        
        $factory = self::$templateRegistry[$templateName];
        $component = $factory($config->rendererConfig['params'] ?? [], $config->defaultProps);
        
        return new Composition(
            id: $config->id,
            component: $component,
            durationInFrames: $config->durationInFrames,
            fps: $config->fps,
            width: $config->width,
            height: $config->height,
            defaultProps: $config->defaultProps,
        );
    }
    
    /**
     * 从图层配置创建
     */
    private static function createFromLayers(CompositionConfig $config): Composition
    {
        $layers = $config->rendererConfig['layers'] ?? [];
        
        $component = new class($layers) implements ComponentInterface {
            private array $layers;
            
            public function __construct(array $layers)
            {
                $this->layers = $layers;
            }
            
            public function render(RenderContext $ctx): \GdImage
            {
                $config = $ctx->getVideoConfig();
                $canvas = imagecreatetruecolor($config->width, $config->height);
                
                foreach ($this->layers as $layerConfig) {
                    $layer = self::createLayer($layerConfig);
                    $layer->render($ctx, $canvas);
                }
                
                return $canvas;
            }
            
            private static function createLayer(array $config): object
            {
                // 根据配置创建不同类型的图层
                return match ($config['type']) {
                    'color' => new \Yangweijie\Remotion\Layers\ColorLayer(
                        $config['width'],
                        $config['height'],
                        ...$config['color']
                    ),
                    'text' => new \Yangweijie\Remotion\Layers\TextLayer(
                        $config['text'],
                        $config['options'] ?? []
                    ),
                    'image' => new \Yangweijie\Remotion\Layers\ImageLayer(
                        $config['path'],
                        $config['width'],
                        $config['height']
                    ),
                    default => throw new \InvalidArgumentException("Unknown layer type: {$config['type']}"),
                };
            }
        };
        
        return new Composition(
            id: $config->id,
            component: $component,
            durationInFrames: $config->durationInFrames,
            fps: $config->fps,
            width: $config->width,
            height: $config->height,
            defaultProps: $config->defaultProps,
        );
    }
    
    /**
     * 创建支持并行渲染的 Composition（使用模板）
     * 
     * 示例:
     * ```php
     * $composition = CompositionFactory::createParallelizable(
     *     id: 'my-animation',
     *     template: 'text-fade',
     *     templateParams: ['text' => 'Hello'],
     *     durationInFrames: 60,
     *     fps: 30,
     *     width: 640,
     *     height: 360,
     * );
     * ```
     */
    public static function createParallelizable(
        string $id,
        string $template,
        array $templateParams = [],
        int $durationInFrames = 60,
        int $fps = 30,
        int $width = 640,
        int $height = 360,
        array $defaultProps = [],
    ): Composition {
        if (!isset(self::$templateRegistry[$template])) {
            throw new \InvalidArgumentException("Template '{$template}' not found. Register it first.");
        }
        
        $factory = self::$templateRegistry[$template];
        $component = $factory($templateParams, $defaultProps);
        
        $composition = new Composition(
            id: $id,
            component: $component,
            durationInFrames: $durationInFrames,
            fps: $fps,
            width: $width,
            height: $height,
            defaultProps: $defaultProps,
        );
        
        // 存储配置以便后续序列化
        $composition->setConfig(new CompositionConfig(
            id: $id,
            durationInFrames: $durationInFrames,
            fps: $fps,
            width: $width,
            height: $height,
            rendererType: 'template',
            rendererConfig: [
                'template' => $template,
                'params' => $templateParams,
            ],
            defaultProps: $defaultProps,
        ));
        
        return $composition;
    }
    
    /**
     * 注册常用模板
     */
    public static function registerDefaultTemplates(): void
    {
        // 文本淡入模板
        self::registerTemplate('text-fade', function(array $params, array $props) {
            $text = $params['text'] ?? 'Hello';
            $fontSize = $params['fontSize'] ?? 5;
            
            return new class($text, $fontSize) implements ComponentInterface {
                private string $text;
                private int $fontSize;
                
                public function __construct(string $text, int $fontSize)
                {
                    $this->text = $text;
                    $this->fontSize = $fontSize;
                }
                
                public function render(RenderContext $ctx): \GdImage
                {
                    $frame = $ctx->getCurrentFrame();
                    $config = $ctx->getVideoConfig();
                    
                    $canvas = imagecreatetruecolor($config->width, $config->height);
                    $bgColor = imagecolorallocate($canvas, 20, 20, 50);
                    imagefill($canvas, 0, 0, $bgColor);
                    
                    // 淡入效果
                    $opacity = min(1.0, $frame / 30);
                    $white = imagecolorallocate($canvas, 255, 255, 255);
                    
                    $x = (int)($config->width / 2 - strlen($this->text) * $this->fontSize * 2);
                    $y = (int)($config->height / 2);
                    
                    imagestring($canvas, $this->fontSize, $x, $y, $this->text, $white);
                    
                    return $canvas;
                }
            };
        });
        
        // 颜色渐变模板
        self::registerTemplate('color-gradient', function(array $params, array $props) {
            $fromColor = $params['from'] ?? [255, 0, 0];
            $toColor = $params['to'] ?? [0, 0, 255];
            
            return new class($fromColor, $toColor) implements ComponentInterface {
                private array $fromColor;
                private array $toColor;
                
                public function __construct(array $fromColor, array $toColor)
                {
                    $this->fromColor = $fromColor;
                    $this->toColor = $toColor;
                }
                
                public function render(RenderContext $ctx): \GdImage
                {
                    $frame = $ctx->getCurrentFrame();
                    $config = $ctx->getVideoConfig();
                    $progress = $frame / $config->durationInFrames;
                    
                    $canvas = imagecreatetruecolor($config->width, $config->height);
                    
                    // 颜色插值
                    $r = (int)($this->fromColor[0] + ($this->toColor[0] - $this->fromColor[0]) * $progress);
                    $g = (int)($this->fromColor[1] + ($this->toColor[1] - $this->fromColor[1]) * $progress);
                    $b = (int)($this->fromColor[2] + ($this->toColor[2] - $this->fromColor[2]) * $progress);
                    
                    $color = imagecolorallocate($canvas, $r, $g, $b);
                    imagefill($canvas, 0, 0, $color);
                    
                    return $canvas;
                }
            };
        });
    }
}
