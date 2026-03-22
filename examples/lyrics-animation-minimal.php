<?php
/**
 * 歌词动画生成器 - 极简诗意风格
 * 风格参考：《所以生命啊》
 * 特点：暗调背景、白色细体字、无特效、重度模糊
 */

require_once __DIR__ . "/../vendor/autoload.php";

use Yangweijie\Remotion\Core\VideoConfig;
use GifCreator\GifCreator;

// ==================== 解析命令行参数 ====================
$options = getopt('', [
    'cover:', 'lyrics:', 'audio:', 'output:', 'title:', 'artist:',
    'width:', 'height:', 'fps:', 'font:', 'help',
]);

if (isset($options['help']) || empty($options)) {
    echo <<<HELP
🎬 歌词动画生成器 - 极简诗意风格

用法:
  php lyrics-animation-minimal.php --cover=封面.jpg --lyrics=歌词.lrc --audio=音频.mp3

风格特点:
  - 暗调背景（重度模糊+深色遮罩）
  - 白色细体字（无阴影、无特效）
  - 极简诗意风格
  - 参考：《所以生命啊》

HELP;
    exit(0);
}

foreach (['cover', 'lyrics', 'audio'] as $param) {
    if (empty($options[$param]) || !file_exists($options[$param])) {
        echo "❌ 错误: 缺少或无效参数 --{$param}\n";
        exit(1);
    }
}

$startTime = microtime(true);

echo "🎬 歌词动画生成器 - 极简诗意风格\n\n";

// ==================== 配置（极简风格）====================
$coverPath = $options['cover'];
$lyricsPath = $options['lyrics'];
$audioPath = $options['audio'];

// 解析歌曲信息
$parsedInfo = parseSongInfo($coverPath, $options['title'] ?? null, $options['artist'] ?? null);
$title = $parsedInfo['title'];
$artist = $parsedInfo['artist'];

// 输出路径
$outputDir = __DIR__ . '/../output';
if (!is_dir($outputDir)) mkdir($outputDir, 0755, true);

if (!empty($options['output'])) {
    $outputVideo = $options['output'];
    $outputGif = str_replace(['.mp4', '.mov', '.avi'], '.gif', $outputVideo);
} else {
    $safeTitle = preg_replace('/[^\w\-\x{4e00}-\x{9fa5}]/u', '_', $title);
    $safeArtist = preg_replace('/[^\w\-\x{4e00}-\x{9fa5}]/u', '_', $artist);
    $outputVideo = "{$outputDir}/{$safeTitle}-{$safeArtist}-minimal.mp4";
    $outputGif = "{$outputDir}/{$safeTitle}-{$safeArtist}-minimal.gif";
}

// 极简风格配置
$width = (int)($options['width'] ?? 640);
$height = (int)($options['height'] ?? 360);
$fps = (int)($options['fps'] ?? 10);

// 字体路径（优先使用细体）
$fontPath = $options['font'] ?? findThinFont();

echo "📀 歌曲: {$title}\n";
echo "🎤 歌手: {$artist}\n";
echo "🎨 风格: 极简诗意（参考《所以生命啊》）\n";
echo "📐 分辨率: {$width}x{$height} @ {$fps}fps\n\n";

// ==================== 解析 LRC ====================
echo "📝 解析歌词...\n";
$lyrics = parseLrcFile($lyricsPath);
if (empty($lyrics)) {
    echo "❌ 错误: 无法解析歌词\n";
    exit(1);
}
echo "✅ 共 " . count($lyrics) . " 句歌词\n\n";

