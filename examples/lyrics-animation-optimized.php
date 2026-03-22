<?php
/**
 * 《愿你能记住这个夜晚》歌词动画 - 轻量整合版
 * 特点：保留关键帧+GIF delay优化，使用 Remotion 配置和工具类
 * 性能：接近原生，仅增加少量 Remotion 开销
 */

require_once __DIR__ . "/../vendor/autoload.php";

use Yangweijie\Remotion\Core\VideoConfig;
use Yangweijie\Remotion\Animation\Easing;
use GifCreator\GifCreator;

$startTime = microtime(true);

echo "🎬 歌词动画 - 轻量整合版 (使用 Remotion 工具类)...\n\n";

// ==================== 使用 Remotion VideoConfig ====================
$config = new VideoConfig(
    durationInFrames: 2100, // 210秒 * 10fps
    fps: 10,
    width: 640,
    height: 360,
);

echo "📐 VideoConfig: {$config->width}x{$config->height} @ {$config->fps}fps\n";
echo "⏱️  总帧数: {$config->durationInFrames}\n\n";

// ==================== 配置 ====================
$ASSETS = [
    "imagePath" => __DIR__ . "/../愿你能记住这个夜晚-马乙.jpg",
    "audioPath" => __DIR__ . "/../愿你能记住这个夜晚-马乙.mp3",
    "fontPath" => "/Library/Fonts/WenQuanWeiMiHei-1.ttf",
    "outputGif" => __DIR__ . "/../output/lyrics-animation-optimized.gif",
    "outputVideo" => __DIR__ . "/../output/愿你能记住这个夜晚-optimized.mp4",
];

// ==================== 歌词时间轴 ====================
$LYRICS = [
    ["time" => 16.54, "text" => "散场时能否留下来\n角落里的红裙姑娘"],
    ["time" => 25.25, "text" => "我会为你点杯高大师啤酒\n唱支从未曾唱过的歌"],
    ["time" => 33.2, "text" => "这个世界有太多难解的谜\n越清醒越感到压抑"],
    ["time" => 40.85, "text" => "我愿迷失在你忧郁的眼里\n或是泛着泡沫的啤酒里"],
    ["time" => 51.9, "text" => "孤独的漫漫长夜\n看不到尽头的长路"],
    ["time" => 58.74, "text" => "今夜你温柔的目光\n让我不会倒下去"],
    ["time" => 81.53, "text" => "不敢奢望你陪我走下去\n旅途哪来你想象中有趣"],
    [
        "time" => 89.89,
        "text" => "那些动人的旋律从山地到平原\n总有天你会看透这把戏",
    ],
    [
        "time" => 97.62,
        "text" => "还是再见吧年轻的朋友阿\n愿你能记住这醉人的夜晚",
    ],
    ["time" => 105.58, "text" => "我将看着车窗外后退的城市\n默默地想念着你"],
    ["time" => 115.69, "text" => "也许是撒旦的咒语\n我停不下来的脚步"],
    ["time" => 123.39, "text" => "清晨我将再次踏上\n看不到尽头的长路"],
    ["time" => 131.41, "text" => "反复中渐起的鼓点\n狂欢已临近尾声"],
    ["time" => 138.89, "text" => "让我们再来一遍\n趁钟声还没响起"],
    ["time" => 147.4, "text" => "孤独的漫漫长夜\n看不到尽头的长路"],
    ["time" => 154.8, "text" => "今夜你温柔的目光\n让我不会倒下去"],
    ["time" => 162.81, "text" => "孤独的漫漫长夜\n看不到尽头的长路"],
    ["time" => 170.21, "text" => "让我们再来一遍\n趁钟声还没响起"],
    ["time" => 178.54, "text" => "孤独的漫漫长夜\n看不到尽头的长路"],
    ["time" => 186.18, "text" => "今夜你温柔的目光\n让我不会倒下去"],
    ["time" => 194.11, "text" => "让我再看你一眼\n别让我倒下去"],
];

// 加载资源
$bgImage = imagecreatefromjpeg($ASSETS["imagePath"]);
$origWidth = imagesx($bgImage);
$origHeight = imagesy($bgImage);

// ==================== 使用 Remotion Interpolate 计算淡入 ====================
function calculateOpacity(
    float $currentTime,
    float $startTime,
    float $duration,
): float {
    if ($currentTime < $startTime) {
        return 0.0;
    }
    $progress = min(1.0, ($currentTime - $startTime) / $duration);
    // 使用 Remotion Easing
    return Easing::easeOut($progress);
}

echo "🎨 生成关键帧...\n";

$tempDir = __DIR__ . "/../output/temp_opt_" . uniqid();
mkdir($tempDir, 0755, true);

$frames = [];
$durations = [];

