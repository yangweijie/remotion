<?php

declare(strict_types=1);

namespace Yangweijie\Remotion\Tests;

use Yangweijie\Remotion\Animation\Easing;
use Yangweijie\Remotion\Animation\Interpolate;
use Yangweijie\Remotion\Animation\Spring;
use Yangweijie\Remotion\Helpers\Color;
use Yangweijie\Remotion\Core\Sequence;
use Yangweijie\Remotion\Core\RenderContext;
use Yangweijie\Remotion\Core\VideoConfig;
use Yangweijie\Remotion\Contracts\ComponentInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

uses(TestCase::class);

beforeEach(function () {
    // 测试前清理
});

afterEach(function () {
    // 测试后清理
});

// ============================================================
// Easing 测试
// ============================================================
describe('Easing', function () {

    it('linear should return same value', function () {
        expect(Easing::linear(0.0))->toBe(0.0);
        expect(Easing::linear(0.5))->toBe(0.5);
        expect(Easing::linear(1.0))->toBe(1.0);
    });

    it('ease should be between 0 and 1', function () {
        $values = [0.0, 0.25, 0.5, 0.75, 1.0];
        foreach ($values as $t) {
            $result = Easing::ease($t);
            expect($result)->toBeGreaterThanOrEqual(0.0);
            expect($result)->toBeLessThanOrEqual(1.0);
        }
    });

    it('easeIn should start slow', function () {
        $eased = Easing::easeIn(0.1);
        expect($eased)->toBeLessThan(0.1);
    });

    it('easeOut should end slow', function () {
        $eased = Easing::easeOut(0.9);
        expect($eased)->toBeGreaterThan(0.9);
    });

    it('easeInOut should be symmetric', function () {
        $t = 0.3;
        $eased = Easing::easeInOut($t);
        $mirrored = 1 - Easing::easeInOut(1 - $t);
        expect(abs($eased - $mirrored))->toBeLessThan(0.01);
    });

    it('quad should return t^2', function () {
        expect(Easing::quad(0.0))->toBe(0.0);
        expect(Easing::quad(1.0))->toBe(1.0);
        expect(Easing::quad(2.0))->toBe(4.0);
    });

    it('cubic should return t^3', function () {
        expect(Easing::cubic(0.0))->toBe(0.0);
        expect(Easing::cubic(1.0))->toBe(1.0);
        expect(Easing::cubic(2.0))->toBe(8.0);
    });

    it('sin should return correct value', function () {
        $result = Easing::sin(0.0);
        expect(abs($result - 0.0))->toBeLessThan(0.00001);
        $result = Easing::sin(1.0);
        expect(abs($result - 1.0))->toBeLessThan(0.00001);
    });

    it('circle should return correct value', function () {
        expect(Easing::circle(0.0))->toBe(0.0);
        expect(Easing::circle(1.0))->toBe(1.0);
    });

    it('exp should handle edge cases', function () {
        expect(Easing::exp(0.0))->toBe(0.0);
        expect(Easing::exp(1.0))->toBe(1.0);
    });

    it('bounce should return correct range', function () {
        $values = [0.0, 0.25, 0.5, 0.75, 1.0];
        foreach ($values as $t) {
            $result = Easing::bounce($t);
            expect($result)->toBeGreaterThanOrEqual(0.0);
            expect($result)->toBeLessThanOrEqual(1.0);
        }
    });

    it('elastic should be callable', function () {
        $elastic = Easing::elastic(1.0);
        expect($elastic(0.0))->toBe(0.0);
        expect($elastic(1.0))->toBe(1.0);
    });

    it('back should be callable', function () {
        $back = Easing::back(1.70158);
        expect(is_callable($back))->toBeTrue();
    });

    it('bezier should return values between 0 and 1', function () {
        $bezier = Easing::bezier(0.25, 0.1, 0.25, 1.0);
        $values = [0.0, 0.25, 0.5, 0.75, 1.0];
        foreach ($values as $t) {
            $result = $bezier($t);
            expect($result)->toBeGreaterThanOrEqual(0.0);
            expect($result)->toBeLessThanOrEqual(1.0);
        }
    });

    it('in should return the same easing', function () {
        $easing = fn(float $t): float => $t * $t;
        $inEasing = Easing::in($easing);
        expect($inEasing(0.5))->toBe(0.25);
    });

    it('out should reverse easing', function () {
        $easing = fn(float $t): float => $t * $t;
        $outEasing = Easing::out($easing);
        $result = $outEasing(0.5);
        expect($result)->toBeGreaterThan(0.5);
        expect($result)->toBeLessThanOrEqual(1.0);
    });

    it('inOut should combine easing', function () {
        $easing = fn(float $t): float => $t * $t;
        $inOutEasing = Easing::inOut($easing);
        expect($inOutEasing(0.0))->toBe(0.0);
        expect($inOutEasing(1.0))->toBe(1.0);
    });
});

