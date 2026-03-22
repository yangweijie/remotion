<?php

declare(strict_types=1);

use Yangweijie\Remotion\Animation\Easing;
use Yangweijie\Remotion\Animation\Interpolate;
use Yangweijie\Remotion\Animation\Spring;
use Yangweijie\Remotion\Helpers\Pipeline;
use Grafika\Grafika;
use Grafika\Color;

/**
 * 全局助手函数
 *
 * 提供类似 remotion 顶级函数的 PHP 版本：
 * - interpolate()  对标 remotion 的 interpolate()
 * - spring()       对标 remotion 的 spring()
 * - pipe()         管道操作符语法糖
 * - clamp()        数值截断
 */

// ============================================================
// 配置常量
// ============================================================

/** @var int 默认阴影层数 */
const DEFAULT_SHADOW_LAYERS = 8;

/** @var int 电影级阴影层数 */
const CINEMATIC_SHADOW_LAYERS = 20;

/** @var int 默认阴影偏移像素 */
const DEFAULT_SHADOW_OFFSET = 2;

/** @var int 电影级阴影偏移像素 */
const CINEMATIC_SHADOW_OFFSET = 3;

/** @var int 默认噪点数量 */
const DEFAULT_NOISE_COUNT = 3000;

/** @var int 进度条宽度（字符数） */
const PROGRESS_BAR_WIDTH = 20;

/** @var float 最后一句歌词默认时长（秒） */
const DEFAULT_LAST_LYRIC_DURATION = 4.0;

/** @var int 电影级背景层字体比例 */
const CINEMATIC_BG_FONT_RATIO = 150; // 1.5x

if (!function_exists('interpolate')) {
    /**
     * 值插值（对标 remotion 的 interpolate()）
     *
     * @param float   $input       当前输入值（通常是帧号）
     * @param float[] $inputRange  输入范围
     * @param float[] $outputRange 输出范围
     * @param array   $options     选项
     */
    function interpolate(float $input, array $inputRange, array $outputRange, array $options = []): float
    {
        return Interpolate::interpolate($input, $inputRange, $outputRange, $options);
    }
}

if (!function_exists('spring')) {
    /**
     * 弹簧动画（对标 remotion 的 spring()）
     *
     * @param int   $frame  当前帧号
     * @param int   $fps    帧率
     * @param array $config 弹簧配置
     */
    function spring(int $frame, int $fps, array $config = []): float
    {
        return Spring::spring($frame, $fps, $config);
    }
}

if (!function_exists('pipe')) {
    /**
     * 创建管道（对标管道操作符 |>）
     *
     * @param mixed $value 初始值
     */
    function pipe(mixed $value): Pipeline
    {
        return Pipeline::make($value);
    }
}

if (!function_exists('clamp')) {
    /**
     * 将值限制在 [min, max] 范围内
     *
     * @param int|float $value 输入值
     * @param int|float $min 最小值
     * @param int|float $max 最大值
     * @return int 限制后的整数值
     */
    function clamp(int|float $value, int|float $min, int|float $max): int
    {
        return (int) max($min, min($max, $value));
    }
}

if (!function_exists('ease_in_out')) {
    /**
     * 快速缓入缓出插值
     */
    function ease_in_out(float $input, array $inputRange, array $outputRange): float
    {
        return interpolate($input, $inputRange, $outputRange, [
            'easing'           => Easing::easeInOut(...),
            'extrapolateLeft'  => 'clamp',
            'extrapolateRight' => 'clamp',
        ]);
    }
}

if (!function_exists('fade_in')) {
    /**
     * 快速淡入插值（从 0 到 1 的不透明度）
     *
     * @param int $frame     当前帧
     * @param int $startFrame 开始帧
     * @param int $endFrame   结束帧
     */
    function fade_in(int $frame, int $startFrame = 0, int $endFrame = 20): float
    {
        return clamp(
            interpolate($frame, [$startFrame, $endFrame], [0.0, 1.0]),
            0.0,
            1.0
        );
    }
}