// ==================== 创建配置 ====================
$audioDuration = getAudioDuration($audioPath);
$config = new VideoConfig(
    durationInFrames: (int)($audioDuration * $fps),
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

// 加载封面
$bgImage = imagecreatefromstring(file_get_contents($coverPath));
if (!$bgImage) {
    echo "❌ 错误: 无法加载封面\n";
    exit(1);
}
$origWidth = imagesx($bgImage);
$origHeight = imagesy($bgImage);

// ==================== 生成关键帧 ====================
echo "🎨 生成关键帧（极简风格）...\n";

$tempDir = $outputDir . "/temp_min_" . uniqid();
mkdir($tempDir, 0755, true);

$frames = [];
$durations = [];

// 1. 标题帧（3秒，极简风格）
echo "  生成标题帧...\n";
$titleFrame = generateMinimalTitleFrame($bgImage, $origWidth, $origHeight, $ASSETS, $config);
$titlePath = "$tempDir/title.png";
imagepng($titleFrame, $titlePath);
imagedestroy($titleFrame);
$frames[] = $titlePath;
$durations[] = 300;

// 2. 前奏帧
$firstLyricTime = $lyrics[0]["time"] ?? 0;
$introDuration = max(0, $firstLyricTime - 3);
echo "  前奏时长: " . round($introDuration, 2) . " 秒\n";

$introFrame = generateMinimalBackgroundFrame($bgImage, $origWidth, $origHeight, $config);
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

    $lyricFrame = generateMinimalLyricFrame(
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

// 4. 结尾帧
$endFrame = generateMinimalBackgroundFrame($bgImage, $origWidth, $origHeight, $config);
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

// 清理
foreach ($frames as $frame) unlink($frame);
rmdir($tempDir);
imagedestroy($bgImage);

// ==================== 合并视频 ====================
echo "\n🎬 合并视频...\n";
mergeVideo($ASSETS);

$totalTime = round(microtime(true) - $startTime, 2);
echo "\n🎉 全部完成！总耗时: {$totalTime} 秒\n";
echo "💾 输出: {$outputVideo}\n";

// ==================== 辅助函数 ====================

function findThinFont(): string
{
    // 优先使用细体字体
    $thinFonts = [
        '/System/Library/Fonts/PingFang.ttc',           // 苹方（细体）
        '/Library/Fonts/WenQuanWeiMiHei-1.ttf',         // 文泉微米黑
        '/System/Library/Fonts/STHeiti Light.ttc',      // 黑体细体
        '/System/Library/Fonts/Helvetica.ttc',          // Helvetica
    ];
    
    foreach ($thinFonts as $font) {
        if (file_exists($font)) {
            return $font;
        }
    }
    
    return '/System/Library/Fonts/Helvetica.ttc';
}

function parseSongInfo(string $coverPath, ?string $title, ?string $artist): array
{
    if (!empty($title) && !empty($artist)) {
        return ['title' => $title, 'artist' => $artist];
    }
    
    $filename = basename($coverPath, '.' . pathinfo($coverPath, PATHINFO_EXTENSION));
    
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

function parseLrcFile(string $lrcPath): array
{
    $content = file_get_contents($lrcPath);
    if ($content === false) return [];
    
    $lyrics = [];
    $lines = explode("\n", $content);
    $timeRegex = '/\[(\d{2}):(\d{2})\.(\d{2,3})\](.*)/';
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        if (preg_match('/^\[\w+:.+\]$/', $line)) continue;
        
        if (preg_match($timeRegex, $line, $matches)) {
            $minutes = (int)$matches[1];
            $seconds = (int)$matches[2];
            $milliseconds = (int)str_pad($matches[3], 3, '0', STR_PAD_RIGHT);
            $text = trim($matches[4]);
            
            if (isMetadataLine($text)) continue;
            
            $time = $minutes * 60 + $seconds + $milliseconds / 1000;
            
            if (!empty($text)) {
                $lyrics[] = ['time' => $time, 'text' => $text];
            }
        }
    }
    
    usort($lyrics, fn($a, $b) => $a['time'] <=> $b['time']);
    
    return $lyrics;
}

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

function getAudioDuration(string $audioPath): float
{
    $cmd = sprintf('ffprobe -v error -show_entries format=duration -of csv=p=0 "%s"', $audioPath);
    $duration = shell_exec($cmd);
    return floatval($duration) ?: 60.0;
}

// ==================== 极简风格帧生成 ====================

function generateMinimalTitleFrame($bgImage, int $origW, int $origH, array $assets, VideoConfig $config): \GdImage
{
    $width = $config->width;
    $height = $config->height;
    $canvas = imagecreatetruecolor($width, $height);

    // 极简背景：重度模糊+深色遮罩
    drawMinimalBackground($canvas, $bgImage, $origW, $origH, $width, $height);
    drawDarkOverlay($canvas, $width, $height);

    $title = $assets["title"];
    $artist = $assets["artist"];
    $font = $assets["fontPath"];

    // 极简标题：细体、白色、无阴影
    $titleFontSize = calculateMinimalFontSize($title, $font, $width - 100, 36);
    $artistFontSize = 24;

    // 歌名 - 白色细体，垂直居中
    $bbox = imagettfbbox($titleFontSize, 0, $font, $title);
    $textWidth = $bbox[2] - $bbox[0];
    $x = (int) (($width - $textWidth) / 2);
    $y = (int) ($height / 2 - 10);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagettftext($canvas, $titleFontSize, 0, $x, $y, $white, $font, $title);

    // 歌手 - 更小的白色细体
    $bbox = imagettfbbox($artistFontSize, 0, $font, $artist);
    $textWidth = $bbox[2] - $bbox[0];
    $x = (int) (($width - $textWidth) / 2);
    $y = (int) ($height / 2 + 35);
    $grayWhite = imagecolorallocate($canvas, 220, 220, 220);
    imagettftext($canvas, $artistFontSize, 0, $x, $y, $grayWhite, $font, $artist);

    return $canvas;
}

function generateMinimalBackgroundFrame($bgImage, int $origW, int $origH, VideoConfig $config): \GdImage
{
    $width = $config->width;
    $height = $config->height;
    $canvas = imagecreatetruecolor($width, $height);

    drawMinimalBackground($canvas, $bgImage, $origW, $origH, $width, $height);
    drawDarkOverlay($canvas, $width, $height);

    return $canvas;
}

function generateMinimalLyricFrame($bgImage, int $origW, int $origH, array $assets, VideoConfig $config, string $text): \GdImage
{
    $width = $config->width;
    $height = $config->height;
    $canvas = imagecreatetruecolor($width, $height);

    drawMinimalBackground($canvas, $bgImage, $origW, $origH, $width, $height);
    drawDarkOverlay($canvas, $width, $height);

    $font = $assets["fontPath"];
    $maxWidth = $width - 80;

    // 解析歌词
    $lines = explode("\n", $text);
    
    // 处理每行
    $processedLines = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // 极简风格：统一使用较小的细体字
        $fontSize = 28;
        
        // 检查是否需要换行
        $bbox = imagettfbbox($fontSize, 0, $font, $line);
        if ($bbox[2] - $bbox[0] > $maxWidth) {
            $wrapped = splitMinimalLine($line, $font, $fontSize, $maxWidth);
            foreach ($wrapped as $wLine) {
                $processedLines[] = $wLine;
            }
        } else {
            $processedLines[] = $line;
        }
    }

    if (empty($processedLines)) {
        return $canvas;
    }
    
    // 计算布局
    $fontSize = 28;
    $lineHeight = (int) ($fontSize * 1.8);
    $totalHeight = count($processedLines) * $lineHeight;
    $startY = (int) (($height - $totalHeight) / 2 + $lineHeight * 0.7);

    // 极简白色，无阴影
    $white = imagecolorallocate($canvas, 255, 255, 255);

    foreach ($processedLines as $i => $line) {
        $bbox = imagettfbbox($fontSize, 0, $font, $line);
        $textWidth = $bbox[2] - $bbox[0];
        $x = (int) (($width - $textWidth) / 2);
        $y = (int) ($startY + $i * $lineHeight);
        
        // 纯白色文字，无任何特效
        imagettftext($canvas, $fontSize, 0, $x, $y, $white, $font, $line);
    }

    return $canvas;
}

// ==================== 极简风格背景处理 ====================

function drawMinimalBackground($canvas, $bgImage, int $origW, int $origH, int $width, int $height): void
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

    // 重度模糊（极简风格特点）
    for ($i = 0; $i < 12; $i++) {
        imagefilter($temp, IMG_FILTER_GAUSSIAN_BLUR);
    }

    // 可选：降低饱和度（去色效果）
    imagefilter($temp, IMG_FILTER_GRAYSCALE);
    imagefilter($temp, IMG_FILTER_BRIGHTNESS, -20);

    imagecopy($canvas, $temp, 0, 0, 0, 0, $width, $height);
    imagedestroy($temp);
}

