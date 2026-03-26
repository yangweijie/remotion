/**
 * PHP Remotion 可视化动画编辑器
 * Visual Animation Editor
 */

// 编辑器状态管理
class EditorState {
    constructor() {
        // 合成配置
        this.composition = {
            id: 'my-animation',
            width: 640,
            height: 360,
            fps: 30,
            durationInFrames: 90
        };

        // 图层列表
        this.layers = [];

        // 当前选中的图层
        this.selectedLayerId = null;

        // 当前播放帧
        this.currentFrame = 0;

        // 播放状态
        this.isPlaying = false;

        // 播放定时器
        this.playTimer = null;

        // 图层计数器（用于生成唯一ID）
        this.layerCounter = 0;
    }

    // 添加图层
    addLayer(type, config = {}) {
        this.layerCounter++;
        const layer = {
            id: `layer-${this.layerCounter}`,
            type: type,
            name: config.name || `${this.getLayerTypeName(type)} ${this.layerCounter}`,
            visible: true,
            from: config.from || 0,
            duration: config.duration || this.composition.durationInFrames,
            config: { ...this.getDefaultLayerConfig(type), ...config }
        };
        this.layers.push(layer);
        return layer;
    }

    // 删除图层
    removeLayer(layerId) {
        const index = this.layers.findIndex(l => l.id === layerId);
        if (index > -1) {
            this.layers.splice(index, 1);
            if (this.selectedLayerId === layerId) {
                this.selectedLayerId = null;
            }
        }
    }

    // 获取图层类型名称
    getLayerTypeName(type) {
        const names = {
            color: '纯色层',
            gradient: '渐变层',
            text: '文字层',
            image: '图片层',
            shape: '形状层'
        };
        return names[type] || '图层';
    }

    // 获取默认图层配置
    getDefaultLayerConfig(type) {
        const defaults = {
            color: {
                color: '#e94560',
                x: 0,
                y: 0,
                width: 200,
                height: 150
            },
            gradient: {
                startColor: '#e94560',
                endColor: '#0f3460',
                direction: 'horizontal',
                x: 0,
                y: 0,
                width: 200,
                height: 150
            },
            text: {
                text: 'Hello World',
                fontSize: 32,
                color: '#ffffff',
                x: 50,
                y: 50,
                align: 'left'
            },
            image: {
                src: '',
                x: 0,
                y: 0,
                width: 200,
                height: 150,
                fit: 'cover'
            },
            shape: {
                shape: 'circle',
                color: '#e94560',
                x: 100,
                y: 100,
                size: 50
            }
        };
        return defaults[type] || {};
    }

    // 更新图层配置
    updateLayer(layerId, updates) {
        const layer = this.layers.find(l => l.id === layerId);
        if (layer) {
            Object.assign(layer.config, updates);
        }
    }

    // 更新图层时间
    updateLayerTime(layerId, from, duration) {
        const layer = this.layers.find(l => l.id === layerId);
        if (layer) {
            layer.from = from;
            layer.duration = duration;
        }
    }

    // 更新图层名称
    updateLayerName(layerId, name) {
        const layer = this.layers.find(l => l.id === layerId);
        if (layer) {
            layer.name = name;
        }
    }

    // 切换图层可见性
    toggleLayerVisibility(layerId) {
        const layer = this.layers.find(l => l.id === layerId);
        if (layer) {
            layer.visible = !layer.visible;
        }
    }

    // 选择图层
    selectLayer(layerId) {
        this.selectedLayerId = layerId;
    }

    // 获取选中的图层
    getSelectedLayer() {
        return this.layers.find(l => l.id === this.selectedLayerId);
    }

    // 更新合成配置
    updateComposition(updates) {
        Object.assign(this.composition, updates);
    }

    // 设置当前帧
    setCurrentFrame(frame) {
        this.currentFrame = Math.max(0, Math.min(frame, this.composition.durationInFrames));
    }

    // 清除所有图层
    clear() {
        this.layers = [];
        this.selectedLayerId = null;
        this.currentFrame = 0;
        this.layerCounter = 0;
    }

    // 导出配置
    exportConfig() {
        return {
            composition: { ...this.composition },
            layers: this.layers.map(l => ({
                id: l.id,
                type: l.type,
                name: l.name,
                from: l.from,
                duration: l.duration,
                config: { ...l.config }
            }))
        };
    }
}

// 预览渲染器
class PreviewRenderer {
    constructor(canvasId) {
        this.canvas = document.getElementById(canvasId);
        this.ctx = this.canvas.getContext('2d');
        this.selectedLayerId = null;
        this.dragState = null;
    }

    // 设置画布尺寸
    setSize(width, height) {
        this.canvas.width = width;
        this.canvas.height = height;
    }

    // 渲染帧
    render(state) {
        const { width, height } = state.composition;

        // 清空画布
        this.ctx.fillStyle = '#1a1a2e';
        this.ctx.fillRect(0, 0, width, height);

        // 渲染每个可见且在当前帧范围内的图层
        state.layers.forEach(layer => {
            if (!layer.visible) return;
            if (state.currentFrame < layer.from || state.currentFrame >= layer.from + layer.duration) return;

            this.renderLayer(layer, state);
        });

        // 渲染选中图层的边界框
        this.selectedLayerId = state.selectedLayerId;
        const selectedLayer = state.getSelectedLayer();
        if (selectedLayer && selectedLayer.visible &&
            state.currentFrame >= selectedLayer.from &&
            state.currentFrame < selectedLayer.from + selectedLayer.duration) {
            this.renderSelectionBox(selectedLayer);
        }
    }

    // 渲染选中框
    renderSelectionBox(layer) {
        const bounds = this.getLayerBounds(layer);
        if (!bounds) return;

        const { x, y, width, height } = bounds;

        this.ctx.save();
        this.ctx.strokeStyle = '#ffd93d';
        this.ctx.lineWidth = 2;
        this.ctx.setLineDash([5, 5]);
        this.ctx.strokeRect(x, y, width, height);

        // 绘制四角和四边中点控制点
        const handleSize = 8;
        this.ctx.fillStyle = '#ffd93d';
        this.ctx.setLineDash([]);

        // 8个控制点：四角 + 四边中点
        const handles = [
            { x: x, y: y, cursor: 'nw-resize', type: 'nw' },
            { x: x + width, y: y, cursor: 'ne-resize', type: 'ne' },
            { x: x, y: y + height, cursor: 'sw-resize', type: 'sw' },
            { x: x + width, y: y + height, cursor: 'se-resize', type: 'se' },
            { x: x + width / 2, y: y, cursor: 'n-resize', type: 'n' },
            { x: x + width / 2, y: y + height, cursor: 's-resize', type: 's' },
            { x: x, y: y + height / 2, cursor: 'w-resize', type: 'w' },
            { x: x + width, y: y + height / 2, cursor: 'e-resize', type: 'e' },
        ];

        handles.forEach(handle => {
            this.ctx.fillRect(handle.x - handleSize/2, handle.y - handleSize/2, handleSize, handleSize);
        });

        this.ctx.restore();
    }

