<?php
/**
 * 《愿你能记住这个夜晚》歌词动画 V3
 * 优化：只生成关键帧，使用 GIF delay 控制时长
 * 参考 MvGeneratorV2.php 思路
 */

require_once __DIR__ . "/vendor/autoload.php";

use GifCreator\GifCreator;

// ==================== 配置 ====================
$CONFIG = [
    "width" => 640,
    "height" => 360,
    "fps" => 10,
    "imagePath" => __DIR__ . "/愿你能记住这个夜晚-马乙.jpg",
    "outputGif" => __DIR__ . "/output/lyrics-animation-v3.gif",
    "outputVideo" => __DIR__ . "/output/愿你能记住这个夜晚-v3.mp4",
    "fontPath" => "/Library/Fonts/WenQuanWeiMiHei-1.ttf",
    "audioPath" => __DIR__ . "/愿你能记住这个夜晚-马乙.mp3",
];

// ==================== 歌词时间轴（带换行） ====================
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

echo "🎬 创建《愿你能记住这个夜晚》歌词动画 V3 (关键帧优化版)...\n";
echo "分辨率: {$CONFIG["width"]}x{$CONFIG["height"]}\n\n";

// 检查字体
if (!file_exists($CONFIG["fontPath"])) {
    echo "⚠️  警告: 字体不存在，尝试备用字体...\n";
    $CONFIG["fontPath"] = "/System/Library/Fonts/PingFang.ttc";
    if (!file_exists($CONFIG["fontPath"])) {
        $CONFIG["fontPath"] = "/Library/Fonts/Arial Unicode.ttf";
    }
}
echo "📝 使用字体: {$CONFIG["fontPath"]}\n";

// 加载背景图片
if (!file_exists($CONFIG["imagePath"])) {
    die("❌ 错误: 图片不存在: {$CONFIG["imagePath"]}\n");
}

$bgImage = imagecreatefromjpeg($CONFIG["imagePath"]);
if (!$bgImage) {
    die("❌ 错误: 无法加载图片\n");
}

$origWidth = imagesx($bgImage);
$origHeight = imagesy($bgImage);
echo "📷 原图尺寸: {$origWidth}x{$origHeight}\n\n";

// 创建输出目录
if (!is_dir(__DIR__ . "/output")) {
    mkdir(__DIR__ . "/output", 0755, true);
}

$startTime = microtime(true);

// ==================== 生成关键帧 ====================

echo "🎨 生成关键帧...\n";

$tempDir = __DIR__ . "/output/temp_frames_" . uniqid();
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
}

$frames = [];
$durations = []; // 单位：百分之一秒

// 1. 标题帧（歌名-歌手，显示3秒）
$titleText = "愿你能记住这个夜晚\n马乙";
echo "🎵 标题帧: {$titleText}\n";
$titleFrame = generateTitleFrame($bgImage, $origWidth, $origHeight, $CONFIG, $titleText);
$titlePath = $tempDir . "/title.png";
imagepng($titleFrame, $titlePath, 3);
imagedestroy($titleFrame);
$frames[] = $titlePath;
$durations[] = 300; // 3秒

// 2. 前奏帧（封面，无歌词）
$firstLyricTime = $LYRICS[0]["time"] ?? 0;
$introDuration = max(0, $firstLyricTime - 3); // 减去标题帧的3秒
echo "⏱️  前奏时长: {$firstLyricTime} 秒 (标题3秒 + 前奏{$introDuration}秒)\n";

$introFrame = generateFrame(
    $bgImage,
    $origWidth,
    $origHeight,
    $CONFIG,
    null,
    0,
);
$introPath = $tempDir . "/intro.png";
imagepng($introFrame, $introPath, 3);
imagedestroy($introFrame);
$frames[] = $introPath;
$durations[] = (int) ($introDuration * 100); // 转换为百分之一秒

// 2. 歌词帧
$prevEndTime = $firstLyricTime;
$frameCount = 1;

