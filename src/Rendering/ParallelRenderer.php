<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Rendering;

use Psl\Async;
use Psl\Channel;
use Psl\Str;
use Yangweijie\Remotion\Core\Composition;
use Yangweijie\Remotion\Core\VideoConfig;

/**
 * 并行渲染器 - 基于 PSL Async + 多进程
 * 
 * 使用 PSL 的协程进行任务调度，使用多进程实现真正的并行渲染
 */
class ParallelRenderer
{
    private int $workerCount;
    private string $tempDir;
    private ?Async\CancellationTokenInterface $cancellationToken = null;
    
    public function __construct(int $workerCount = 4)
    {
        $this->workerCount = max(1, $workerCount);
        $this->tempDir = sys_get_temp_dir() . '/remotion_parallel_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }
    
    /**
     * 设置取消令牌
     */
    public function withCancellation(Async\CancellationTokenInterface $token): self
    {
        $this->cancellationToken = $token;
        return $this;
    }
    
    /**
     * 并行渲染到图片序列
     * 
     * @param Composition $composition 合成对象
     * @param string $outputDir 输出目录
     * @param string $format 图片格式 (png, jpg)
     * @param callable|null $onProgress 进度回调 (current, total)
     * @return array 生成的文件列表
     */
    public function renderToFrames(
        Composition $composition,
        string $outputDir,
        string $format = 'png',
        ?callable $onProgress = null
    ): array {
        $config = $composition->getVideoConfig();
        $totalFrames = $config->durationInFrames;
        
        // 创建通道用于进程间通信
        // PSL Channel\unbounded() 返回 [ReceiverInterface, SenderInterface]
        [$receiver, $sender] = Channel\unbounded();

        // 分块
        $chunks = $this->splitIntoChunks($totalFrames);

        // 使用 PSL concurrently 并行启动工作进程
        $tasks = [];
        foreach ($chunks as $index => $chunk) {
            $tasks[$index] = function() use ($composition, $chunk, $outputDir, $format, $index, $sender) {
                return $this->renderChunk($composition, $chunk, $outputDir, $format, $index, $sender);
            };
        }

        // 启动进度监控任务
        $progressTask = Async\run(function() use ($receiver, $totalFrames, $onProgress) {
            $completed = 0;
            foreach ($receiver as $count) {
                $completed += $count;
                if ($onProgress) {
                    $onProgress($completed, $totalFrames);
                }
                if ($completed >= $totalFrames) {
                    break;
                }
            }
        });
        
        try {
            // 并行执行所有渲染任务
            $results = Async\concurrently($tasks);
            
            // 等待进度监控完成
            $sender->close();
            $progressTask->await();
            
            // 合并结果
            $files = [];
            foreach ($results as $chunkFiles) {
                $files = array_merge($files, $chunkFiles);
            }
            
            ksort($files);
            return $files;
            
        } catch (Async\Exception\CancelledException $e) {
            // 清理已生成的文件
            $this->cleanup($outputDir);
            throw new RenderCancelledException('渲染已取消', 0, $e);
        }
    }
    
    /**
     * 并行渲染为 GIF
     */
    public function renderToGif(
        Composition $composition,
        string $outputPath,
        array $options = [],
        ?callable $onProgress = null
    ): bool {
        // 先并行渲染为图片序列
        $tempDir = $this->tempDir . '/frames_' . uniqid();
        mkdir($tempDir, 0755, true);
        
        try {
            $files = $this->renderToFrames($composition, $tempDir, 'png', $onProgress);
            
            // 使用现有 GIF 编码器
            $encoder = new AnimatedGifEncoder();
            $encoder->createFromFiles($files, $composition->getVideoConfig()->fps);
            file_put_contents($outputPath, $encoder->getGif());
            
            return true;
        } finally {
            // 清理临时文件
            $this->cleanup($tempDir);
        }
    }
    
    /**
     * 渲染单个分块（在子进程中执行）
     */
    private function renderChunk(
        Composition $composition,
        array $frames,
        string $outputDir,
        string $format,
        int $workerId,
        Channel\SenderInterface $progressChannel
    ): array {
        // 检查取消令牌
        if ($this->cancellationToken?->isCancelled()) {
            throw new Async\Exception\CancelledException();
        }
        
        $config = $composition->getVideoConfig();
        $generatedFiles = [];
        
        // 在子进程中渲染（使用 proc_open 实现真正的并行）
        $script = $this->createWorkerScript($composition, $frames, $outputDir, $format, $workerId);
        $scriptPath = $this->tempDir . "/worker_{$workerId}.php";
        file_put_contents($scriptPath, $script);
        
        // 使用 PSL 异步执行子进程
        $process = $this->runWorkerProcess($scriptPath);
        
        // 等待进程完成并收集结果
        $result = $process->await();
        
        if ($result['success']) {
            foreach ($frames as $frame) {
                $filename = sprintf('%s/frame_%04d.%s', $outputDir, $frame, $format);
                if (file_exists($filename)) {
                    $generatedFiles[$frame] = $filename;
                    $progressChannel->send(1);
                }
            }
        } else {
            throw new \RuntimeException("Worker {$workerId} failed: " . ($result['error'] ?? 'Unknown error'));
        }
        
        return $generatedFiles;
    }
    
    /**
     * 使用 PSL 异步运行工作进程
     */
    private function runWorkerProcess(string $scriptPath): Async\Awaitable
    {
        return Async\run(function() use ($scriptPath) {
            $descriptors = [
                1 => ['pipe', 'w'], // stdout
                2 => ['pipe', 'w'], // stderr
            ];
            
            $process = proc_open(
                ['php', $scriptPath],
                $descriptors,
                $pipes,
                null,
                ['PHP_MEMORY_LIMIT' => '512M']
            );
            
            if (!is_resource($process)) {
                return ['success' => false, 'error' => 'Failed to start worker process'];
            }
            
            // 异步读取输出
            $stdout = '';
            $stderr = '';
            
            while (true) {
                $status = proc_get_status($process);
                
                if (!$status['running']) {
                    break;
                }
                
                // 检查取消
                if ($this->cancellationToken?->isCancelled()) {
                    proc_terminate($process, SIGTERM);
                    throw new Async\Exception\CancelledException();
                }
                
                // 非阻塞读取
                $read = [$pipes[1], $pipes[2]];
                $write = null;
                $except = null;
                
                if (stream_select($read, $write, $except, 0, 100000) > 0) {
                    foreach ($read as $stream) {
                        $data = fread($stream, 8192);
                        if ($stream === $pipes[1]) {
                            $stdout .= $data;
                        } else {
                            $stderr .= $data;
                        }
                    }
                }
                
                // 让出控制权
                Async\later();
            }
            
            // 读取剩余输出
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);
            
            fclose($pipes[1]);
            fclose($pipes[2]);
            
            $exitCode = proc_close($process);
            
            return [
                'success' => $exitCode === 0,
                'stdout' => $stdout,
                'stderr' => $stderr,
                'exitCode' => $exitCode,
            ];
        });
    }
    