    // 获取图层边界
    getLayerBounds(layer) {
        const { type, config } = layer;
        
        // 确保配置存在
        if (!config) return null;

        switch (type) {
            case 'color':
            case 'gradient':
                return {
                    x: config.x || 0,
                    y: config.y || 0,
                    width: config.width || 100,
                    height: config.height || 100
                };
            case 'text':
                this.ctx.font = `${config.fontSize || 16}px -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif`;
                const metrics = this.ctx.measureText(config.text || 'Text');
                return {
                    x: config.x || 0,
                    y: config.y || 0,
                    width: metrics.width,
                    height: config.fontSize || 16
                };
            case 'shape':
                const size = config.size || 50;
                return {
                    x: (config.x || 0) - size / 2,
                    y: (config.y || 0) - size / 2,
                    width: size,
                    height: size
                };
            default:
                return null;
        }
    }

    // 存储控制点信息
    getResizeHandles(layer) {
        const bounds = this.getLayerBounds(layer);
        if (!bounds) return [];

        const { x, y, width, height } = bounds;
        const handleSize = 12; // 点击区域稍大

        return [
            { x: x - handleSize/2, y: y - handleSize/2, w: handleSize, h: handleSize, type: 'nw', cursor: 'nw-resize' },
            { x: x + width - handleSize/2, y: y - handleSize/2, w: handleSize, h: handleSize, type: 'ne', cursor: 'ne-resize' },
            { x: x - handleSize/2, y: y + height - handleSize/2, w: handleSize, h: handleSize, type: 'sw', cursor: 'sw-resize' },
            { x: x + width - handleSize/2, y: y + height - handleSize/2, w: handleSize, h: handleSize, type: 'se', cursor: 'se-resize' },
            { x: x + width/2 - handleSize/2, y: y - handleSize/2, w: handleSize, h: handleSize, type: 'n', cursor: 'n-resize' },
            { x: x + width/2 - handleSize/2, y: y + height - handleSize/2, w: handleSize, h: handleSize, type: 's', cursor: 's-resize' },
            { x: x - handleSize/2, y: y + height/2 - handleSize/2, w: handleSize, h: handleSize, type: 'w', cursor: 'w-resize' },
            { x: x + width - handleSize/2, y: y + height/2 - handleSize/2, w: handleSize, h: handleSize, type: 'e', cursor: 'e-resize' },
        ];
    }

    // 检测是否点击了控制点
    hitTestHandle(layer, mouseX, mouseY) {
        const handles = this.getResizeHandles(layer);
        for (const handle of handles) {
            if (mouseX >= handle.x && mouseX <= handle.x + handle.w &&
                mouseY >= handle.y && mouseY <= handle.y + handle.h) {
                return handle;
            }
        }
        return null;
    }

    // 开始调整大小
    startResize(layer, handleType, mouseX, mouseY) {
        const bounds = this.getLayerBounds(layer);
        if (!bounds) return;

        this.resizeState = {
            layerId: layer.id,
            handleType: handleType,
            startX: mouseX,
            startY: mouseY,
            startBounds: { ...bounds },
            startConfig: { ...layer.config }
        };
    }

    // 调整大小中
    onResize(mouseX, mouseY, state) {
        if (!this.resizeState) return null;

        const layer = state.layers.find(l => l.id === this.resizeState.layerId);
        if (!layer) return null;

        const dx = mouseX - this.resizeState.startX;
        const dy = mouseY - this.resizeState.startY;
        const { handleType, startBounds, startConfig } = this.resizeState;

        const minSize = 10;
        let newConfig = { ...startConfig };

        // 根据控制点类型计算新尺寸
        switch (handleType) {
            case 'se': // 右下
                newConfig.width = Math.max(minSize, startBounds.width + dx);
                newConfig.height = Math.max(minSize, startBounds.height + dy);
                if (layer.type === 'shape') {
                    newConfig.size = Math.max(minSize, Math.max(startConfig.size + dx, startConfig.size + dy));
                }
                break;
            case 'sw': // 左下
                newConfig.x = startBounds.x + dx;
                newConfig.width = Math.max(minSize, startBounds.width - dx);
                newConfig.height = Math.max(minSize, startBounds.height + dy);
                if (layer.type === 'shape') {
                    newConfig.x = startConfig.x + dx / 2;
                    newConfig.size = Math.max(minSize, Math.max(startConfig.size - dx, startConfig.size + dy));
                }
                break;
            case 'ne': // 右上
                newConfig.y = startBounds.y + dy;
                newConfig.width = Math.max(minSize, startBounds.width + dx);
                newConfig.height = Math.max(minSize, startBounds.height - dy);
                if (layer.type === 'shape') {
                    newConfig.y = startConfig.y + dy / 2;
                    newConfig.size = Math.max(minSize, Math.max(startConfig.size + dx, startConfig.size - dy));
                }
                break;
            case 'nw': // 左上
                newConfig.x = startBounds.x + dx;
                newConfig.y = startBounds.y + dy;
                newConfig.width = Math.max(minSize, startBounds.width - dx);
                newConfig.height = Math.max(minSize, startBounds.height - dy);
                if (layer.type === 'shape') {
                    newConfig.x = startConfig.x + dx / 2;
                    newConfig.y = startConfig.y + dy / 2;
                    newConfig.size = Math.max(minSize, Math.max(startConfig.size - dx, startConfig.size - dy));
                }
                break;
            case 'n': // 上
                newConfig.y = startBounds.y + dy;
                newConfig.height = Math.max(minSize, startBounds.height - dy);
                if (layer.type === 'shape') {
                    newConfig.y = startConfig.y + dy / 2;
                    newConfig.size = Math.max(minSize, startConfig.size - dy);
                }
                break;
            case 's': // 下
                newConfig.height = Math.max(minSize, startBounds.height + dy);
                if (layer.type === 'shape') {
                    newConfig.size = Math.max(minSize, startConfig.size + dy);
                }
                break;
            case 'w': // 左
                newConfig.x = startBounds.x + dx;
                newConfig.width = Math.max(minSize, startBounds.width - dx);
                if (layer.type === 'shape') {
                    newConfig.x = startConfig.x + dx / 2;
                    newConfig.size = Math.max(minSize, startConfig.size - dx);
                }
                break;
            case 'e': // 右
                newConfig.width = Math.max(minSize, startBounds.width + dx);
                if (layer.type === 'shape') {
                    newConfig.size = Math.max(minSize, startConfig.size + dx);
                }
                break;
        }

        return newConfig;
    }

    // 结束调整大小
    endResize() {
        this.resizeState = null;
    }

    // 检测点击位置是否在图层内
    hitTest(layer, mouseX, mouseY) {
        const bounds = this.getLayerBounds(layer);
        if (!bounds) return false;

        return mouseX >= bounds.x && mouseX <= bounds.x + bounds.width &&
               mouseY >= bounds.y && mouseY <= bounds.y + bounds.height;
    }

    // 开始拖动
    startDrag(layer, mouseX, mouseY) {
        const bounds = this.getLayerBounds(layer);
        if (!bounds) return;

        this.dragState = {
            layerId: layer.id,
            startX: mouseX,
            startY: mouseY,
            layerStartX: layer.config.x,
            layerStartY: layer.config.y
        };
    }

    // 拖动中
    onDrag(mouseX, mouseY, state) {
        if (!this.dragState) return null;

        const layer = state.layers.find(l => l.id === this.dragState.layerId);
        if (!layer) return null;

        const dx = mouseX - this.dragState.startX;
        const dy = mouseY - this.dragState.startY;

        const newX = this.dragState.layerStartX + dx;
        const newY = this.dragState.layerStartY + dy;

        return { x: Math.round(newX), y: Math.round(newY) };
    }

