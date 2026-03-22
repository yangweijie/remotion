<?php

namespace App\Services\MvGenerator;

use Exception;
use Illuminate\Support\Facades\Log;
use Grafika\Grafika;
use GifCreator\GifCreator;

// Load vendor autoload from build_mv2

/**
 * MV Generator V2 - 使用封面图片作为背景
 * 对应 build_mv2 的功能
 */
class MvGeneratorV2
{
    private string $ffmpegPath = "ffmpeg";

    private string $ffprobePath = "ffprobe";

    private string $tempDir;

    public function __construct(?string $tempDir = null)
    {
        $this->tempDir = $tempDir ?? storage_path("app/mv_temp");
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    /**
     * 生成MV视频
     *
     * @param  string  $coverImage  封面图片路径
     * @param  string  $lyricFile  歌词文件路径 (LRC格式)
     * @param  string  $audioFile  音频文件路径
     * @param  string  $outputFile  输出视频路径
     * @param  string|null  $title  歌曲标题
     * @param  string|null  $artist  艺术家
     * @return string 输出文件路径
     */
    public function generate(
        string $coverImage,
        string $lyricFile,
        string $audioFile,
        string $outputFile,
        ?string $title = null,
        ?string $artist = null,
    ): string {
        $startTime = microtime(true);

        // 参数验证
        if (!file_exists($coverImage)) {
            throw new Exception("封面图片不存在: {$coverImage}");
        }
        if (!file_exists($lyricFile)) {
            throw new Exception("歌词文件不存在: {$lyricFile}");
        }
        if (!file_exists($audioFile)) {
            throw new Exception("音频文件不存在: {$audioFile}");
        }

        $title = $title ?? "歌曲名";
        $artist = $artist ?? "艺术家";

        Log::info("[MvGeneratorV2] 开始生成MV", [
            "cover" => $coverImage,
            "lyric" => $lyricFile,
            "audio" => $audioFile,
            "output" => $outputFile,
            "title" => $title,
            "artist" => $artist,
        ]);

        // 1. 解析歌词
        Log::info("[MvGeneratorV2] 解析歌词文件...");
        $lrcContent = file_get_contents($lyricFile);
        $lyrics = LyricParserV2::parseLrc($lrcContent);
        Log::info("[MvGeneratorV2] 解析完成，共 " . count($lyrics) . " 句歌词");

        // 2. 生成 GIF
        Log::info("[MvGeneratorV2] 生成 GIF 动画...");
        $tempGif = $this->tempDir . "/music_video_" . uniqid() . ".gif";

        $gifGenerator = new GifGeneratorV2(
            $coverImage,
            $title,
            $artist,
            $lyrics,
            "jay的实验室",
            640, // width
            360, // height
        );

        $gifTime = $gifGenerator->generate($tempGif);
        Log::info(
            "[MvGeneratorV2] GIF 生成完成，耗时: " . round($gifTime, 2) . " 秒",
        );

        // 3. 合并为视频
        Log::info("[MvGeneratorV2] 合并 GIF 和音频为视频...");
        $videoGenerator = new VideoGeneratorV2(
            $tempGif,
            $audioFile,
            $outputFile,
            1280,
            720,
        );

        $videoTime = $videoGenerator->generate(true);
        Log::info(
            "[MvGeneratorV2] 视频生成完成，耗时: " .
                round($videoTime, 2) .
                " 秒",
        );

        // 清理临时文件
        if (file_exists($tempGif)) {
            unlink($tempGif);
        }

        $totalTime = microtime(true) - $startTime;
        Log::info(
            "[MvGeneratorV2] 生成完成，总耗时: " . round($totalTime, 2) . " 秒",
        );

        return $outputFile;
    }

    /**
     * 设置 FFmpeg 路径
     */
    public function setFFmpegPath(string $ffmpegPath, string $ffprobePath): self
    {
        $this->ffmpegPath = $ffmpegPath;
        $this->ffprobePath = $ffprobePath;

        return $this;
    }
}

/**
 * 歌词解析器 - V2版本
 */
class LyricParserV2
{
    // 需要过滤的元数据关键词
    private static array $metadataKeywords = [
        "作词",
        "作曲",
        "编曲",
        "演唱",
        "混音",
        "母带",
        "制作人",
        "录音",
        "吉他",
        "贝斯",
        "鼓",
        "键盘",
        "和声",
        "弦乐",
        "词曲",
        "OP",
        "SP",
    ];