// ============================================================
// Interpolate 测试
// ============================================================
describe('Interpolate', function () {

    it('should interpolate between two values', function () {
        $result = Interpolate::interpolate(0.5, [0, 1], [0, 100]);
        expect($result)->toBe(50.0);
    });

    it('should interpolate at boundaries', function () {
        expect(Interpolate::interpolate(0.0, [0, 1], [0, 100]))->toBe(0.0);
        expect(Interpolate::interpolate(1.0, [0, 1], [0, 100]))->toBe(100.0);
    });

    it('should handle multiple keyframes', function () {
        $result = Interpolate::interpolate(50, [0, 50, 100], [0, 50, 100]);
        expect($result)->toBe(50.0);
    });

    it('should apply easing function', function () {
        $result = Interpolate::interpolate(0.5, [0, 1], [0, 100], [
            'easing' => fn(float $t): float => $t * $t,
        ]);
        expect($result)->toBe(25.0);
    });

    it('should clamp left boundary', function () {
        $result = Interpolate::interpolate(-0.5, [0, 1], [0, 100], [
            'extrapolateLeft' => 'clamp',
        ]);
        expect($result)->toBe(0.0);
    });

    it('should clamp right boundary', function () {
        $result = Interpolate::interpolate(1.5, [0, 1], [0, 100], [
            'extrapolateRight' => 'clamp',
        ]);
        expect($result)->toBe(100.0);
    });

    it('should extend left boundary by default', function () {
        $result = Interpolate::interpolate(-0.5, [0, 1], [0, 100]);
        expect($result)->toBe(-50.0);
    });

    it('should extend right boundary by default', function () {
        $result = Interpolate::interpolate(1.5, [0, 1], [0, 100]);
        expect($result)->toBe(150.0);
    });

    it('should use identity extrapolation for out of range input', function () {
        // identity 在范围外时返回输入值本身
        $result = Interpolate::interpolate(-1.0, [0, 1], [0, 100], [
            'extrapolateLeft' => 'identity',
        ]);
        expect($result)->toBe(-1.0);
        
        $result = Interpolate::interpolate(2.0, [0, 1], [0, 100], [
            'extrapolateRight' => 'identity',
        ]);
        expect($result)->toBe(2.0);
    });

    it('should throw on mismatched range lengths', function () {
        expect(fn () => Interpolate::interpolate(0.5, [0, 1, 2], [0, 100]))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('should throw on less than 2 elements', function () {
        expect(fn () => Interpolate::interpolate(0.5, [0], [0]))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('should interpolate colors', function () {
        $color = Interpolate::interpolateColors(
            0.5,
            [0, 1],
            [[255, 0, 0], [0, 0, 255]]
        );
        expect($color[0])->toBe(128);
        expect($color[1])->toBe(0);
        // 允许小偏差
        expect(abs($color[2] - 128))->toBeLessThan(2);
    });

    it('should clamp color values', function () {
        $color = Interpolate::interpolateColors(
            2.0,
            [0, 1],
            [[255, 0, 0], [0, 0, 255]]
        );
        expect($color[0])->toBeGreaterThanOrEqual(0);
        expect($color[0])->toBeLessThanOrEqual(255);
    });
});

// ============================================================
// Spring 测试
// ============================================================
describe('Spring', function () {

    it('should return from value at frame 0', function () {
        $result = Spring::spring(0, 30, ['from' => 10, 'to' => 100]);
        expect($result)->toBe(10.0);
    });

    it('should approach to value over time', function () {
        $early = Spring::spring(5, 30, ['from' => 0, 'to' => 1]);
        $late = Spring::spring(50, 30, ['from' => 0, 'to' => 1]);
        expect($late)->toBeGreaterThan($early);
    });

    it('should clamp to to value with duration', function () {
        $result = Spring::spring(100, 30, ['from' => 0, 'to' => 1], 10.0);
        expect($result)->toBe(1.0);
    });

    it('should handle custom stiffness', function () {
        $slow = Spring::spring(10, 30, ['stiffness' => 50, 'from' => 0, 'to' => 1]);
        $fast = Spring::spring(10, 30, ['stiffness' => 500, 'from' => 0, 'to' => 1]);
        expect($fast)->toBeGreaterThan($slow);
    });

    it('should handle custom damping', function () {
        $underdamped = Spring::spring(10, 30, ['damping' => 1, 'from' => 0, 'to' => 1]);
        $overdamped = Spring::spring(10, 30, ['damping' => 50, 'from' => 0, 'to' => 1]);
        expect($underdamped)->not->toBe($overdamped);
    });

    it('isSettled should return true when settled', function () {
        $settled = Spring::isSettled(100, 30, ['from' => 0, 'to' => 1]);
        expect($settled)->toBeTrue();
    });

    it('isSettled should return false when not settled', function () {
        $notSettled = Spring::isSettled(1, 30, ['from' => 0, 'to' => 1, 'damping' => 1]);
        expect($notSettled)->toBeFalse();
    });

    it('getSettledFrame should return reasonable frame count', function () {
        $frame = Spring::getSettledFrame(30, ['from' => 0, 'to' => 1, 'damping' => 10]);
        expect($frame)->toBeGreaterThan(0);
        expect($frame)->toBeLessThan(900); // 30 seconds max
    });

    it('should handle negative direction', function () {
        $result = Spring::spring(10, 30, ['from' => 100, 'to' => 0]);
        // 弹簧可能会过冲到负值，这是正常行为
        expect($result)->toBeLessThan(100.0);
        // 只检查是否在合理范围内（允许过冲）
        expect($result)->toBeGreaterThan(-20.0);
    });

    it('should clamp overshoot with overshootClamping', function () {
        $result = Spring::spring(5, 30, [
            'from' => 0,
            'to' => 1,
            'damping' => 1,
            'overshootClamping' => true,
        ]);
        expect($result)->toBeLessThanOrEqual(1.0);
    });
});

// ============================================================
// Color 测试
// ============================================================
describe('Color', function () {

    it('should create from RGB', function () {
        $color = new Color(255, 128, 64);
        expect($color->getR())->toBe(255);
        expect($color->getG())->toBe(128);
        expect($color->getB())->toBe(64);
        expect($color->getA())->toBe(1.0);
    });

    it('should create from hex without hash', function () {
        $color = Color::fromHex('FF8000');
        expect($color->getR())->toBe(255);
        expect($color->getG())->toBe(128);
        expect($color->getB())->toBe(0);
    });

    it('should create from hex with hash', function () {
        $color = Color::fromHex('#FF8000');
        expect($color->getR())->toBe(255);
        expect($color->getG())->toBe(128);
        expect($color->getB())->toBe(0);
    });

    it('should handle short hex', function () {
        $color = Color::fromHex('#F00');
        expect($color->getR())->toBe(255);
        expect($color->getG())->toBe(0);
        expect($color->getB())->toBe(0);
    });

    it('should clamp RGB values', function () {
        $color = new Color(300, -50, 128);
        expect($color->getR())->toBe(255);
        expect($color->getG())->toBe(0);
        expect($color->getB())->toBe(128);
    });

    it('should clamp alpha value', function () {
        $color = new Color(255, 0, 0, 2.0);
        expect($color->getA())->toBe(1.0);

        $color = new Color(255, 0, 0, -1.0);
        expect($color->getA())->toBe(0.0);
    });

    it('should create common colors', function () {
        expect(Color::white()->toHex())->toBe('#ffffff');
        expect(Color::black()->toHex())->toBe('#000000');
        expect(Color::red()->toHex())->toBe('#ff0000');
        expect(Color::green()->toHex())->toBe('#00ff00');
        expect(Color::blue()->toHex())->toBe('#0000ff');
    });

    it('should create transparent color', function () {
        $color = Color::transparent();
        expect($color->getA())->toBe(0.0);
    });

    it('should mix two colors', function () {
        $red = Color::fromHex('#FF0000');
        $blue = Color::fromHex('#0000FF');
        $mixed = $red->mix($blue, 0.5);

        expect($mixed->getR())->toBe(128);
        expect($mixed->getG())->toBe(0);
        // 允许小偏差
        expect(abs($mixed->getB() - 128))->toBeLessThan(2);
    });

    it('should lighten color', function () {
        $color = Color::fromHex('#808080');
        $light = $color->lighten(1.5);

        expect($light->getR())->toBeGreaterThan($color->getR());
        expect($light->getG())->toBeGreaterThan($color->getG());
        expect($light->getB())->toBeGreaterThan($color->getB());
    });

    it('should set alpha', function () {
        $color = Color::fromHex('#FF0000')->withAlpha(0.5);
        expect($color->getA())->toBe(0.5);
    });

    it('should convert to RGB array', function () {
        $color = new Color(255, 128, 64);
        $rgb = $color->toRgb();

        expect($rgb)->toBe([255, 128, 64]);
    });

    it('should convert to RGBA array', function () {
        $color = new Color(255, 128, 64, 0.8);
        $rgba = $color->toRgba();

        expect($rgba[0])->toBe(255);
        expect($rgba[1])->toBe(128);
        expect($rgba[2])->toBe(64);
        expect($rgba[3])->toBe((int) (0.8 * 255));
    });

    it('should convert to hex string', function () {
        $color = new Color(255, 128, 64);
        expect($color->toHex())->toBe('#ff8040');
    });

    it('should convert to GD color', function () {
        $image = imagecreatetruecolor(100, 100);
        $color = Color::fromHex('#FF0000');
        $gdColor = $color->toGdColor($image);

        expect(is_int($gdColor))->toBeTrue();
        imagedestroy($image);
    });

    it('should convert to string', function () {
        $opaque = new Color(255, 0, 0);
        expect((string) $opaque)->toBe('#ff0000');

        $transparent = new Color(255, 0, 0, 0.5);
        $str = (string) $transparent;
        expect(str_contains($str, 'rgba'))->toBeTrue();
    });

    it('should create from RGB array', function () {
        $color = Color::fromRgb([100, 150, 200]);
        expect($color->getR())->toBe(100);
        expect($color->getG())->toBe(150);
        expect($color->getB())->toBe(200);
    });

    it('should create from HSL', function () {
        $red = Color::fromHsl(0.0, 1.0, 0.5);
        expect($red->getR())->toBe(255);
        expect($red->getG())->toBe(0);
        expect($red->getB())->toBe(0);
    });

    it('should create grayscale from HSL', function () {
        $gray = Color::fromHsl(0.0, 0.0, 0.5);
        expect($gray->getR())->toBe($gray->getG());
        expect($gray->getG())->toBe($gray->getB());
    });
});

// ============================================================
// VideoConfig 测试
// ============================================================
describe('VideoConfig', function () {

    it('should create with valid values', function () {
        $config = new VideoConfig(150, 30, 1920, 1080);

        expect($config->durationInFrames)->toBe(150);
        expect($config->fps)->toBe(30);
        expect($config->width)->toBe(1920);
        expect($config->height)->toBe(1080);
    });

    it('should throw on invalid duration', function () {
        expect(fn () => new VideoConfig(0, 30, 1920, 1080))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('should throw on invalid fps', function () {
        expect(fn () => new VideoConfig(150, 0, 1920, 1080))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('should throw on invalid dimensions', function () {
        expect(fn () => new VideoConfig(150, 30, -1, 1080))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('should calculate duration in seconds', function () {
        $config = new VideoConfig(150, 30, 1920, 1080);
        expect($config->getDurationInSeconds())->toBe(5.0);
    });

    it('should calculate aspect ratio', function () {
        $config = new VideoConfig(150, 30, 1920, 1080);
        expect($config->getAspectRatio())->toBe(16.0 / 9.0);
    });

    it('should convert seconds to frames', function () {
        $config = new VideoConfig(150, 30, 1920, 1080);
        expect($config->secondsToFrames(1.0))->toBe(30);
        expect($config->secondsToFrames(2.5))->toBe(75);
    });

    it('should convert frames to seconds', function () {
        $config = new VideoConfig(150, 30, 1920, 1080);
        expect($config->framesToSeconds(30))->toBe(1.0);
        expect($config->framesToSeconds(75))->toBe(2.5);
    });

    it('should create HD preset', function () {
        $config = VideoConfig::hd(300, 60);
        expect($config->width)->toBe(1920);
        expect($config->height)->toBe(1080);
        expect($config->fps)->toBe(60);
        expect($config->durationInFrames)->toBe(300);
    });

    it('should create square preset', function () {
        $config = VideoConfig::square(1080);
        expect($config->width)->toBe(1080);
        expect($config->height)->toBe(1080);
    });

    it('should create portrait preset', function () {
        $config = VideoConfig::portrait(150, 30);
        expect($config->width)->toBe(1080);
        expect($config->height)->toBe(1920);
    });

    it('should convert to array', function () {
        $config = new VideoConfig(150, 30, 1920, 1080);
        $arr = $config->toArray();

        expect($arr['durationInFrames'])->toBe(150);
        expect($arr['fps'])->toBe(30);
        expect($arr['width'])->toBe(1920);
        expect($arr['height'])->toBe(1080);
    });

    it('should convert to string', function () {
        $config = new VideoConfig(150, 30, 1920, 1080);
        $str = (string) $config;

        expect(str_contains($str, '1920'))->toBeTrue();
        expect(str_contains($str, '1080'))->toBeTrue();
        expect(str_contains($str, '30'))->toBeTrue();
    });
});

// ============================================================
// RenderContext 测试
// ============================================================
describe('RenderContext', function () {

    it('should create with valid values', function () {
        $config = new VideoConfig(150, 30, 1920, 1080);
        $ctx = new RenderContext(50, $config);

        expect($ctx->frame)->toBe(50);
        expect($ctx->videoConfig)->toBe($config);
    });

    it('should get current frame', function () {
        $config = new VideoConfig(150, 30, 1920, 1080);
        $ctx = new RenderContext(50, $config);

        expect($ctx->getCurrentFrame())->toBe(50);
    });

    it('should get video config', function () {
        $config = new VideoConfig(150, 30, 1920, 1080);
        $ctx = new RenderContext(50, $config);

        expect($ctx->getVideoConfig())->toBe($config);
    });

    it('should get prop with default', function () {
        $config = new VideoConfig(150, 30, 1920, 1080);
        $ctx = new RenderContext(50, $config, ['title' => 'Test']);

        expect($ctx->getProp('title'))->toBe('Test');
        expect($ctx->getProp('missing', 'default'))->toBe('default');
    });

    it('should calculate progress', function () {
        $config = new VideoConfig(100, 30, 1920, 1080);
        $ctx = new RenderContext(50, $config);

        expect($ctx->getProgress())->toBe(50.0 / 99.0);
    });

    it('should calculate current time', function () {
        $config = new VideoConfig(150, 30, 1920, 1080);
        $ctx = new RenderContext(30, $config);

        expect($ctx->getCurrentTime())->toBe(1.0);
    });

    it('should create with frame offset', function () {
        $config = new VideoConfig(150, 30, 1920, 1080);
        $ctx = new RenderContext(50, $config);
        $offsetCtx = $ctx->withFrameOffset(20);

        expect($offsetCtx->frame)->toBe(30);
    });

    it('should create with props', function () {
        $config = new VideoConfig(150, 30, 1920, 1080);
        $ctx = new RenderContext(50, $config, ['title' => 'Original']);
        $newCtx = $ctx->withProps(['title' => 'New', 'extra' => 'value']);

        expect($newCtx->getProp('title'))->toBe('New');
        expect($newCtx->getProp('extra'))->toBe('value');
    });

    it('should create with new video config', function () {
        $config1 = new VideoConfig(150, 30, 1920, 1080);
        $config2 = new VideoConfig(300, 60, 1280, 720);
        $ctx = new RenderContext(50, $config1);
        $newCtx = $ctx->withVideoConfig($config2);

        expect($newCtx->videoConfig)->toBe($config2);
        expect($newCtx->frame)->toBe(50);
    });

    it('should check if in range', function () {
        $config = new VideoConfig(150, 30, 1920, 1080);
        $ctx = new RenderContext(50, $config);

        expect($ctx->isInRange(40, 30))->toBeTrue();
        expect($ctx->isInRange(80, 30))->toBeFalse();
    });

    it('should preserve props in withFrameOffset', function () {
        $config = new VideoConfig(150, 30, 1920, 1080);
        $ctx = new RenderContext(50, $config, ['title' => 'Test']);
        $offsetCtx = $ctx->withFrameOffset(10);

        expect($offsetCtx->getProp('title'))->toBe('Test');
    });
});

// ============================================================
// Sequence 测试
// ============================================================
describe('Sequence', function () {

    it('should create sequence with valid values', function () {
        $config = new VideoConfig(150, 30, 1920, 1080);
        $component = new class implements ComponentInterface {
            public function render(RenderContext $ctx): \GdImage
            {
                return imagecreatetruecolor(100, 100);
            }
        };

        $sequence = new Sequence('test', $component, 0, 60);

        expect($sequence->getId())->toBe('test');
        expect($sequence->getFrom())->toBe(0);
        expect($sequence->getDurationInFrames())->toBe(60);
    });

    it('should create sequence from closure', function () {
        $sequence = Sequence::make('test', fn($ctx) => imagecreatetruecolor(100, 100), 0, 60);

        expect($sequence->getId())->toBe('test');
    });

    it('should throw on negative from', function () {
        $component = new class implements ComponentInterface {
            public function render(RenderContext $ctx): \GdImage
            {
                return imagecreatetruecolor(100, 100);
            }
        };

        expect(fn () => new Sequence('test', $component, -1, 60))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('should check if active', function () {
        $config = new VideoConfig(150, 30, 1920, 1080);
        $component = new class implements ComponentInterface {
            public function render(RenderContext $ctx): \GdImage
            {
                return imagecreatetruecolor(100, 100);
            }
        };

        $sequence = new Sequence('test', $component, 10, 30);

        expect($sequence->isActive(5, $config))->toBeFalse();
        expect($sequence->isActive(20, $config))->toBeTrue();
        expect($sequence->isActive(50, $config))->toBeFalse();
    });

    it('should get local frame', function () {
        $component = new class implements ComponentInterface {
            public function render(RenderContext $ctx): \GdImage
            {
                return imagecreatetruecolor(100, 100);
            }
        };

        $sequence = new Sequence('test', $component, 10, 30);

        expect($sequence->getLocalFrame(15))->toBe(5);
        expect($sequence->getLocalFrame(40))->toBe(30);
    });

    it('should return null when not active', function () {
        $config = new VideoConfig(150, 30, 1920, 1080);
        $component = new class implements ComponentInterface {
            public function render(RenderContext $ctx): \GdImage
            {
                return imagecreatetruecolor(100, 100);
            }
        };

        $sequence = new Sequence('test', $component, 50, 30);
        $ctx = new RenderContext(10, $config);

        expect($sequence->render($ctx, $config))->toBeNull();
    });

    it('should render when active', function () {
        $config = new VideoConfig(150, 30, 1920, 1080);
        $component = new class implements ComponentInterface {
            public function render(RenderContext $ctx): \GdImage
            {
                return imagecreatetruecolor(100, 100);
            }
        };

        $sequence = new Sequence('test', $component, 10, 30);
        $ctx = new RenderContext(20, $config);

        $result = $sequence->render($ctx, $config);
        expect($result)->toBeInstanceOf(\GdImage::class);
    });

    it('should use auto duration when null', function () {
        $config = new VideoConfig(150, 30, 1920, 1080);
        $component = new class implements ComponentInterface {
            public function render(RenderContext $ctx): \GdImage
            {
                return imagecreatetruecolor(100, 100);
            }
        };

        $sequence = new Sequence('test', $component, 100);

        expect($sequence->isActive(149, $config))->toBeTrue();
        expect($sequence->isActive(150, $config))->toBeFalse();
    });

    it('should convert to array', function () {
        $component = new class implements ComponentInterface {
            public function render(RenderContext $ctx): \GdImage
            {
                return imagecreatetruecolor(100, 100);
            }
        };

        $sequence = new Sequence('test', $component, 10, 30, 'Test Name');
        $arr = $sequence->toArray();

        expect($arr['id'])->toBe('test');
        expect($arr['from'])->toBe(10);
        expect($arr['durationInFrames'])->toBe(30);
        expect($arr['name'])->toBe('Test Name');
    });

    it('should convert to string', function () {
        $component = new class implements ComponentInterface {
            public function render(RenderContext $ctx): \GdImage
            {
                return imagecreatetruecolor(100, 100);
            }
        };

        $sequence = new Sequence('test', $component, 10, 30);
        $str = (string) $sequence;

        expect(str_contains($str, 'test'))->toBeTrue();
        expect(str_contains($str, '10'))->toBeTrue();
    });

    it('should use id as default name', function () {
        $component = new class implements ComponentInterface {
            public function render(RenderContext $ctx): \GdImage
            {
                return imagecreatetruecolor(100, 100);
            }
        };

        $sequence = new Sequence('my-sequence', $component, 0, 30);
        expect($sequence->getName())->toBe('my-sequence');
    });

    it('should offset frame correctly in render', function () {
        $config = new VideoConfig(150, 30, 1920, 1080);
        $renderedFrames = [];

        $component = new class($renderedFrames) implements ComponentInterface {
            private array $frames;

            public function __construct(array &$frames)
            {
                $this->frames = &$frames;
            }

            public function render(RenderContext $ctx): \GdImage
            {
                $this->frames[] = $ctx->frame;
                return imagecreatetruecolor(100, 100);
            }
        };

        $sequence = new Sequence('test', $component, 50, 30);

        for ($frame = 50; $frame < 80; $frame++) {
            $ctx = new RenderContext($frame, $config);
            $sequence->render($ctx, $config);
        }

        expect($renderedFrames[0])->toBe(0);
        expect($renderedFrames[29])->toBe(29);
    });
});