    // 结束拖动
    endDrag() {
        this.dragState = null;
    }

    // 渲染单个图层
    renderLayer(layer, state) {
        const localFrame = state.currentFrame - layer.from;
        const progress = localFrame / layer.duration;

        switch (layer.type) {
            case 'color':
                this.renderColorLayer(layer, progress);
                break;
            case 'gradient':
                this.renderGradientLayer(layer, progress);
                break;
            case 'text':
                this.renderTextLayer(layer, progress);
                break;
            case 'shape':
                this.renderShapeLayer(layer, progress);
                break;
        }
    }

    // 渲染纯色层
    renderColorLayer(layer, progress) {
        const config = layer.config || {};
        const x = config.x || 0;
        const y = config.y || 0;
        const width = config.width || 100;
        const height = config.height || 100;
        const color = config.color || '#e94560';
        
        const anim = this.calculateAnimation(layer, progress);

        this.ctx.save();
        this.ctx.globalAlpha = Math.max(0, Math.min(1, anim.opacity));
        this.ctx.fillStyle = color;

        // 计算中心点
        const posX = x + (anim.x || 0);
        const posY = y + (anim.y || 0);
        const scaledWidth = Math.max(1, width * (anim.scaleX || 1));
        const scaledHeight = Math.max(1, height * (anim.scaleY || 1));
        const centerX = posX + scaledWidth / 2;
        const centerY = posY + scaledHeight / 2;

        // 应用旋转变换
        if (anim.rotation && anim.rotation !== 0) {
            this.ctx.translate(centerX, centerY);
            this.ctx.rotate(anim.rotation * Math.PI / 180);
            this.ctx.translate(-centerX, -centerY);
        }

        this.ctx.fillRect(posX, posY, scaledWidth, scaledHeight);
        this.ctx.restore();
    }

    // 渲染渐变层
    renderGradientLayer(layer, progress) {
        const config = layer.config || {};
        const x = config.x || 0;
        const y = config.y || 0;
        const width = config.width || 100;
        const height = config.height || 100;
        const startColor = config.startColor || '#e94560';
        const endColor = config.endColor || '#0f3460';
        const direction = config.direction || 'horizontal';
        
        const anim = this.calculateAnimation(layer, progress);

        this.ctx.save();
        this.ctx.globalAlpha = Math.max(0, Math.min(1, anim.opacity));

        const posX = x + (anim.x || 0);
        const posY = y + (anim.y || 0);
        const scaledWidth = Math.max(1, width * (anim.scaleX || 1));
        const scaledHeight = Math.max(1, height * (anim.scaleY || 1));
        const centerX = posX + scaledWidth / 2;
        const centerY = posY + scaledHeight / 2;

        // 应用旋转变换
        if (anim.rotation && anim.rotation !== 0) {
            this.ctx.translate(centerX, centerY);
            this.ctx.rotate(anim.rotation * Math.PI / 180);
            this.ctx.translate(-centerX, -centerY);
        }

        const gradient = direction === 'horizontal'
            ? this.ctx.createLinearGradient(posX, posY, posX + scaledWidth, posY)
            : this.ctx.createLinearGradient(posX, posY, posX, posY + scaledHeight);

        gradient.addColorStop(0, startColor);
        gradient.addColorStop(1, endColor);

        this.ctx.fillStyle = gradient;
        this.ctx.fillRect(posX, posY, scaledWidth, scaledHeight);
        this.ctx.restore();
    }

    // 渲染文字层
    renderTextLayer(layer, progress) {
        const config = layer.config || {};
        const x = config.x || 0;
        const y = config.y || 0;
        const text = config.text || 'Text';
        const fontSize = config.fontSize || 24;
        const color = config.color || '#ffffff';
        const align = config.align || 'left';
        
        const anim = this.calculateAnimation(layer, progress);

        this.ctx.save();
        this.ctx.globalAlpha = Math.max(0, Math.min(1, anim.opacity));
        this.ctx.font = `${fontSize}px -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif`;
        this.ctx.fillStyle = color;
        this.ctx.textAlign = align;
        this.ctx.textBaseline = 'top';

        const posX = x + (anim.x || 0);
        const posY = y + (anim.y || 0);

        this.ctx.translate(posX, posY);
        this.ctx.scale(anim.scaleX || 1, anim.scaleY || 1);
        
        if (anim.rotation && anim.rotation !== 0) {
            this.ctx.rotate(anim.rotation * Math.PI / 180);
        }

        this.ctx.fillText(text, 0, 0);

        this.ctx.restore();
    }

    // 渲染形状层
    renderShapeLayer(layer, progress) {
        const config = layer.config || {};
        const x = config.x || 0;
        const y = config.y || 0;
        const size = config.size || 50;
        const color = config.color || '#e94560';
        const shape = config.shape || 'circle';
        
        const anim = this.calculateAnimation(layer, progress);

        this.ctx.save();
        this.ctx.globalAlpha = Math.max(0, Math.min(1, anim.opacity));
        this.ctx.fillStyle = color;

        // 计算位置
        const posX = x + (anim.x || 0);
        const posY = y + (anim.y || 0);
        const scaledSize = Math.max(1, size * (anim.scaleX || 1));

        // 先移动到位置中心
        this.ctx.translate(posX, posY);
        
        // 应用旋转变换（围绕中心点旋转）
        if (anim.rotation && anim.rotation !== 0) {
            this.ctx.rotate(anim.rotation * Math.PI / 180);
        }

        // 绘制形状（以原点为中心）
        if (shape === 'circle') {
            this.ctx.beginPath();
            this.ctx.arc(0, 0, scaledSize / 2, 0, Math.PI * 2);
            this.ctx.fill();
        } else if (shape === 'rect') {
            this.ctx.fillRect(-scaledSize / 2, -scaledSize / 2, scaledSize, scaledSize);
        } else if (shape === 'triangle') {
            // 等边三角形，顶点朝上，中心在原点
            const h = scaledSize * Math.sqrt(3) / 2;
            this.ctx.beginPath();
            this.ctx.moveTo(0, -h * 2/3);
            this.ctx.lineTo(scaledSize / 2, h / 3);
            this.ctx.lineTo(-scaledSize / 2, h / 3);
            this.ctx.closePath();
            this.ctx.fill();
        }

        this.ctx.restore();
    }

