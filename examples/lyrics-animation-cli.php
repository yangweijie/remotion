<?php
/**
 * 歌词动画生成器 - CLI 版本
 * 支持动态传参和 LRC 歌词解析
 * 
 * 用法:
 * php lyrics-animation-cli.php --cover=封面.jpg --lyrics=歌词.lrc --audio=音频.mp3 --output=输出.mp4
 * php lyrics-animation-cli.php --cover=封面.jpg --lyrics=歌词.lrc --audio=音频.mp3 --title="歌名" --artist="歌手"
 */

require_once __DIR__ . "/../vendor/autoload.php";

use Yangweijie\Remotion\Core\VideoConfig;
use Yangweijie\Remotion\Animation\Easing;
use GifCreator\GifCreator;

// ==================== 解析命令行参数 ====================
$options = getopt('', [
    'cover:',      // 封面图片路径
    'lyrics:',     // LRC 歌词文件路径
    'audio:',      // 音频文件路径
    'output:',     // 输出视频路径（可选）
    'title:',      // 歌曲名（可选，默认从文件名推断）
    'artist:',     // 歌手名（可选，默认从文件名推断）
    'width:',      // 视频宽度（可选，默认640）
    'height:',     // 视频高度（可选，默认360）
    'fps:',        // 帧率（可选，默认10）
    'font:',       // 字体路径（可选）
    'help',        // 显示帮助
]);

// 显示帮助
if (isset($options['help']) || empty($options)) {
    echo <<<HELP
🎬 歌词动画生成器 - CLI 版本

用法:
  php lyrics-animation-cli.php --cover=封面.jpg --lyrics=歌词.lrc --audio=音频.mp3 [选项]

必需参数:
  --cover=PATH      封面图片路径 (JPG/PNG)
  --lyrics=PATH     LRC 歌词文件路径
  --audio=PATH      音频文件路径 (MP3)

可选参数:
  --output=PATH     输出视频路径 (默认: output/歌名-歌手.mp4)
  --title=TEXT      歌曲名 (默认从文件名推断)
  --artist=TEXT     歌手名 (默认从文件名推断)
  --width=NUM       视频宽度 (默认: 640)
  --height=NUM      视频高度 (默认: 360)
  --fps=NUM         帧率 (默认: 10)
  --font=PATH       字体路径 (默认: /Library/Fonts/WenQuanWeiMiHei-1.ttf)
  --help            显示此帮助信息

示例:
  # 基础用法
  php lyrics-animation-cli.php --cover=song.jpg --lyrics=song.lrc --audio=song.mp3

  # 指定歌名歌手
  php lyrics-animation-cli.php --cover=song.jpg --lyrics=song.lrc --audio=song.mp3 --title="我的歌" --artist="张三"

  # 自定义输出路径和分辨率
  php lyrics-animation-cli.php --cover=song.jpg --lyrics=song.lrc --audio=song.mp3 --output=video.mp4 --width=1280 --height=720

HELP;
    exit(0);
}

// 验证必需参数
$required = ['cover', 'lyrics', 'audio'];
foreach ($required as $param) {
    if (empty($options[$param])) {
        echo "❌ 错误: 缺少必需参数 --{$param}\n";
        echo "使用 --help 查看用法\n";
        exit(1);
    }
}

// 检查文件是否存在
foreach (['cover', 'lyrics', 'audio'] as $param) {
    if (!file_exists($options[$param])) {
        echo "❌ 错误: 文件不存在: {$options[$param]}\n";
        exit(1);
    }
}

$startTime = microtime(true);

echo "🎬 歌词动画生成器\n\n";

// ==================== 配置参数 ====================
$coverPath = $options['cover'];
$lyricsPath = $options['lyrics'];
$audioPath = $options['audio'];

// 解析歌曲名和歌手
$parsedInfo = parseSongInfo($coverPath, $options['title'] ?? null, $options['artist'] ?? null);
$title = $parsedInfo['title'];
$artist = $parsedInfo['artist'];

// 输出路径
if (!empty($options['output'])) {
    $outputVideo = $options['output'];
    $outputGif = str_replace(['.mp4', '.mov', '.avi'], '.gif', $outputVideo);
} else {
    $outputDir = __DIR__ . '/../output';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    $safeTitle = preg_replace('/[^\w\-\x{4e00}-\x{9fa5}]/u', '_', $title);
    $safeArtist = preg_replace('/[^\w\-\x{4e00}-\x{9fa5}]/u', '_', $artist);
    $outputVideo = "{$outputDir}/{$safeTitle}-{$safeArtist}.mp4";
    $outputGif = "{$outputDir}/{$safeTitle}-{$safeArtist}.gif";
}