if (!function_exists('fade_out')) {
    /**
     * 快速淡出插值（从 1 到 0 的不透明度）
     */
    function fade_out(int $frame, int $startFrame, int $endFrame): float
    {
        return clamp(
            interpolate($frame, [$startFrame, $endFrame], [1.0, 0.0]),
            0.0,
            1.0
        );
    }
}

// ============================================================
// Grafika 图像处理助手函数（支持 GD 和 Imagick）
// ============================================================

if (!function_exists('create_image')) {
    /**
     * 创建空白图像（自动检测 GD 或 Imagick）
     *
     * @param int   $width  宽度
     * @param int   $height 高度
     * @param array $bgColor 背景色 [R, G, B] 或十六进制 '#RRGGBB'
     * @return \Grafika\ImageInterface 图像对象
     */
    function create_image(int $width, int $height, array|string $bgColor = [0, 0, 0]): \Grafika\ImageInterface
    {
        $image = Grafika::createBlankImage($width, $height);
        $editor = Grafika::createEditor();
        
        // 转换颜色格式
        $color = is_array($bgColor) 
            ? sprintf('#%02x%02x%02x', $bgColor[0], $bgColor[1], $bgColor[2])
            : $bgColor;
        
        $editor->fill($image, new Color($color));
        
        return $image;
    }
}

if (!function_exists('open_image')) {
    /**
     * 打开图像文件
     *
     * @param string $path 图像文件路径
     * @return \Grafika\ImageInterface 图像对象
     */
    function open_image(string $path): \Grafika\ImageInterface
    {
        return Grafika::createImage($path);
    }
}

if (!function_exists('save_image')) {
    /**
     * 保存图像到文件
     *
     * @param \Grafika\ImageInterface $image 图像对象
     * @param string   $path 保存路径
     * @param string|null $type 格式类型 (null, 'gif', 'png', 'jpeg')
     * @param int|null $quality JPEG 质量 (0-100)
     */
    function save_image(\Grafika\ImageInterface $image, string $path, ?string $type = null, ?int $quality = null): void
    {
        $editor = Grafika::createEditor();
        $editor->save($image, $path, $type, $quality);
    }
}

if (!function_exists('resize_image')) {
    /**
     * 调整图像大小
     *
     * @param \Grafika\ImageInterface $image 图像对象
     * @param int   $width 新宽度
     * @param int   $height 新高度
     * @param string $mode 模式: 'exact', 'fit', 'fill', 'exactWidth', 'exactHeight'
     */
    function resize_image(\Grafika\ImageInterface &$image, int $width, int $height, string $mode = 'fit'): void
    {
        $editor = Grafika::createEditor();
        $editor->resize($image, $width, $height, $mode);
    }
}

if (!function_exists('draw_text')) {
    /**
     * 在图像上绘制文本（支持中文，使用 Grafika 抽象层）
     *
     * @param \Grafika\ImageInterface $image 图像对象
     * @param string $text 文本内容
     * @param int    $size 字体大小
     * @param int    $x X 坐标
     * @param int    $y Y 坐标（基线位置）
     * @param array|string $color 颜色 [R, G, B] 或 '#RRGGBB'
     * @param string $fontPath 字体路径
     * @param int    $angle 旋转角度
     */
    function draw_text(
        \Grafika\ImageInterface &$image,
        string $text,
        int $size,
        int $x,
        int $y,
        array|string $color = [0, 0, 0],
        string $fontPath = '',
        int $angle = 0
    ): void {
        $editor = Grafika::createEditor();
        
        // 转换颜色格式
        $colorStr = is_array($color)
            ? sprintf('#%02x%02x%02x', $color[0], $color[1], $color[2])
            : $color;
        
        $editor->text($image, $text, $size, $x, $y, new Color($colorStr), $fontPath, $angle);
    }
}