    // 计算动画属性
    calculateAnimation(layer, progress) {
        // 默认动画值
        const anim = {
            opacity: 1,
            x: 0,
            y: 0,
            scaleX: 1,
            scaleY: 1,
            rotation: 0
        };

        // 应用动画效果
        const animations = layer.config.animations || [];

        animations.forEach(animation => {
            // 确保必要属性有默认值
            const animFrom = parseInt(animation.from) || 0;
            const animDuration = Math.max(1, parseInt(animation.duration) || 30);
            const animStartFrame = animFrom;
            const animEndFrame = animFrom + animDuration;
            const layerDuration = layer.duration || 1;
            const localFrame = progress * layerDuration;
            
            // 获取动画值范围（确保有效）
            const valueFrom = parseFloat(animation.valueFrom) || 0;
            const valueTo = parseFloat(animation.valueTo) !== undefined ? parseFloat(animation.valueTo) : 1;
            
            // 如果当前帧不在动画范围内
            if (localFrame < animStartFrame) {
                // 动画未开始，使用起始值
                switch (animation.type) {
                    case 'rotate':
                        anim.rotation = valueFrom;
                        break;
                    case 'scale':
                    case 'bounce':
                    case 'spring':
                        anim.scaleX = valueFrom;
                        anim.scaleY = valueFrom;
                        break;
                }
                return;
            }
            
            if (localFrame > animEndFrame) {
                // 动画已结束，使用最终值
                switch (animation.type) {
                    case 'rotate':
                        anim.rotation = valueTo;
                        break;
                    case 'scale':
                    case 'bounce':
                    case 'spring':
                        anim.scaleX = valueTo;
                        anim.scaleY = valueTo;
                        break;
                }
                return;
            }

            // 计算动画进度 (0-1)
            const animProgress = Math.max(0, Math.min(1, (localFrame - animStartFrame) / animDuration));
            const easedProgress = this.applyEasing(animProgress, animation.easing || 'easeOut');

            switch (animation.type) {
                case 'fadeIn':
                    anim.opacity = easedProgress;
                    break;
                case 'fadeOut':
                    anim.opacity = 1 - easedProgress;
                    break;
                case 'slideInLeft':
                    anim.x = -200 * (1 - easedProgress);
                    break;
                case 'slideInRight':
                    anim.x = 200 * (1 - easedProgress);
                    break;
                case 'slideInTop':
                    anim.y = -200 * (1 - easedProgress);
                    break;
                case 'slideInBottom':
                    anim.y = 200 * (1 - easedProgress);
                    break;
                case 'scale':
                    anim.scaleX = valueFrom + (valueTo - valueFrom) * easedProgress;
                    anim.scaleY = anim.scaleX;
                    break;
                case 'rotate':
                    anim.rotation = valueFrom + (valueTo - valueFrom) * easedProgress;
                    break;
                case 'bounce':
                case 'spring':
                    anim.scaleX = valueFrom + (valueTo - valueFrom) * easedProgress;
                    anim.scaleY = anim.scaleX;
                    break;
            }
        });

        return anim;
    }

    // 缓动函数
    applyEasing(t, easing) {
        switch (easing) {
            case 'easeIn':
                return t * t;
            case 'easeOut':
                return 1 - (1 - t) * (1 - t);
            case 'easeInOut':
                return t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
            case 'bounce':
                if (t < 1 / 2.75) {
                    return 7.5625 * t * t;
                } else if (t < 2 / 2.75) {
                    return 7.5625 * (t -= 1.5 / 2.75) * t + 0.75;
                } else if (t < 2.5 / 2.75) {
                    return 7.5625 * (t -= 2.25 / 2.75) * t + 0.9375;
                } else {
                    return 7.5625 * (t -= 2.625 / 2.75) * t + 0.984375;
                }
            default:
                return t;
        }
    }
}

// PHP代码生成器
class PHPCodeGenerator {
    generate(state) {
        const { composition, layers } = state;

        let code = `<?php\n\n/**\n * PHP Remotion 自动生成的动画配置\n * 生成时间: ${new Date().toLocaleString()}\n */\n\nrequire_once __DIR__ . '/vendor/autoload.php';\n\nuse Yangweijie\\Remotion\\Remotion;\nuse Yangweijie\\Remotion\\Core\\RenderContext;\nuse Yangweijie\\Remotion\\Animation\\Easing;\n\n// 创建合成\n$composition = Remotion::composition(\n    id: '${composition.id}',\n    renderer: function (RenderContext $ctx): \\GdImage {\n        $frame = $ctx->getCurrentFrame();\n        $config = $ctx->getVideoConfig();\n        \n        // 创建画布\n        $canvas = Remotion::createCanvas($config->width, $config->height, [26, 26, 46]);\n`;

        // 生成每个图层的渲染代码
        layers.forEach(layer => {
            code += this.generateLayerCode(layer);
        });

        code += `\n        return $canvas;\n    },\n    durationInFrames: ${composition.durationInFrames},\n    fps: ${composition.fps},\n    width: ${composition.width},\n    height: ${composition.height},\n);\n\n// 导出合成（供其他脚本使用）\nreturn $composition;\n`;

        return code;
    }

    generateLayerCode(layer) {
        const { type, config, from, duration } = layer;
        let code = '';

        switch (type) {
            case 'color':
                code = this.generateColorLayerCode(layer);
                break;
            case 'gradient':
                code = this.generateGradientLayerCode(layer);
                break;
            case 'text':
                code = this.generateTextLayerCode(layer);
                break;
            case 'shape':
                code = this.generateShapeLayerCode(layer);
                break;
        }

        return code;
    }

    generateColorLayerCode(layer) {
        const { config, from, duration } = layer;
        const hexToRgb = hex => {
            const r = parseInt(hex.slice(1, 3), 16);
            const g = parseInt(hex.slice(3, 5), 16);
            const b = parseInt(hex.slice(5, 7), 16);
            return [r, g, b];
        };
        const [r, g, b] = hexToRgb(config.color);

        return `\n        // ${layer.name}\n        if ($frame >= ${from} && $frame < ${from + duration}) {\n            $layer = Remotion::colorLayer(${config.width}, ${config.height}, ${r}, ${g}, ${b});\n            $layer->drawOn($canvas, ${config.x}, ${config.y});\n        }\n`;
    }

    generateGradientLayerCode(layer) {
        const { config, from, duration } = layer;
        const hexToRgb = hex => ({
            r: parseInt(hex.slice(1, 3), 16),
            g: parseInt(hex.slice(3, 5), 16),
            b: parseInt(hex.slice(5, 7), 16)
        });
        const start = hexToRgb(config.startColor);
        const end = hexToRgb(config.endColor);

        return `\n        // ${layer.name}\n        if ($frame >= ${from} && $frame < ${from + duration}) {\n            $layer = Remotion::gradientLayer(\n                ${config.width}, ${config.height},\n                ['r' => ${start.r}, 'g' => ${start.g}, 'b' => ${start.b}],\n                ['r' => ${end.r}, 'g' => ${end.g}, 'b' => ${end.b}],\n                '${config.direction}'\n            );\n            $layer->drawOn($canvas, ${config.x}, ${config.y});\n        }\n`;
    }

    generateTextLayerCode(layer) {
        const { config, from, duration } = layer;
        const hexToRgb = hex => ({
            r: parseInt(hex.slice(1, 3), 16),
            g: parseInt(hex.slice(3, 5), 16),
            b: parseInt(hex.slice(5, 7), 16)
        });
        const color = hexToRgb(config.color);

        return `\n        // ${layer.name}\n        if ($frame >= ${from} && $frame < ${from + duration}) {\n            $textLayer = Remotion::textLayer('${config.text}', [\n                'fontSize' => ${Math.ceil(config.fontSize / 5)},\n                'r' => ${color.r}, 'g' => ${color.g}, 'b' => ${color.b},\n                'align' => '${config.align}',\n            ]);\n            $textLayer->drawOn($canvas, ${config.x}, ${config.y});\n        }\n`;
    }

