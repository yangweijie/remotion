<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Rendering;

/**
 * AnimatedGifEncoder
 *
 * PHP 8.0+ 兼容的动态 GIF 编码器，替代不兼容 PHP 8.0+ 的 sybio/gif-creator。
 * 纯 PHP 实现，无外部依赖，支持 GD 图像直接输入。
 *
 * 基于 GIF89a 规范实现：
 * @see https://www.w3.org/Graphics/GIF/spec-gif89a.txt
 */
class AnimatedGifEncoder
{
    /** @var string[] GIF 帧数据（原始 GIF89a 字节流） */
    private array $frames = [];

    /** @var int[] 帧延迟（厘秒，1/100 秒） */
    private array $delays = [];

    /** @var string 编码后的 GIF 数据 */
    private string $gif = '';

    private int $loop;

    /**
     * @param int $loop 循环次数，0 = 无限循环
     */
    public function __construct(int $loop = 0)
    {
        $this->loop = $loop;
    }

    /**
     * 添加一帧（GD 图像）
     *
     * @param \GdImage $image  GD 图像资源
     * @param int      $delayCs 延迟（厘秒）
     */
    public function addFrame(\GdImage $image, int $delayCs = 10): self
    {
        ob_start();
        imagegif($image);
        $data = ob_get_clean();

        if (empty($data)) {
            throw new \RuntimeException('Failed to convert GD image to GIF');
        }

        $this->frames[] = $data;
        $this->delays[] = $delayCs;

        return $this;
    }

    /**
     * 添加一帧（原始 GIF 字节流）
     */
    public function addRawFrame(string $gifData, int $delayCs = 10): self
    {
        $this->frames[] = $gifData;
        $this->delays[] = $delayCs;
        return $this;
    }

    /**
     * 编码所有帧为动态 GIF
     *
     * @return string 动态 GIF 字节流
     */
    public function encode(): string
    {
        if (empty($this->frames)) {
            throw new \RuntimeException('No frames to encode');
        }

        $this->gif = '';
        $this->addHeader();

        for ($i = 0; $i < count($this->frames); $i++) {
            $this->addFrameData($i);
        }

        $this->addFooter();
        return $this->gif;
    }

    /**
     * 将编码结果写入文件
     */
    public function saveToFile(string $path): bool
    {
        return file_put_contents($path, $this->encode()) !== false;
    }

    // ---- GIF 结构构建 ----

    private function addHeader(): void
    {
        $first = $this->frames[0];

        // GIF89a 签名 + 逻辑屏幕描述符（从第一帧复制）
        // 字节 6-12：宽高、全局颜色表标志等
        $screenDesc = substr($first, 6, 7);
        $this->gif  = 'GIF89a' . $screenDesc;

        // 全局颜色表（如果存在）
        $flags = ord($first[10]);
        if ($flags & 0x80) {
            $colorTableSize = 3 * (2 << ($flags & 0x07));
            $this->gif .= substr($first, 13, $colorTableSize);
        }

        // Netscape 应用扩展（启用循环）
        $loopCount = $this->loop;
        $this->gif .= "!\xFF\x0BNETSCAPE2.0\x03\x01"
            . chr($loopCount & 0xFF)
            . chr(($loopCount >> 8) & 0xFF)
            . "\x00";
    }

    private function addFrameData(int $i): void
    {
        $frameData = $this->frames[$i];
        $delay     = $this->delays[$i];
        $flags     = ord($frameData[10]);

        // 计算颜色表偏移
        $colorTableStart = 13;
        $colorTableSize  = 0;
        if ($flags & 0x80) {
            $colorTableSize = 3 * (2 << ($flags & 0x07));
        }

        // 图形控制扩展（设置帧延迟）
        $this->gif .= "!\xF9\x04"     // GCE 标识
            . "\x00"                   // 处置方式：不处置
            . chr($delay & 0xFF)       // 延迟低字节
            . chr(($delay >> 8) & 0xFF) // 延迟高字节
            . "\x00\x00";             // 透明色索引（无）

        // 定位图像块开始（跳过全局颜色表和文件头）
        $offset = $colorTableStart + $colorTableSize;
        $len    = strlen($frameData);

        // 遍历找到图像块（','）并输出后续内容
        while ($offset < $len) {
            $block = $frameData[$offset];

            if ($block === ',') {
                // 图像描述符（从 ',' 开始，共 10 字节）
                $this->gif .= substr($frameData, $offset);
                return;
            }

            if ($block === '!') {
                // 扩展块，跳过
                $offset += 2; // 扩展标识 + 标签
                $subBlockSize = ord($frameData[$offset] ?? "\x00");
                while ($subBlockSize > 0 && $offset < $len) {
                    $offset += $subBlockSize + 1;
                    $subBlockSize = ord($frameData[$offset] ?? "\x00");
                }
                $offset++;
            } else {
                $offset++;
            }
        }
    }

    private function addFooter(): void
    {
        $this->gif .= ';';
    }
}
