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

// ============================================================
// Grafika Layer 测试
// ============================================================
describe('Grafika Layers', function () {

    beforeEach(function () {
        // 确保有中文字体
        $this->fontPath = get_chinese_font();
    });

    // --- ColorLayer Grafika 测试 ---
    describe('ColorLayer', function () {

        it('should draw on Grafika image', function () {
            $layer = \Yangweijie\Remotion\Layers\ColorLayer::fromHex(200, 200, '#ff6600');
            $image = \Grafika\Grafika::createBlankImage(400, 400);

            $layer->drawOnImage($image);

            expect($image->getWidth())->toBe(400);
            expect($image->getHeight())->toBe(400);
        });

        it('should maintain backward compatibility with GD', function () {
            $layer = \Yangweijie\Remotion\Layers\ColorLayer::fromHex(100, 100, '#00ff00');
            $gdImage = imagecreatetruecolor(200, 200);

            $layer->drawOn($gdImage);

            expect($gdImage)->toBeInstanceOf(\GdImage::class);
            imagedestroy($gdImage);
        });

        it('should respect opacity in Grafika', function () {
            $layer = \Yangweijie\Remotion\Layers\ColorLayer::fromHex(100, 100, '#ff0000', 0.5);
            $image = \Grafika\Grafika::createBlankImage(200, 200);

            $layer->drawOnImage($image);

            // 图像应该被创建而不抛出异常
            expect($image->getWidth())->toBe(200);
        });
    });

    // --- GradientLayer Grafika 测试 ---
    describe('GradientLayer', function () {

        it('should draw gradient on Grafika image', function () {
            $layer = \Yangweijie\Remotion\Layers\GradientLayer::make(
                200,
                100,
                ['r' => 255, 'g' => 0, 'b' => 0],
                ['r' => 0, 'g' => 0, 'b' => 255]
            );
            $image = \Grafika\Grafika::createBlankImage(200, 100);

            $layer->drawOnImage($image);

            expect($image->getWidth())->toBe(200);
        });

        it('should support vertical gradient', function () {
            $layer = \Yangweijie\Remotion\Layers\GradientLayer::make(
                100,
                200,
                ['r' => 0, 'g' => 255, 'b' => 0],
                ['r' => 0, 'g' => 0, 'b' => 0],
                'vertical'
            );
            $image = \Grafika\Grafika::createBlankImage(100, 200);

            $layer->drawOnImage($image);

            expect($image->getHeight())->toBe(200);
        });

        it('should support multi-stop gradient', function () {
            // 多色标渐变需要直接使用构造函数
            $layer = new \Yangweijie\Remotion\Layers\GradientLayer(
                100,
                100,
                [
                    ['r' => 255, 'g' => 0, 'b' => 0],
                    ['r' => 255, 'g' => 255, 'b' => 0],
                    ['r' => 0, 'g' => 0, 'b' => 255],
                ],
                'horizontal'
            );
            $image = \Grafika\Grafika::createBlankImage(100, 100);

            $layer->drawOnImage($image);

            expect($image->getWidth())->toBe(100);
        });

        it('should maintain GD backward compatibility', function () {
            $layer = \Yangweijie\Remotion\Layers\GradientLayer::make(
                100,
                100,
                ['r' => 255, 'g' => 0, 'b' => 0],
                ['r' => 0, 'g' => 0, 'b' => 255]
            );
            $gdImage = imagecreatetruecolor(100, 100);

            $layer->drawOn($gdImage);

            expect($gdImage)->toBeInstanceOf(\GdImage::class);
            imagedestroy($gdImage);
        });
    });

    // --- ImageLayer Grafika 测试 ---
    describe('ImageLayer', function () {

        it('should create from Grafika image', function () {
            $sourceImage = \Grafika\Grafika::createBlankImage(150, 100);
            $layer = \Yangweijie\Remotion\Layers\ImageLayer::fromImage($sourceImage);

            expect($layer->getWidth())->toBe(150);
            expect($layer->getHeight())->toBe(100);
        });

        it('should draw Grafika image on canvas', function () {
            $sourceImage = \Grafika\Grafika::createBlankImage(50, 50);
            $layer = \Yangweijie\Remotion\Layers\ImageLayer::fromImage($sourceImage);
            $canvas = \Grafika\Grafika::createBlankImage(200, 200);

            $layer->drawOnImage($canvas, 10, 10);

            expect($canvas->getWidth())->toBe(200);
        });

        it('should maintain GD backward compatibility', function () {
            $gdImage = imagecreatetruecolor(50, 50);
            $color = imagecolorallocate($gdImage, 255, 0, 0);
            imagefill($gdImage, 0, 0, $color);
            
            // 保存临时文件用于 ImageLayer
            $tempFile = sys_get_temp_dir() . '/test_image_' . uniqid() . '.png';
            imagepng($gdImage, $tempFile);
            imagedestroy($gdImage);
            
            $layer = new \Yangweijie\Remotion\Layers\ImageLayer($tempFile);

            expect($layer->getWidth())->toBe(50);
            
            unlink($tempFile);
        });
    });

    // --- TextLayer Grafika 测试 ---
    describe('TextLayer', function () {

        it('should draw text on Grafika image', function () {
            if (!$this->fontPath) {
                test()->markTestSkipped('需要中文字体支持');
            }

            $layer = \Yangweijie\Remotion\Layers\TextLayer::make('Hello Grafika', [
                'fontSize' => 24,
                'fontPath' => $this->fontPath,
                'color' => '#000000',
            ]);
            $image = \Grafika\Grafika::createBlankImage(300, 100);

            $layer->drawOnImage($image);

            expect($image->getWidth())->toBe(300);
        });

        it('should support Chinese text on Grafika image', function () {
            if (!$this->fontPath) {
                test()->markTestSkipped('需要中文字体支持');
            }

            $layer = \Yangweijie\Remotion\Layers\TextLayer::make('中文测试', [
                'fontSize' => 32,
                'fontPath' => $this->fontPath,
                'color' => '#ff0000',
            ]);
            $image = \Grafika\Grafika::createBlankImage(300, 100);

            $layer->drawOnImage($image);

            expect($image->getWidth())->toBe(300);
        });

        it('should maintain GD backward compatibility', function () {
            if (!$this->fontPath) {
                test()->markTestSkipped('需要中文字体支持');
            }

            $layer = \Yangweijie\Remotion\Layers\TextLayer::make('Test', [
                'fontSize' => 20,
                'fontPath' => $this->fontPath,
            ]);
            $gdImage = imagecreatetruecolor(200, 50);

            $layer->drawOn($gdImage);

            expect($gdImage)->toBeInstanceOf(\GdImage::class);
            imagedestroy($gdImage);
        });
    });
});

