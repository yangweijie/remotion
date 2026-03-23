<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Core;

use Yangweijie\Remotion\Contracts\ComponentInterface;
use Yangweijie\Remotion\Transitions\Transition;

/**
 * SequenceWithTransition
 *
 * 支持平滑过渡效果的 Sequence 组件。
 * 在 Sequence 之间切换时自动应用转场动画。
 *
 * 使用示例：
 * ```php
 * // 创建带过渡的 Sequence
 * $seq1 = new SequenceWithTransition(
 *     id: 'intro',
 *     component: $introComponent,
 *     from: 0,
 *     durationInFrames: 60,
 *     transitionIn: new FadeTransition(15),  // 淡入
 *     transitionOut: new SlideTransition(15, 'right'),  // 向右滑出
 * );
 *
 * $seq2 = new SequenceWithTransition(
 *     id: 'main',
 *     component: $mainComponent,
 *     from: 60,
 *     durationInFrames: 120,
 *     transitionIn: new FadeTransition(15),  // 从前一个 Sequence 淡入
 * );
 * ```
 */
class SequenceWithTransition extends Sequence
{
    /** @var Transition|null 进入转场 */
    private ?Transition $transitionIn;

    /** @var Transition|null 退出转场 */
    private ?Transition $transitionOut;

    /** @var Sequence|null 前一个 Sequence（用于过渡） */
    private ?Sequence $previousSequence = null;

    /** @var Sequence|null 后一个 Sequence（用于过渡） */
    private ?Sequence $nextSequence = null;

    public function __construct(
        string $id,
        ComponentInterface|\Closure $component,
        int $from = 0,
        ?int $durationInFrames = null,
        string $name = '',
        bool $layout = true,
        ?Transition $transitionIn = null,
        ?Transition $transitionOut = null,
    ) {
        parent::__construct($id, $component, $from, $durationInFrames, $name, $layout);
        $this->transitionIn = $transitionIn;
        $this->transitionOut = $transitionOut;
    }

    /**
     * 静态工厂方法
     */
    public static function make(
        string $id,
        ComponentInterface|\Closure $component,
        int $from = 0,
        ?int $durationInFrames = null,
        string $name = '',
        ?Transition $transitionIn = null,
        ?Transition $transitionOut = null,
    ): self {
        return new self($id, $component, $from, $durationInFrames, $name, true, $transitionIn, $transitionOut);
    }

    /**
     * 设置前一个 Sequence（用于计算过渡）
     */
    public function setPreviousSequence(?Sequence $sequence): self
    {
        $this->previousSequence = $sequence;
        return $this;
    }

    /**
     * 设置后一个 Sequence（用于计算过渡）
     */
    public function setNextSequence(?Sequence $sequence): self
    {
        $this->nextSequence = $sequence;
        return $this;
    }

    /**
     * 设置进入转场
     */
    public function withTransitionIn(?Transition $transition): self
    {
        $this->transitionIn = $transition;
        return $this;
    }

    /**
     * 设置退出转场
     */
    public function withTransitionOut(?Transition $transition): self
    {
        $this->transitionOut = $transition;
        return $this;
    }

    /**
     * 渲染序列（带过渡效果）
     */
    public function renderWithTransition(
        RenderContext $ctx,
        VideoConfig $compositionConfig,
        ?\GdImage $previousFrame = null,
        ?\GdImage $nextFrame = null,
    ): ?\GdImage {
        $totalDuration = $this->getDurationInFrames() ?? $compositionConfig->durationInFrames - $this->getFrom();
        $globalFrame = $ctx->getCurrentFrame();

        // 判断当前帧是否在此序列的活跃范围内
        if ($globalFrame < $this->getFrom() || $globalFrame >= $this->getFrom() + $totalDuration) {
            return null;
        }

        $localFrame = $this->getLocalFrame($globalFrame);

        // 渲染当前序列内容
        $localCtx = $ctx->withFrameOffset($this->getFrom());
        $currentFrame = $this->getComponent()->render($localCtx);

        // 处理进入过渡
        if ($this->transitionIn !== null && $previousFrame !== null) {
            $transitionDuration = $this->transitionIn->getDuration();

            if ($localFrame < $transitionDuration) {
                $progress = ($localFrame + 1) / $transitionDuration;
                $currentFrame = $this->transitionIn->apply($previousFrame, $currentFrame, $progress);
            }
        }

        // 处理退出过渡
        if ($this->transitionOut !== null && $nextFrame !== null) {
            $transitionDuration = $this->transitionOut->getDuration();
            $exitStartFrame = $totalDuration - $transitionDuration;

            if ($localFrame >= $exitStartFrame) {
                $progress = ($localFrame - $exitStartFrame + 1) / $transitionDuration;
                $currentFrame = $this->transitionOut->apply($currentFrame, $nextFrame, $progress);
            }
        }

        return $currentFrame;
    }

    /**
     * 获取进入转场
     */
    public function getTransitionIn(): ?Transition
    {
        return $this->transitionIn;
    }