// 1. 标题帧 (3秒)
$titleFrame = generateTitleFrame(
    $bgImage,
    $origWidth,
    $origHeight,
    $ASSETS,
    $config,
);
$titlePath = "$tempDir/title.png";
imagepng($titleFrame, $titlePath);
imagedestroy($titleFrame);
$frames[] = $titlePath;
$durations[] = 300;

// 2. 前奏帧
$firstLyricTime = $LYRICS[0]["time"] ?? 0;
$introDuration = max(0, $firstLyricTime - 3);
$introFrame = generateBackgroundFrame(
    $bgImage,
    $origWidth,
    $origHeight,
    $ASSETS,
    $config,
);
$introPath = "$tempDir/intro.png";
imagepng($introFrame, $introPath);
imagedestroy($introFrame);
$frames[] = $introPath;
$durations[] = (int) ($introDuration * 100);

// 3. 歌词帧
$prevEndTime = $firstLyricTime;
foreach ($LYRICS as $index => $lyric) {
    $currentTime = $lyric["time"];
    $nextLyric = $LYRICS[$index + 1] ?? null;
    $endTime = $nextLyric ? $nextLyric["time"] : $currentTime + 5;

    // 使用 Remotion Interpolate 计算淡入透明度
    $opacity = calculateOpacity($currentTime, $lyric["time"], 0.8);

    $lyricFrame = generateLyricFrame(
        $bgImage,
        $origWidth,
        $origHeight,
        $ASSETS,
        $config,
        $lyric["text"],
        $opacity,
    );
    $lyricPath = sprintf("%s/lyric_%04d.png", $tempDir, $index);
    imagepng($lyricFrame, $lyricPath);
    imagedestroy($lyricFrame);
    $frames[] = $lyricPath;
    $durations[] = (int) (($endTime - $currentTime) * 100);
    $prevEndTime = $endTime;
}

// 4. 结尾帧
$endFrame = generateBackgroundFrame(
    $bgImage,
    $origWidth,
    $origHeight,
    $ASSETS,
    $config,
);
$endPath = "$tempDir/end.png";
imagepng($endFrame, $endPath);
imagedestroy($endFrame);
$frames[] = $endPath;
$durations[] = 300;

// 合成 GIF
$gifStart = microtime(true);
$gifCreator = new GifCreator();
$gifCreator->create($frames, $durations, 0);
file_put_contents($ASSETS["outputGif"], $gifCreator->getGif());
$gifTime = round(microtime(true) - $gifStart, 2);

// 清理
foreach ($frames as $frame) {
    unlink($frame);
}
rmdir($tempDir);
imagedestroy($bgImage);

echo "✅ GIF 生成完成！耗时: {$gifTime} 秒\n";
echo "📦 文件大小: " .
    round(filesize($ASSETS["outputGif"]) / 1024 / 1024, 2) .
    " MB\n\n";

// 合并视频
mergeVideo($ASSETS);

$totalTime = round(microtime(true) - $startTime, 2);
echo "🎉 全部完成！总耗时: {$totalTime} 秒\n";

// ==================== 辅助函数 ====================

function generateTitleFrame(
    $bgImage,
    int $origW,
    int $origH,
    array $assets,
    VideoConfig $config,
): \GdImage {
    $width = $config->width;
    $height = $config->height;
    $canvas = imagecreatetruecolor($width, $height);

    // 背景处理
    drawBackground($canvas, $bgImage, $origW, $origH, $width, $height);
    drawOverlay($canvas, $width, $height);

    // 标题
    $title = "愿你能记住这个夜晚";
    $artist = "马乙";
    $font = $assets["fontPath"];

    // 歌名
    $bbox = imagettfbbox(48, 0, $font, $title);
    $x = (int) (($width - ($bbox[2] - $bbox[0])) / 2);
    $y = (int) ($height / 2 - 20);
    $red = imagecolorallocate($canvas, 192, 0, 0);
    imagettftext(
        $canvas,
        48,
        0,
        $x + 3,
        $y + 3,
        imagecolorallocatealpha($canvas, 0, 0, 0, 70),
        $font,
        $title,
    );
    imagettftext($canvas, 48, 0, $x, $y, $red, $font, $title);

    // 歌手
    $bbox = imagettfbbox(32, 0, $font, $artist);
    $x = (int) (($width - ($bbox[2] - $bbox[0])) / 2);
    $y = (int) ($height / 2 + 40);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagettftext(
        $canvas,
        32,
        0,
        $x + 2,
        $y + 2,
        imagecolorallocatealpha($canvas, 0, 0, 0, 70),
        $font,
        $artist,
    );
    imagettftext($canvas, 32, 0, $x, $y, $white, $font, $artist);

    return $canvas;
}

function generateBackgroundFrame(
    $bgImage,
    int $origW,
    int $origH,
    array $assets,
    VideoConfig $config,
): \GdImage {
    $width = $config->width;
    $height = $config->height;
    $canvas = imagecreatetruecolor($width, $height);

    drawBackground($canvas, $bgImage, $origW, $origH, $width, $height);
    drawOverlay($canvas, $width, $height);

    return $canvas;
}