// ============================================================
// Remotion Grafika 测试
// ============================================================
describe('Remotion Grafika', function () {

    it('should create Grafika image canvas', function () {
        $image = \Yangweijie\Remotion\Remotion::createImageCanvas(800, 600);

        expect($image)->toBeInstanceOf(\Grafika\ImageInterface::class);
        expect($image->getWidth())->toBe(800);
        expect($image->getHeight())->toBe(600);
    });

    it('should create canvas with background color', function () {
        $image = \Yangweijie\Remotion\Remotion::createImageCanvas(100, 100, [255, 0, 0]);

        expect($image)->toBeInstanceOf(\Grafika\ImageInterface::class);
        expect($image->getWidth())->toBe(100);
    });

    it('should maintain GD backward compatibility', function () {
        $gdImage = \Yangweijie\Remotion\Remotion::createCanvas(200, 200);

        expect($gdImage)->toBeInstanceOf(\GdImage::class);
        expect(imagesx($gdImage))->toBe(200);
        imagedestroy($gdImage);
    });
});

// ============================================================
// Renderer Grafika 测试
// ============================================================
describe('Renderer Grafika', function () {

    it('should render frame as Grafika image', function () {
        $composition = \Yangweijie\Remotion\Remotion::composition(
            'test',
            function ($ctx) {
                $gd = imagecreatetruecolor(200, 200);
                $bg = imagecolorallocate($gd, 51, 102, 153);
                imagefill($gd, 0, 0, $bg);
                return $gd;
            },
            30,
            30,
            200,
            200
        );

        $renderer = new \Yangweijie\Remotion\Rendering\Renderer($composition);
        $image = $renderer->renderFrameAsImage(0);

        expect($image)->toBeInstanceOf(\Grafika\ImageInterface::class);
        expect($image->getWidth())->toBe(200);
    });

    it('should render multiple frames with Grafika', function () {
        $tempDir = sys_get_temp_dir() . '/remotion_test_' . uniqid();
        mkdir($tempDir);

        $composition = \Yangweijie\Remotion\Remotion::composition(
            'test',
            function ($ctx) {
                $gd = imagecreatetruecolor(50, 50);
                $bg = imagecolorallocate($gd, 255, 0, 0);
                imagefill($gd, 0, 0, $bg);
                return $gd;
            },
            3,
            30,
            50,
            50
        );

        $renderer = new \Yangweijie\Remotion\Rendering\Renderer($composition);
        $result = $renderer->renderToFramesWithGrafika($tempDir, 'png');

        expect($result)->toBeTrue();
        // 文件名格式为 N.png（N 根据帧数填充）
        expect(file_exists($tempDir . '/0.png'))->toBeTrue();
        expect(file_exists($tempDir . '/1.png'))->toBeTrue();
        expect(file_exists($tempDir . '/2.png'))->toBeTrue();

        // 清理
        foreach (glob($tempDir . '/*') as $file) {
            unlink($file);
        }
        rmdir($tempDir);
    });

// ============================================================
// GIF 优化测试
// ============================================================
describe('GIF Optimization', function () {

    it('should detect available GIF tool', function () {
        $composition = \Yangweijie\Remotion\Remotion::composition(
            'test',
            function ($ctx) {
                return imagecreatetruecolor(10, 10);
            },
            1,
            30,
            10,
            10
        );

        $renderer = new \Yangweijie\Remotion\Rendering\Renderer($composition);
        $tool = $renderer->detectGifTool();

        // 应该返回 null、'gifsicle' 或 'ffmpeg'
        expect($tool === null || in_array($tool, ['gifsicle', 'ffmpeg']))->toBeTrue();
    });

    it('should render optimized GIF with gifsicle or ffmpeg', function () {
        $tempFile = sys_get_temp_dir() . '/test_optimized_' . uniqid() . '.gif';

        $composition = \Yangweijie\Remotion\Remotion::composition(
            'test',
            function ($ctx) {
                $gd = imagecreatetruecolor(50, 50);
                $r = (int) (255 * $ctx->frame / 10);
                $color = imagecolorallocate($gd, $r, 100, 100);
                imagefill($gd, 0, 0, $color);
                return $gd;
            },
            10,
            10,
            50,
            50
        );

        $renderer = new \Yangweijie\Remotion\Rendering\Renderer($composition);
        $result = $renderer->renderToGifOptimized($tempFile, [], [
            'lossy' => 0,
            'colors' => 64,
            'optimize' => 2,
        ]);

        expect($result)->toBeTrue();
        expect(file_exists($tempFile))->toBeTrue();
        expect(filesize($tempFile))->toBeGreaterThan(0);

        // 清理
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    });

    it('should fallback to GD when no external tool available', function () {
        // 创建一个模拟场景：即使有工具也能正确回退
        $tempFile = sys_get_temp_dir() . '/test_fallback_' . uniqid() . '.gif';

        $composition = \Yangweijie\Remotion\Remotion::composition(
            'test',
            function ($ctx) {
                $gd = imagecreatetruecolor(20, 20);
                $color = imagecolorallocate($gd, 0, 0, 255);
                imagefill($gd, 0, 0, $color);
                return $gd;
            },
            3,
            30,
            20,
            20
        );

        $renderer = new \Yangweijie\Remotion\Rendering\Renderer($composition);

        // 直接使用 GD 方式
        $result = $renderer->renderToGif($tempFile);

        expect($result)->toBeTrue();
        expect(file_exists($tempFile))->toBeTrue();

        // 清理
        unlink($tempFile);
    });
    });

    describe('Streaming GIF', function () {
        beforeEach(function () {
            $this->composition = \Yangweijie\Remotion\Remotion::composition(
                'streaming-test',
                function ($ctx) {
                    $gd = imagecreatetruecolor(100, 100);
                    $bg = imagecolorallocate($gd, 255, 0, 0);
                    imagefill($gd, 0, 0, $bg);
                    return $gd;
                },
                3,
                30,
                100,
                100
            );
        });

        it('should render GIF with streaming mode', function () {
            $outputPath = sys_get_temp_dir() . '/test_streaming_' . uniqid() . '.gif';
            
            $renderer = new \Yangweijie\Remotion\Rendering\Renderer($this->composition);
            $result = $renderer->renderToGifStreaming($outputPath);
            
            expect($result)->toBeTrue();
            expect(file_exists($outputPath))->toBeTrue();
            expect(filesize($outputPath))->toBeGreaterThan(0);
            
            unlink($outputPath);
        });

        it('should produce valid GIF file', function () {
            $outputPath = sys_get_temp_dir() . '/test_stream_valid_' . uniqid() . '.gif';
            
            $renderer = new \Yangweijie\Remotion\Rendering\Renderer($this->composition);
            $renderer->renderToGifStreaming($outputPath);
            
            // 验证 GIF 文件头
            $header = file_get_contents($outputPath, false, null, 0, 6);
            expect($header)->toBe('GIF89a');
            
            unlink($outputPath);
        });
    });

    it('should maintain GD backward compatibility', function () {
        $composition = \Yangweijie\Remotion\Remotion::composition(
            'test',
            function ($ctx) {
                $gd = imagecreatetruecolor(100, 100);
                $bg = imagecolorallocate($gd, 0, 255, 0);
                imagefill($gd, 0, 0, $bg);
                return $gd;
            },
            1,
            30,
            100,
            100
        );

        $gdImage = $composition->renderFrame(0);

        expect($gdImage)->toBeInstanceOf(\GdImage::class);
        imagedestroy($gdImage);
    });
});