if (!function_exists('draw_gradient')) {
    /**
     * 绘制渐变背景
     *
     * @param \Grafika\ImageInterface $image 图像对象
     * @param array $topColor 顶部颜色 [R, G, B]
     * @param array $bottomColor 底部颜色 [R, G, B]
     * @param bool  $vertical 是否垂直渐变
     */
    function draw_gradient(\Grafika\ImageInterface &$image, array $topColor, array $bottomColor, bool $vertical = true): void
    {
        validate_rgb_color($topColor, 'topColor');
        validate_rgb_color($bottomColor, 'bottomColor');
        
        $width = $image->getWidth();
        $height = $image->getHeight();
        $core = $image->getCore();
        
        // 根据编辑器类型选择实现
        $editorName = Grafika::detectAvailableEditor();
        
        if ($editorName === 'Imagick') {
            // Imagick 实现（更高效）
            $draw = new \ImagickDraw();
            
            if ($vertical) {
                $draw->setFillOpacity(1);
                for ($y = 0; $y < $height; $y++) {
                    $ratio = $y / $height;
                    $r = (int)($topColor[0] + ($bottomColor[0] - $topColor[0]) * $ratio);
                    $g = (int)($topColor[1] + ($bottomColor[1] - $topColor[1]) * $ratio);
                    $b = (int)($topColor[2] + ($bottomColor[2] - $topColor[2]) * $ratio);
                    $draw->setFillColor(sprintf('#%02x%02x%02x', $r, $g, $b));
                    $draw->line(0, $y, $width, $y);
                }
            } else {
                for ($x = 0; $x < $width; $x++) {
                    $ratio = $x / $width;
                    $r = (int)($topColor[0] + ($bottomColor[0] - $topColor[0]) * $ratio);
                    $g = (int)($topColor[1] + ($bottomColor[1] - $topColor[1]) * $ratio);
                    $b = (int)($topColor[2] + ($bottomColor[2] - $topColor[2]) * $ratio);
                    $draw->setFillColor(sprintf('#%02x%02x%02x', $r, $g, $b));
                    $draw->line($x, 0, $x, $height);
                }
            }
            
            $core->drawImage($draw);
        } else {
            // GD 实现
            for ($y = 0; $y < $height; $y++) {
                $ratio = $vertical ? ($y / $height) : 0;
                for ($x = 0; $x < $width; $x++) {
                    if (!$vertical) {
                        $ratio = $x / $width;
                    }
                    $r = (int)($topColor[0] + ($bottomColor[0] - $topColor[0]) * $ratio);
                    $g = (int)($topColor[1] + ($bottomColor[1] - $topColor[1]) * $ratio);
                    $b = (int)($topColor[2] + ($bottomColor[2] - $topColor[2]) * $ratio);
                    $color = imagecolorallocate($core, clamp($r, 0, 255), clamp($g, 0, 255), clamp($b, 0, 255));
                    imagesetpixel($core, $x, $y, $color);
                }
            }
        }
    }
}

if (!function_exists('apply_filter')) {
    /**
     * 应用滤镜效果
     *
     * @param \Grafika\ImageInterface $image 图像对象
     * @param string $filterName 滤镜名称: 'Blur', 'Brightness', 'Contrast', 'Grayscale', 'Sharpen' 等
     * @param mixed  ...$args 滤镜参数
     */
    function apply_filter(\Grafika\ImageInterface &$image, string $filterName, mixed ...$args): void
    {
        $editor = Grafika::createEditor();
        $filter = Grafika::createFilter($filterName, ...$args);
        $editor->apply($image, $filter);
    }
}

if (!function_exists('blend_images')) {
    /**
     * 混合两个图像
     *
     * @param \Grafika\ImageInterface $base 底层图像
     * @param \Grafika\ImageInterface $top 顶层图像
     * @param string $type 混合模式: 'normal', 'multiply', 'overlay', 'screen'
     * @param float  $opacity 不透明度 (0.0-1.0)
     * @param string $position 位置: 'top-left', 'center', 'bottom-right' 等
     */
    function blend_images(
        \Grafika\ImageInterface &$base,
        \Grafika\ImageInterface $top,
        string $type = 'normal',
        float $opacity = 1.0,
        string $position = 'center'
    ): void {
        $editor = Grafika::createEditor();
        $editor->blend($base, $top, $type, $opacity, $position);
    }
}