    generateShapeLayerCode(layer) {
        const { config, from, duration } = layer;
        const hexToRgb = hex => ({
            r: parseInt(hex.slice(1, 3), 16),
            g: parseInt(hex.slice(3, 5), 16),
            b: parseInt(hex.slice(5, 7), 16)
        });
        const color = hexToRgb(config.color);

        if (config.shape === 'circle') {
            return `\n        // ${layer.name} - 圆形\n        if ($frame >= ${from} && $frame < ${from + duration}) {\n            $color = imagecolorallocate($canvas, ${color.r}, ${color.g}, ${color.b});\n            imagefilledellipse($canvas, ${config.x}, ${config.y}, ${config.size}, ${config.size}, $color);\n        }\n`;
        } else if (config.shape === 'rect') {
            return `\n        // ${layer.name} - 矩形\n        if ($frame >= ${from} && $frame < ${from + duration}) {\n            $color = imagecolorallocate($canvas, ${color.r}, ${color.g}, ${color.b});\n            $halfSize = ${config.size} / 2;\n            imagefilledrectangle(\n                $canvas,\n                ${config.x} - $halfSize,\n                ${config.y} - $halfSize,\n                ${config.x} + $halfSize,\n                ${config.y} + $halfSize,\n                $color\n            );\n        }\n`;
        }

        return '';
    }
}

// 初始化编辑器
const state = new EditorState();
const renderer = new PreviewRenderer('preview-canvas');
const codeGenerator = new PHPCodeGenerator();

// UI更新函数
function updateUI() {
    updateLayerList();
    updateTimeline();
    updateCompositionSettings();
    updateLayerProperties();
    renderPreview();
}

// 更新图层列表
function updateLayerList() {
    const list = document.getElementById('layer-list');
    list.innerHTML = '';

    [...state.layers].reverse().forEach(layer => {
        const item = document.createElement('div');
        item.className = `layer-item ${layer.id === state.selectedLayerId ? 'active' : ''}`;
        item.innerHTML = `
            <span class="visibility" data-id="${layer.id}">${layer.visible ? '👁' : '🙈'}</span>
            <span class="layer-icon">${getLayerIcon(layer.type)}</span>
            <span class="layer-name">${layer.name}</span>
            <span class="layer-delete" data-id="${layer.id}">✕</span>
        `;

        item.addEventListener('click', () => {
            state.selectLayer(layer.id);
            updateUI();
        });

        list.appendChild(item);
    });

    // 绑定可见性和删除按钮事件
    document.querySelectorAll('.layer-item .visibility').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            state.toggleLayerVisibility(btn.dataset.id);
            updateUI();
        });
    });

    document.querySelectorAll('.layer-item .layer-delete').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            state.removeLayer(btn.dataset.id);
            updateUI();
        });
    });
}

// 获取图层图标
function getLayerIcon(type) {
    const icons = {
        color: '🎨',
        gradient: '🌈',
        text: '📝',
        image: '🖼️',
        shape: '⭕'
    };
    return icons[type] || '📄';
}

// 更新时间轴
function updateTimeline() {
    const tracks = document.getElementById('timeline-tracks');
    const scale = document.getElementById('timeline-scale');
    const playhead = document.getElementById('playhead');

    // 更新时间刻度
    scale.innerHTML = '';
    const duration = state.composition.durationInFrames;
    const step = Math.ceil(duration / 10);
    for (let i = 0; i <= duration; i += step) {
        const mark = document.createElement('div');
        mark.className = 'timeline-scale-mark';
        mark.style.left = `${(i / duration) * 100}%`;
        mark.textContent = `${i}f`;
        scale.appendChild(mark);
    }

    // 更新轨道
    tracks.innerHTML = '';
    state.layers.forEach(layer => {
        const track = document.createElement('div');
        track.className = 'timeline-track';
        track.innerHTML = `
            <div class="track-label">${layer.name}</div>
            <div class="track-content">
                <div class="track-item ${layer.id === state.selectedLayerId ? 'selected' : ''}"
                     style="left: ${(layer.from / duration) * 100}%; width: ${(layer.duration / duration) * 100}%;"
                     data-id="${layer.id}">
                    ${layer.name}
                    <div class="track-item-resize left"></div>
                    <div class="track-item-resize right"></div>
                </div>
            </div>
        `;
        tracks.appendChild(track);
    });

    // 更新播放头位置
    playhead.style.left = `${(state.currentFrame / duration) * 100}%`;

    // 更新时间显示
    const timeDisplay = document.getElementById('time-display');
    const currentTime = (state.currentFrame / state.composition.fps).toFixed(2);
    const totalTime = (state.composition.durationInFrames / state.composition.fps).toFixed(2);
    timeDisplay.textContent = `${currentTime}s / ${totalTime}s`;

    // 更新时间轴滑块
    const scrubber = document.getElementById('timeline-scrubber');
    scrubber.max = duration;
    scrubber.value = state.currentFrame;
}

// 更新合成设置面板
function updateCompositionSettings() {
    document.getElementById('comp-id').value = state.composition.id;
    document.getElementById('comp-width').value = state.composition.width;
    document.getElementById('comp-height').value = state.composition.height;
    document.getElementById('comp-fps').value = state.composition.fps;
    document.getElementById('comp-duration').value = state.composition.durationInFrames;
}

// 更新图层属性面板
function updateLayerProperties() {
    const layerProps = document.getElementById('layer-properties');
    const animProps = document.getElementById('animation-properties');
    const propsContent = document.getElementById('layer-props-content');
    const animContent = document.getElementById('anim-props-content');

    const layer = state.getSelectedLayer();

    if (!layer) {
        layerProps.style.display = 'none';
        animProps.style.display = 'none';
        return;
    }

    layerProps.style.display = 'block';
    animProps.style.display = 'block';

    // 生成图层属性表单
    propsContent.innerHTML = generateLayerPropertyForm(layer);

    // 生成动画属性表单
    animContent.innerHTML = generateAnimationPropertyForm(layer);

    // 绑定属性变更事件
    bindLayerPropertyEvents(layer);

    // 绑定动画属性事件
    bindAnimationPropertyEvents(layer);
}

// 生成动画属性表单
function generateAnimationPropertyForm(layer) {
    const animations = layer.config.animations || [];
    let html = '';

    if (animations.length === 0) {
        html = '<p style="color: var(--text-secondary); font-size: 12px; text-align: center; padding: 10px;">暂无动画，点击下方按钮添加</p>';
    } else {
        animations.forEach((anim, index) => {
            const animNames = {
                fadeIn: '淡入',
                fadeOut: '淡出',
                slideInLeft: '从左滑入',
                slideInRight: '从右滑入',
                slideInTop: '从上滑入',
                slideInBottom: '从下滑入',
                scale: '缩放',
                rotate: '旋转',
                bounce: '弹跳',
                spring: '弹簧'
            };

            html += `
                <div class="anim-property" data-index="${index}">
                    <div class="anim-property-header">
                        <span class="anim-property-title">${animNames[anim.type] || anim.type}</span>
                        <span class="anim-property-remove" data-index="${index}">✕</span>
                    </div>
                    <div class="form-group">
                        <label>开始帧</label>
                        <input type="number" class="anim-from" data-index="${index}" value="${anim.from}" min="0">
                    </div>
                    <div class="form-group">
                        <label>持续帧数</label>
                        <input type="number" class="anim-duration" data-index="${index}" value="${anim.duration}" min="1">
                    </div>
                    <div class="form-group">
                        <label>缓动</label>
                        <select class="anim-easing" data-index="${index}">
                            <option value="linear" ${anim.easing === 'linear' ? 'selected' : ''}>线性</option>
                            <option value="easeIn" ${anim.easing === 'easeIn' ? 'selected' : ''}>缓入</option>
                            <option value="easeOut" ${anim.easing === 'easeOut' ? 'selected' : ''}>缓出</option>
                            <option value="easeInOut" ${anim.easing === 'easeInOut' ? 'selected' : ''}>缓入缓出</option>
                            <option value="bounce" ${anim.easing === 'bounce' ? 'selected' : ''}>弹跳</option>
                        </select>
                    </div>
            `;

            // 为旋转和缩放动画添加值范围编辑
            if (anim.type === 'rotate') {
                html += `
                    <div class="form-group">
                        <label>起始角度</label>
                        <input type="number" class="anim-value-from" data-index="${index}" value="${anim.valueFrom !== undefined ? anim.valueFrom : 0}">
                    </div>
                    <div class="form-group">
                        <label>结束角度</label>
                        <input type="number" class="anim-value-to" data-index="${index}" value="${anim.valueTo !== undefined ? anim.valueTo : 360}">
                    </div>
                `;
            } else if (anim.type === 'scale' || anim.type === 'bounce' || anim.type === 'spring') {
                html += `
                    <div class="form-group">
                        <label>起始值</label>
                        <input type="number" class="anim-value-from" data-index="${index}" value="${anim.valueFrom !== undefined ? anim.valueFrom : 0.5}" step="0.1">
                    </div>
                    <div class="form-group">
                        <label>结束值</label>
                        <input type="number" class="anim-value-to" data-index="${index}" value="${anim.valueTo !== undefined ? anim.valueTo : 1.0}" step="0.1">
                    </div>
                `;
            }

            html += '</div>';
        });
    }

    return html;
}