function generateLyricFrame(
    $bgImage,
    int $origW,
    int $origH,
    array $assets,
    VideoConfig $config,
    string $text,
    float $opacity,
): \GdImage {
    $width = $config->width;
    $height = $config->height;
    $canvas = imagecreatetruecolor($width, $height);

    drawBackground($canvas, $bgImage, $origW, $origH, $width, $height);
    drawOverlay($canvas, $width, $height);

    // 自适应字体
    $font = $assets["fontPath"];
    $fontSize =
        mb_strlen($text, "UTF-8") > 50
            ? 36
            : (mb_strlen($text, "UTF-8") > 30
                ? 42
                : 48);

    $lines = explode("\n", $text);
    $maxWidth = $width - 80;

    // 智能换行
    $wrapped = [];
    foreach ($lines as $line) {
        $bbox = imagettfbbox($fontSize, 0, $font, $line);
        if ($bbox[2] - $bbox[0] > $maxWidth) {
            $wrapped = array_merge(
                $wrapped,
                splitLongLine($line, $font, $fontSize, $maxWidth),
            );
        } else {
            $wrapped[] = $line;
        }
    }

    // 绘制
    $lineHeight = (int) ($fontSize * 1.5);
    $totalHeight = count($wrapped) * $lineHeight;
    $startY = (int) (($height - $totalHeight) / 2 + $lineHeight * 0.6);

    $red = imagecolorallocate($canvas, 192, 0, 0);
    $shadow = imagecolorallocatealpha($canvas, 0, 0, 0, 70);

    foreach ($wrapped as $i => $line) {
        $bbox = imagettfbbox($fontSize, 0, $font, $line);
        $x = (int) (($width - ($bbox[2] - $bbox[0])) / 2);
        $y = (int) ($startY + $i * $lineHeight);
        imagettftext(
            $canvas,
            $fontSize,
            0,
            $x + 3,
            $y + 3,
            $shadow,
            $font,
            $line,
        );
        imagettftext($canvas, $fontSize, 0, $x, $y, $red, $font, $line);
    }

    return $canvas;
}

function drawBackground(
    $canvas,
    $bgImage,
    int $origW,
    int $origH,
    int $width,
    int $height,
): void {
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

function drawOverlay($canvas, int $width, int $height): void
{
    $overlay = imagecreatetruecolor($width, $height);
    $color = imagecolorallocatealpha($overlay, 40, 30, 20, 60);
    imagefill($overlay, 0, 0, $color);
    imagecopymerge($canvas, $overlay, 0, 0, 0, 0, $width, $height, 50);
    imagedestroy($overlay);
}

function splitLongLine(
    string $text,
    string $font,
    int $size,
    int $maxWidth,
): array {
    $chars = preg_split("//u", $text, -1, PREG_SPLIT_NO_EMPTY);
    $total = count($chars);
    $bestSplit = 0;
    $bestScore = -PHP_INT_MAX;

    $weights = [
        "看" => 600,
        "着" => 500,
        "的" => 500,
        "我" => 400,
        "你" => 400,
    ];

    for ($i = 3; $i < $total - 3; $i++) {
        $line1 = implode("", array_slice($chars, 0, $i));
        $line2 = implode("", array_slice($chars, $i));

        $bbox1 = imagettfbbox($size, 0, $font, $line1);
        $bbox2 = imagettfbbox($size, 0, $font, $line2);

        if (
            $bbox1[2] - $bbox1[0] > $maxWidth ||
            $bbox2[2] - $bbox2[0] > $maxWidth
        ) {
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

function mergeVideo(array $assets): void
{
    echo "🎬 合并视频...\n";

    $cmd = sprintf(
        'ffprobe -v error -show_entries format=duration -of csv=p=0 "%s"',
        $assets["audioPath"],
    );
    $duration = (float) shell_exec($cmd);

    $ffmpegCmd = sprintf(
        'ffmpeg -y -ignore_loop 0 -i "%s" -i "%s" -r 10 -vsync cfr -c:v h264_videotoolbox -c:a copy -b:v 2M -pix_fmt yuv420p -t %.3f -movflags +faststart "%s" 2>&1',
        $assets["outputGif"],
        $assets["audioPath"],
        $duration,
        $assets["outputVideo"],
    );

    exec($ffmpegCmd, $output, $code);

    if ($code === 0 && file_exists($assets["outputVideo"])) {
        echo "✅ 视频合并完成\n";
        echo "📦 文件大小: " .
            round(filesize($assets["outputVideo"]) / 1024 / 1024, 2) .
            " MB\n\n";
    } else {
        echo "❌ 视频合并失败\n";
    }
}