    public static function parseLrc(string $lrcContent): array
    {
        $entries = [];
        $lines = explode("\n", trim($lrcContent));
        $timeRegex = "/\[(\d{2}):(\d{2})\.(\d{2,3})\](.*)/";
        $parsedLines = [];

        foreach ($lines as $line) {
            if (preg_match($timeRegex, $line, $matches)) {
                $minutes = intval($matches[1]);
                $seconds = intval($matches[2]);
                $milliseconds = intval(str_pad($matches[3], 3, "0"));
                $text = trim($matches[4]);

                // 过滤空行和元数据行
                if (empty($text) || self::isMetadata($text)) {
                    continue;
                }

                $time = $minutes * 60 + $seconds + $milliseconds / 1000;
                $parsedLines[] = ["time" => $time, "text" => $text];
            }
        }

        // Sort by time
        usort($parsedLines, fn($a, $b) => $a["time"] <=> $b["time"]);

        // Create entries with end times
        for ($i = 0; $i < count($parsedLines); $i++) {
            $current = $parsedLines[$i];
            $next = $parsedLines[$i + 1] ?? null;
            $endTime = $next ? $next["time"] : $current["time"] + 5;

            $entries[] = new LyricEntryV2(
                $i + 1,
                $current["time"],
                $endTime,
                $current["text"],
            );
        }

        return $entries;
    }

    private static function isMetadata(string $text): bool
    {
        foreach (self::$metadataKeywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }
}

/**
 * 歌词条目 - V2版本
 */
class LyricEntryV2
{
    public int $id;

    public float $startTime;

    public float $endTime;

    public string $text;

    public function __construct(
        int $id,
        float $startTime,
        float $endTime,
        string $text,
    ) {
        $this->id = $id;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->text = $text;
    }
}

/**
 * GIF生成器 - V2版本
 */
class GifGeneratorV2
{
    private string $coverImage;

    private string $title;

    private string $artist;

    private string $watermark;

    private array $lyrics;

    private int $width;

    private int $height;

    public function __construct(
        string $coverImage,
        string $title,
        string $artist,
        array $lyrics,
        string $watermark = "jay的实验室",
        int $width = 640,
        int $height = 360,
    ) {
        $this->coverImage = $coverImage;
        $this->title = $title;
        $this->artist = $artist;
        $this->lyrics = $lyrics;
        $this->watermark = $watermark;
        $this->width = $width;
        $this->height = $height;
    }