if (!function_exists('get_gd_image')) {
    /**
     * 从 Grafika 图像获取 GD 资源（用于兼容现有代码）
     *
     * @param \Grafika\ImageInterface $image Grafika 图像对象
     * @return \GdImage GD 图像资源
     */
    function get_gd_image(\Grafika\ImageInterface $image): \GdImage
    {
        $core = $image->getCore();
        
        if ($core instanceof \GdImage) {
            return $core;
        }
        
        // Imagick 转 GD
        $imagick = $core;
        $blob = $imagick->getImageBlob();
        $gd = imagecreatefromstring($blob);
        
        if ($gd === false) {
            throw new \RuntimeException('Failed to convert Imagick to GD');
        }
        
        return $gd;
    }
}

if (!function_exists('image_to_gd')) {
    /**
     * 将 Grafika 图像转换为 GD 图像
     *
     * @param \Grafika\ImageInterface $image Grafika 图像
     * @return \GdImage GD 图像
     */
    function image_to_gd(\Grafika\ImageInterface $image): \GdImage
    {
        return get_gd_image($image);
    }
}

if (!function_exists('gd_to_image')) {
    /**
     * 将 GD 图像转换为 Grafika 图像
     *
     * @param \GdImage $gd GD 图像
     * @return \Grafika\ImageInterface Grafika 图像
     */
    function gd_to_image(\GdImage $gd): \Grafika\ImageInterface
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
}

// ============================================================
// 歌词动画相关助手函数
// ============================================================

if (!function_exists('validate_rgb_color')) {
    /**
     * 验证 RGB 颜色数组
     *
     * @param array  $color 颜色数组 [R, G, B]
     * @param string $name 参数名（用于错误信息）
     * @throws \InvalidArgumentException 颜色格式无效
     */
    function validate_rgb_color(array $color, string $name = 'color'): void
    {
        if (count($color) !== 3) {
            throw new \InvalidArgumentException("{$name} must contain exactly 3 elements [R, G, B]");
        }
        foreach ($color as $i => $value) {
            if (!is_int($value) || $value < 0 || $value > 255) {
                throw new \InvalidArgumentException("{$name}[{$i}] must be integer 0-255, got: " . var_export($value, true));
            }
        }
    }
}

if (!function_exists('parse_lrc_file')) {
    /**
     * 解析 LRC 歌词文件
     *
     * @param string $filepath LRC 文件路径
     * @param array  $skipPrefixes 需要跳过的行前缀（默认过滤作词/作曲等元数据）
     * @return array 歌词数组，每项包含 time(秒), text(歌词), duration(持续秒数)
     */
    function parse_lrc_file(string $filepath, array $skipPrefixes = ['作词', '作曲', '编曲', '混音', '录音', '母带']): array
    {
        if (!file_exists($filepath)) {
            return [];
        }

        $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $lyrics = [];

        foreach ($lines as $line) {
            if (preg_match('/\[(\d{2}):(\d{2})\.(\d{2,3})\](.*)/', $line, $matches)) {
                $minutes = (int) $matches[1];
                $seconds = (int) $matches[2];
                $ms = (int) str_pad($matches[3], 3, '0');
                $time = $minutes * 60 + $seconds + $ms / 1000;
                $text = trim($matches[4]);

                // 跳过空行和元数据行
                if (empty($text)) {
                    continue;
                }

                foreach ($skipPrefixes as $prefix) {
                    if (str_starts_with($text, $prefix)) {
                        continue 2;
                    }
                }

                $lyrics[] = [
                    'time' => $time,
                    'text' => $text,
                ];
            }
        }

        // 按时间排序
        usort($lyrics, fn($a, $b) => $a['time'] <=> $b['time']);

        // 计算每句歌词的持续时间
        for ($i = 0; $i < count($lyrics) - 1; $i++) {
            $lyrics[$i]['duration'] = $lyrics[$i + 1]['time'] - $lyrics[$i]['time'];
        }
        if (!empty($lyrics)) {
            $lyrics[count($lyrics) - 1]['duration'] = DEFAULT_LAST_LYRIC_DURATION;
        }

        return $lyrics;
    }
}