function drawDarkOverlay($canvas, int $width, int $height): void
{
    // 深色遮罩（60%透明度）
    $overlay = imagecreatetruecolor($width, $height);
    $darkColor = imagecolorallocatealpha($overlay, 20, 20, 20, 60);
    imagefill($overlay, 0, 0, $darkColor);
    imagecopymerge($canvas, $overlay, 0, 0, 0, 0, $width, $height, 60);
    imagedestroy($overlay);
}

// ==================== 辅助函数 ====================

function calculateMinimalFontSize(string $text, string $font, int $maxWidth, int $defaultSize): int
{
    $sizes = [$defaultSize, 32, 28, 24];
    
    foreach ($sizes as $size) {
        $bbox = imagettfbbox($size, 0, $font, $text);
        if ($bbox[2] - $bbox[0] <= $maxWidth) {
            return $size;
        }
    }
    
    return 22;
}

function splitMinimalLine(string $text, string $font, int $size, int $maxWidth): array
{
    $chars = preg_split("//u", $text, -1, PREG_SPLIT_NO_EMPTY);
    $total = count($chars);
    $bestSplit = 0;
    $bestScore = -PHP_INT_MAX;

    // 极简换行：优先在标点符号后换行
    $breakWeights = [
        '，' => 1000, '。' => 1000, '、' => 1000, '；' => 1000,
        '？' => 1000, '！' => 1000, '的' => 500, '了' => 400,
    ];

    for ($i = 3; $i < $total - 3; $i++) {
        $line1 = implode("", array_slice($chars, 0, $i));
        $line2 = implode("", array_slice($chars, $i));

        $bbox1 = imagettfbbox($size, 0, $font, $line1);
        $bbox2 = imagettfbbox($size, 0, $font, $line2);

        if ($bbox1[2] - $bbox1[0] > $maxWidth || $bbox2[2] - $bbox2[0] > $maxWidth) {
            continue;
        }

        $score = 100 - abs($i - ($total - $i)) * 5;
        $prevChar = $chars[$i - 1] ?? "";
        if (isset($breakWeights[$prevChar])) {
            $score += $breakWeights[$prevChar];
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
    }
}