    public function generate(string $outputPath): float
    {
        $startTime = microtime(true);

        // 创建临时目录
        $tempDir = storage_path("app/mv_temp_" . uniqid());
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $frames = [];
        $durations = [];

        // 获取第一句歌词的开始时间（前奏时长）
        $firstLyricTime = !empty($this->lyrics)
            ? $this->lyrics[0]->startTime
            : 0;

        Log::info("[GifGeneratorV2] 前奏时长: {$firstLyricTime} 秒");

        // 1. 生成前奏帧（标题帧，显示到第一句歌词开始）
        Log::info("[GifGeneratorV2] 生成前奏帧（标题）...");
        $titleFrame = $this->generateTitleFrame($tempDir);
        $frames[] = $titleFrame;
        // 前奏时长转换为百分之一秒
        $durations[] = (int) ($firstLyricTime * 100);

        // 2. 为每句歌词生成一帧，按实际时间计算延迟
        Log::info("[GifGeneratorV2] 生成歌词帧...");
        $prevEndTime = $firstLyricTime;

        foreach ($this->lyrics as $index => $lyric) {
            // 如果有间隙，添加空白帧
            if ($lyric->startTime > $prevEndTime + 0.1) {
                $gapFrame = $this->generateEmptyFrame(
                    $tempDir,
                    "gap_" . $index,
                );
                $frames[] = $gapFrame;
                $gapDuration = (int) (($lyric->startTime - $prevEndTime) * 100);
                $durations[] = $gapDuration;
            }

            $framePath = $this->generateLyricFrame(
                $lyric->text,
                $tempDir,
                $index,
            );
            $frames[] = $framePath;

            // 该帧显示时长 = 歌词结束时间 - 歌词开始时间
            $durationCs = (int) (($lyric->endTime - $lyric->startTime) * 100);
            $durations[] = max($durationCs, 10);

            $prevEndTime = $lyric->endTime;
        }

        // 3. 生成结尾帧（最后3秒空白）
        Log::info("[GifGeneratorV2] 生成结尾帧...");
        $endFrame = $this->generateEndFrame($tempDir);
        $frames[] = $endFrame;
        $durations[] = 300; // 3秒

        // 计算总时长
        $totalDuration = array_sum($durations) / 100;
        Log::info(
            "[GifGeneratorV2] 共 " .
                count($frames) .
                " 帧，总时长: {$totalDuration} 秒",
        );

        // 4. 使用 GifCreator 合成 GIF
        Log::info("[GifGeneratorV2] 合成 GIF...");
        $gifCreator = new GifCreator();
        $gifCreator->create($frames, $durations, 0);

        // 保存 GIF
        file_put_contents($outputPath, $gifCreator->getGif());

        // 清理临时文件
        $this->cleanup($tempDir);

        $endTime = microtime(true);

        return $endTime - $startTime;
    }

    private function generateTitleFrame(string $tempDir): string
    {
        $image = $this->createBaseImage();

        $red = imagecolorallocate($image, 192, 0, 0);
        $white = imagecolorallocate($image, 255, 255, 255);

        // 标题字体（无阴影）
        $this->drawCenteredTextNoShadow($image, $this->title, 48, $red, -20);
        $this->drawCenteredTextNoShadow($image, $this->artist, 16, $white, 30);

        $framePath = $tempDir . "/title.png";
        imagepng($image, $framePath, 3);
        imagedestroy($image);

        return $framePath;
    }

    private function generateEmptyFrame(string $tempDir, string $suffix): string
    {
        $image = $this->createBaseImage();

        $framePath = $tempDir . "/empty_" . $suffix . ".png";
        imagepng($image, $framePath, 3);
        imagedestroy($image);

        return $framePath;
    }

    private function generateLyricFrame(
        string $text,
        string $tempDir,
        int $index,
    ): string {
        $image = $this->createBaseImage();

        $red = imagecolorallocate($image, 192, 0, 0);
        // 根据歌词长度调整字体大小：超过40字符用48，否则用56
        $fontSize = mb_strlen($text, "UTF-8") > 40 ? 48 : 56;
        $this->drawCenteredTextMultiLine($image, $text, $fontSize, $red, 0);

        $framePath = sprintf("%s/lyric_%04d.png", $tempDir, $index);
        imagepng($image, $framePath, 3);
        imagedestroy($image);

        return $framePath;
    }

    private function generateEndFrame(string $tempDir): string
    {
        $image = $this->createBaseImage();

        $gray = imagecolorallocatealpha($image, 255, 255, 255, 80);
        $this->drawCenteredText($image, "感谢观看", 24, $gray, 0);

        $framePath = $tempDir . "/end.png";
        imagepng($image, $framePath, 3);
        imagedestroy($image);

        return $framePath;
    }