foreach ($LYRICS as $index => $lyric) {
    $currentTime = $lyric["time"];
    $nextLyric = $LYRICS[$index + 1] ?? null;
    $endTime = $nextLyric ? $nextLyric["time"] : $currentTime + 5;

    // 如果有间隙，添加空白帧
    if ($currentTime > $prevEndTime + 0.1) {
        $gapDuration = $currentTime - $prevEndTime;
        $durations[count($durations) - 1] += (int) ($gapDuration * 100);
    }

    // 生成歌词帧
    $lyricFrame = generateFrame(
        $bgImage,
        $origWidth,
        $origHeight,
        $CONFIG,
        $lyric["text"],
        $index,
    );
    $lyricPath = sprintf("%s/lyric_%04d.png", $tempDir, $index);
    imagepng($lyricFrame, $lyricPath, 3);
    imagedestroy($lyricFrame);
    $frames[] = $lyricPath;

    // 该帧显示时长
    $duration = $endTime - $currentTime;
    $durations[] = (int) ($duration * 100);

    $prevEndTime = $endTime;
    $frameCount++;
}

// 3. 结尾帧（最后3秒）
$endFrame = generateFrame($bgImage, $origWidth, $origHeight, $CONFIG, null, -1);
$endPath = $tempDir . "/end.png";
imagepng($endFrame, $endPath, 3);
imagedestroy($endFrame);
$frames[] = $endPath;
$durations[] = 300; // 3秒

$totalDuration = array_sum($durations) / 100;
echo "✅ 关键帧生成完成: {$frameCount} 帧\n";
echo "⏱️  总时长: {$totalDuration} 秒\n\n";

// ==================== 合成 GIF ====================

echo "🎬 合成 GIF...\n";

$gifCreator = new GifCreator();
$gifCreator->create($frames, $durations, 0);
file_put_contents($CONFIG["outputGif"], $gifCreator->getGif());

$gifTime = round(microtime(true) - $startTime, 2);
echo "✅ GIF 生成完成！耗时: {$gifTime} 秒\n";
echo "📁 输出: {$CONFIG["outputGif"]}\n";
echo "📦 文件大小: " .
    round(filesize($CONFIG["outputGif"]) / 1024 / 1024, 2) .
    " MB\n\n";

// 清理临时帧文件
foreach ($frames as $frame) {
    if (file_exists($frame)) {
        unlink($frame);
    }
}
@rmdir($tempDir);
imagedestroy($bgImage);

echo "🎬 开始合并视频...\n";

// ==================== 合并视频 ====================

$videoStartTime = microtime(true);

// 检查文件
if (!file_exists($CONFIG["outputGif"])) {
    die("❌ 错误: GIF 文件不存在\n");
}
if (!file_exists($CONFIG["audioPath"])) {
    die("❌ 错误: 音频文件不存在\n");
}

// 获取音频时长
$audioInfo = getAudioInfo($CONFIG["audioPath"]);
$audioDuration = $audioInfo["duration"];
$audioCodec = $audioInfo["codec"];
echo "🎵 音频时长: {$audioDuration}s, 编码: {$audioCodec}\n";

// 构建 FFmpeg 命令
$codec = "h264_videotoolbox"; // 硬件加速
$bitrate = "2M";
$audioCodecArg = ($audioCodec === "mp3" || $audioCodec === "libmp3lame") ? "-c:a copy" : "-c:a aac";

$ffmpegCmd = sprintf(
    'ffmpeg -y -ignore_loop 0 -i "%s" -i "%s" -r 10 -vsync cfr -c:v %s %s -b:v %s -pix_fmt yuv420p -t %.3f -movflags +faststart "%s" 2>&1',
    $CONFIG["outputGif"],
    $CONFIG["audioPath"],
    $codec,
    $audioCodecArg,
    $bitrate,
    $audioDuration,
    $CONFIG["outputVideo"]
);

echo "🚀 使用 VideoToolbox 硬件加速...\n";
exec($ffmpegCmd, $output, $returnCode);

if ($returnCode !== 0) {
    echo "❌ FFmpeg 错误:\n" . implode("\n", array_slice($output, -10)) . "\n";
    exit(1);
}

$videoTime = round(microtime(true) - $videoStartTime, 2);
$totalTime = round(microtime(true) - $startTime, 2);

if (file_exists($CONFIG["outputVideo"])) {
    $fileSize = round(filesize($CONFIG["outputVideo"]) / 1024 / 1024, 2);
    echo "✅ 视频合并完成！耗时: {$videoTime} 秒\n";
    echo "📁 输出: {$CONFIG["outputVideo"]}\n";
    echo "📦 文件大小: {$fileSize} MB\n\n";
    echo "🎉 全部完成！总耗时: {$totalTime} 秒\n";
} else {
    echo "❌ 视频生成失败\n";
    exit(1);
}

// ==================== 辅助函数 ====================