// 视频配置
$width = (int)($options['width'] ?? 640);
$height = (int)($options['height'] ?? 360);
$fps = (int)($options['fps'] ?? 10);

// 字体路径
$fontPath = $options['font'] ?? '/Library/Fonts/WenQuanWeiMiHei-1.ttf';
if (!file_exists($fontPath)) {
    // 尝试备用字体
    $fallbackFonts = [
        '/System/Library/Fonts/PingFang.ttc',
        '/Library/Fonts/Arial Unicode.ttf',
        '/System/Library/Fonts/Helvetica.ttc',
    ];
    foreach ($fallbackFonts as $font) {
        if (file_exists($font)) {
            $fontPath = $font;
            break;
        }
    }
}

echo "📀 歌曲: {$title}\n";
echo "🎤 歌手: {$artist}\n";
echo "🖼️  封面: {$coverPath}\n";
echo "📝 歌词: {$lyricsPath}\n";
echo "🎵 音频: {$audioPath}\n";
echo "📐 分辨率: {$width}x{$height} @ {$fps}fps\n";
echo "💾 输出: {$outputVideo}\n\n";

// ==================== 解析 LRC 歌词 ====================
echo "📝 解析歌词文件...\n";
$lyrics = parseLrcFile($lyricsPath);
if (empty($lyrics)) {
    echo "❌ 错误: 无法解析歌词或歌词为空\n";
    exit(1);
}
echo "✅ 解析完成，共 " . count($lyrics) . " 句歌词\n\n";

// ==================== 使用 Remotion VideoConfig ====================
// 从音频获取时长
$audioDuration = getAudioDuration($audioPath);
$durationInFrames = (int)($audioDuration * $fps);

$config = new VideoConfig(
    durationInFrames: $durationInFrames,
    fps: $fps,
    width: $width,
    height: $height,
);

$ASSETS = [
    "imagePath" => $coverPath,
    "audioPath" => $audioPath,
    "fontPath" => $fontPath,
    "outputGif" => $outputGif,
    "outputVideo" => $outputVideo,
    "title" => $title,
    "artist" => $artist,
];

// 加载封面图
$bgImage = imagecreatefromstring(file_get_contents($coverPath));
if (!$bgImage) {
    echo "❌ 错误: 无法加载封面图片\n";
    exit(1);
}
$origWidth = imagesx($bgImage);
$origHeight = imagesy($bgImage);

// ==================== 生成关键帧 ====================
echo "🎨 生成关键帧...\n";

$tempDir = __DIR__ . "/../output/temp_" . uniqid();
mkdir($tempDir, 0755, true);

$frames = [];
$durations = [];

// 1. 标题帧 (3秒)
echo "  生成标题帧...\n";
$titleFrame = generateTitleFrame($bgImage, $origWidth, $origHeight, $ASSETS, $config);
$titlePath = "$tempDir/title.png";
imagepng($titleFrame, $titlePath);
imagedestroy($titleFrame);
$frames[] = $titlePath;
$durations[] = 300;

// 2. 前奏帧
$firstLyricTime = $lyrics[0]["time"] ?? 0;
$introDuration = max(0, $firstLyricTime - 3);
echo "  前奏时长: " . round($introDuration, 2) . " 秒\n";

$introFrame = generateBackgroundFrame($bgImage, $origWidth, $origHeight, $config);
$introPath = "$tempDir/intro.png";
imagepng($introFrame, $introPath);
imagedestroy($introFrame);
$frames[] = $introPath;
$durations[] = (int) ($introDuration * 100);

// 3. 歌词帧
echo "  生成歌词帧...\n";
foreach ($lyrics as $index => $lyric) {
    $currentTime = $lyric["time"];
    $nextLyric = $lyrics[$index + 1] ?? null;
    $endTime = $nextLyric ? $nextLyric["time"] : $currentTime + 5;

    $lyricFrame = generateLyricFrame(
        $bgImage,
        $origWidth,
        $origHeight,
        $ASSETS,
        $config,
        $lyric["text"],
    );
    $lyricPath = sprintf("%s/lyric_%04d.png", $tempDir, $index);
    imagepng($lyricFrame, $lyricPath);
    imagedestroy($lyricFrame);
    $frames[] = $lyricPath;
    $durations[] = (int) (($endTime - $currentTime) * 100);
}