    private function createBaseImage()
    {
        $image = imagecreatetruecolor($this->width, $this->height);
        $this->drawBackground($image);
        $this->drawGradientOverlay($image);

        return $image;
    }

    private function drawBackground($image): void
    {
        try {
            // 使用 Grafika 处理背景
            $editor = Grafika::createEditor();
            $cover = Grafika::createImage($this->coverImage);

            // 缩放到目标尺寸
            $editor->resizeExact($cover, $this->width, $this->height);

            // 灰度化
            $editor->apply($cover, Grafika::createFilter("Grayscale"));

            // 高斯模糊 (blur_heavy = 80)
            $editor->apply($cover, Grafika::createFilter("Blur", 80));

            // 保存到临时文件
            $tempPath = storage_path("app/bg_" . uniqid() . ".png");
            $editor->save($cover, $tempPath);

            // 加载处理后的图片
            $processed = @imagecreatefrompng($tempPath);
            if ($processed) {
                imagecopy(
                    $image,
                    $processed,
                    0,
                    0,
                    0,
                    0,
                    $this->width,
                    $this->height,
                );
                imagedestroy($processed);
            }

            // 清理临时文件
            @unlink($tempPath);
        } catch (Exception $e) {
            // 失败时使用简单处理
            $cover = @imagecreatefromstring(
                file_get_contents($this->coverImage),
            );
            if ($cover) {
                imagecopyresampled(
                    $image,
                    $cover,
                    0,
                    0,
                    0,
                    0,
                    $this->width,
                    $this->height,
                    imagesx($cover),
                    imagesy($cover),
                );
                imagefilter($image, IMG_FILTER_GRAYSCALE);
                for ($i = 0; $i < 20; $i++) {
                    imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
                }
                imagedestroy($cover);
            } else {
                imagefill($image, 0, 0, imagecolorallocate($image, 0, 0, 0));
            }
        }
    }

    private function drawGradientOverlay($image): void
    {
        for ($y = 0; $y < $this->height; $y++) {
            $alpha = (int) (($y / $this->height) * 180);
            $alphaValue = (int) (127 - (127 - $alpha / 2));
            $color = imagecolorallocatealpha($image, 0, 0, 0, $alphaValue);
            imageline($image, 0, $y, $this->width, $y, $color);
        }
    }

    private function drawWatermark($image): void
    {
        $white = imagecolorallocate($image, 255, 255, 255);
        $gray = imagecolorallocatealpha($image, 255, 255, 255, 60);

        imagettftext(
            $image,
            10,
            0,
            $this->width - 120,
            25,
            $white,
            $this->getFont(),
            $this->watermark,
        );
        imagettftext(
            $image,
            8,
            0,
            $this->width - 80,
            40,
            $gray,
            $this->getFont(),
            "bilibili",
        );
    }

    private function drawCenteredText(
        $image,
        string $text,
        int $size,
        $color,
        int $yOffset,
    ): void {
        $font = $this->getFont();
        $bbox = imagettfbbox($size, 0, $font, $text);
        $textWidth = $bbox[2] - $bbox[0];
        $x = (int) (($this->width - $textWidth) / 2);
        $y = (int) ($this->height / 2 + $yOffset);

        // 投影阴影
        $shadow = imagecolorallocatealpha($image, 0, 0, 0, 70);
        imagettftext($image, $size, 0, $x + 4, $y + 4, $shadow, $font, $text);

        // 主文字
        imagettftext($image, $size, 0, $x, $y, $color, $font, $text);
    }

    private function drawCenteredTextNoShadow(
        $image,
        string $text,
        int $size,
        $color,
        int $yOffset,
    ): void {
        $font = $this->getFont();
        $bbox = imagettfbbox($size, 0, $font, $text);
        $textWidth = $bbox[2] - $bbox[0];
        $x = (int) (($this->width - $textWidth) / 2);
        $y = (int) ($this->height / 2 + $yOffset);

        // 无阴影，直接绘制主文字
        imagettftext($image, $size, 0, $x, $y, $color, $font, $text);
    }