function generateFrame(
    $bgImage,
    int $origWidth,
    int $origHeight,
    array $config,
    ?string $lyricText,
    int $index,
): \GdImage {
    $width = $config["width"];
    $height = $config["height"];
    $fontPath = $config["fontPath"];

    $canvas = imagecreatetruecolor($width, $height);

    // 1. 绘制背景（缩放+模糊）
    $scale = max($width / $origWidth, $height / $origHeight);
    $scaledWidth = (int) ($origWidth * $scale);
    $scaledHeight = (int) ($origHeight * $scale);

    $srcX = (int) (($scaledWidth - $width) / 2.0 / $scale);
    $srcY = (int) (($scaledHeight - $height) / 2.0 / $scale);
    $srcW = (int) ($width / $scale);
    $srcH = (int) ($height / $scale);

    $tempCanvas = imagecreatetruecolor($width, $height);
    imagecopyresampled(
        $tempCanvas,
        $bgImage,
        0,
        0,
        $srcX,
        $srcY,
        $width,
        $height,
        $srcW,
        $srcH,
    );

    // 高斯模糊
    for ($i = 0; $i < 8; $i++) {
        imagefilter($tempCanvas, IMG_FILTER_GAUSSIAN_BLUR);
    }

    imagecopy($canvas, $tempCanvas, 0, 0, 0, 0, $width, $height);
    imagedestroy($tempCanvas);

    // 2. 暗色遮罩
    $overlay = imagecreatetruecolor($width, $height);
    $darkColor = imagecolorallocatealpha($overlay, 40, 30, 20, 60);
    imagefill($overlay, 0, 0, $darkColor);
    imagecopymerge($canvas, $overlay, 0, 0, 0, 0, $width, $height, 50);
    imagedestroy($overlay);

    // 3. 绘制歌词（如果有）
    if ($lyricText !== null) {
        drawLyricText($canvas, $lyricText, $width, $height, $fontPath);
    }

    return $canvas;
}

/**
 * 生成标题帧（歌名-歌手）
 */
function generateTitleFrame($bgImage, int $origWidth, int $origHeight, array $config, string $titleText): \GdImage
{
    $width = $config["width"];
    $height = $config["height"];
    $fontPath = $config["fontPath"];

    $canvas = imagecreatetruecolor($width, $height);

    // 1. 绘制背景（缩放+模糊）
    $scale = max($width / $origWidth, $height / $origHeight);
    $scaledWidth = (int) ($origWidth * $scale);
    $scaledHeight = (int) ($origHeight * $scale);

    $srcX = (int) (($scaledWidth - $width) / 2.0 / $scale);
    $srcY = (int) (($scaledHeight - $height) / 2.0 / $scale);
    $srcW = (int) ($width / $scale);
    $srcH = (int) ($height / $scale);

    $tempCanvas = imagecreatetruecolor($width, $height);
    imagecopyresampled(
        $tempCanvas,
        $bgImage,
        0,
        0,
        $srcX,
        $srcY,
        $width,
        $height,
        $srcW,
        $srcH,
    );

    // 高斯模糊
    for ($i = 0; $i < 8; $i++) {
        imagefilter($tempCanvas, IMG_FILTER_GAUSSIAN_BLUR);
    }

    imagecopy($canvas, $tempCanvas, 0, 0, 0, 0, $width, $height);
    imagedestroy($tempCanvas);

    // 2. 暗色遮罩
    $overlay = imagecreatetruecolor($width, $height);
    $darkColor = imagecolorallocatealpha($overlay, 40, 30, 20, 60);
    imagefill($overlay, 0, 0, $darkColor);
    imagecopymerge($canvas, $overlay, 0, 0, 0, 0, $width, $height, 50);
    imagedestroy($overlay);

    // 3. 绘制标题文字
    $lines = explode("\n", $titleText);
    $titleSize = 48;
    $artistSize = 32;

    // 计算总高度
    $bboxTitle = imagettfbbox($titleSize, 0, $fontPath, $lines[0]);
    $bboxArtist = imagettfbbox($artistSize, 0, $fontPath, $lines[1] ?? "");
    $titleHeight = abs($bboxTitle[1] - $bboxTitle[7]);
    $artistHeight = abs($bboxArtist[1] - $bboxArtist[7]);
    $totalHeight = $titleHeight + $artistHeight + 20; // 20px 间距

    $startY = (int) (($height - $totalHeight) / 2 + $titleHeight / 2);

    // 歌名（红色，大字体）
    $red = imagecolorallocate($canvas, 192, 0, 0);
    $shadow = imagecolorallocatealpha($canvas, 0, 0, 0, 70);

    $bbox = imagettfbbox($titleSize, 0, $fontPath, $lines[0]);
    $textWidth = $bbox[2] - $bbox[0];
    $x = (int) (($width - $textWidth) / 2);
    $y = $startY;

    imagettftext($canvas, $titleSize, 0, $x + 3, $y + 3, $shadow, $fontPath, $lines[0]);
    imagettftext($canvas, $titleSize, 0, $x, $y, $red, $fontPath, $lines[0]);

    // 歌手（白色，小字体）
    if (isset($lines[1])) {
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $bbox = imagettfbbox($artistSize, 0, $fontPath, $lines[1]);
        $textWidth = $bbox[2] - $bbox[0];
        $x = (int) (($width - $textWidth) / 2);
        $y = $startY + $titleHeight + 20;

        imagettftext($canvas, $artistSize, 0, $x + 2, $y + 2, $shadow, $fontPath, $lines[1]);
        imagettftext($canvas, $artistSize, 0, $x, $y, $white, $fontPath, $lines[1]);
    }

    return $canvas;
}