// 4. 结尾帧 (3秒)
$endFrame = generateBackgroundFrame($bgImage, $origWidth, $origHeight, $config);
$endPath = "$tempDir/end.png";
imagepng($endFrame, $endPath);
imagedestroy($endFrame);
$frames[] = $endPath;
$durations[] = 300;

// ==================== 合成 GIF ====================
echo "\n🎬 合成 GIF...\n";
$gifStart = microtime(true);

$gifCreator = new GifCreator();
$gifCreator->create($frames, $durations, 0);
file_put_contents($ASSETS["outputGif"], $gifCreator->getGif());

$gifTime = round(microtime(true) - $gifStart, 2);
echo "✅ GIF 生成完成！耗时: {$gifTime} 秒\n";
echo "📦 文件大小: " . round(filesize($ASSETS["outputGif"]) / 1024 / 1024, 2) . " MB\n";

// 清理临时帧
foreach ($frames as $frame) {
    unlink($frame);
}
rmdir($tempDir);
imagedestroy($bgImage);

// ==================== 合并视频 ====================
echo "\n🎬 合并视频...\n";
mergeVideo($ASSETS);

$totalTime = round(microtime(true) - $startTime, 2);
echo "\n🎉 全部完成！总耗时: {$totalTime} 秒\n";

// ==================== 辅助函数 ====================

/**
 * 解析歌曲信息
 */
function parseSongInfo(string $coverPath, ?string $title, ?string $artist): array
{
    // 如果提供了标题和歌手，直接返回
    if (!empty($title) && !empty($artist)) {
        return ['title' => $title, 'artist' => $artist];
    }
    
    // 从文件名推断
    $filename = basename($coverPath, '.' . pathinfo($coverPath, PATHINFO_EXTENSION));
    
    // 尝试匹配 "歌名-歌手" 或 "歌名_歌手" 格式
    if (preg_match('/^(.+)[\-_]([^\-_]+)$/u', $filename, $matches)) {
        $parsedTitle = trim($matches[1]);
        $parsedArtist = trim($matches[2]);
    } else {
        $parsedTitle = $filename;
        $parsedArtist = '未知歌手';
    }
    
    return [
        'title' => $title ?: $parsedTitle,
        'artist' => $artist ?: $parsedArtist,
    ];
}

/**
 * 解析 LRC 歌词文件
 */
function parseLrcFile(string $lrcPath): array
{
    $content = file_get_contents($lrcPath);
    if ($content === false) {
        return [];
    }
    
    $lyrics = [];
    $lines = explode("\n", $content);
    $timeRegex = '/\[(\d{2}):(\d{2})\.(\d{2,3})\](.*)/';
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // 跳过元数据标签（如 [ti:标题], [ar:歌手]）
        if (preg_match('/^\[\w+:.+\]$/', $line)) {
            continue;
        }
        
        // 解析时间标签和歌词文本
        if (preg_match($timeRegex, $line, $matches)) {
            $minutes = (int)$matches[1];
            $seconds = (int)$matches[2];
            $milliseconds = (int)str_pad($matches[3], 3, '0', STR_PAD_RIGHT);
            $text = trim($matches[4]);
            
            // 跳过纯元数据行（如作词、作曲等）
            if (isMetadataLine($text)) {
                continue;
            }
            
            $time = $minutes * 60 + $seconds + $milliseconds / 1000;
            
            if (!empty($text)) {
                $lyrics[] = [
                    'time' => $time,
                    'text' => $text,
                ];
            }
        }
    }
    
    // 按时间排序
    usort($lyrics, fn($a, $b) => $a['time'] <=> $b['time']);
    
    return $lyrics;
}

/**
 * 检查是否为元数据行
 */