    /**
     * 将帧分割成多个块
     */
    private function splitIntoChunks(int $totalFrames): array
    {
        $chunkSize = (int) ceil($totalFrames / $this->workerCount);
        $chunks = [];
        
        for ($i = 0; $i < $totalFrames; $i += $chunkSize) {
            $chunks[] = range($i, min($i + $chunkSize - 1, $totalFrames - 1));
        }
        
        return $chunks;
    }
    
    /**
     * 创建工作进程脚本
     */
    private function createWorkerScript(
        Composition $composition,
        array $frames,
        string $outputDir,
        string $format,
        int $workerId
    ): string {
        $config = $composition->getConfig();

        if ($config === null) {
            throw new \RuntimeException(
                'Composition does not have a serializable config. ' .
                'Use CompositionFactory::createParallelizable() to create compositions for parallel rendering.'
            );
        }

        $configArray = $config->toArray();
        $framesArray = $frames;

        $configJson = json_encode($configArray, JSON_THROW_ON_ERROR);
        $framesJson = json_encode($framesArray, JSON_THROW_ON_ERROR);

        // 获取 vendor autoload 路径
        $autoloadPath = dirname(__DIR__, 2) . '/vendor/autoload.php';

        return <<<PHP
<?php
require_once '{$autoloadPath}';

use Yangweijie\Remotion\Core\CompositionConfig;
use Yangweijie\Remotion\Core\CompositionFactory;

// 重建配置
\$config = CompositionConfig::fromArray(json_decode('{$configJson}', true));
\$frames = json_decode('{$framesJson}', true);

// 注册默认模板
CompositionFactory::registerDefaultTemplates();

// 从配置重建 Composition
\$comp = CompositionFactory::createFromConfig(\$config);

foreach (\$frames as \$frame) {
    try {
        \$image = \$comp->renderFrame(\$frame);
        \$filename = sprintf('{$outputDir}/frame_%04d.{$format}', \$frame);

        if ('{$format}' === 'jpg' || '{$format}' === 'jpeg') {
            imagejpeg(\$image, \$filename, 95);
        } else {
            imagepng(\$image, \$filename, 6);
        }

        imagedestroy(\$image);
        echo "OK: \$frame\n";
    } catch (\Throwable \$e) {
        echo "Error: " . \$e->getMessage() . "\n";
        exit(1);
    }
}

exit(0);
PHP;
    }
    
    /**
     * 清理临时文件
     */
    private function cleanup(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        rmdir($dir);
    }
    
    public function __destruct()
    {
        // 清理临时目录
        if (is_dir($this->tempDir)) {
            $this->cleanup($this->tempDir);
        }
    }
}

/**
 * 渲染取消异常
 */
class RenderCancelledException extends \RuntimeException
{
}