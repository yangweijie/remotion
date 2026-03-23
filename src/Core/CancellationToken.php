<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Core;

/**
 * 取消令牌
 *
 * 用于在渲染过程中安全地取消长时间运行的操作。
 * 基于协作式取消模式，渲染代码需要定期检查是否被取消。
 *
 * 使用示例：
 * ```php
 * $token = new CancellationToken();
 *
 * // 在另一个线程/信号处理器中取消
 * $token->cancel();
 *
 * // 在渲染循环中检查
 * for ($frame = 0; $frame < $total; $frame++) {
 *     $token->throwIfCancelled(); // 如果已取消则抛出异常
 *     // 渲染帧...
 * }
 * ```
 */
class CancellationToken
{
    private bool $cancelled = false;

    /** @var string|null 取消原因 */
    private ?string $reason = null;

    /** @var array<callable> 取消监听器 */
    private array $listeners = [];

    /** @var int 监听器ID计数器 */
    private int $listenerId = 0;

    /**
     * 检查是否已取消
     */
    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    /**
     * 取消操作
     *
     * @param string|null $reason 取消原因（用于调试）
     */
    public function cancel(?string $reason = null): void
    {
        if ($this->cancelled) {
            return;
        }

        $this->cancelled = true;
        $this->reason = $reason;

        // 通知所有监听器
        foreach ($this->listeners as $listener) {
            try {
                $listener($reason);
            } catch (\Throwable $e) {
                // 监听器异常不应阻止取消流程
            }
        }
    }

    /**
     * 如果已取消则抛出异常
     *
     * @throws RenderCancelledException
     */
    public function throwIfCancelled(): void
    {
        if ($this->cancelled) {
            throw new RenderCancelledException(
                $this->reason ?? '渲染已取消'
            );
        }
    }

    /**
     * 获取取消原因
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }

    /**
     * 注册取消监听器
     *
     * @param callable $listener function(?string $reason): void
     * @return int 监听器ID（用于注销）
     */
    public function registerListener(callable $listener): int
    {
        $id = ++$this->listenerId;
        $this->listeners[$id] = $listener;
        return $id;
    }

    /**
     * 注销取消监听器
     */
    public function unregisterListener(int $id): bool
    {
        if (!isset($this->listeners[$id])) {
            return false;
        }

        unset($this->listeners[$id]);
        return true;
    }

    /**
     * 创建链接令牌（任一源令牌取消时此令牌也取消）
     *
     * @param array<self> $tokens
     */
    public static function linked(array $tokens): self
    {
        $linked = new self();

        foreach ($tokens as $token) {
            if ($token->isCancelled()) {
                $linked->cancel($token->getReason());
                return $linked;
            }

            $token->registerListener(function (?string $reason) use ($linked) {
                $linked->cancel($reason);
            });
        }

        return $linked;
    }

    /**
     * 创建带超时的令牌
     *
     * @param float $timeoutSeconds 超时时间（秒）
     */
    public static function withTimeout(float $timeoutSeconds): self
    {
        $token = new self();

        // 使用异步延迟取消
        \Psl\Async\run(function () use ($token, $timeoutSeconds): void {
            // 延迟指定时间
            $ms = (int) ($timeoutSeconds * 1000);
            if ($ms > 0) {
                usleep($ms * 1000);
            }
            $token->cancel("操作超时 ({$timeoutSeconds}秒)");
        });

        return $token;
    }

    /**
     * 创建永不过期的令牌（用于禁用取消功能）
     */
    public static function none(): self
    {
        return new self();
    }
}

/**
 * 渲染取消异常
 */
class RenderCancelledException extends \RuntimeException
{
    public function __construct(string $message = '渲染已取消', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