function isMetadataLine(string $text): bool
{
    $metadataKeywords = [
        '作词', '作曲', '编曲', '演唱', '混音', '母带', '制作人',
        '录音', '吉他', '贝斯', '鼓', '键盘', '和声', '弦乐', '词曲',
        'OP', 'SP', '出品', '发行', '监制', '策划',
    ];
    
    foreach ($metadataKeywords as $keyword) {
        if (mb_strpos($text, $keyword, 0, 'UTF-8') !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * 获取音频时长
 */
function getAudioDuration(string $audioPath): float
{
    $cmd = sprintf(
        'ffprobe -v error -show_entries format=duration -of csv=p=0 "%s"',
        $audioPath
    );
    $duration = shell_exec($cmd);
    return floatval($duration) ?: 210.0;
}

/**
 * 生成标题帧
 */
function generateTitleFrame($bgImage, int $origW, int $origH, array $assets, VideoConfig $config): \GdImage
{
    $width = $config->width;
    $height = $config->height;
    $canvas = imagecreatetruecolor($width, $height);

    drawBackground($canvas, $bgImage, $origW, $origH, $width, $height);
    drawOverlay($canvas, $width, $height);

    $title = $assets["title"];
    $artist = $assets["artist"];
    $font = $assets["fontPath"];

    // 动态调整标题字体大小
    $titleFontSize = calculateFontSize($title, $font, $width - 80, 48);
    $artistFontSize = calculateFontSize($artist, $font, $width - 80, 32);

    // 歌名
    $bbox = imagettfbbox($titleFontSize, 0, $font, $title);
    $x = (int) (($width - ($bbox[2] - $bbox[0])) / 2);
    $y = (int) ($height / 2 - 20);
    $red = imagecolorallocate($canvas, 192, 0, 0);
    imagettftext(
        $canvas,
        $titleFontSize,
        0,
        $x + 3,
        $y + 3,
        imagecolorallocatealpha($canvas, 0, 0, 0, 70),
        $font,
        $title,
    );
    imagettftext($canvas, $titleFontSize, 0, $x, $y, $red, $font, $title);

    // 歌手
    $bbox = imagettfbbox($artistFontSize, 0, $font, $artist);
    $x = (int) (($width - ($bbox[2] - $bbox[0])) / 2);
    $y = (int) ($height / 2 + 40);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagettftext(
        $canvas,
        $artistFontSize,
        0,
        $x + 2,
        $y + 2,
        imagecolorallocatealpha($canvas, 0, 0, 0, 70),
        $font,
        $artist,
    );
    imagettftext($canvas, $artistFontSize, 0, $x, $y, $white, $font, $artist);

    return $canvas;
}

/**
 * 生成背景帧
 */
function generateBackgroundFrame($bgImage, int $origW, int $origH, VideoConfig $config): \GdImage
{
    $width = $config->width;
    $height = $config->height;
    $canvas = imagecreatetruecolor($width, $height);

    drawBackground($canvas, $bgImage, $origW, $origH, $width, $height);
    drawOverlay($canvas, $width, $height);

    return $canvas;
}

/**
 * 生成歌词帧
 */
function generateLyricFrame($bgImage, int $origW, int $origH, array $assets, VideoConfig $config, string $text): \GdImage
{
    $width = $config->width;
    $height = $config->height;
    $canvas = imagecreatetruecolor($width, $height);

    drawBackground($canvas, $bgImage, $origW, $origH, $width, $height);
    drawOverlay($canvas, $width, $height);

    $font = $assets["fontPath"];
    $maxWidth = $width - 80;

    // 解析多行歌词
    $lines = explode("\\n", $text);
    
    // 为每行计算合适的字体大小
    $processedLines = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $fontSize = calculateFontSize($line, $font, $maxWidth, 48);
        
        // 检查是否需要换行
        $bbox = imagettfbbox($fontSize, 0, $font, $line);
        if ($bbox[2] - $bbox[0] > $maxWidth) {
            // 智能换行
            $wrapped = splitLongLine($line, $font, $fontSize, $maxWidth);
            foreach ($wrapped as $wLine) {
                $processedLines[] = ['text' => $wLine, 'size' => $fontSize];
            }
        } else {
            $processedLines[] = ['text' => $line, 'size' => $fontSize];
        }
    }

    // 计算行高和起始位置
    if (empty($processedLines)) {
        return $canvas;
    }
    
    $minFontSize = min(array_column($processedLines, 'size'));
    $lineHeight = (int) ($minFontSize * 1.5);
    $totalHeight = count($processedLines) * $lineHeight;
    $startY = (int) (($height - $totalHeight) / 2 + $lineHeight * 0.6);

    $red = imagecolorallocate($canvas, 192, 0, 0);
    $shadow = imagecolorallocatealpha($canvas, 0, 0, 0, 70);

    foreach ($processedLines as $i => $lineInfo) {
        $line = $lineInfo['text'];
        $fontSize = $lineInfo['size'];
        
        $bbox = imagettfbbox($fontSize, 0, $font, $line);
        $x = (int) (($width - ($bbox[2] - $bbox[0])) / 2);
        $y = (int) ($startY + $i * $lineHeight);
        
        imagettftext($canvas, $fontSize, 0, $x + 3, $y + 3, $shadow, $font, $line);
        imagettftext($canvas, $fontSize, 0, $x, $y, $red, $font, $line);
    }

    return $canvas;
}

/**
 * 计算合适的字体大小
 */
function calculateFontSize(string $text, string $font, int $maxWidth, int $defaultSize): int
{
    $sizes = [$defaultSize, 42, 36, 32, 28];
    
    foreach ($sizes as $size) {
        $bbox = imagettfbbox($size, 0, $font, $text);
        if ($bbox[2] - $bbox[0] <= $maxWidth) {
            return $size;
        }
    }
    
    return 24; // 最小字号
}

/**
 * 绘制背景
 */
function drawBackground($canvas, $bgImage, int $origW, int $origH, int $width, int $height): void
{
    $scale = max($width / $origW, $height / $origH);
    $scaledW = (int) ($origW * $scale);
    $scaledH = (int) ($origH * $scale);
    $srcX = (int) (($scaledW - $width) / 2.0 / $scale);
    $srcY = (int) (($scaledH - $height) / 2.0 / $scale);

    $temp = imagecreatetruecolor($width, $height);
    imagecopyresampled(
        $temp,
        $bgImage,
        0,
        0,
        $srcX,
        $srcY,
        $width,
        $height,
        (int) ($width / $scale),
        (int) ($height / $scale),
    );

    for ($i = 0; $i < 8; $i++) {
        imagefilter($temp, IMG_FILTER_GAUSSIAN_BLUR);
    }

    imagecopy($canvas, $temp, 0, 0, 0, 0, $width, $height);
    imagedestroy($temp);
}

/**
 * 绘制遮罩
 */
function drawOverlay($canvas, int $width, int $height): void
{
    $overlay = imagecreatetruecolor($width, $height);
    $color = imagecolorallocatealpha($overlay, 40, 30, 20, 60);
    imagefill($overlay, 0, 0, $color);
    imagecopymerge($canvas, $overlay, 0, 0, 0, 0, $width, $height, 50);
    imagedestroy($overlay);
}

/**
 * 智能分割长行
 */
function splitLongLine(string $text, string $font, int $size, int $maxWidth): array
{
    $chars = preg_split("//u", $text, -1, PREG_SPLIT_NO_EMPTY);
    $total = count($chars);
    $bestSplit = 0;
    $bestScore = -PHP_INT_MAX;

    $weights = [
        '，' => 1000, '。' => 1000, '、' => 1000, '；' => 1000,
        '？' => 1000, '！' => 1000, '的' => 500, '了' => 500,
        '着' => 500, '我' => 400, '你' => 400, '让' => 400,
    ];

    for ($i = 3; $i < $total - 3; $i++) {
        $line1 = implode("", array_slice($chars, 0, $i));
        $line2 = implode("", array_slice($chars, $i));

        $bbox1 = imagettfbbox($size, 0, $font, $line1);
        $bbox2 = imagettfbbox($size, 0, $font, $line2);

        if ($bbox1[2] - $bbox1[0] > $maxWidth || $bbox2[2] - $bbox2[0] > $maxWidth) {
            continue;
        }

        $score = 100 - abs($i - ($total - $i)) * 10;
        $prevChar = $chars[$i - 1] ?? "";
        if (isset($weights[$prevChar])) {
            $score += $weights[$prevChar];
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestSplit = $i;
        }
    }

    if ($bestSplit > 0) {
        return [
            implode("", array_slice($chars, 0, $bestSplit)),
            implode("", array_slice($chars, $bestSplit)),
        ];
    }

    return [$text];
}

/**
 * 合并视频
 */
function mergeVideo(array $assets): void
{
    $duration = getAudioDuration($assets["audioPath"]);

    $ffmpegCmd = sprintf(
        'ffmpeg -y -ignore_loop 0 -i "%s" -i "%s" -r 10 -vsync cfr -c:v h264_videotoolbox -c:a copy -b:v 2M -pix_fmt yuv420p -t %.3f -movflags +faststart "%s" 2>&1',
        $assets["outputGif"],
        $assets["audioPath"],
        $duration,
        $assets["outputVideo"],
    );

    exec($ffmpegCmd, $output, $code);

    if ($code === 0 && file_exists($assets["outputVideo"])) {
        $fileSize = round(filesize($assets["outputVideo"]) / 1024 / 1024, 2);
        echo "✅ 视频合并完成\n";
        echo "📦 文件大小: {$fileSize} MB\n";
    } else {
        echo "❌ 视频合并失败\n";
        if (!empty($output)) {
            echo "错误信息: " . implode("\n", array_slice($output, -5)) . "\n";
        }
    }
}
