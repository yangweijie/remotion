<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Rendering;

use Yangweijie\Remotion\Contracts\RendererInterface;
use Yangweijie\Remotion\Core\Composition;
use Yangweijie\Remotion\Core\RenderContext;
use Yangweijie\Remotion\Core\VideoConfig;

/**
 * Renderer
 *
 * 主渲染器，负责将 Composition 渲染为 GIF 动画或图像序列。
 * 整合 FrameRenderer（单帧渲染）和 GifEncoder（GIF 编码）。
 *
 * 使用示例：
 * ```php
 * $renderer = new Renderer($composition);
 * $renderer->renderToGif('/output/video.gif');
 * $renderer->renderToFrames('/output/frames/', 'png');
 * ```
 */
class Renderer implements RendererInterface
{
    /** @var callable|null */
    private $onProgress = null;

    public function __construct(
        private readonly Composition $composition,
    ) {}

    /**
     * 设置进度回调（每渲染一帧触发）
     *
     * @param callable $callback function(int $frame, int $total): void
     */
    public function onProgress(callable $callback): self
    {
        $this->onProgress = $callback;
        return $this;
    }

    /**
     * 渲染单帧（实现 RendererInterface）
     */
    public function renderFrame(RenderContext $ctx): \GdImage
    {
        return $this->composition->renderFrame($ctx->frame, $ctx->props);
    }

    /**
     * 渲染整个合成为 GIF 文件
     *
     * @param string $outputPath 输出路径（含 .gif 后缀）
     * @param array  $props      运行时 Props（覆盖 defaultProps）
     */
    public function renderToGif(string $outputPath, array $props = []): bool
    {
        $this->ensureDirectory(dirname($outputPath));

        $config    = $this->composition->getVideoConfig();
        $frames    = [];
        $delays    = [];
        $totalFrames = $config->durationInFrames;

        // GIF 帧延迟（单位：厘秒，即 10ms）
        $delayCs = (int) round(100 / $config->fps);

        echo "Rendering {$totalFrames} frames...\n";

        for ($frame = 0; $frame < $totalFrames; $frame++) {
            $image = $this->composition->renderFrame($frame, $props);

            // 将 GD 图像转换为 GIF 字节流
            ob_start();
            imagegif($image);
            $gifData = ob_get_clean();

            $frames[]  = $gifData;
            $delays[]  = $delayCs;

            imagedestroy($image);

            if ($this->onProgress) {
                ($this->onProgress)($frame + 1, $totalFrames);
            }
        }

        // 使用自定义 AnimatedGifEncoder（PHP 8.0+ 兼容）
        try {
            $encoder = new AnimatedGifEncoder(0); // 0 = 无限循环
            foreach ($frames as $idx => $gifData) {
                $encoder->addRawFrame($gifData, $delays[$idx]);
            }
            $encoder->saveToFile($outputPath);
            return true;
        } catch (\Throwable $e) {
            throw new \RuntimeException("Failed to create GIF: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 渲染整个合成为图像序列
     *
     * @param string $outputDir 输出目录
     * @param string $format    图像格式：png|jpeg|gif
     * @param array  $props     运行时 Props
     */
    public function renderToFrames(string $outputDir, string $format = 'png', array $props = []): bool
    {
        $this->ensureDirectory($outputDir);

        $config      = $this->composition->getVideoConfig();
        $totalFrames = $config->durationInFrames;
        $padLen      = strlen((string) ($totalFrames - 1));

        echo "Rendering {$totalFrames} frames to {$outputDir}...\n";

        for ($frame = 0; $frame < $totalFrames; $frame++) {
            $image    = $this->composition->renderFrame($frame, $props);
            $filename = $outputDir . DIRECTORY_SEPARATOR
                      . str_pad((string) $frame, $padLen, '0', STR_PAD_LEFT)
                      . '.' . $format;

            match ($format) {
                'jpeg', 'jpg' => imagejpeg($image, $filename, 95),
                'gif'         => imagegif($image, $filename),
                default       => imagepng($image, $filename),
            };

            imagedestroy($image);

            if ($this->onProgress) {
                ($this->onProgress)($frame + 1, $totalFrames);
            }
        }

        echo "Done! Frames saved to: {$outputDir}\n";
        return true;
    }

    /**
     * 渲染单帧并保存为文件
     *
     * @param int    $frame      帧号
     * @param string $outputPath 输出路径
     * @param string $format     格式：png|jpeg
     * @param array  $props      运行时 Props
     */
    public function renderFrameToFile(
        int $frame,
        string $outputPath,
        string $format = 'png',
        array $props = [],
    ): bool {
        $this->ensureDirectory(dirname($outputPath));

        $image = $this->composition->renderFrame($frame, $props);

        $result = match ($format) {
            'jpeg', 'jpg' => imagejpeg($image, $outputPath, 95),
            'gif'         => imagegif($image, $outputPath),
            default       => imagepng($image, $outputPath),
        };

        imagedestroy($image);
        return $result !== false;
    }

    /**
     * 获取合成信息
     */
    public function getComposition(): Composition
    {
        return $this->composition;
    }

    private function ensureDirectory(string $dir): void
    {
        if (!empty($dir) && !is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