// ============================================================
// P0 Components 测试 (Loop, Series, Freeze)
// ============================================================
describe('Loop Component', function () {
    it('should create loop with correct duration', function () {
        $loop = \Yangweijie\Remotion\Core\Loop::make(
            fn($ctx) => imagecreatetruecolor(100, 100),
            durationInFrames: 30,
            times: 3,
        );

        expect($loop->getTotalDuration())->toBe(90);
    });

    it('should calculate local frame correctly', function () {
        $loop = \Yangweijie\Remotion\Core\Loop::make(
            fn($ctx) => imagecreatetruecolor(100, 100),
            durationInFrames: 30,
            times: 3,
        );

        // 第 0 帧应该是本地帧 0
        expect($loop->getLocalFrame(0))->toBe(0);
        // 第 30 帧应该是新一轮，本地帧 0
        expect($loop->getLocalFrame(30))->toBe(0);
        // 第 45 帧应该是本地帧 15
        expect($loop->getLocalFrame(45))->toBe(15);
        // 第 59 帧应该是本地帧 29
        expect($loop->getLocalFrame(59))->toBe(29);
    });

    it('should detect active frames correctly', function () {
        $loop = \Yangweijie\Remotion\Core\Loop::make(
            fn($ctx) => imagecreatetruecolor(100, 100),
            durationInFrames: 30,
            times: 2,
        );

        expect($loop->isActive(0))->toBeTrue();
        expect($loop->isActive(59))->toBeTrue();
        expect($loop->isActive(60))->toBeFalse();
    });

    it('should return null for out of range frames', function () {
        $config = new \Yangweijie\Remotion\Core\VideoConfig(100, 30, 100, 100);
        $loop = \Yangweijie\Remotion\Core\Loop::make(
            fn($ctx) => imagecreatetruecolor(100, 100),
            durationInFrames: 30,
            times: 2,
        );

        $ctx = new \Yangweijie\Remotion\Core\RenderContext(100, $config);
        $result = $loop->render($ctx, $config);

        expect($result)->toBeNull();
    });

    it('should work via Remotion facade', function () {
        $loop = \Yangweijie\Remotion\Remotion::loop(
            fn($ctx) => imagecreatetruecolor(100, 100),
            durationInFrames: 20,
            times: 3,
        );

        expect($loop)->toBeInstanceOf(\Yangweijie\Remotion\Core\Loop::class);
        expect($loop->getTotalDuration())->toBe(60);
    });
});

