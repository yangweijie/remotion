<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Rendering;

use Yangweijie\Remotion\Contracts\RendererInterface;
use Yangweijie\Remotion\Core\CancellationToken;
use Yangweijie\Remotion\Core\Composition;
use Yangweijie\Remotion\Core\FrameCache;
use Yangweijie\Remotion\Core\RenderCancelledException;
use Yangweijie\Remotion\Core\RenderContext;
use Yangweijie\Remotion\Core\VideoConfig;
use GifCreator\GifCreator;
use Grafika\Grafika;

/**
 * Renderer
 *
 * 主渲染器，负责将 Composition 渲染为 GIF 动画或图像序列。
 * 整合 FrameRenderer（单帧渲染）和 GifEncoder（GIF 编码）。
 *
 * 支持 Grafika 抽象层（自动检测 GD 或 Imagick）
 * 支持帧缓存 (LRU) 和取消令牌
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

    /** @var FrameCache|null */
    private ?FrameCache $frameCache = null;

    /** @var CancellationToken|null */
    private ?CancellationToken $cancellationToken = null;

    /** @var array 质量配置 */
    private array $quality = [
        'jpeg' => 95,      // JPEG 质量 (0-100)
        'png'  => -1,      // PNG 压缩级别 (-1 = 默认, 0-9)
        'webp' => 80,      // WebP 质量 (0-100)
        'avif' => 80,      // AVIF 质量 (0-100)
    ];

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
     * 启用帧缓存
     *
     * @param int $capacity 缓存容量（默认30帧）
     */
    public function withCache(int $capacity = 30): self
    {
        $this->frameCache = new FrameCache($capacity);
        return $this;
    }

    /**
     * 设置取消令牌
     */
    public function withCancellation(CancellationToken $token): self
    {
        $this->cancellationToken = $token;
        return $this;
    }

    /**
     * 获取帧缓存实例
     */
    public function getFrameCache(): ?FrameCache
    {
        return $this->frameCache;
    }

    /**
     * 设置 JPEG 质量
     *
     * @param int $quality 质量 (0-100，默认 95)
     */
    public function withJpegQuality(int $quality): self
    {
        $this->quality['jpeg'] = max(0, min(100, $quality));
        return $this;
    }

    /**
     * 设置 PNG 压缩级别
     *
     * @param int $level 压缩级别 (-1=默认, 0=无压缩, 9=最大压缩)
     */
    public function withPngCompression(int $level): self
    {
        $this->quality['png'] = max(-1, min(9, $level));
        return $this;
    }

    /**
     * 设置 WebP 质量
     *
     * @param int $quality 质量 (0-100，默认 80)
     */
    public function withWebpQuality(int $quality): self
    {
        $this->quality['webp'] = max(0, min(100, $quality));
        return $this;
    }

    /**
     * 设置 AVIF 质量
     *
     * @param int $quality 质量 (0-100，默认 80)
     */
    public function withAvifQuality(int $quality): self
    {
        $this->quality['avif'] = max(0, min(100, $quality));
        return $this;
    }

    /**
     * 批量设置质量参数
     *
     * @param array{
     *     jpeg?: int,
     *     png?: int,
     *     webp?: int,
     *     avif?: int,
     * } $quality
     */
    public function withQuality(array $quality): self
    {
        if (isset($quality['jpeg'])) {
            $this->withJpegQuality($quality['jpeg']);
        }
        if (isset($quality['png'])) {
            $this->withPngCompression($quality['png']);
        }
        if (isset($quality['webp'])) {
            $this->withWebpQuality($quality['webp']);
        }
        if (isset($quality['avif'])) {
            $this->withAvifQuality($quality['avif']);
        }
        return $this;
    }

    /**
     * 获取当前质量配置
     */
    public function getQuality(): array
    {
        return $this->quality;
    }

    /**
     * 渲染单帧（实现 RendererInterface）
     */
    public function renderFrame(RenderContext $ctx): \GdImage
    {
        return $this->composition->renderFrame($ctx->frame, $ctx->props);
    }

    /**
     * 渲染指定帧（带缓存支持）
     */
    public function renderFrameWithCache(int $frame, array $props = []): \GdImage
    {
        // 检查取消令牌
        $this->cancellationToken?->throwIfCancelled();

        if ($this->frameCache !== null) {
            return $this->frameCache->get($frame, function ($f) use ($props) {
                return $this->composition->renderFrame($f, $props);
            });
        }

        return $this->composition->renderFrame($frame, $props);
    }

    /**
     * 渲染单帧为 Grafika 图像
     */
    public function renderFrameAsImage(int $frame, array $props = []): \Grafika\ImageInterface
    {
        $gd = $this->composition->renderFrame($frame, $props);
        return $this->gdToImage($gd);
    }

    /**
     * 渲染整个合成为 GIF 文件
     *
     * @param string $outputPath 输出路径（含 .gif 后缀）
     * @param array  $props      运行时 Props（覆盖 defaultProps）
     * @throws RenderCancelledException
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
            // 检查取消令牌
            $this->cancellationToken?->throwIfCancelled();

            $image = $this->renderFrameWithCache($frame, $props);

            // 将 GD 图像转换为 GIF 字节流
            ob_start();
            imagegif($image);
            $gifData = ob_get_clean();

            $frames[]  = $gifData;
            $delays[]  = $delayCs;

            // 如果启用了缓存，不要在这里销毁图像（缓存会管理）
            if ($this->frameCache === null) {
                imagedestroy($image);
            }

            if ($this->onProgress) {
                ($this->onProgress)($frame + 1, $totalFrames);
            }
        }

        // 清空缓存（因为 GIF 需要重新读取图像数据）
        $this->frameCache?->clear();

        // 使用修复后的 GifCreator（PHP 8.0+ 兼容）
        try {
            $creator = new GifCreator();
            
            // 将 GD 图像数据转换回 GD 资源/GdImage 对象
            $gdFrames = [];
            foreach ($frames as $gifData) {
                $gdFrames[] = imagecreatefromstring($gifData);
            }
            
            $creator->create($gdFrames, $delays, 0); // 0 = 无限循环
            
            // 写入文件
            file_put_contents($outputPath, $creator->getGif());
            
            // 清理 GD 资源
            foreach ($gdFrames as $img) {
                imagedestroy($img);
            }
            
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
     * @throws RenderCancelledException
     */
    public function renderToFrames(string $outputDir, string $format = 'png', array $props = []): bool
    {
        $this->ensureDirectory($outputDir);

        $config      = $this->composition->getVideoConfig();
        $totalFrames = $config->durationInFrames;
        $padLen      = strlen((string) ($totalFrames - 1));

        echo "Rendering {$totalFrames} frames to {$outputDir}...\n";

        for ($frame = 0; $frame < $totalFrames; $frame++) {
            // 检查取消令牌
            $this->cancellationToken?->throwIfCancelled();

            $image    = $this->renderFrameWithCache($frame, $props);
            $filename = $outputDir . DIRECTORY_SEPARATOR
                      . str_pad((string) $frame, $padLen, '0', STR_PAD_LEFT)
                      . '.' . $format;

            match ($format) {
                'jpeg', 'jpg' => imagejpeg($image, $filename, $this->quality['jpeg']),
                'gif'         => imagegif($image, $filename),
                'png'         => $this->quality['png'] >= 0
                    ? imagepng($image, $filename, $this->quality['png'])
                    : imagepng($image, $filename),
                'webp'        => function_exists('imagewebp')
                    ? imagewebp($image, $filename, $this->quality['webp'])
                    : imagepng($image, $filename),
                'avif'        => function_exists('imageavif')
                    ? imageavif($image, $filename, $this->quality['avif'])
                    : imagepng($image, $filename),
                default       => imagepng($image, $filename),
            };

            // 如果启用了缓存，不要在这里销毁图像（缓存会管理）
            if ($this->frameCache === null) {
                imagedestroy($image);
            }

            if ($this->onProgress) {
                ($this->onProgress)($frame + 1, $totalFrames);
            }
        }

        // 清理缓存
        $this->frameCache?->clear();

        echo "Done! Frames saved to: {$outputDir}\n";
        return true;
    }

    /**
     * 渲染整个合成为图像序列（内存优化版）
     *
     * 与 renderToFrames 相比，此方法：
     * - 每帧渲染后立即释放 GD 资源
     * - 定期执行垃圾回收
     * - 使用更小的内存缓冲
     *
     * @param string $outputDir 输出目录
     * @param string $format    图像格式：png|jpeg|gif
     * @param array  $props     运行时 Props
     * @param int    $gcInterval 垃圾回收间隔（帧数）
     */
    public function renderToFramesMemoryOptimized(
        string $outputDir,
        string $format = 'png',
        array $props = [],
        int $gcInterval = 10
    ): bool {
        $this->ensureDirectory($outputDir);

        $config      = $this->composition->getVideoConfig();
        $totalFrames = $config->durationInFrames;
        $padLen      = strlen((string) ($totalFrames - 1));

        echo "Rendering {$totalFrames} frames (memory optimized)...\n";

        // 保存原始进度回调
        $originalCallback = $this->onProgress;
        
        // 使用局部变量追踪进度
        $lastReportTime = microtime(true);
        $reportInterval = 1.0; // 每秒报告一次

        for ($frame = 0; $frame < $totalFrames; $frame++) {
            // 渲染帧
            $image = $this->composition->renderFrame($frame, $props);

            // 立即保存到磁盘
            $filename = $outputDir . DIRECTORY_SEPARATOR
                      . str_pad((string) $frame, $padLen, '0', STR_PAD_LEFT)
                      . '.' . $format;

            match ($format) {
                'jpeg', 'jpg' => imagejpeg($image, $filename, $this->quality['jpeg']),
                'gif'         => imagegif($image, $filename),
                'png'         => $this->quality['png'] >= 0
                    ? imagepng($image, $filename, $this->quality['png'])
                    : imagepng($image, $filename),
                'webp'        => function_exists('imagewebp')
                    ? imagewebp($image, $filename, $this->quality['webp'])
                    : imagepng($image, $filename),
                'avif'        => function_exists('imageavif')
                    ? imageavif($image, $filename, $this->quality['avif'])
                    : imagepng($image, $filename),
                default       => imagepng($image, $filename),
            };

            // 立即销毁图像资源
            imagedestroy($image);
            unset($image);

            // 定期垃圾回收
            if ($gcInterval > 0 && ($frame + 1) % $gcInterval === 0) {
                gc_collect_cycles();
            }

            // 进度回调（限流）
            if ($originalCallback) {
                $now = microtime(true);
                if ($now - $lastReportTime >= $reportInterval || $frame === $totalFrames - 1) {
                    $originalCallback($frame + 1, $totalFrames);
                    $lastReportTime = $now;
                }
            }
        }

        // 最终垃圾回收
        gc_collect_cycles();

        echo "Done! Frames saved to: {$outputDir}\n";
        return true;
    }

    /**
     * 使用子进程渲染帧（最大内存优化）
     *
     * 每帧在独立子进程中渲染，完成后内存完全释放。
     * 适用于超长视频或内存受限环境。
     *
     * @param string $outputDir    输出目录
     * @param string $format       图像格式
     * @param int    $totalFrames  总帧数
     * @param int    $batchSize    每批帧数（0=每帧一个进程）
     * @return bool
     */
    public function renderToFramesIsolated(
        string $outputDir,
        string $format = 'gif',
        int $batchSize = 10
    ): bool {
        $this->ensureDirectory($outputDir);

        $config      = $this->composition->getVideoConfig();
        $totalFrames = $config->durationInFrames;
        $padLen      = strlen((string) ($totalFrames - 1));

        // 获取合成定义文件路径
        $rootFile = $this->findRootFile();
        if (!$rootFile) {
            // 回退到内存优化方法
            return $this->renderToFramesMemoryOptimized($outputDir, $format, [], 10);
        }

        echo "Rendering {$totalFrames} frames (isolated process mode)...\n";

        $batchCount = $batchSize > 0 ? ceil($totalFrames / $batchSize) : $totalFrames;

        for ($batch = 0; $batch < $batchCount; $batch++) {
            $startFrame = $batch * ($batchSize > 0 ? $batchSize : 1);
            $endFrame   = min($startFrame + ($batchSize > 0 ? $batchSize : 1), $totalFrames);

            // 构建子进程命令
            $cmd = sprintf(
                'php -r \'%s\' 2>&1',
                $this->buildRenderScript($outputDir, $format, $startFrame, $endFrame, $rootFile, $padLen)
            );

            exec($cmd, $output, $returnCode);

            if ($returnCode !== 0) {
                echo "Batch {$batch} failed\n";
                return false;
            }

            // 子进程结束后内存完全释放
            gc_collect_cycles();

            // 进度报告
            $completed = min($endFrame, $totalFrames);
            if ($this->onProgress) {
                ($this->onProgress)($completed, $totalFrames);
            }
        }

        echo "Done! Frames saved to: {$outputDir}\n";
        return true;
    }

    /**
     * 构建子进程渲染脚本
     */
    private function buildRenderScript(
        string $outputDir,
        string $format,
        int $startFrame,
        int $endFrame,
        string $rootFile,
        int $padLen
    ): string {
        // 需要转义的代码
        $code = <<< 'PHP'
            require_once '%s';
            
            use Yangweijie\Remotion\Remotion;
            use Yangweijie\Remotion\Rendering\Renderer;
            
            // 注册合成
            Remotion::registerRootFile('%s');
            $compositions = Remotion::getRoot();
            $comp = $compositions[0] ?? null;
            
            if (!$comp) {
                exit(1);
            }
            
            $renderer = new Renderer($comp);
            $config = $comp->getVideoConfig();
            
            for ($frame = %d; $frame < %d; $frame++) {
                $image = $comp->renderFrame($frame);
                $filename = '%s' . DIRECTORY_SEPARATOR
                    . str_pad((string) $frame, %d, '0', STR_PAD_LEFT)
                    . '.%s';
                
                match ('%s') {
                    'jpeg', 'jpg' => imagejpeg($image, $filename, %d),
                    'gif'         => imagegif($image, $filename),
                    'png'         => %d >= 0 ? imagepng($image, $filename, %d) : imagepng($image, $filename),
                    default       => imagepng($image, $filename),
                };
                
                imagedestroy($image);
            }
PHP;

        return sprintf(
            $code,
            addslashes(realpath(__DIR__ . '/../../vendor/autoload.php')),
            addslashes($rootFile),
            $startFrame,
            $endFrame,
            addslashes($outputDir),
            $padLen,
            $format,
            $this->quality['jpeg'],
            $this->quality['png'],
            $this->quality['png']
        );
    }

    /**
     * 查找根文件
     */
    private function findRootFile(): ?string
    {
        // 检查是否设置了根文件
        if (method_exists(Remotion::class, 'getRootFile')) {
            $rootFile = Remotion::getRootFile();
            if ($rootFile && file_exists($rootFile)) {
                return $rootFile;
            }
        }
        return null;
    }

    /**
     * 流式 GIF 编码（最低内存占用）
     *
     * 直接将 GIF 数据写入文件，不累积帧数据在内存中。
     * 内存占用恒定，仅相当于一帧的大小。
     *
     * @param string $outputPath 输出文件路径
     * @param array  $props      运行时 Props
     */
    public function renderToGifStreaming(string $outputPath, array $props = []): bool
    {
        $this->ensureDirectory(dirname($outputPath));

        $config      = $this->composition->getVideoConfig();
        $totalFrames = $config->durationInFrames;
        $fps         = $config->fps;
        $width       = $config->width;
        $height      = $config->height;

        // GIF 延迟（厘秒）
        $delayCs = (int) round(100 / $fps);

        echo "Rendering {$totalFrames} frames (streaming mode)...\n";

        // 打开文件句柄
        $fp = fopen($outputPath, 'wb');
        if (!$fp) {
            throw new \RuntimeException("Cannot open file for writing: {$outputPath}");
        }

        try {
            // 写入 GIF 头部
            fwrite($fp, $this->createGifHeader($width, $height));

            // 创建全局调色板（从第一帧提取）
            $firstFrame = $this->composition->renderFrame(0, $props);
            $globalColorTable = $this->extractColorTable($firstFrame);
            imagedestroy($firstFrame);

            // 写入全局颜色表
            fwrite($fp, $globalColorTable);

            // 逐帧编码
            for ($frame = 0; $frame < $totalFrames; $frame++) {
                $image = $this->composition->renderFrame($frame, $props);

                // 写入图形控制扩展（延迟）
                fwrite($fp, $this->createGraphicControlExtension($delayCs));

                // 写入图像描述符和图像数据
                fwrite($fp, $this->createImageDescriptor($width, $height));
                fwrite($fp, $this->encodeFrameData($image, $width, $height));

                // 立即销毁图像
                imagedestroy($image);
                unset($image);

                // 定期垃圾回收
                if (($frame + 1) % 10 === 0) {
                    gc_collect_cycles();
                }

                if ($this->onProgress && ($frame + 1) % 30 === 0) {
                    ($this->onProgress)($frame + 1, $totalFrames);
                }
            }

            // 写入 GIF 结束标记
            fwrite($fp, "\x3B");

        } finally {
            fclose($fp);
        }

        echo "Done! GIF saved to: {$outputPath}\n";
        return true;
    }

    /**
     * 创建 GIF 头部
     */
    private function createGifHeader(int $width, int $height): string
    {
        // GIF89a 签名
        $header = "GIF89a";

        // 逻辑屏幕描述符
        $header .= pack('v', $width);   // 宽度
        $header .= pack('v', $height);  // 高度
        $header .= "\xF7";              // 全局颜色表标志 + 颜色分辨率 + 排序标志 + 颜色表大小 (256色)
        $header .= "\x00";              // 背景颜色索引
        $header .= "\x00";              // 像素宽高比

        return $header;
    }

    /**
     * 从图像提取颜色表
     */
    private function extractColorTable(\GdImage $image): string
    {
        // 简化：使用标准 256 色调色板
        $colorTable = '';
        for ($i = 0; $i < 256; $i++) {
            // 生成均匀分布的颜色
            $r = (int) (($i >> 5) * 36);
            $g = (int) ((($i >> 2) & 0x07) * 36);
            $b = (int) (($i & 0x03) * 85);
            $colorTable .= chr($r) . chr($g) . chr($b);
        }
        return $colorTable;
    }

    /**
     * 创建图形控制扩展
     */
    private function createGraphicControlExtension(int $delayCs): string
    {
        $ext = "\x21\xF9\x04";  // 扩展引入符 + 图形控制标签 + 块大小
        $ext .= "\x00";          // 处置方法 + 用户输入标志 + 透明色标志
        $ext .= pack('v', $delayCs);  // 延迟时间
        $ext .= "\x00";          // 透明色索引
        return $ext;
    }

    /**
     * 创建图像描述符
     */
    private function createImageDescriptor(int $width, int $height): string
    {
        $desc = "\x2C";           // 图像分隔符
        $desc .= "\x00\x00";      // 左边位置
        $desc .= "\x00\x00";      // 顶部位置
        $desc .= pack('v', $width);   // 宽度
        $desc .= pack('v', $height);  // 高度
        $desc .= "\x87";          // 局部颜色表标志 + 交错标志 + 排序标志 + 颜色表大小
        return $desc;
    }

    /**
     * 编码帧数据（LZW 压缩）
     */
    private function encodeFrameData(\GdImage $image, int $width, int $height): string
    {
        // 使用 GD 内置 GIF 编码，只取图像数据部分
        ob_start();
        imagegif($image);
        $gifData = ob_get_clean();

        // 提取图像数据部分（跳过头部）
        // 找到第一个图像描述符 (0x2C)
        $imageStart = strpos($gifData, "\x2C");
        if ($imageStart !== false) {
            // 跳过图像描述符（10字节）后的数据
            $dataStart = $imageStart + 10;
            // 找到结束标记
            $dataEnd = strpos($gifData, "\x3B", $dataStart);
            if ($dataEnd !== false) {
                return substr($gifData, $dataStart, $dataEnd - $dataStart);
            }
            // 如果没有结束标记，取剩余部分
            return substr($gifData, $dataStart);
        }

        // 回退：直接返回 GIF 数据（会导致文件稍大）
        return $gifData;
    }

    /**
     * 渲染整个合成为图像序列（使用 Grafika）
     *
     * @param string $outputDir 输出目录
     * @param string $format    图像格式：png|jpeg|gif
     * @param array  $props     运行时 Props
     * @param int    $quality   JPEG 质量 (0-100)
     */
    public function renderToFramesWithGrafika(
        string $outputDir, 
        string $format = 'png', 
        array $props = [],
        int $quality = 95
    ): bool {
        $this->ensureDirectory($outputDir);

        $config      = $this->composition->getVideoConfig();
        $totalFrames = $config->durationInFrames;
        $padLen      = strlen((string) ($totalFrames - 1));
        $editor      = Grafika::createEditor();

        echo "Rendering {$totalFrames} frames to {$outputDir} (using Grafika)...\n";

        for ($frame = 0; $frame < $totalFrames; $frame++) {
            $gdImage  = $this->composition->renderFrame($frame, $props);
            $image    = $this->gdToImage($gdImage);
            $filename = $outputDir . DIRECTORY_SEPARATOR
                      . str_pad((string) $frame, $padLen, '0', STR_PAD_LEFT)
                      . '.' . $format;

            $type = match ($format) {
                'jpeg', 'jpg' => 'jpeg',
                'gif'         => 'gif',
                default       => 'png',
            };

            $editor->save($image, $filename, $type, $format === 'jpeg' || $format === 'jpg' ? $quality : null);

            imagedestroy($gdImage);

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
            'jpeg', 'jpg' => imagejpeg($image, $outputPath, $this->quality['jpeg']),
            'gif'         => imagegif($image, $outputPath),
            'png'         => $this->quality['png'] >= 0
                ? imagepng($image, $outputPath, $this->quality['png'])
                : imagepng($image, $outputPath),
            'webp'        => function_exists('imagewebp')
                ? imagewebp($image, $outputPath, $this->quality['webp'])
                : imagepng($image, $outputPath),
            'avif'        => function_exists('imageavif')
                ? imageavif($image, $outputPath, $this->quality['avif'])
                : imagepng($image, $outputPath),
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

    // ============================================================
    // 优化的 GIF 渲染方法（使用外部工具）
    // ============================================================

    /**
     * 使用外部工具优化渲染 GIF（推荐用于长视频）
     *
     * 优先级：gifsicle > ffmpeg > GD 回退
     *
     * @param string $outputPath 输出路径
     * @param array  $props      运行时 Props
     * @param array  $options    优化选项
     *                           - lossy: int (0-200) 有损压缩级别，0=无损
     *                           - colors: int (2-256) 颜色数
     *                           - optimize: int (1-3) 优化级别
     *                           - cleanup: bool 是否清理临时文件
     */
    public function renderToGifOptimized(
        string $outputPath,
        array $props = [],
        array $options = []
    ): bool {
        $this->ensureDirectory(dirname($outputPath));

        // 合并默认选项
        $options = array_merge([
            'lossy' => 0,
            'colors' => 256,
            'optimize' => 3,
            'cleanup' => true,
        ], $options);

        // 检测可用工具
        $tool = $this->detectGifTool();

        if ($tool === 'gifsicle') {
            return $this->renderWithGifsicle($outputPath, $props, $options);
        }

        if ($tool === 'ffmpeg') {
            return $this->renderWithFfmpeg($outputPath, $props, $options);
        }

        // 回退到 GD 方式
        echo "No external GIF tool found, falling back to GD...\n";
        return $this->renderToGif($outputPath, $props);
    }

    /**
     * 检测可用的 GIF 工具
     *
     * @return string|null 'gifsicle'|'ffmpeg'|null
     */
    public function detectGifTool(): ?string
    {
        // 优先检查 gifsicle
        if ($this->commandExists('gifsicle')) {
            return 'gifsicle';
        }

        // 次选 ffmpeg
        if ($this->commandExists('ffmpeg')) {
            return 'ffmpeg';
        }

        return null;
    }

    /**
     * 使用 gifsicle 渲染 GIF
     */
    private function renderWithGifsicle(
        string $outputPath,
        array $props,
        array $options
    ): bool {
        $tempDir = $this->createTempDir('gifsicle_');
        $config = $this->composition->getVideoConfig();

        // 1. 渲染帧序列到临时目录 (使用内存优化方法)
        echo "Rendering {$config->durationInFrames} frames to temp directory...\n";
        $this->renderToFramesMemoryOptimized($tempDir, 'gif', $props, 10);

        // 2. 计算 GIF 参数
        $delay = (int) round(100 / $config->fps); // gifsicle 使用厘秒

        // 3. 构建 gifsicle 命令
        $outputEsc = escapeshellarg($outputPath);

        $cmd = sprintf(
            'gifsicle --delay=%d --loop',
            $delay
        );

        // 添加优化选项
        if ($options['optimize'] >= 1) {
            $cmd .= sprintf(' -O%d', $options['optimize']);
        }

        if ($options['colors'] < 256) {
            $cmd .= sprintf(' --colors=%d', $options['colors']);
        }

        if ($options['lossy'] > 0) {
            $cmd .= sprintf(' --lossy=%d', $options['lossy']);
        }

        // 添加输出选项和帧文件
        $framesPath = $tempDir . DIRECTORY_SEPARATOR . '*.gif';
        $cmd .= sprintf(' -o %s %s', $outputEsc, $framesPath);

        echo "Running gifsicle...\n";
        exec($cmd . ' 2>&1', $output, $returnCode);

        // 4. 清理临时文件
        if ($options['cleanup']) {
            $this->cleanupDirectory($tempDir);
        }

        if ($returnCode !== 0) {
            echo "gifsicle failed with code $returnCode\n";
            echo implode("\n", $output) . "\n";
            return false;
        }

        $fileSize = $this->formatBytes(filesize($outputPath));
        echo "GIF created: $outputPath ($fileSize)\n";

        return true;
    }

    /**
     * 使用 ffmpeg 渲染 GIF（备选方案）
     */
    private function renderWithFfmpeg(
        string $outputPath,
        array $props,
        array $options
    ): bool {
        $tempDir = $this->createTempDir('ffmpeg_');
        $config = $this->composition->getVideoConfig();

        // 1. 渲染帧序列 (使用内存优化方法)
        echo "Rendering {$config->durationInFrames} frames to temp directory...\n";
        $this->renderToFramesMemoryOptimized($tempDir, 'png', $props, 10);

        // 2. 使用 ffmpeg 合成 GIF
        // 使用帧序列模式
        $framesPattern = escapeshellarg($tempDir . '/%04d.png');
        $outputEsc = escapeshellarg($outputPath);

        // 计算 fps 和滤镜
        $fps = $config->fps;
        $colors = $options['colors'];

        // 使用 ffmpeg 的 palette 模式获得更好的质量
        $palettePath = $tempDir . '/palette.png';

        // 第一步：生成调色板
        $paletteCmd = sprintf(
            'ffmpeg -framerate %d -i %s -vf "palettegen=max_colors=%d:stats_mode=diff" -y %s 2>/dev/null',
            $fps,
            $framesPattern,
            $colors,
            escapeshellarg($palettePath)
        );

        echo "Generating palette...\n";
        exec($paletteCmd, $output1, $return1);

        // 第二步：使用调色板生成 GIF
        $gifCmd = sprintf(
            'ffmpeg -framerate %d -i %s -i %s -lavfi "paletteuse=dither=bayer:bayer_scale=5:diff_mode=rectangle" -y %s 2>/dev/null',
            $fps,
            $framesPattern,
            escapeshellarg($palettePath),
            $outputEsc
        );

        echo "Encoding GIF with ffmpeg...\n";
        exec($gifCmd, $output2, $returnCode);

        // 3. 清理
        if ($options['cleanup']) {
            $this->cleanupDirectory($tempDir);
        }

        if ($returnCode !== 0) {
            echo "ffmpeg failed\n";
            return false;
        }

        $fileSize = $this->formatBytes(filesize($outputPath));
        echo "GIF created: $outputPath ($fileSize)\n";

        return true;
    }

    /**
     * 创建临时目录
     */
    private function createTempDir(string $prefix = 'remotion_'): string
    {
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . uniqid();
        mkdir($tempDir, 0755, true);
        return $tempDir;
    }

    /**
     * 清理临时目录
     */
    private function cleanupDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_dir($file)) {
                $this->cleanupDirectory($file);
            } else {
                unlink($file);
            }
        }
        rmdir($dir);
    }

    /**
     * 检查命令是否存在
     */
    private function commandExists(string $command): bool
    {
        $result = shell_exec(sprintf('which %s 2>/dev/null', escapeshellarg($command)));
        return !empty(trim((string) $result));
    }

    /**
     * 格式化字节数
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * 将 GD 图像转换为 Grafika 图像
     */
    private function gdToImage(\GdImage $gd): \Grafika\ImageInterface
    {
        $editorName = Grafika::detectAvailableEditor();
        
        if ($editorName === 'Imagick') {
            // GD 转 Imagick
            ob_start();
            imagepng($gd);
            $blob = ob_get_clean();
            $imagick = new \Imagick();
            $imagick->readImageBlob($blob);
            return \Grafika\Imagick\Image::createFromCore($imagick);
        } else {
            return \Grafika\Gd\Image::createFromCore($gd);
        }
    }

    private function ensureDirectory(string $dir): void
    {
        if (!empty($dir) && !is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