    private function drawCenteredTextMultiLine(
        $image,
        string $text,
        int $size,
        $color,
        int $yOffset,
    ): void {
        $font = $this->getFont();
        $maxWidth = $this->width - 80;

        $lines = $this->wrapText($text, $font, $size, $maxWidth);

        $lineHeight = (int) ($size * 1.4);
        $totalHeight = count($lines) * $lineHeight;
        $startY = (int) (($this->height - $totalHeight) / 2 + $yOffset);

        foreach ($lines as $index => $line) {
            $bbox = imagettfbbox($size, 0, $font, $line);
            $textWidth = $bbox[2] - $bbox[0];
            $x = (int) (($this->width - $textWidth) / 2);
            $y = $startY + ($index + 1) * $lineHeight;

            // 投影阴影（向右下方偏移）
            $shadow = imagecolorallocatealpha($image, 0, 0, 0, 70);
            imagettftext(
                $image,
                $size,
                0,
                $x + 4,
                $y + 4,
                $shadow,
                $font,
                $line,
            );

            // 主文字
            imagettftext($image, $size, 0, $x, $y, $color, $font, $line);
        }
    }

    private function wrapText(
        string $text,
        string $font,
        int $size,
        int $maxWidth,
    ): array {
        $chars = preg_split("//u", $text, -1, PREG_SPLIT_NO_EMPTY);
        $totalChars = count($chars);

        // 如果只有一行能放下，直接返回
        $fullText = implode("", $chars);
        $bbox = imagettfbbox($size, 0, $font, $fullText);
        if ($maxWidth >= $bbox[2] - $bbox[0]) {
            return [$fullText];
        }

        // 需要换行，尝试找到最佳换行点
        // 目标：第一行字数 <= 第二行字数
        $bestSplit = 0;
        $minDiff = PHP_INT_MAX;

        // 从中间开始尝试不同的分割点
        for ($i = 1; $i < $totalChars; $i++) {
            $line1 = implode("", array_slice($chars, 0, $i));
            $line2 = implode("", array_slice($chars, $i));

            // 检查两行是否都能放下
            $bbox1 = imagettfbbox($size, 0, $font, $line1);
            $bbox2 = imagettfbbox($size, 0, $font, $line2);
            $width1 = $bbox1[2] - $bbox1[0];
            $width2 = $bbox2[2] - $bbox2[0];

            if ($width1 <= $maxWidth && $width2 <= $maxWidth) {
                // 计算字数差（第一行少，第二行多）
                $len1 = $i;
                $len2 = $totalChars - $i;
                $diff = $len2 - $len1; // 希望这个值 >= 0 且尽可能小

                // 优先选择第一行字数少的方案
                if ($diff >= 0 && $diff < $minDiff) {
                    $minDiff = $diff;
                    $bestSplit = $i;
                }
            }
        }

        // 如果没找到合适的分割点（都不满足宽度），使用默认的贪心分割
        if ($bestSplit === 0) {
            $lines = [];
            $currentLine = "";
            foreach ($chars as $char) {
                $testLine = $currentLine . $char;
                $bbox = imagettfbbox($size, 0, $font, $testLine);
                if ($maxWidth < $bbox[2] - $bbox[0] && !empty($currentLine)) {
                    $lines[] = $currentLine;
                    $currentLine = $char;
                } else {
                    $currentLine = $testLine;
                }
            }
            if (!empty($currentLine)) {
                $lines[] = $currentLine;
            }

            return $lines;
        }

        // 使用找到的最佳分割点
        $line1 = implode("", array_slice($chars, 0, $bestSplit));
        $line2 = implode("", array_slice($chars, $bestSplit));

        return [$line1, $line2];
    }