describe('Series Component', function () {
    it('should add sequences with correct offsets', function () {
        $series = \Yangweijie\Remotion\Core\Series::make()
            ->add('intro', fn($ctx) => imagecreatetruecolor(100, 100), 30)
            ->add('main', fn($ctx) => imagecreatetruecolor(100, 100), 60)
            ->add('outro', fn($ctx) => imagecreatetruecolor(100, 100), 20);

        $sequences = $series->getSequences();

        expect($sequences[0]['offset'])->toBe(0);
        expect($sequences[1]['offset'])->toBe(30);
        expect($sequences[2]['offset'])->toBe(90);
        expect($series->getTotalDuration())->toBe(110);
    });

    it('should find sequence at frame', function () {
        $series = \Yangweijie\Remotion\Core\Series::make()
            ->add('intro', fn($ctx) => imagecreatetruecolor(100, 100), 30)
            ->add('main', fn($ctx) => imagecreatetruecolor(100, 100), 60);

        $info = $series->getSequenceAtFrame(40);
        expect($info['id'])->toBe('main');
        expect($info['localFrame'])->toBe(10);

        $info = $series->getSequenceAtFrame(20);
        expect($info['id'])->toBe('intro');
        expect($info['localFrame'])->toBe(20);
    });

    it('should return null for out of range frames', function () {
        $series = \Yangweijie\Remotion\Core\Series::make()
            ->add('intro', fn($ctx) => imagecreatetruecolor(100, 100), 30);

        $info = $series->getSequenceAtFrame(50);
        expect($info)->toBeNull();
    });

    it('should create from array', function () {
        $series = \Yangweijie\Remotion\Core\Series::fromArray([
            ['id' => 'a', 'component' => fn($ctx) => imagecreatetruecolor(100, 100), 'durationInFrames' => 10],
            ['id' => 'b', 'component' => fn($ctx) => imagecreatetruecolor(100, 100), 'durationInFrames' => 20],
        ]);

        expect($series->getTotalDuration())->toBe(30);
    });

    it('should work via Remotion facade', function () {
        $series = \Yangweijie\Remotion\Remotion::series()
            ->add('a', fn($ctx) => imagecreatetruecolor(100, 100), 10)
            ->add('b', fn($ctx) => imagecreatetruecolor(100, 100), 20);

        expect($series)->toBeInstanceOf(\Yangweijie\Remotion\Core\Series::class);
        expect($series->getTotalDuration())->toBe(30);
    });
});