if (!function_exists('find_current_lyric')) {
    /**
     * 根据当前时间找到正在播放的歌词（二分查找优化）
     *
     * @param array $lyrics 歌词数组（由 parse_lrc_file 返回，需按时间排序）
     * @param float $currentTime 当前播放时间（秒）
     * @return array ['index' => 歌词索引, 'progress' => 当前句进度 0-1, 'lyric' => 歌词数据]
     */
    function find_current_lyric(array $lyrics, float $currentTime): array
    {
        $result = ['index' => -1, 'progress' => 0.0, 'lyric' => null];

        if (empty($lyrics)) {
            return $result;
        }

        // 二分查找 O(log n)
        $low = 0;
        $high = count($lyrics) - 1;

        while ($low <= $high) {
            $mid = (int)(($low + $high) / 2);
            $lyric = $lyrics[$mid];
            $end = $lyric['time'] + $lyric['duration'];

            if ($currentTime < $lyric['time']) {
                $high = $mid - 1;
            } elseif ($currentTime >= $end) {
                $low = $mid + 1;
            } else {
                return [
                    'index'    => $mid,
                    'progress' => ($currentTime - $lyric['time']) / $lyric['duration'],
                    'lyric'    => $lyric,
                ];
            }
        }

        return $result;
    }
}

if (!function_exists('validate_font_path')) {
    /**
     * 验证字体文件路径
     *
     * @param string $fontPath 字体路径
     * @throws \InvalidArgumentException 字体不存在
     * @throws \RuntimeException 字体不可读
     */
    function validate_font_path(string $fontPath): void
    {
        if (!file_exists($fontPath)) {
            throw new \InvalidArgumentException("Font file not found: {$fontPath}");
        }
        if (!is_readable($fontPath)) {
            throw new \RuntimeException("Font file not readable: {$fontPath}");
        }
    }
}

if (!function_exists('get_text_bbox')) {
    /**
     * 获取文本边界框（内部辅助函数）
     *
     * @param string $text 文本内容
     * @param int    $size 字体大小
     * @param string $fontPath 字体路径
     * @param int    $angle 旋转角度
     * @return array{width: int, height: int, bbox: array|false}
     * @throws \RuntimeException 计算失败
     */
    function get_text_bbox(string $text, int $size, string $fontPath, int $angle = 0): array
    {
        validate_font_path($fontPath);

        $bbox = @imagettfbbox($size, $angle, $fontPath, $text);
        if ($bbox === false) {
            throw new \RuntimeException("Failed to calculate text bounding box for font: {$fontPath}");
        }

        return [
            'width'  => $bbox[2] - $bbox[0],
            'height' => $bbox[1] - $bbox[7],
            'bbox'   => $bbox,
        ];
    }
}

// ============================================================
// GD 兼容函数（保留用于向后兼容）
// ============================================================

if (!function_exists('draw_chinese_text')) {
    /**
     * 使用 TTF 字体绘制中文文本（GD 兼容函数）
     *
     * @param \GdImage $canvas 画布
     * @param string   $text 文本内容
     * @param int      $x X 坐标
     * @param int      $y Y 坐标（顶部位置）
     * @param int      $size 字体大小
     * @param int      $color 颜色
     * @param string   $fontPath 字体路径
     * @param int      $angle 旋转角度（默认0）
     * @throws \InvalidArgumentException 字体不存在
     * @throws \RuntimeException 渲染失败
     */
    function draw_chinese_text(\GdImage $canvas, string $text, int $x, int $y, int $size, int $color, string $fontPath, int $angle = 0): void
    {
        validate_font_path($fontPath);

        // imagettftext 的 y 坐标是基线位置，需要调整
        $baselineY = $y + $size;
        $result = @imagettftext($canvas, $size, $angle, $x, $baselineY, $color, $fontPath, $text);
        if ($result === false) {
            throw new \RuntimeException("Failed to render text with font: {$fontPath}");
        }
    }
}