    private function getFont(): string
    {
        // 宋体字体路径（macOS）
        $fonts = [
            "/System/Library/Fonts/STHeiti Light.ttc", // 黑体（类似宋体效果）
            "/System/Library/Fonts/PingFang.ttc", // 苹方
            "/System/Library/Fonts/Supplemental/Songti.ttc", // 宋体
            "/Library/Fonts/Songti.ttc", // 宋体
            "/System/Library/Fonts/Helvetica.ttc", // 后备
        ];

        foreach ($fonts as $font) {
            if (file_exists($font)) {
                return $font;
            }
        }

        return "/System/Library/Fonts/Helvetica.ttc";
    }

    private function cleanup(string $tempDir): void
    {
        $files = glob($tempDir . "/*");
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        @rmdir($tempDir);
    }
}

/**
 * 视频生成器 - V2版本
 */
class VideoGeneratorV2
{
    private string $gifPath;

    private string $audioPath;

    private string $outputPath;

    private int $targetWidth;

    private int $targetHeight;

    public function __construct(
        string $gifPath,
        string $audioPath,
        string $outputPath,
        int $width = 1280,
        int $height = 720,
    ) {
        $this->gifPath = $gifPath;
        $this->audioPath = $audioPath;
        $this->outputPath = $outputPath;
        $this->targetWidth = $width;
        $this->targetHeight = $height;
    }

    public function generate(bool $useHardwareAcceleration = true): float
    {
        $startTime = microtime(true);

        Log::info("[VideoGeneratorV2] 使用 FFmpeg 合并 GIF 和音频...");

        // 获取音频时长和编码格式
        $audioInfo = $this->getAudioInfo();
        $audioDuration = $audioInfo["duration"];
        $audioCodec = $audioInfo["codec"];
        Log::info(
            "[VideoGeneratorV2] 音频时长: {$audioDuration} 秒, 编码: {$audioCodec}",
        );

        // 构建 FFmpeg 命令
        $codec = $useHardwareAcceleration ? "h264_videotoolbox" : "libx264";
        $bitrate = "2M"; // 歌词视频画面简单，2Mbps 足够

        // 音频编码：MP3 直接复制，其他格式转为 AAC
        $audioCodecArg =
            $audioCodec === "mp3" || $audioCodec === "libmp3lame"
                ? "-c:a copy"
                : "-c:a aac";

        // 使用 -r 10 指定固定帧率 10fps，避免 B 站时间戳跳变问题
        // -vsync cfr 确保恒定帧率
        // -ignore_loop 0 让 GIF 循环播放，-t 指定时长
        $ffmpegCmd = sprintf(
            'ffmpeg -y -ignore_loop 0 -i "%s" -i "%s" -r 10 -vsync cfr -c:v %s %s -b:v %s -pix_fmt yuv420p -t %.3f -movflags +faststart "%s" 2>&1',
            $this->gifPath,
            $this->audioPath,
            $codec,
            $audioCodecArg,
            $bitrate,
            $audioDuration,
            $this->outputPath,
        );

        Log::info("[VideoGeneratorV2] 执行命令: {$ffmpegCmd}");

        exec($ffmpegCmd, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::error(
                "[VideoGeneratorV2] FFmpeg 错误输出: " .
                    implode("\n", array_slice($output, -20)),
            );
            throw new Exception("FFmpeg 执行失败");
        }

        $endTime = microtime(true);

        return $endTime - $startTime;
    }

    private function getAudioInfo(): array
    {
        $cmd = sprintf(
            'ffprobe -v error -show_entries format=duration:stream=codec_name -of json "%s"',
            $this->audioPath,
        );

        $output = shell_exec($cmd);
        $data = json_decode($output, true);

        $duration = 60.0;
        $codec = "aac";

        if (isset($data["format"]["duration"])) {
            $duration = floatval($data["format"]["duration"]);
        }

        // 获取音频流的编码格式
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

    private function getAudioDuration(): float
    {
        $info = $this->getAudioInfo();

        return $info["duration"];
    }
}