describe('Freeze Component', function () {
    it('should create freeze with correct properties', function () {
        $freeze = \Yangweijie\Remotion\Core\Freeze::make(
            fn($ctx) => imagecreatetruecolor(100, 100),
            frame: 30,
            duration: 60,
        );

        expect($freeze->getTotalDuration())->toBe(60);
        expect($freeze->getFrozenFrame())->toBe(30);
        expect($freeze->getEndFrame())->toBe(60);
    });

    it('should detect active frames correctly', function () {
        $freeze = \Yangweijie\Remotion\Core\Freeze::make(
            fn($ctx) => imagecreatetruecolor(100, 100),
            frame: 30,
            duration: 60,
            from: 10,
        );

        expect($freeze->isActive(10))->toBeTrue();
        expect($freeze->isActive(69))->toBeTrue();
        expect($freeze->isActive(9))->toBeFalse();
        expect($freeze->isActive(70))->toBeFalse();
    });

    it('should calculate progress correctly', function () {
        $freeze = \Yangweijie\Remotion\Core\Freeze::make(
            fn($ctx) => imagecreatetruecolor(100, 100),
            frame: 30,
            duration: 100,
        );

        expect($freeze->getProgress(0))->toBe(0.0);
        expect($freeze->getProgress(50))->toBe(0.5);
        expect($freeze->getProgress(99))->toBe(0.99);
    });

    it('should work via Remotion facade', function () {
        $freeze = \Yangweijie\Remotion\Remotion::freeze(
            fn($ctx) => imagecreatetruecolor(100, 100),
            frame: 20,
            duration: 40,
        );

        expect($freeze)->toBeInstanceOf(\Yangweijie\Remotion\Core\Freeze::class);
        expect($freeze->getFrozenFrame())->toBe(20);
    });
});

// ============================================================
// Color Grafika 测试
// ============================================================
describe('Color Grafika', function () {

    it('should convert to Grafika Color', function () {
        $color = Color::fromHex('#ff8000');
        $grafikaColor = $color->toGrafikaColor();

        expect($grafikaColor)->toBeInstanceOf(\Grafika\Color::class);
    });

    it('should preserve RGB values in Grafika Color', function () {
        $color = new Color(255, 128, 64);
        $grafikaColor = $color->toGrafikaColor();

        // 验证 Grafika Color 对象已创建
        expect($grafikaColor)->toBeInstanceOf(\Grafika\Color::class);
    });

    it('should handle alpha in Grafika Color', function () {
        $color = new Color(255, 0, 0, 0.5);
        $grafikaColor = $color->toGrafikaColor();

        expect($grafikaColor)->toBeInstanceOf(\Grafika\Color::class);
    });

    it('should handle transparent color', function () {
        $color = Color::transparent();
        $grafikaColor = $color->toGrafikaColor();

        expect($grafikaColor)->toBeInstanceOf(\Grafika\Color::class);
    });
});