if (!function_exists('get_text_width')) {
    /**
     * 获取文本宽度（使用 TTF 字体精确计算）
     *
     * @param string $text 文本内容
     * @param int    $size 字体大小
     * @param string $fontPath 字体路径
     * @return int 文本宽度（像素）
     * @throws \InvalidArgumentException 字体不存在
     * @throws \RuntimeException 计算失败
     */
    function get_text_width(string $text, int $size, string $fontPath): int
    {
        return get_text_bbox($text, $size, $fontPath)['width'];
    }
}

if (!function_exists('get_text_height')) {
    /**
     * 获取文本高度（使用 TTF 字体精确计算）
     *
     * @param string $text 文本内容
     * @param int    $size 字体大小
     * @param string $fontPath 字体路径
     * @return int 文本高度（像素）
     * @throws \InvalidArgumentException 字体不存在
     * @throws \RuntimeException 计算失败
     */
    function get_text_height(string $text, int $size, string $fontPath): int
    {
        return get_text_bbox($text, $size, $fontPath)['height'];
    }
}

if (!function_exists('create_gradient_background')) {
    /**
     * 创建渐变背景（GD 兼容函数）
     *
     * @param int   $width 宽度
     * @param int   $height 高度
     * @param array $topColor 顶部颜色 [R, G, B]
     * @param array $bottomColor 底部颜色 [R, G, B]
     * @param bool  $vertical 是否垂直渐变（默认 true）
     * @return \GdImage 画布
     * @throws \InvalidArgumentException 颜色格式无效
     */
    function create_gradient_background(int $width, int $height, array $topColor, array $bottomColor, bool $vertical = true): \GdImage
    {
        // 验证颜色参数
        validate_rgb_color($topColor, 'topColor');
        validate_rgb_color($bottomColor, 'bottomColor');

        $canvas = imagecreatetruecolor($width, $height);

        // 预计算颜色差值
        $dr = $bottomColor[0] - $topColor[0];
        $dg = $bottomColor[1] - $topColor[1];
        $db = $bottomColor[2] - $topColor[2];

        if ($vertical) {
            for ($y = 0; $y < $height; $y++) {
                $ratio = $y / $height;
                $r = (int)($topColor[0] + $dr * $ratio);
                $g = (int)($topColor[1] + $dg * $ratio);
                $b = (int)($topColor[2] + $db * $ratio);
                $color = imagecolorallocate($canvas, clamp($r, 0, 255), clamp($g, 0, 255), clamp($b, 0, 255));
                imageline($canvas, 0, $y, $width, $y, $color);
            }
        } else {
            for ($x = 0; $x < $width; $x++) {
                $ratio = $x / $width;
                $r = (int)($topColor[0] + $dr * $ratio);
                $g = (int)($topColor[1] + $dg * $ratio);
                $b = (int)($topColor[2] + $db * $ratio);
                $color = imagecolorallocate($canvas, clamp($r, 0, 255), clamp($g, 0, 255), clamp($b, 0, 255));
                imageline($canvas, $x, 0, $x, $height, $color);
            }
        }

        return $canvas;
    }
}

if (!function_exists('add_noise_texture')) {
    /**
     * 添加噪点纹理效果（GD 兼容函数）
     *
     * @param \GdImage $canvas 画布
     * @param int      $count 噪点数量
     * @param array    $color 噪点颜色 [R, G, B]
     * @param int      $alpha 透明度 (0-127)
     */
    function add_noise_texture(\GdImage $canvas, int $count = DEFAULT_NOISE_COUNT, array $color = [255, 50, 30], int $alpha = 120): void
    {
        $width = imagesx($canvas);
        $height = imagesy($canvas);
        $alpha = clamp($alpha, 0, 127);
        $noiseColor = imagecolorallocatealpha($canvas, $color[0], $color[1], $color[2], $alpha);

        for ($i = 0; $i < $count; $i++) {
            $x = rand(0, $width - 1);
            $y = rand(0, $height - 1);
            imagesetpixel($canvas, $x, $y, $noiseColor);
        }
    }
}