function drawLyricText(
    $canvas,
    string $text,
    int $width,
    int $height,
    string $fontPath,
): void {
    $maxWidth = $width - 80;

    // 先按换行符分割
    $rawLines = explode("\n", $text);
    $lines = [];

    // 为每行计算合适的字体大小
    foreach ($rawLines as $line) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }

        // 尝试不同字体大小，找到能放下的最大字号
        $fontSize = 48;
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $line);
        $lineWidth = $bbox[2] - $bbox[0];

        if ($lineWidth > $maxWidth) {
            $fontSize = 42;
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $line);
            $lineWidth = $bbox[2] - $bbox[0];
        }

        if ($lineWidth > $maxWidth) {
            $fontSize = 36;
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $line);
            $lineWidth = $bbox[2] - $bbox[0];
        }

        if ($lineWidth > $maxWidth) {
            $fontSize = 32;
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $line);
            $lineWidth = $bbox[2] - $bbox[0];
        }

        // 如果还放不下，使用智能换行
        if ($lineWidth > $maxWidth) {
            $wrapped = splitLongLine($line, $fontPath, $fontSize, $maxWidth);
            foreach ($wrapped as $wLine) {
                $lines[] = ["text" => $wLine, "size" => $fontSize];
            }
        } else {
            $lines[] = ["text" => $line, "size" => $fontSize];
        }
    }

    // 统一使用最小字体大小计算行高（保持一致性）
    $minFontSize = min(array_column($lines, "size"));
    $bbox = imagettfbbox($minFontSize, 0, $fontPath, "中");
    $lineHeight = abs($bbox[1] - $bbox[7]) * 1.5;

    $lineCount = count($lines);
    $totalHeight = $lineCount * $lineHeight;
    $startY = (int) (($height - $totalHeight) / 2 + $lineHeight * 0.6);

    // 红色歌词
    $red = imagecolorallocate($canvas, 192, 0, 0);
    $shadow = imagecolorallocatealpha($canvas, 0, 0, 0, 70);

    foreach ($lines as $i => $lineInfo) {
        $line = $lineInfo["text"];
        $fontSize = $lineInfo["size"];

        $bbox = imagettfbbox($fontSize, 0, $fontPath, $line);
        $lineWidth = $bbox[2] - $bbox[0];
        $x = (int) (($width - $lineWidth) / 2);
        $y = (int) ($startY + $i * $lineHeight);

        // 阴影
        imagettftext(
            $canvas,
            $fontSize,
            0,
            $x + 3,
            $y + 3,
            $shadow,
            $fontPath,
            $line,
        );
        // 主文字
        imagettftext($canvas, $fontSize, 0, $x, $y, $red, $fontPath, $line);
    }
}

/**
 * 智能换行：优先按原有换行，超长时在语义边界自动分割
 * 目标：第一行字数 <= 第二行字数，优先在标点或自然边界处换行
 */
function wrapLyricText(
    string $text,
    string $font,
    int $size,
    int $maxWidth,
): array {
    // 先按原有换行符分割
    $paragraphs = explode("\n", $text);
    $result = [];

    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);
        if (empty($paragraph)) {
            continue;
        }

        // 检查这行是否能放下
        $bbox = imagettfbbox($size, 0, $font, $paragraph);
        $textWidth = $bbox[2] - $bbox[0];

        if ($textWidth <= $maxWidth) {
            // 能放下，直接添加
            $result[] = $paragraph;
        } else {
            // 放不下，需要智能分割
            $result = array_merge(
                $result,
                splitLongLine($paragraph, $font, $size, $maxWidth),
            );
        }
    }

    return $result;
}