// 绑定动画属性事件
function bindAnimationPropertyEvents(layer) {
    const animations = layer.config.animations || [];

    // 删除动画
    document.querySelectorAll('.anim-property-remove').forEach(btn => {
        btn.addEventListener('click', () => {
            const index = parseInt(btn.dataset.index);
            animations.splice(index, 1);
            state.updateLayer(layer.id, { animations });
            updateLayerProperties();
            renderPreview();
        });
    });

    // 修改开始帧
    document.querySelectorAll('.anim-from').forEach(input => {
        input.addEventListener('change', () => {
            const index = parseInt(input.dataset.index);
            animations[index].from = parseInt(input.value);
            state.updateLayer(layer.id, { animations });
            renderPreview();
        });
    });

    // 修改持续帧数
    document.querySelectorAll('.anim-duration').forEach(input => {
        input.addEventListener('change', () => {
            const index = parseInt(input.dataset.index);
            animations[index].duration = parseInt(input.value);
            state.updateLayer(layer.id, { animations });
            renderPreview();
        });
    });

    // 修改缓动
    document.querySelectorAll('.anim-easing').forEach(select => {
        select.addEventListener('change', () => {
            const index = parseInt(select.dataset.index);
            animations[index].easing = select.value;
            state.updateLayer(layer.id, { animations });
            renderPreview();
        });
    });

    // 修改起始值（旋转角度/缩放值）
    document.querySelectorAll('.anim-value-from').forEach(input => {
        input.addEventListener('change', () => {
            const index = parseInt(input.dataset.index);
            animations[index].valueFrom = parseFloat(input.value);
            state.updateLayer(layer.id, { animations });
            renderPreview();
        });
    });

    // 修改结束值（旋转角度/缩放值）
    document.querySelectorAll('.anim-value-to').forEach(input => {
        input.addEventListener('change', () => {
            const index = parseInt(input.dataset.index);
            animations[index].valueTo = parseFloat(input.value);
            state.updateLayer(layer.id, { animations });
            renderPreview();
        });
    });
}

// 生成图层属性表单
function generateLayerPropertyForm(layer) {
    const config = layer.config;
    let html = '';

    // 通用属性
    html += `
        <div class="form-group">
            <label>图层名称</label>
            <input type="text" id="layer-name" value="${layer.name}">
        </div>
        <div class="form-group">
            <label>开始帧</label>
            <input type="number" id="layer-from" value="${layer.from}" min="0">
        </div>
        <div class="form-group">
            <label>持续帧数</label>
            <input type="number" id="layer-duration" value="${layer.duration}" min="1">
        </div>
    `;

    // 特定类型属性
    switch (layer.type) {
        case 'color':
            html += `
                <div class="form-group">
                    <label>颜色</label>
                    <input type="color" id="prop-color" value="${config.color}">
                </div>
                <div class="form-group">
                    <label>X 坐标</label>
                    <input type="number" id="prop-x" value="${config.x}">
                </div>
                <div class="form-group">
                    <label>Y 坐标</label>
                    <input type="number" id="prop-y" value="${config.y}">
                </div>
                <div class="form-group">
                    <label>宽度</label>
                    <input type="number" id="prop-width" value="${config.width}" min="1">
                </div>
                <div class="form-group">
                    <label>高度</label>
                    <input type="number" id="prop-height" value="${config.height}" min="1">
                </div>
            `;
            break;
        case 'gradient':
            html += `
                <div class="form-group">
                    <label>起始颜色</label>
                    <input type="color" id="prop-startColor" value="${config.startColor}">
                </div>
                <div class="form-group">
                    <label>结束颜色</label>
                    <input type="color" id="prop-endColor" value="${config.endColor}">
                </div>
                <div class="form-group">
                    <label>方向</label>
                    <select id="prop-direction">
                        <option value="horizontal" ${config.direction === 'horizontal' ? 'selected' : ''}>水平</option>
                        <option value="vertical" ${config.direction === 'vertical' ? 'selected' : ''}>垂直</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>X 坐标</label>
                    <input type="number" id="prop-x" value="${config.x}">
                </div>
                <div class="form-group">
                    <label>Y 坐标</label>
                    <input type="number" id="prop-y" value="${config.y}">
                </div>
                <div class="form-group">
                    <label>宽度</label>
                    <input type="number" id="prop-width" value="${config.width}" min="1">
                </div>
                <div class="form-group">
                    <label>高度</label>
                    <input type="number" id="prop-height" value="${config.height}" min="1">
                </div>
            `;
            break;
        case 'text':
            html += `
                <div class="form-group">
                    <label>文字内容</label>
                    <input type="text" id="prop-text" value="${config.text}">
                </div>
                <div class="form-group">
                    <label>字体大小</label>
                    <input type="number" id="prop-fontSize" value="${config.fontSize}" min="1">
                </div>
                <div class="form-group">
                    <label>颜色</label>
                    <input type="color" id="prop-color" value="${config.color}">
                </div>
                <div class="form-group">
                    <label>X 坐标</label>
                    <input type="number" id="prop-x" value="${config.x}">
                </div>
                <div class="form-group">
                    <label>Y 坐标</label>
                    <input type="number" id="prop-y" value="${config.y}">
                </div>
                <div class="form-group">
                    <label>对齐方式</label>
                    <select id="prop-align">
                        <option value="left" ${config.align === 'left' ? 'selected' : ''}>左对齐</option>
                        <option value="center" ${config.align === 'center' ? 'selected' : ''}>居中</option>
                        <option value="right" ${config.align === 'right' ? 'selected' : ''}>右对齐</option>
                    </select>
                </div>
            `;
            break;
        case 'shape':
            html += `
                <div class="form-group">
                    <label>形状</label>
                    <select id="prop-shape">
                        <option value="circle" ${config.shape === 'circle' ? 'selected' : ''}>圆形</option>
                        <option value="rect" ${config.shape === 'rect' ? 'selected' : ''}>矩形</option>
                        <option value="triangle" ${config.shape === 'triangle' ? 'selected' : ''}>三角形</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>颜色</label>
                    <input type="color" id="prop-color" value="${config.color}">
                </div>
                <div class="form-group">
                    <label>X 坐标</label>
                    <input type="number" id="prop-x" value="${config.x}">
                </div>
                <div class="form-group">
                    <label>Y 坐标</label>
                    <input type="number" id="prop-y" value="${config.y}">
                </div>
                <div class="form-group">
                    <label>大小</label>
                    <input type="number" id="prop-size" value="${config.size}" min="1">
                </div>
            `;
            break;
    }

    return html;
}