    /**
     * 获取退出转场
     */
    public function getTransitionOut(): ?Transition
    {
        return $this->transitionOut;
    }

    /**
     * 检查当前帧是否处于进入过渡中
     */
    public function isInTransitionIn(int $globalFrame): bool
    {
        if ($this->transitionIn === null) {
            return false;
        }

        $localFrame = $this->getLocalFrame($globalFrame);
        return $localFrame >= 0 && $localFrame < $this->transitionIn->getDuration();
    }

    /**
     * 检查当前帧是否处于退出过渡中
     */
    public function isInTransitionOut(int $globalFrame, VideoConfig $compositionConfig): bool
    {
        if ($this->transitionOut === null) {
            return false;
        }

        $totalDuration = $this->getDurationInFrames() ?? $compositionConfig->durationInFrames - $this->getFrom();
        $localFrame = $this->getLocalFrame($globalFrame);
        $exitStartFrame = $totalDuration - $this->transitionOut->getDuration();

        return $localFrame >= $exitStartFrame && $localFrame < $totalDuration;
    }

    /**
     * 检查当前帧是否处于任何过渡中
     */
    public function isInAnyTransition(int $globalFrame, VideoConfig $compositionConfig): bool
    {
        return $this->isInTransitionIn($globalFrame) || $this->isInTransitionOut($globalFrame, $compositionConfig);
    }

    /**
     * 获取过渡进度 (0-1)
     */
    public function getTransitionProgress(int $globalFrame, VideoConfig $compositionConfig): ?float
    {
        if ($this->isInTransitionIn($globalFrame)) {
            $localFrame = $this->getLocalFrame($globalFrame);
            return ($localFrame + 1) / $this->transitionIn->getDuration();
        }

        if ($this->isInTransitionOut($globalFrame, $compositionConfig)) {
            $totalDuration = $this->getDurationInFrames() ?? $compositionConfig->durationInFrames - $this->getFrom();
            $localFrame = $this->getLocalFrame($globalFrame);
            $exitStartFrame = $totalDuration - $this->transitionOut->getDuration();
            return ($localFrame - $exitStartFrame + 1) / $this->transitionOut->getDuration();
        }

        return null;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'transitionIn'  => $this->transitionIn !== null ? get_class($this->transitionIn) : null,
            'transitionOut' => $this->transitionOut !== null ? get_class($this->transitionOut) : null,
        ]);
    }
}

/**
 * SequenceTransitionManager
 *
 * 管理多个 Sequence 之间的过渡效果
 */
class SequenceTransitionManager
{
    /** @var SequenceWithTransition[] */
    private array $sequences = [];

    /**
     * 添加带过渡的 Sequence
     */
    public function add(SequenceWithTransition $sequence): self
    {
        $this->sequences[] = $sequence;
        $this->updateSequenceLinks();
        return $this;
    }

    /**
     * 渲染指定帧
     */
    public function render(int $frame, RenderContext $ctx, VideoConfig $config): ?\GdImage
    {
        // 找到当前活跃的 Sequence
        $activeSequence = null;
        $activeIndex = -1;

        foreach ($this->sequences as $index => $sequence) {
            if ($sequence->isActive($frame, $config)) {
                $activeSequence = $sequence;
                $activeIndex = $index;
                break;
            }
        }

        if ($activeSequence === null) {
            return null;
        }

        // 准备前后帧用于过渡
        $previousFrame = null;
        $nextFrame = null;

        if ($activeSequence->getTransitionIn() !== null && $activeIndex > 0) {
            $prevSequence = $this->sequences[$activeIndex - 1];
            if ($prevSequence->isActive($frame, $config)) {
                $previousFrame = $prevSequence->render($ctx, $config);
            }
        }

        if ($activeSequence->getTransitionOut() !== null && $activeIndex < count($this->sequences) - 1) {
            $nextSequence = $this->sequences[$activeIndex + 1];
            $nextCtx = $ctx->withFrameOffset($activeSequence->getFrom());
            $nextFrame = $nextSequence->getComponent()->render($nextCtx);
        }

        return $activeSequence->renderWithTransition($ctx, $config, $previousFrame, $nextFrame);
    }

    /**
     * 更新 Sequence 之间的链接关系
     */
    private function updateSequenceLinks(): void
    {
        $count = count($this->sequences);

        for ($i = 0; $i < $count; $i++) {
            $sequence = $this->sequences[$i];

            if ($i > 0) {
                $sequence->setPreviousSequence($this->sequences[$i - 1]);
            }

            if ($i < $count - 1) {
                $sequence->setNextSequence($this->sequences[$i + 1]);
            }
        }
    }

    /**
     * 获取所有 Sequence
     *
     * @return SequenceWithTransition[]
     */
    public function getSequences(): array
    {
        return $this->sequences;
    }

    /**
     * 清空所有 Sequence
     */
    public function clear(): void
    {
        $this->sequences = [];
    }
}
