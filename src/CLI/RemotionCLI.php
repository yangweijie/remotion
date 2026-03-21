<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\CLI;

use Minicli\App;
use Minicli\Command\CommandCall;
use Yangweijie\Remotion\Core\Composition;
use Yangweijie\Remotion\Rendering\Renderer;

/**
 * CLI Application
 *
 * 基于 minicli 的命令行界面，对标 remotion CLI 工具。
 * 提供渲染、预览、列出合成等命令。
 *
 * 使用方式：
 * ```
 * php bin/remotion render <compositionId> --output=output.gif
 * php bin/remotion list
 * php bin/remotion render-frames <compositionId> --dir=frames/ --format=png
 * ```
 */
class RemotionCLI
{
    private App $app;

    public function __construct()
    {
        $this->app = new App([
            'app_path' => [
                __DIR__ . '/Commands',
            ],
            'debug' => false,
        ]);

        $this->registerCommands();
    }

    /**
     * 注册所有内置命令
     */
    private function registerCommands(): void
    {
        // help 命令
        $this->app->registerCommand('help', function (CommandCall $input): void {
            $this->printBanner();
            echo "\nAvailable commands:\n";
            echo "  render         Render a composition to GIF\n";
            echo "  render-frames  Render a composition to image frames\n";
            echo "  list           List all registered compositions\n";
            echo "  info           Show info about a composition\n";
            echo "\nExamples:\n";
            echo "  php bin/remotion render MyComp --output=video.gif --root=bootstrap.php\n";
            echo "  php bin/remotion render-frames MyComp --dir=frames/ --format=png\n";
            echo "  php bin/remotion list --root=bootstrap.php\n\n";
        });

        // list 命令：列出所有注册的合成
        $this->app->registerCommand('list', function (CommandCall $input): void {
            $this->loadRoot($input);
            $compositions = Composition::all();

            if (empty($compositions)) {
                echo "No compositions registered.\n";
                echo "Make sure to register your compositions using \$composition->register();\n";
                return;
            }

            echo "\nRegistered Compositions:\n";
            echo str_repeat('-', 60) . "\n";
            foreach ($compositions as $id => $comp) {
                $cfg = $comp->getVideoConfig();
                printf(
                    "  %-20s %dx%d @ %dfps, %d frames (%.2fs)\n",
                    $id,
                    $cfg->width,
                    $cfg->height,
                    $cfg->fps,
                    $cfg->durationInFrames,
                    $cfg->getDurationInSeconds()
                );
            }
            echo "\n";
        });

        // info 命令：显示合成详情
        $this->app->registerCommand('info', function (CommandCall $input): void {
            $this->loadRoot($input);
            $id = $input->args[2] ?? null;

            if (!$id) {
                echo "Usage: remotion info <compositionId>\n";
                return;
            }

            $comp = Composition::get($id);
            if (!$comp) {
                echo "Error: Composition '{$id}' not found.\n";
                return;
            }

            $cfg = $comp->getVideoConfig();
            echo "\nComposition: {$id}\n";
            echo str_repeat('-', 40) . "\n";
            echo "  Width:            {$cfg->width}px\n";
            echo "  Height:           {$cfg->height}px\n";
            echo "  FPS:              {$cfg->fps}\n";
            echo "  Duration:         {$cfg->durationInFrames} frames\n";
            echo "  Duration (secs):  " . round($cfg->getDurationInSeconds(), 2) . "s\n";
            echo "  Sequences:        " . count($comp->getSequences()) . "\n";

            if (!empty($comp->defaultProps)) {
                echo "  Default Props:\n";
                foreach ($comp->defaultProps as $k => $v) {
                    echo "    {$k}: " . json_encode($v) . "\n";
                }
            }
            echo "\n";
        });

        // render 命令：渲染合成为 GIF
        $this->app->registerCommand('render', function (CommandCall $input): void {
            $this->loadRoot($input);
            $id = $input->args[2] ?? null;

            if (!$id) {
                echo "Usage: remotion render <compositionId> [--output=output.gif] [--loop=0]\n";
                return;
            }

            $comp = Composition::get($id);
            if (!$comp) {
                echo "Error: Composition '{$id}' not found.\n";
                return;
            }

            $output = $input->getParam('output') ?? "{$id}.gif";
            $props  = [];

            $renderer = new Renderer($comp);
            $renderer->onProgress(function (int $frame, int $total) {
                $pct = (int) round($frame / $total * 100);
                echo "\r  Rendering frame {$frame}/{$total} ({$pct}%)...";
            });

            echo "\nRendering '{$id}' to {$output}...\n";
            $start = microtime(true);
            $renderer->renderToGif($output, $props);
            $elapsed = round(microtime(true) - $start, 2);

            echo "\n  Done! Saved to: {$output} ({$elapsed}s)\n\n";
        });

        // render-frames 命令：渲染合成为图像序列
        $this->app->registerCommand('render-frames', function (CommandCall $input): void {
            $this->loadRoot($input);
            $id = $input->args[2] ?? null;

            if (!$id) {
                echo "Usage: remotion render-frames <compositionId> [--dir=frames/] [--format=png]\n";
                return;
            }

            $comp = Composition::get($id);
            if (!$comp) {
                echo "Error: Composition '{$id}' not found.\n";
                return;
            }

            $dir    = $input->getParam('dir')    ?? "{$id}_frames";
            $format = $input->getParam('format') ?? 'png';

            $renderer = new Renderer($comp);
            $renderer->onProgress(function (int $frame, int $total) {
                echo "\r  Rendering frame {$frame}/{$total}...";
            });

            echo "\nRendering '{$id}' frames to {$dir}/...\n";
            $start = microtime(true);
            $renderer->renderToFrames($dir, $format);
            $elapsed = round(microtime(true) - $start, 2);

            echo "\n  Done! ({$elapsed}s)\n\n";
        });
    }

    /**
     * 加载用户的根文件（包含 Composition 注册代码）
     */
    private function loadRoot(CommandCall $input): void
    {
        $root = $input->getParam('root') ?? 'remotion.php';

        if (file_exists($root)) {
            require_once $root;
        } elseif (file_exists(getcwd() . '/' . $root)) {
            require_once getcwd() . '/' . $root;
        }
    }

    private function printBanner(): void
    {
        echo "\n";
        echo "  ██████╗ ███████╗███╗   ███╗ ██████╗ ████████╗██╗ ██████╗ ███╗   ██╗  PHP\n";
        echo "  ██╔══██╗██╔════╝████╗ ████║██╔═══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║\n";
        echo "  ██████╔╝█████╗  ██╔████╔██║██║   ██║   ██║   ██║██║   ██║██╔██╗ ██║\n";
        echo "  ██╔══██╗██╔══╝  ██║╚██╔╝██║██║   ██║   ██║   ██║██║   ██║██║╚██╗██║\n";
        echo "  ██║  ██║███████╗██║ ╚═╝ ██║╚██████╔╝   ██║   ██║╚██████╔╝██║ ╚████║\n";
        echo "  ╚═╝  ╚═╝╚══════╝╚═╝     ╚═╝ ╚═════╝    ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝\n";
        echo "  PHP Port of Remotion - Programmatic Animation/Video Composition\n";
    }

    /**
     * 运行 CLI 应用
     */
    public function run(array $argv): void
    {
        $this->app->runCommand($argv);
    }
}