if (!function_exists('draw_shadowed_text')) {
    /**
     * 绘制多层阴影文字效果（GD 兼容函数）
     *
     * @param \GdImage $canvas 画布
     * @param string   $text 文本内容
     * @param int      $centerX 中心 X 坐标
     * @param int      $centerY 中心 Y 坐标
     * @param int      $fontSize 字体大小
     * @param string   $fontPath 字体路径
     * @param array    $options 选项：
     *   - shadowLayers: 阴影层数（默认 8）
     *   - shadowOffset: 每层偏移量（默认 2）
     *   - shadowColor: 阴影颜色 [R, G, B]（默认 [120, 20, 20]）
     *   - mainColor: 主文字颜色 [R, G, B]（默认 [255, 100, 50]）
     *   - alpha: 透明度（默认 0-127，0=不透明）
     *   - fadeIn: 淡入进度 0-1（默认 1.0）
     */
    function draw_shadowed_text(\GdImage $canvas, string $text, int $centerX, int $centerY, int $fontSize, string $fontPath, array $options = []): void
    {
        validate_font_path($fontPath);

        $shadowLayers = $options['shadowLayers'] ?? DEFAULT_SHADOW_LAYERS;
        $shadowOffset = $options['shadowOffset'] ?? DEFAULT_SHADOW_OFFSET;
        $shadowColor = $options['shadowColor'] ?? [120, 20, 20];
        $mainColor = $options['mainColor'] ?? [255, 100, 50];
        $alpha = $options['alpha'] ?? 0;
        $fadeIn = $options['fadeIn'] ?? 1.0;

        // 计算文本尺寸
        $textInfo = get_text_bbox($text, $fontSize, $fontPath);
        $textWidth = $textInfo['width'];
        $textHeight = $textInfo['height'];

        $x = (int)($centerX - $textWidth / 2);
        $y = (int)($centerY + $textHeight / 4);

        // 绘制阴影层
        for ($layer = $shadowLayers; $layer >= 1; $layer--) {
            $offsetX = $layer * $shadowOffset;
            $offsetY = $layer * $shadowOffset;
            $shadowAlpha = (int)(127 - ($shadowLayers - $layer) * (100 / $shadowLayers));
            $color = imagecolorallocatealpha($canvas, $shadowColor[0], $shadowColor[1], $shadowColor[2], clamp($shadowAlpha, 0, 127));
            imagettftext($canvas, $fontSize, 0, $x + $offsetX, $y + $offsetY, $color, $fontPath, $text);
        }

        // 绘制主文字
        $finalAlpha = $alpha ?: (int)(127 * (1 - $fadeIn));
        $color = imagecolorallocatealpha($canvas, $mainColor[0], $mainColor[1], $mainColor[2], clamp($finalAlpha, 0, 127));
        imagettftext($canvas, $fontSize, 0, $x, $y, $color, $fontPath, $text);
    }
}

