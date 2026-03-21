# PHP Remotion AI Agent Skills

本目录包含 AI Agent 可使用的 Skills，用于辅助开发 PHP Remotion 项目。

## Skills 列表

| Skill | 描述 | 触发词 |
|-------|------|--------|
| [remotion-info.yaml](remotion-info.yaml) | 项目信息和文档 | 项目信息、help、帮助 |
| [remotion-render.yaml](remotion-render.yaml) | 渲染动画为 GIF | 渲染动画、render |
| [remotion-test.yaml](remotion-test.yaml) | 运行单元测试 | 运行测试、pest |
| [remotion-new-animation.yaml](remotion-new-animation.yaml) | 创建新动画 | 创建动画、new animation |
| [remotion-new-test.yaml](remotion-new-test.yaml) | 创建单元测试 | 创建测试、new test |
| [remotion-easing-explorer.yaml](remotion-easing-explorer.yaml) | 探索缓动函数 | 缓动函数、easing |
| [remotion-spring-configurator.yaml](remotion-spring-configurator.yaml) | 配置弹簧动画 | 弹簧动画、spring |

## 使用方法

### 命令行调用

```bash
# 渲染动画
php example.php spring-scale

# 运行测试
./vendor/bin/pest

# 测试缓动函数
php test-easing.php
```

### 在 AI 对话中使用

```
@remotion-render spring-scale output.gif
@remotion-test Easing
@remotion-easing-explorer easeOut
@remotion-spring-configurator stiffness=100 damping=10
```

## 开发新 Skill

创建新的 Skill 只需在 `skills/` 目录下添加 YAML 文件：

```yaml
name: skill-name
description: 技能描述
triggers:
  - "触发词1"
  - "触发词2"
parameters:
  - name: param1
    type: string
    required: true
command: "执行命令 {param1}"
```