// 绑定图层属性事件
function bindLayerPropertyEvents(layer) {
    // 名称变更
    const nameInput = document.getElementById('layer-name');
    if (nameInput) {
        nameInput.addEventListener('input', (e) => {
            state.updateLayerName(layer.id, e.target.value);
            updateTimeline();
        });
    }

    // 时间变更
    const fromInput = document.getElementById('layer-from');
    const durationInput = document.getElementById('layer-duration');
    if (fromInput && durationInput) {
        const updateTime = () => {
            state.updateLayerTime(layer.id, parseInt(fromInput.value), parseInt(durationInput.value));
            updateTimeline();
        };
        fromInput.addEventListener('change', updateTime);
        durationInput.addEventListener('change', updateTime);
    }

    // 配置属性变更
    const configProps = ['color', 'startColor', 'endColor', 'direction', 'text', 'fontSize', 'align', 'shape', 'size', 'x', 'y', 'width', 'height'];
    configProps.forEach(prop => {
        const input = document.getElementById(`prop-${prop}`);
        if (input) {
            input.addEventListener('input', (e) => {
                const value = e.target.type === 'number' ? parseInt(e.target.value) : e.target.value;
                state.updateLayer(layer.id, { [prop]: value });
                renderPreview();
            });
        }
    });
}

// 渲染预览
function renderPreview() {
    renderer.setSize(state.composition.width, state.composition.height);
    renderer.render(state);
}

// 播放控制
function play() {
    if (state.isPlaying) return;
    state.isPlaying = true;

    const frameInterval = 1000 / state.composition.fps;

    state.playTimer = setInterval(() => {
        state.currentFrame++;
        if (state.currentFrame >= state.composition.durationInFrames) {
            state.currentFrame = 0;
        }
        updateTimeline();
        renderPreview();
    }, frameInterval);
}

function pause() {
    state.isPlaying = false;
    if (state.playTimer) {
        clearInterval(state.playTimer);
        state.playTimer = null;
    }
}

function stop() {
    pause();
    state.currentFrame = 0;
    updateTimeline();
    renderPreview();
}

// 导出PHP代码
function exportPHP() {
    const code = codeGenerator.generate(state);
    const preview = document.getElementById('php-code-preview');
    preview.textContent = code;
    document.getElementById('export-modal').classList.add('active');
}