if (!function_exists('draw_cinematic_text')) {
    /**
     * 绘制电影级多层文字效果（GD 兼容函数）
     *
     * @param \GdImage $canvas 画布
     * @param string   $text 文本内容
     * @param int      $centerX 中心 X 坐标
     * @param int      $centerY 中心 Y 坐标
     * @param int      $fontSize 字体大小
     * @param string   $fontPath 字体路径
     * @param float    $fadeIn 淡入进度 0-1
     */
    function draw_cinematic_text(\GdImage $canvas, string $text, int $centerX, int $centerY, int $fontSize, string $fontPath, float $fadeIn = 1.0): void
    {
        validate_font_path($fontPath);

        // 计算文本尺寸
        $textInfo = get_text_bbox($text, $fontSize, $fontPath);
        $textWidth = $textInfo['width'];
        $textHeight = $textInfo['height'];

        $x = (int)($centerX - $textWidth / 2);
        $y = (int)($centerY + $textHeight / 4);

        $alpha = (int)(127 * (1 - $fadeIn));

        // 1. 厚重阴影层
        for ($layer = CINEMATIC_SHADOW_LAYERS; $layer >= 1; $layer--) {
            $offsetX = $layer * CINEMATIC_SHADOW_OFFSET;
            $offsetY = $layer * CINEMATIC_SHADOW_OFFSET;
            $shadowAlpha = (int)(127 - (CINEMATIC_SHADOW_LAYERS - $layer) * 5);
            $shadowColor = imagecolorallocatealpha($canvas, 139, 0, 0, clamp($shadowAlpha, 0, 127));
            imagettftext($canvas, $fontSize, 0, $x + $offsetX, $y + $offsetY, $shadowColor, $fontPath, $text);
        }

        // 2. 背景层叠文字（更大更淡）
        $bgSize = (int)($fontSize * CINEMATIC_BG_FONT_RATIO / 100);
        $bgInfo = get_text_bbox($text, $bgSize, $fontPath);
        $bgWidth = $bgInfo['width'];
        $bgColor = imagecolorallocatealpha($canvas, 80, 0, 0, 110);
        imagettftext($canvas, $bgSize, 0, (int)($centerX - $bgWidth / 2), (int)($y + 20), $bgColor, $fontPath, $text);

        // 3. 橙色发光层
        $glowColor = imagecolorallocatealpha($canvas, 255, 140, 0, 100);
        for ($g = -3; $g <= 3; $g += 2) {
            imagettftext($canvas, $fontSize, 0, $x + $g, $y + $g, $glowColor, $fontPath, $text);
        }

        // 4. 主文字（红色）
        $mainColor = imagecolorallocatealpha($canvas, 220, 50, 30, clamp($alpha, 0, 127));
        imagettftext($canvas, $fontSize, 0, $x, $y, $mainColor, $fontPath, $text);

        // 5. 高光层（黄色）
        $highlightAlpha = clamp($alpha + 30, 0, 127);
        $highlightColor = imagecolorallocatealpha($canvas, 255, 200, 100, $highlightAlpha);
        imagettftext($canvas, $fontSize, 0, $x - 1, $y - 2, $highlightColor, $fontPath, $text);
    }
}

if (!function_exists('render_progress_bar')) {
    /**
     * 渲染进度条回调（用于 Remotion 渲染器）
     *
     * @return callable
     */
    function render_progress_bar(): callable
    {
        return function (int $frame, int $total): void {
            $pct = (int) round($frame / $total * 100);
            $filled = (int) ($pct / (100 / PROGRESS_BAR_WIDTH));
            $bar = str_repeat('█', $filled) . str_repeat('░', PROGRESS_BAR_WIDTH - $filled);
            echo "\r  [{$bar}] {$pct}%";
        };
    }
}

if (!function_exists('calculate_font_size')) {
    /**
     * 根据文本长度自动计算合适的字体大小
     *
     * @param string $text 文本内容
     * @param int    $maxSize 最大字体大小
     * @param int    $minSize 最小字体大小
     * @param int    $targetWidth 目标宽度（默认画布宽度80%）
     * @param string $fontPath 字体路径
     * @return int 计算后的字体大小
     */
    function calculate_font_size(string $text, int $maxSize, int $minSize, int $targetWidth, string $fontPath): int
    {
        $textLen = mb_strlen($text);

        // 简单规则：根据字数调整
        if ($textLen <= 4) {
            return $maxSize;
        } elseif ($textLen <= 8) {
            return (int)($maxSize * 0.75);
        } elseif ($textLen <= 12) {
            return (int)($maxSize * 0.6);
        } else {
            return max($minSize, (int)($maxSize * 0.5));
        }
    }
}

if (!function_exists('get_chinese_font')) {
    /**
     * 获取系统中文字体路径
     *
     * @return string|null 字体路径，找不到返回 null
     */
    function get_chinese_font(): ?string
    {
        $fonts = [
            '/Library/Fonts/WenQuanWeiMiHei-1.ttf',     // 文泉驿微米黑
            '/Library/Fonts/WenQuanZhengHei-1.ttf',     // 文泉驿正黑
            '/Library/Fonts/Arial Unicode.ttf',          // Arial Unicode
            '/System/Library/Fonts/PingFang.ttc',        // 苹方
            '/System/Library/Fonts/STHeiti Light.ttc',   // 黑体
            '/usr/share/fonts/truetype/wqy/wqy-microhei.ttc', // Linux
            '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc', // Linux Noto
        ];

        foreach ($fonts as $font) {
            if (file_exists($font)) {
                return $font;
            }
        }

        return null;
    }
}