/**
 * 分割长行，优先在语义边界处换行
 * 策略：优先在标点、介词、连词后换行，避免单行过长或过短
 */
function splitLongLine(
    string $text,
    string $font,
    int $size,
    int $maxWidth,
): array {
    $chars = preg_split("//u", $text, -1, PREG_SPLIT_NO_EMPTY);
    $totalChars = count($chars);

    // 定义语义边界字符及其权重
    $breakWeights = [
        // 标点符号（最高优先级）
        "，" => 1000,
        "。" => 1000,
        "、" => 1000,
        "；" => 1000,
        "？" => 1000,
        "！" => 1000,
        "," => 1000,
        "." => 1000,
        // 介词/连词/助词（高优先级）
        "在" => 800,
        "从" => 800,
        "向" => 800,
        "往" => 800,
        "和" => 700,
        "与" => 700,
        "或" => 700,
        "而" => 700,
        // 动词（中等优先级）
        "看" => 600,
        "听" => 600,
        "想" => 600,
        "念" => 600,
        "让" => 600,
        "使" => 600,
        "让" => 600,
        // 结构助词
        "的" => 500,
        "了" => 500,
        "着" => 500,
        "过" => 500,
        // 代词
        "我" => 400,
        "你" => 400,
        "他" => 400,
        "她" => 400,
        "它" => 400,
    ];

    // 尝试找到最佳换行点
    $bestSplit = 0;
    $bestScore = -PHP_INT_MAX;
    $targetLen = $totalChars / 2; // 目标：尽量居中

    for ($i = 3; $i < $totalChars - 3; $i++) {
        // 避免太短
        $line1 = implode("", array_slice($chars, 0, $i));
        $line2 = implode("", array_slice($chars, $i));

        $bbox1 = imagettfbbox($size, 0, $font, $line1);
        $bbox2 = imagettfbbox($size, 0, $font, $line2);
        $width1 = $bbox1[2] - $bbox1[0];
        $width2 = $bbox2[2] - $bbox2[0];

        if ($width1 > $maxWidth || $width2 > $maxWidth) {
            continue;
        }

        $score = 0;

        // 1. 语义边界权重
        $prevChar = $chars[$i - 1] ?? "";
        if (isset($breakWeights[$prevChar])) {
            $score += $breakWeights[$prevChar];
        }

        // 2. 长度平衡（越接近中间越好）
        $len1 = $i;
        $len2 = $totalChars - $i;
        $balance = 100 - abs($len1 - $len2) * 10; // 字数差越小越好
        $score += $balance;

        // 3. 避免第二行太短
        if ($len2 < 4) {
            $score -= 200;
        }

        // 4. 避免行首是标点
        $nextChar = $chars[$i] ?? "";
        if (
            in_array($nextChar, ["，", "。", "、", "；", "？", "！", ",", "."])
        ) {
            $score -= 500;
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

    // 没找到合适的分割点，使用贪心分割
    $result = [];
    $currentLine = "";
    foreach ($chars as $char) {
        $testLine = $currentLine . $char;
        $bbox = imagettfbbox($size, 0, $font, $testLine);
        if ($maxWidth < $bbox[2] - $bbox[0] && !empty($currentLine)) {
            $result[] = $currentLine;
            $currentLine = $char;
        } else {
            $currentLine = $testLine;
        }
    }
    if (!empty($currentLine)) {
        $result[] = $currentLine;
    }

    return $result;
}

/**
 * 获取音频文件信息
 */
function getAudioInfo(string $audioPath): array
{
    $cmd = sprintf(
        'ffprobe -v error -show_entries format=duration:stream=codec_name -of json "%s"',
        $audioPath
    );

    $output = shell_exec($cmd);
    $data = json_decode($output, true);

    $duration = 60.0;
    $codec = "aac";

    if (isset($data["format"]["duration"])) {
        $duration = floatval($data["format"]["duration"]);
    }

    if (isset($data["streams"]) && is_array($data["streams"])) {
        foreach ($data["streams"] as $stream) {
            if (isset($stream["codec_name"])) {
                $codec = $stream["codec_name"];
                break;
            }
        }
    }

    return [
        "duration" => $duration > 0 ? $duration : 60.0,
        "codec" => $codec,
    ];
}