// 事件绑定
document.addEventListener('DOMContentLoaded', () => {
    // 初始化UI
    updateUI();

    // 拖拽功能
    document.querySelectorAll('.layer-type').forEach(item => {
        item.addEventListener('dragstart', (e) => {
            e.dataTransfer.setData('layer-type', item.dataset.type);
            item.classList.add('dragging');
        });

        item.addEventListener('dragend', () => {
            item.classList.remove('dragging');
        });
    });

    // 预览区域放置
    const previewContainer = document.getElementById('preview-container');
    const previewCanvas = document.getElementById('preview-canvas');

    // 交互状态
    let isDraggingLayer = false;
    let isResizingLayer = false;

    previewContainer.addEventListener('dragover', (e) => {
        e.preventDefault();
        previewContainer.classList.add('drag-over');
    });

    previewContainer.addEventListener('dragleave', () => {
        previewContainer.classList.remove('drag-over');
    });

    previewContainer.addEventListener('drop', (e) => {
        e.preventDefault();
        previewContainer.classList.remove('drag-over');

        const type = e.dataTransfer.getData('layer-type');
        if (type) {
            const coords = getCanvasCoordinates(e, previewCanvas);
            const layer = state.addLayer(type, { x: coords.x, y: coords.y });
            state.selectLayer(layer.id);
            updateUI();
        }
    });

    // 获取画布坐标
    function getCanvasCoordinates(e, canvas) {
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;
        return {
            x: Math.round((e.clientX - rect.left) * scaleX),
            y: Math.round((e.clientY - rect.top) * scaleY)
        };
    }

    // 画布鼠标按下 - 选择/拖动/调整大小
    previewCanvas.addEventListener('mousedown', (e) => {
        const coords = getCanvasCoordinates(e, previewCanvas);
        const mouseX = coords.x;
        const mouseY = coords.y;

        // 获取可见图层
        const visibleLayers = state.layers.filter(layer => {
            if (!layer.visible) return false;
            if (state.currentFrame < layer.from || state.currentFrame >= layer.from + layer.duration) return false;
            return true;
        }).reverse();

        // 1. 首先检查是否点击了选中图层的控制点
        const selectedLayer = state.getSelectedLayer();
        if (selectedLayer && visibleLayers.includes(selectedLayer)) {
            const handle = renderer.hitTestHandle(selectedLayer, mouseX, mouseY);
            if (handle) {
                renderer.startResize(selectedLayer, handle.type, mouseX, mouseY);
                isResizingLayer = true;
                previewCanvas.style.cursor = handle.cursor;
                return;
            }
        }

        // 2. 检查是否点击了某个图层
        let clickedLayer = null;
        for (const layer of visibleLayers) {
            if (renderer.hitTest(layer, mouseX, mouseY)) {
                clickedLayer = layer;
                break;
            }
        }

        if (clickedLayer) {
            state.selectLayer(clickedLayer.id);
            renderer.startDrag(clickedLayer, mouseX, mouseY);
            isDraggingLayer = true;
            previewCanvas.style.cursor = 'grabbing';
            updateUI();
        } else {
            // 点击空白区域，取消选择
            state.selectLayer(null);
            updateUI();
        }
    });

    // 画布鼠标移动
    previewCanvas.addEventListener('mousemove', (e) => {
        const coords = getCanvasCoordinates(e, previewCanvas);
        const mouseX = coords.x;
        const mouseY = coords.y;

        // 调整大小
        if (isResizingLayer) {
            const newConfig = renderer.onResize(mouseX, mouseY, state);
            if (newConfig) {
                const layer = state.getSelectedLayer();
                if (layer) {
                    state.updateLayer(layer.id, newConfig);
                    renderPreview();
                    updatePropertyInputs(layer, newConfig);
                }
            }
            return;
        }

        // 拖动图层
        if (isDraggingLayer) {
            const newPos = renderer.onDrag(mouseX, mouseY, state);
            if (newPos) {
                const layer = state.getSelectedLayer();
                if (layer) {
                    state.updateLayer(layer.id, { x: newPos.x, y: newPos.y });
                    renderPreview();
                    updatePropertyInputs(layer, { x: newPos.x, y: newPos.y });
                }
            }
            return;
        }

        // 更新鼠标光标（悬停在控制点上时）
        const selectedLayer = state.getSelectedLayer();
        if (selectedLayer) {
            const handle = renderer.hitTestHandle(selectedLayer, mouseX, mouseY);
            if (handle) {
                previewCanvas.style.cursor = handle.cursor;
                return;
            }
        }

        previewCanvas.style.cursor = 'default';
    });

    // 更新属性面板输入框
    function updatePropertyInputs(layer, updates) {
        Object.keys(updates).forEach(key => {
            const input = document.getElementById(`prop-${key}`);
            if (input) {
                input.value = updates[key];
            }
        });
    }

    // 画布鼠标释放
    previewCanvas.addEventListener('mouseup', () => {
        if (isResizingLayer) {
            renderer.endResize();
            isResizingLayer = false;
        }
        if (isDraggingLayer) {
            renderer.endDrag();
            isDraggingLayer = false;
        }
        previewCanvas.style.cursor = 'default';
    });

    // 鼠标离开画布
    previewCanvas.addEventListener('mouseleave', () => {
        if (isResizingLayer) {
            renderer.endResize();
            isResizingLayer = false;
        }
        if (isDraggingLayer) {
            renderer.endDrag();
            isDraggingLayer = false;
        }
        previewCanvas.style.cursor = 'default';
    });

    // 画布点击空白区域 - 取消选择
    previewCanvas.addEventListener('click', (e) => {
        if (isDraggingLayer || isResizingLayer) return;

        const coords = getCanvasCoordinates(e, previewCanvas);
        let clickedLayer = false;

        for (const layer of state.layers) {
            if (!layer.visible) continue;
            if (state.currentFrame < layer.from || state.currentFrame >= layer.from + layer.duration) continue;

            if (renderer.hitTest(layer, coords.x, coords.y)) {
                clickedLayer = true;
                break;
            }
        }

        if (!clickedLayer) {
            state.selectLayer(null);
            updateUI();
        }
    });

    // 播放控制
    document.getElementById('btn-play').addEventListener('click', play);
    document.getElementById('btn-pause').addEventListener('click', pause);
    document.getElementById('btn-stop').addEventListener('click', stop);

    // 时间轴滑块
    document.getElementById('timeline-scrubber').addEventListener('input', (e) => {
        pause();
        state.setCurrentFrame(parseInt(e.target.value));
        updateTimeline();
        renderPreview();
    });

    // 合成设置变更
    ['comp-id', 'comp-width', 'comp-height', 'comp-fps', 'comp-duration'].forEach(id => {
        document.getElementById(id).addEventListener('change', (e) => {
            const value = e.target.type === 'number' ? parseInt(e.target.value) : e.target.value;
            const prop = id.replace('comp-', '');
            state.updateComposition({ [prop]: value });
            updateUI();
        });
    });

    // 预设选择
    document.getElementById('preset-select').addEventListener('change', (e) => {
        const presets = {
            'hd-1080p': { width: 1920, height: 1080 },
            'hd-720p': { width: 1280, height: 720 },
            'story-1080p': { width: 1080, height: 1920 },
            'square-1080p': { width: 1080, height: 1080 },
            'sd-480p': { width: 854, height: 480 }
        };

        if (presets[e.target.value]) {
            state.updateComposition(presets[e.target.value]);
            updateUI();
        }
    });

    // 导出功能
    document.getElementById('btn-export').addEventListener('click', exportPHP);
    document.getElementById('btn-preview').addEventListener('click', play);

    // 模态框关闭
    document.querySelector('.modal-close').addEventListener('click', () => {
        document.getElementById('export-modal').classList.remove('active');
    });

    // 复制代码
    document.getElementById('btn-copy-code').addEventListener('click', () => {
        const code = document.getElementById('php-code-preview').textContent;
        navigator.clipboard.writeText(code).then(() => {
            alert('代码已复制到剪贴板！');
        });
    });

    // 下载PHP文件
    document.getElementById('btn-download').addEventListener('click', () => {
        const code = document.getElementById('php-code-preview').textContent;
        const name = document.getElementById('export-name').value || 'animation-config';
        const blob = new Blob([code], { type: 'text/php' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${name}.php`;
        a.click();
        URL.revokeObjectURL(url);
    });

    // 清空按钮
    document.getElementById('btn-clear').addEventListener('click', () => {
        if (confirm('确定要清空所有图层吗？')) {
            state.clear();
            updateUI();
        }
    });

    // 动画预设点击
    document.querySelectorAll('.anim-preset').forEach(preset => {
        preset.addEventListener('click', () => {
            const layer = state.getSelectedLayer();
            if (!layer) {
                alert('请先选择一个图层');
                return;
            }

            const animations = layer.config.animations || [];
            const animType = preset.dataset.anim;
            
            // 基础动画配置
            const animConfig = {
                type: animType,
                from: state.currentFrame,
                duration: 30,
                easing: 'easeOut'
            };
            
            // 为特定动画类型添加默认值
            if (animType === 'rotate') {
                animConfig.valueFrom = 0;
                animConfig.valueTo = 360;
            } else if (animType === 'scale') {
                animConfig.valueFrom = 0.5;
                animConfig.valueTo = 1.0;
            } else if (animType === 'bounce' || animType === 'spring') {
                animConfig.valueFrom = 0;
                animConfig.valueTo = 1;
            }
            
            animations.push(animConfig);
            state.updateLayer(layer.id, { animations });
            updateLayerProperties();
            renderPreview();
        });
    });

    // 添加动画按钮
    const addAnimBtn = document.getElementById('btn-add-anim');
    if (addAnimBtn) {
        addAnimBtn.addEventListener('click', () => {
            const layer = state.getSelectedLayer();
            if (!layer) {
                alert('请先选择一个图层');
                return;
            }

            // 显示动画选择菜单
            const animTypes = [
                { type: 'fadeIn', name: '淡入' },
                { type: 'fadeOut', name: '淡出' },
                { type: 'slideInLeft', name: '从左滑入' },
                { type: 'slideInRight', name: '从右滑入' },
                { type: 'slideInTop', name: '从上滑入' },
                { type: 'slideInBottom', name: '从下滑入' },
                { type: 'scale', name: '缩放' },
                { type: 'rotate', name: '旋转' },
                { type: 'bounce', name: '弹跳' },
                { type: 'spring', name: '弹簧' }
            ];

            const choice = prompt(
                '选择动画类型：\n' + animTypes.map((a, i) => `${i + 1}. ${a.name}`).join('\n') + '\n\n请输入数字：'
            );

            if (choice) {
                const index = parseInt(choice) - 1;
                if (index >= 0 && index < animTypes.length) {
                    const selectedAnim = animTypes[index];
                    const animations = layer.config.animations || [];
                    
                    // 基础动画配置
                    const animConfig = {
                        type: selectedAnim.type,
                        from: state.currentFrame,  // 开始帧
                        duration: 30,               // 持续帧数
                        easing: 'easeOut'
                    };
                    
                    // 为特定动画类型添加默认值
                    if (selectedAnim.type === 'rotate') {
                        animConfig.valueFrom = 0;    // 起始角度
                        animConfig.valueTo = 360;    // 结束角度
                    } else if (selectedAnim.type === 'scale') {
                        animConfig.valueFrom = 0.5;  // 起始缩放
                        animConfig.valueTo = 1.0;    // 结束缩放
                    } else if (selectedAnim.type === 'bounce' || selectedAnim.type === 'spring') {
                        animConfig.valueFrom = 0;
                        animConfig.valueTo = 1;
                    }
                    
                    animations.push(animConfig);
                    state.updateLayer(layer.id, { animations });
                    updateLayerProperties();
                    renderPreview();
                }
            }
        });
    }
});