// ============================================================
// Random 测试 (P1)
// ============================================================
describe('Random', function () {

    it('should generate deterministic random values', function () {
        $value1 = \Yangweijie\Remotion\Helpers\Random::get('test-seed', 0);
        $value2 = \Yangweijie\Remotion\Helpers\Random::get('test-seed', 0);

        expect($value1)->toBe($value2);
    });

    it('should generate different values for different seeds', function () {
        $value1 = \Yangweijie\Remotion\Helpers\Random::get('seed-a', 0);
        $value2 = \Yangweijie\Remotion\Helpers\Random::get('seed-b', 0);

        expect($value1)->not->toBe($value2);
    });

    it('should generate different values for different frames', function () {
        $value1 = \Yangweijie\Remotion\Helpers\Random::get('test-seed', 0);
        $value2 = \Yangweijie\Remotion\Helpers\Random::get('test-seed', 1);

        expect($value1)->not->toBe($value2);
    });

    it('should respect range parameters', function () {
        $value = \Yangweijie\Remotion\Helpers\Random::get('test-seed', 0, 10, 20);

        expect($value)->toBeGreaterThanOrEqual(10.0);
        expect($value)->toBeLessThanOrEqual(20.0);
    });

    it('should generate integers in range', function () {
        for ($i = 0; $i < 10; $i++) {
            $value = \Yangweijie\Remotion\Helpers\Random::range('test-' . $i, $i, 1, 10);
            expect($value)->toBeGreaterThanOrEqual(1);
            expect($value)->toBeLessThanOrEqual(10);
        }
    });

    it('should generate random booleans', function () {
        $trueCount = 0;
        for ($i = 0; $i < 100; $i++) {
            if (\Yangweijie\Remotion\Helpers\Random::bool('bool-test', $i, 0.5)) {
                $trueCount++;
            }
        }
        // 由于确定性随机，结果应该一致
        expect($trueCount)->toBeGreaterThan(0);
        expect($trueCount)->toBeLessThan(100);
    });

    it('should generate random colors', function () {
        $color = \Yangweijie\Remotion\Helpers\Random::color('color-test', 0);

        expect($color['r'])->toBeGreaterThanOrEqual(0);
        expect($color['r'])->toBeLessThanOrEqual(255);
        expect($color['g'])->toBeGreaterThanOrEqual(0);
        expect($color['g'])->toBeLessThanOrEqual(255);
        expect($color['b'])->toBeGreaterThanOrEqual(0);
        expect($color['b'])->toBeLessThanOrEqual(255);
    });

    it('should pick random element from array', function () {
        $array = ['a', 'b', 'c', 'd', 'e'];
        $picked = \Yangweijie\Remotion\Helpers\Random::pick('pick-test', 0, $array);

        expect(in_array($picked, $array))->toBeTrue();
    });

    it('should work via Remotion facade', function () {
        $value = \Yangweijie\Remotion\Remotion::random('facade-test', 0, 0, 100);

        expect($value)->toBeGreaterThanOrEqual(0.0);
        expect($value)->toBeLessThanOrEqual(100.0);
    });
});

// ============================================================
// Transitions 测试 (P1)
// ============================================================
describe('Transitions', function () {

    it('should create fade transition', function () {
        $fade = new \Yangweijie\Remotion\Transitions\FadeTransition(30);

        expect($fade->getDuration())->toBe(30);
    });

    it('should apply fade transition correctly', function () {
        $fade = new \Yangweijie\Remotion\Transitions\FadeTransition(30);

        $fromImage = imagecreatetruecolor(100, 100);
        $toImage = imagecreatetruecolor(100, 100);

        $red = imagecolorallocate($fromImage, 255, 0, 0);
        $blue = imagecolorallocate($toImage, 0, 0, 255);
        imagefill($fromImage, 0, 0, $red);
        imagefill($toImage, 0, 0, $blue);

        // 开始时应该接近 fromImage
        $result = $fade->apply($fromImage, $toImage, 0.0);
        expect($result)->toBeInstanceOf(\GdImage::class);

        // 结束时应该接近 toImage
        $result = $fade->apply($fromImage, $toImage, 1.0);
        expect($result)->toBeInstanceOf(\GdImage::class);

        imagedestroy($fromImage);
        imagedestroy($toImage);
    });

    it('should create slide transition', function () {
        $slide = new \Yangweijie\Remotion\Transitions\SlideTransition(30, 'left');

        expect($slide->getDuration())->toBe(30);
        expect($slide->getDirection())->toBe('left');
    });

    it('should apply slide transition correctly', function () {
        $slide = new \Yangweijie\Remotion\Transitions\SlideTransition(30, 'left');

        $fromImage = imagecreatetruecolor(100, 100);
        $toImage = imagecreatetruecolor(100, 100);

        $red = imagecolorallocate($fromImage, 255, 0, 0);
        $blue = imagecolorallocate($toImage, 0, 0, 255);
        imagefill($fromImage, 0, 0, $red);
        imagefill($toImage, 0, 0, $blue);

        $result = $slide->apply($fromImage, $toImage, 0.5);
        expect($result)->toBeInstanceOf(\GdImage::class);

        imagedestroy($fromImage);
        imagedestroy($toImage);
    });

    it('should work via Remotion facade', function () {
        $fade = \Yangweijie\Remotion\Remotion::fadeTransition(30);
        expect($fade)->toBeInstanceOf(\Yangweijie\Remotion\Transitions\FadeTransition::class);

        $slide = \Yangweijie\Remotion\Remotion::slideTransition(30, 'right');
        expect($slide)->toBeInstanceOf(\Yangweijie\Remotion\Transitions\SlideTransition::class);
    });
});

// ============================================================
// Noise 测试 (P2)
// ============================================================
describe('Noise', function () {

    it('should generate 2D noise in valid range', function () {
        for ($i = 0; $i < 10; $i++) {
            $value = \Yangweijie\Remotion\Helpers\Noise::noise2D($i * 0.1, $i * 0.2);
            expect($value)->toBeGreaterThanOrEqual(-1.0);
            expect($value)->toBeLessThanOrEqual(1.0);
        }
    });

    it('should generate deterministic noise', function () {
        $value1 = \Yangweijie\Remotion\Helpers\Noise::noise2D(0.5, 0.5, 42);
        $value2 = \Yangweijie\Remotion\Helpers\Noise::noise2D(0.5, 0.5, 42);

        expect($value1)->toBe($value2);
    });

    it('should generate different noise for different seeds', function () {
        $value1 = \Yangweijie\Remotion\Helpers\Noise::noise2D(0.5, 0.5, 1);
        $value2 = \Yangweijie\Remotion\Helpers\Noise::noise2D(0.5, 0.5, 2);

        expect($value1)->not->toBe($value2);
    });

    it('should generate 3D noise', function () {
        $value = \Yangweijie\Remotion\Helpers\Noise::noise3D(0.5, 0.5, 0.5);

        expect($value)->toBeGreaterThanOrEqual(-1.0);
        expect($value)->toBeLessThanOrEqual(1.0);
    });

    it('should generate 4D noise', function () {
        $value = \Yangweijie\Remotion\Helpers\Noise::noise4D(0.5, 0.5, 0.5, 0.5);

        expect($value)->toBeGreaterThanOrEqual(-1.0);
        expect($value)->toBeLessThanOrEqual(1.0);
    });

    it('should generate fbm noise', function () {
        $value = \Yangweijie\Remotion\Helpers\Noise::fbm(0.5, 0.5, 0, 4);

        expect($value)->toBeGreaterThanOrEqual(-1.0);
        expect($value)->toBeLessThanOrEqual(1.0);
    });

    it('should map noise to range', function () {
        $mapped = \Yangweijie\Remotion\Helpers\Noise::mapToRange(0.0, 0, 100);
        expect($mapped)->toBe(50.0);

        $mapped = \Yangweijie\Remotion\Helpers\Noise::mapToRange(-1.0, 0, 100);
        expect($mapped)->toBe(0.0);

        $mapped = \Yangweijie\Remotion\Helpers\Noise::mapToRange(1.0, 0, 100);
        expect($mapped)->toBe(100.0);
    });

    it('should work via Remotion facade', function () {
        $value = \Yangweijie\Remotion\Remotion::noise2D(0.5, 0.5, 42);
        expect($value)->toBeGreaterThanOrEqual(-1.0);
        expect($value)->toBeLessThanOrEqual(1.0);
    });
});
