/**
 * PHP Remotion 可视化动画编辑器
 * Visual Animation Editor
 * 
 * =====================
 * 架构说明 (Architecture)
 * =====================
 * 
 * 全局应用状态（App 对象）：
 * - App.state: 编辑器状态管理，负责图层列表、合成配置、播放状态等
 * - App.renderer: 预览渲染器，负责画布渲染和图层绘制
 * - App.codeGenerator: PHP 代码生成器，负责生成可执行的 PHP 代码
 * - App.interaction: 交互状态管理，负责拖拽、缩放等交互操作
 * 
 * 职责分离：
 * - EditorState: 数据模型层，管理编辑器的核心数据状态
 * - PreviewRenderer: 视图层，负责 Canvas 渲染和图形绘制
 * - PHPCodeGenerator: 代码生成层，负责将编辑器状态转换为 PHP 代码
 * 
 * 依赖关系：
 * - UI 层 → App.state (更新数据)
 * - App.renderer → App.state (读取数据进行渲染)
 * - App.codeGenerator → App.state (读取数据生成代码)
 * 
 * =====================
 * 主要功能模块
 * =====================
 * 
 * 1. 安全工具模块
 *    - escapeHtml(): HTML 转义，防止 XSS
 *    - escapePhpString(): PHP 字符串转义，防止代码注入
 *    - validateComposition(): 合成配置验证
 *    - validateLayerConfig(): 图层配置验证
 * 
 * 2. 错误处理模块
 *    - 全局错误捕获
 *    - Promise 拒绝处理
 *    - 错误提示显示
 *    - safeExecute(): 安全执行包装器
 * 
 * 3. 性能优化模块
 *    - debounce(): 防抖函数，减少频繁渲染
 *    - throttle(): 节流函数，限制事件触发频率
 * 
 * 4. 编辑器状态管理
 *    - 图层增删改查
 *    - 合成配置管理
 *    - 播放状态控制
 * 
 * 5. 预览渲染引擎
 *    - 图层渲染（颜色、渐变、文字、形状）
 *    - 动画效果计算
 *    - 选择框和控制点绘制
 *    - 拖拽和缩放交互
 * 
 * 6. 代码生成引擎
 *    - PHP 代码生成
 *    - 图层配置转换
 *    - 动画代码生成
 * 
 * =====================
 * 使用说明
 * =====================
 * 
 * 1. 初始化：页面加载时自动调用 initializeEditor()
 * 2. 交互：通过鼠标拖拽添加图层，通过控制点调整大小
 * 3. 配置：在右侧面板修改图层属性和动画
 * 4. 导出：点击"导出 PHP"按钮生成可执行代码
 * 
 * =====================
 * 维护指南
 * =====================
 * 
 * 1. 添加新功能：
 *    - 确定功能属于哪个模块
 *    - 遵循职责分离原则
 *    - 添加适当的错误处理
 *    - 更新文档注释
 * 
 * 2. 修复 Bug：
 *    - 先检查相关模块的职责
 *    - 确保修复不违反职责分离
 *    - 添加回归测试
 * 
 * 3. 性能优化：
 *    - 使用防抖/节流减少重复操作
 *    - 避免不必要的重渲染
 *    - 优化复杂算法
 * 
 * 4. 安全考虑：
 *    - 所有用户输入必须经过验证
 *    - 使用转义函数防止注入
 *    - 添加错误边界
 * 
 */

// ========== 历史记录管理（撤销/重做） ==========

/**
 * 历史记录管理模块
 * 
 * 职责：
 * - 管理撤销/重做栈
 * - 保存状态快照
 * - 执行撤销和重做操作
 * 
 * 特性：
 * - 深度克隆状态，避免引用问题
 * - 限制历史记录数量，防止内存溢出
 * - 支持批量操作（连续修改）
 */

// 保存当前状态到历史记录
function saveState(action = '操作') {
    if (!App.state) return;
    
    // 深度克隆当前状态
    const stateSnapshot = {
        composition: { ...App.state.composition },
        layers: App.state.layers.map(layer => ({ ...layer, config: { ...layer.config } })),
        selectedLayerId: App.state.selectedLayerId,
        currentFrame: App.state.currentFrame,
        isPlaying: App.state.isPlaying,  // 保存播放状态
        timestamp: Date.now(),
        action: action
    };
    
    // 添加到撤销栈
    App.history.undoStack.push(stateSnapshot);
    
    // 限制栈大小
    if (App.history.undoStack.length > App.history.maxSize) {
        App.history.undoStack.shift();
    }
    
    // 清空重做栈（新操作后不能再重做）
    App.history.redoStack = [];
    
    // 更新撤销/重做按钮状态
    updateUndoRedoButtons();
}

// 撤销操作
function undo() {
    if (App.history.undoStack.length === 0) {
        showErrorToast('没有可撤销的操作');
        return false;
    }
    
    // 暂停播放，避免状态恢复时的冲突
    const wasPlaying = App.state.isPlaying;
    if (wasPlaying) {
        pause();
    }
    
    // 保存当前状态到重做栈
    const currentState = {
        composition: { ...App.state.composition },
        layers: App.state.layers.map(layer => ({ ...layer, config: { ...layer.config } })),
        selectedLayerId: App.state.selectedLayerId,
        currentFrame: App.state.currentFrame,
        isPlaying: wasPlaying,
        timestamp: Date.now(),
        action: '撤销'
    };
    
    App.history.redoStack.push(currentState);
    
    // 从撤销栈取出上一个状态
    const previousState = App.history.undoStack.pop();
    
    // 恢复状态
    App.state.composition = { ...previousState.composition };
    App.state.layers = previousState.layers.map(layer => ({ ...layer, config: { ...layer.config } }));
    App.state.selectedLayerId = previousState.selectedLayerId;
    App.state.currentFrame = previousState.currentFrame;
    App.state.isPlaying = previousState.isPlaying || false;
    
    // 更新界面
    updateUI();
    updateUndoRedoButtons();
    
    // 如果之前在播放，恢复播放
    if (App.state.isPlaying) {
        play();
    }
    
    return true;
}

// 重做操作
function redo() {
    if (App.history.redoStack.length === 0) {
        showErrorToast('没有可重做的操作');
        return false;
    }
    
    // 暂停播放，避免状态恢复时的冲突
    const wasPlaying = App.state.isPlaying;
    if (wasPlaying) {
        pause();
    }
    
    // 保存当前状态到撤销栈
    const currentState = {
        composition: { ...App.state.composition },
        layers: App.state.layers.map(layer => ({ ...layer, config: { ...layer.config } })),
        selectedLayerId: App.state.selectedLayerId,
        currentFrame: App.state.currentFrame,
        isPlaying: wasPlaying,
        timestamp: Date.now(),
        action: '重做'
    };
    
    App.history.undoStack.push(currentState);
    
    // 从重做栈取出下一个状态
    const nextState = App.history.redoStack.pop();
    
    // 恢复状态
    App.state.composition = { ...nextState.composition };
    App.state.layers = nextState.layers.map(layer => ({ ...layer, config: { ...layer.config } }));
    App.state.selectedLayerId = nextState.selectedLayerId;
    App.state.currentFrame = nextState.currentFrame;
    App.state.isPlaying = nextState.isPlaying || false;
    
    // 更新界面
    updateUI();
    updateUndoRedoButtons();
    
    // 如果之前在播放，恢复播放
    if (App.state.isPlaying) {
        play();
    }
    
    return true;
}

// 开始批量操作（避免保存中间状态）
function beginBatch() {
    App.history.isBatching = true;
    App.history.batchChanges = [];
}

// 结束批量操作
function endBatch(action = '批量操作') {
    App.history.isBatching = false;
    if (App.history.batchChanges.length > 0) {
        saveState(action);
    }
    App.history.batchChanges = [];
}

// 检查是否可以撤销
function canUndo() {
    return App.history.undoStack.length > 0;
}

// 检查是否可以重做
function canRedo() {
    return App.history.redoStack.length > 0;
}

// 更新撤销/重做按钮状态
function updateUndoRedoButtons() {
    const undoBtn = document.getElementById('btn-undo');
    const redoBtn = document.getElementById('btn-redo');
    
    if (undoBtn) {
        undoBtn.disabled = !canUndo();
        undoBtn.style.opacity = canUndo() ? '1' : '0.5';
    }
    
    if (redoBtn) {
        redoBtn.disabled = !canRedo();
        redoBtn.style.opacity = canRedo() ? '1' : '0.5';
    }
}

// 清空历史记录
function clearHistory() {
    App.history.undoStack = [];
    App.history.redoStack = [];
    updateUndoRedoButtons();
}

// ========== 自动保存功能 ==========

/**
 * 自动保存当前编辑器状态到 localStorage
 */
function autosave() {
    if (!App.autosave.enabled || !App.state) return;
    
    try {
        const saveData = {
            composition: App.state.composition,
            layers: App.state.layers,
            selectedLayerId: App.state.selectedLayerId,
            currentFrame: App.state.currentFrame,
            saveTime: Date.now()
        };
        
        localStorage.setItem(App.autosave.saveKey, JSON.stringify(saveData));
        App.autosave.lastSaveTime = Date.now();
        
        console.log('自动保存成功:', new Date().toLocaleTimeString());
    } catch (error) {
        console.error('自动保存失败:', error);
    }
}

/**
 * 从 localStorage 恢复编辑器状态
 * @returns {boolean} 是否成功恢复
 */
function restoreAutosave() {
    try {
        const savedData = localStorage.getItem(App.autosave.saveKey);
        if (!savedData) return false;
        
        const saveData = JSON.parse(savedData);
        
        // 检查保存时间，如果太久远则不恢复（7天）
        const saveTime = saveData.saveTime || 0;
        const oneWeek = 7 * 24 * 60 * 60 * 1000;
        if (Date.now() - saveTime > oneWeek) {
            console.log('自动保存已过期，不恢复');
            localStorage.removeItem(App.autosave.saveKey);
            return false;
        }
        
        // 恢复状态
        App.state.composition = saveData.composition;
        App.state.layers = saveData.layers;
        App.state.selectedLayerId = saveData.selectedLayerId;
        App.state.currentFrame = saveData.currentFrame;
        
        // 清空历史记录，避免冲突
        clearHistory();
        
        // 保存初始状态
        saveState('恢复自动保存');
        
        console.log('自动保存恢复成功:', new Date(saveTime).toLocaleString());
        return true;
    } catch (error) {
        console.error('恢复自动保存失败:', error);
        return false;
    }
}

/**
 * 启动自动保存定时器
 */
function startAutosave() {
    if (!App.autosave.enabled) return;
    
    // 初始保存
    autosave();
    
    // 启动定时保存
    setInterval(() => {
        autosave();
    }, App.autosave.interval);
}

/**
 * 清除自动保存的数据
 */
function clearAutosave() {
    localStorage.removeItem(App.autosave.saveKey);
    App.autosave.lastSaveTime = 0;
    console.log('自动保存数据已清除');
}

// ========== 主题切换功能 ==========

/**
 * 切换主题
 */
function toggleTheme() {
    const newTheme = App.theme.current === 'dark' ? 'light' : 'dark';
    setTheme(newTheme);
}

/**
 * 设置主题
 */
function setTheme(themeName) {
    if (!App.theme.themes[themeName]) {
        console.error('主题不存在:', themeName);
        return;
    }
    
    App.theme.current = themeName;
    const theme = App.theme.themes[themeName];
    
    // 应用主题颜色
    const root = document.documentElement;
    Object.keys(theme).forEach(key => {
        root.style.setProperty(key, theme[key]);
    });
    
    // 保存主题偏好
    localStorage.setItem('remotion-editor-theme', themeName);
    
    console.log('主题已切换为:', themeName);
}

/**
 * 初始化主题
 */
function initializeTheme() {
    // 从 localStorage 读取主题偏好
    const savedTheme = localStorage.getItem('remotion-editor-theme');
    if (savedTheme && App.theme.themes[savedTheme]) {
        setTheme(savedTheme);
    } else {
        setTheme('dark');
    }
}

// ========== 性能优化工具 ==========

// 防抖函数
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// 节流函数
function throttle(func, limit) {
    let inThrottle;
    return function executedFunction(...args) {
        if (!inThrottle) {
            func(...args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// ========== 全局应用状态管理 ==========
// 将所有全局变量封装到 App 对象中，便于测试和维护
const App = {
    // 编辑器状态
    state: null,
    // 预览渲染器
    renderer: null,
    // 代码生成器
    codeGenerator: null,
    // 交互状态
    interaction: {
        isDraggingLayer: false,
        isResizingLayer: false,
        dragStartX: 0,
        dragStartY: 0,
        resizeHandle: null,
        selectedLayerIds: []  // 多选图层 ID 列表
    },
    // 历史记录（撤销/重做）
    history: {
        undoStack: [],      // 撤销栈
        redoStack: [],      // 重做栈
        maxSize: 50,        // 最大历史记录数
        isBatching: false,  // 是否在批量操作中
        batchChanges: []    // 批量操作中的变更
    },
    // 自动保存配置
    autosave: {
        enabled: true,          // 是否启用自动保存
        interval: 30000,        // 保存间隔（毫秒），默认 30 秒
        lastSaveTime: 0,        // 上次保存时间
        saveKey: 'remotion-editor-autosave'  // localStorage 保存键
    },
    // 主题配置
    theme: {
        current: 'dark',  // 当前主题：dark | light
        themes: {
            dark: {
                '--bg-primary': '#1a1a2e',
                '--bg-secondary': '#16213e',
                '--bg-tertiary': '#0f3460',
                '--bg-hover': '#1f4068',
                '--text-primary': '#ffffff',
                '--text-secondary': '#a0a0a0',
                '--accent-primary': '#e94560',
                '--accent-secondary': '#ff6b6b',
                '--border-color': '#2d3561',
                '--success': '#51cf66',
                '--warning': '#ffd93d',
                '--danger': '#ff6b6b'
            },
            light: {
                '--bg-primary': '#ffffff',
                '--bg-secondary': '#f5f5f5',
                '--bg-tertiary': '#e0e0e0',
                '--bg-hover': '#e8e8e8',
                '--text-primary': '#333333',
                '--text-secondary': '#666666',
                '--accent-primary': '#e94560',
                '--accent-secondary': '#ff6b6b',
                '--border-color': '#d0d0d0',
                '--success': '#51cf66',
                '--warning': '#ffd93d',
                '--danger': '#ff6b6b'
            }
        }
    }
};

// ========== 安全工具函数 ==========

// HTML 转义函数，防止 XSS 攻击
function escapeHtml(unsafe) {
    if (typeof unsafe !== 'string') {
        return '';
    }
    return unsafe
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// PHP 字符串转义函数，防止代码注入
function escapePhpString(str) {
    if (typeof str !== 'string') {
        return '';
    }
    return str
        .replace(/\\/g, '\\\\')
        .replace(/\$/g, '\\$')
        .replace(/"/g, '\\"')
        .replace(/'/g, "\\'")
        .replace(/\n/g, '\\n')
        .replace(/\r/g, '\\r')
        .replace(/\t/g, '\\t');
}

// 合成配置验证函数
function validateComposition(config) {
    const validated = { ...config };

    // 验证并限制宽度（最小 320，最大 4096）
    if (typeof validated.width !== 'number' || validated.width < 320 || validated.width > 4096) {
        validated.width = Math.max(320, Math.min(4096, validated.width || 640));
    }

    // 验证并限制高度（最小 240，最大 4096）
    if (typeof validated.height !== 'number' || validated.height < 240 || validated.height > 4096) {
        validated.height = Math.max(240, Math.min(4096, validated.height || 360));
    }

    // 验证并限制帧率（最小 1，最大 60）
    if (typeof validated.fps !== 'number' || validated.fps < 1 || validated.fps > 60) {
        validated.fps = Math.max(1, Math.min(60, validated.fps || 30));
    }

    // 验证并限制时长（最小 1，最大 60000）
    if (typeof validated.durationInFrames !== 'number' || validated.durationInFrames < 1 || validated.durationInFrames > 60000) {
        validated.durationInFrames = Math.max(1, Math.min(60000, validated.durationInFrames || 90));
    }

    // 验证 ID（只允许字母、数字、下划线和短横线）
    if (typeof validated.id !== 'string' || !/^[a-zA-Z0-9_-]+$/.test(validated.id)) {
        validated.id = 'my-animation';
    }

    // 验证背景图片（可选）
    if (validated.backgroundImage !== undefined && validated.backgroundImage !== '') {
        if (typeof validated.backgroundImage !== 'string') {
            validated.backgroundImage = '';
        }
    } else {
        validated.backgroundImage = '';
    }

    return validated;
}

// 图层配置验证函数
function validateLayerConfig(type, config) {
    const validated = { ...config };

    // 验证并限制坐标（允许负值，最大值限制为画布尺寸的2倍）
    if (validated.x !== undefined) {
        if (typeof validated.x !== 'number' || isNaN(validated.x)) {
            validated.x = 0;
        }
    }
    if (validated.y !== undefined) {
        if (typeof validated.y !== 'number' || isNaN(validated.y)) {
            validated.y = 0;
        }
    }

    // 验证并限制尺寸（最小 1，最大 4096）
    if (validated.width !== undefined) {
        if (typeof validated.width !== 'number' || validated.width < 1 || validated.width > 4096) {
            validated.width = Math.max(1, Math.min(4096, validated.width || 100));
        }
    }
    if (validated.height !== undefined) {
        if (typeof validated.height !== 'number' || validated.height < 1 || validated.height > 4096) {
            validated.height = Math.max(1, Math.min(4096, validated.height || 100));
        }
    }

    // 验证颜色格式（必须是有效的十六进制颜色）
    const colorFields = ['color', 'startColor', 'endColor'];
    colorFields.forEach(field => {
        if (validated[field] !== undefined) {
            if (typeof validated[field] === 'string') {
                if (!/^#[0-9A-Fa-f]{6}$/.test(validated[field])) {
                    validated[field] = field === 'startColor' ? '#e94560' :
                                      field === 'endColor' ? '#0f3460' : '#e94560';
                }
            } else {
                validated[field] = field === 'startColor' ? '#e94560' :
                                  field === 'endColor' ? '#0f3460' : '#e94560';
            }
        }
    });

    // 验证文本内容（限制长度，防止内存溢出）
    if (validated.text !== undefined && typeof validated.text === 'string') {
        validated.text = validated.text.slice(0, 1000);
    }

    // 验证字体大小（最小 8，最大 200）
    if (validated.fontSize !== undefined) {
        if (typeof validated.fontSize !== 'number' || validated.fontSize < 8 || validated.fontSize > 200) {
            validated.fontSize = Math.max(8, Math.min(200, validated.fontSize || 32));
        }
    }

    // 验证形状大小（最小 1，最大 2000）
    if (validated.size !== undefined) {
        if (typeof validated.size !== 'number' || validated.size < 1 || validated.size > 2000) {
            validated.size = Math.max(1, Math.min(2000, validated.size || 50));
        }
    }

    // 验证对齐方式
    if (validated.align !== undefined && typeof validated.align === 'string') {
        const validAligns = ['left', 'center', 'right'];
        if (!validAligns.includes(validated.align)) {
            validated.align = 'left';
        }
    }

    // 验证渐变方向
    if (validated.direction !== undefined && typeof validated.direction === 'string') {
        const validDirections = ['horizontal', 'vertical'];
        if (!validDirections.includes(validated.direction)) {
            validated.direction = 'horizontal';
        }
    }

    // 验证形状类型
    if (validated.shape !== undefined && typeof validated.shape === 'string') {
        const validShapes = ['circle', 'rect', 'triangle', 'diamond', 'pentagon', 'hexagon', 'octagon', 'star', 'heart', 'petal', 'clover', 'oval', 'cross', 'arrow', 'cloud', 'moon', 'sun', 'lightning', 'shield', 'bubble'];
        if (!validShapes.includes(validated.shape)) {
            validated.shape = 'circle';
        }
    }

    // 验证时间帧数
    if (validated.from !== undefined) {
        if (typeof validated.from !== 'number' || validated.from < 0 || isNaN(validated.from)) {
            validated.from = 0;
        }
    }
    if (validated.duration !== undefined) {
        if (typeof validated.duration !== 'number' || validated.duration < 1 || isNaN(validated.duration)) {
            validated.duration = 90;
        }
    }

    return validated;
}

// ========== 错误处理机制 ==========

// 全局错误处理器
window.addEventListener('error', (event) => {
    console.error('全局错误:', event.error);
    showErrorToast('发生错误: ' + (event.error?.message || '未知错误'));
    event.preventDefault();
});

// 未处理的 Promise 拒绝
window.addEventListener('unhandledrejection', (event) => {
    console.error('未处理的 Promise 拒绝:', event.reason);
    showErrorToast('操作失败: ' + (event.reason?.message || '未知错误'));
    event.preventDefault();
});

// 显示错误提示
function showErrorToast(message) {
    const toast = document.createElement('div');
    toast.className = 'error-toast';
    toast.textContent = message;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #e74c3c;
        color: white;
        padding: 12px 24px;
        border-radius: 4px;
        z-index: 10000;
        animation: slideIn 0.3s ease-out;
    `;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

// 添加 CSS 动画
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);

// 安全执行函数（带错误处理）
function safeExecute(fn, errorMessage = '操作失败') {
    try {
        return fn();
    } catch (error) {
        console.error(errorMessage, error);
        showErrorToast(errorMessage + ': ' + (error.message || '未知错误'));
        return null;
    }
}

// ========== 编辑器状态管理 (EditorState) ==========
/**
 * 编辑器状态管理类
 * 
 * 职责：
 * - 管理合成配置（分辨率、帧率、时长）
 * - 管理图层列表（添加、删除、更新）
 * - 管理播放状态（播放、暂停、停止）
 * - 提供图层查询和选择功能
 * 
 * 不负责：
 * - 渲染逻辑（由 PreviewRenderer 负责）
 * - UI 更新（由 UI 函数负责）
 * - 代码生成（由 PHPCodeGenerator 负责）
 */
class EditorState {
    constructor() {
        // 合成配置（使用验证后的默认值）
        this.composition = validateComposition({
            id: 'my-animation',
            width: 640,
            height: 360,
            fps: 30,
            durationInFrames: 90
        });

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
        try {
            this.layerCounter++;
            const defaultConfig = this.getDefaultLayerConfig(type);
            const validatedConfig = validateLayerConfig(type, { ...defaultConfig, ...config });

            const layer = {
                id: `layer-${this.layerCounter}`,
                type: type,
                name: config.name || `${this.getLayerTypeName(type)} ${this.layerCounter}`,
                visible: true,
                locked: config.locked || false,  // 图层锁定状态
                from: config.from || 0,
                duration: config.duration || this.composition.durationInFrames,
                config: validatedConfig
            };
            this.layers.push(layer);
            
            // 保存状态到历史记录
            saveState('添加图层');
            
            return layer;
        } catch (error) {
            console.error('添加图层失败:', error);
            throw new Error('添加图层失败: ' + error.message);
        }
    }

    // 删除图层
    removeLayer(layerId) {
        const index = this.layers.findIndex(l => l.id === layerId);
        if (index > -1) {
            // 检查图层是否锁定
            if (this.layers[index].locked) {
                showErrorToast('图层已锁定，无法删除');
                return;
            }
            
            // 如果正在播放，停止播放（因为画布内容已变化）
            const wasPlaying = this.isPlaying;
            if (wasPlaying) {
                pause();
            }
            
            this.layers.splice(index, 1);
            if (this.selectedLayerId === layerId) {
                this.selectedLayerId = null;
            }
            
            // 保存状态到历史记录（不保存播放状态，因为删除图层会停止播放）
            saveState('删除图层');
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
    updateLayer(layerId, updates, skipHistory = false) {
        try {
            const layer = this.layers.find(l => l.id === layerId);
            if (layer) {
                const validatedUpdates = validateLayerConfig(layer.type, updates);
                Object.assign(layer.config, validatedUpdates);
                // 只有在非拖拽状态下才保存历史记录
                if (!skipHistory && !App.interaction.isDraggingLayer && !App.interaction.isResizingLayer) {
                    saveState('更新图层');
                }
            }
        } catch (error) {
            console.error('更新图层配置失败:', error);
            throw new Error('更新图层配置失败: ' + error.message);
        }
    }

    // 更新图层时间
    updateLayerTime(layerId, from, duration) {
        try {
            const layer = this.layers.find(l => l.id === layerId);
            if (layer) {
                // 验证时间参数
                layer.from = Math.max(0, parseInt(from) || 0);
                layer.duration = Math.max(1, parseInt(duration) || 1);
                // 保存状态到历史记录
                saveState('更新图层时间');
            }
        } catch (error) {
            console.error('更新图层时间失败:', error);
            throw new Error('更新图层时间失败: ' + error.message);
        }
    }

    // 更新图层名称
    updateLayerName(layerId, name) {
        try {
            const layer = this.layers.find(l => l.id === layerId);
            if (layer && typeof name === 'string') {
                layer.name = name.slice(0, 100); // 限制名称长度
                // 保存状态到历史记录
                saveState('重命名图层');
            }
        } catch (error) {
            console.error('更新图层名称失败:', error);
            throw new Error('更新图层名称失败: ' + error.message);
        }
    }

    // 切换图层可见性
    toggleLayerVisibility(layerId) {
        const layer = this.layers.find(l => l.id === layerId);
        if (layer) {
            layer.visible = !layer.visible;
            // 保存状态到历史记录
            saveState('切换图层可见性');
        }
    }

    // 选择图层
    selectLayer(layerId) {
        // 只有当选择改变时才保存状态
        if (this.selectedLayerId !== layerId) {
            this.selectedLayerId = layerId;
            saveState('选择图层');
        } else {
            this.selectedLayerId = layerId;
        }
    }

    // 多选图层
    selectMultipleLayers(layerIds) {
        if (layerIds.length === 0) {
            this.selectedLayerId = null;
        } else {
            this.selectedLayerId = layerIds[0];
        }
        saveState('多选图层');
    }

    // 添加到多选
    addToSelection(layerId) {
        if (!App.interaction.selectedLayerIds.includes(layerId)) {
            App.interaction.selectedLayerIds.push(layerId);
            if (!this.selectedLayerId) {
                this.selectedLayerId = layerId;
            }
        }
    }

    // 从多选中移除
    removeFromSelection(layerId) {
        const index = App.interaction.selectedLayerIds.indexOf(layerId);
        if (index > -1) {
            App.interaction.selectedLayerIds.splice(index, 1);
            if (this.selectedLayerId === layerId) {
                this.selectedLayerId = App.interaction.selectedLayerIds[0] || null;
            }
        }
    }

    // 清空多选
    clearSelection() {
        App.interaction.selectedLayerIds = [];
        this.selectedLayerId = null;
    }

    // 切换图层选择状态
    toggleLayerSelection(layerId) {
        if (App.interaction.selectedLayerIds.includes(layerId)) {
            this.removeFromSelection(layerId);
        } else {
            this.addToSelection(layerId);
        }
    }

    // 获取所有选中的图层
    getSelectedLayers() {
        const selectedIds = App.interaction.selectedLayerIds.length > 0 
            ? App.interaction.selectedLayerIds 
            : (this.selectedLayerId ? [this.selectedLayerId] : []);
        return this.layers.filter(l => selectedIds.includes(l.id));
    }

    // 获取选中的图层
    getSelectedLayer() {
        return this.layers.find(l => l.id === this.selectedLayerId);
    }

    // 更新合成配置
    updateComposition(updates) {
        // 验证更新后的配置
        const validated = validateComposition({
            ...this.composition,
            ...updates
        });
        Object.assign(this.composition, validated);
        // 保存状态到历史记录
        saveState('更新合成配置');
    }

    // 设置当前帧
    setCurrentFrame(frame) {
        this.currentFrame = Math.max(0, Math.min(frame, this.composition.durationInFrames));
    }

    // 清除所有图层
    clear() {
        // 如果正在播放，停止播放（因为画布内容已变化）
        if (this.isPlaying) {
            pause();
        }
        
        this.layers = [];
        this.selectedLayerId = null;
        this.currentFrame = 0;
        this.layerCounter = 0;
        // 保存状态到历史记录
        saveState('清空所有图层');
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

// ========== 预览渲染引擎 (PreviewRenderer) ==========
/**
 * 预览渲染器类
 * 
 * 职责：
 * - 渲染所有图层到 Canvas
 * - 计算和显示动画效果
 * - 绘制选择框和控制点
 * - 处理拖拽和缩放交互
 * - 提供图层边界计算
 * 
 * 不负责：
 * - 状态管理（由 EditorState 负责）
 * - PHP 代码生成（由 PHPCodeGenerator 负责）
 * - UI 事件绑定（由事件监听器负责）
 */
class PreviewRenderer {
    constructor(canvasId) {
        this.canvas = document.getElementById(canvasId);
        this.ctx = this.canvas.getContext('2d');
        this.selectedLayerId = null;
        this.dragState = null;
        this.loadedImages = {}; // 缓存已加载的图片
    }

    // 设置画布尺寸
    setSize(width, height) {
        this.canvas.width = width;
        this.canvas.height = height;
    }

    // 渲染帧
    render(state) {
        if (!state || !state.composition || !state.layers) {
            console.error('渲染状态无效');
            return;
        }

        const { width, height, backgroundImage } = state.composition;

        // 验证画布尺寸
        if (typeof width !== 'number' || typeof height !== 'number' ||
            width <= 0 || height <= 0) {
            console.error('无效的画布尺寸');
            return;
        }

        // 清空画布
        this.ctx.fillStyle = '#1a1a2e';
        this.ctx.fillRect(0, 0, width, height);

        // 渲染背景图片
        if (backgroundImage) {
            const bgImage = this.loadedImages['background'];
            if (!bgImage) {
                // 加载背景图片
                const img = new Image();
                img.onload = () => {
                    this.loadedImages['background'] = img;
                    renderPreview(); // 重新渲染
                };
                img.src = backgroundImage;
            } else {
                // 绘制背景图片（覆盖模式）
                const imgRatio = bgImage.width / bgImage.height;
                const canvasRatio = width / height;
                
                let drawWidth, drawHeight, drawX, drawY;
                
                if (imgRatio > canvasRatio) {
                    // 图片更宽，以高度为准
                    drawHeight = height;
                    drawWidth = drawHeight * imgRatio;
                    drawX = (width - drawWidth) / 2;
                    drawY = 0;
                } else {
                    // 图片更高，以宽度为准
                    drawWidth = width;
                    drawHeight = drawWidth / imgRatio;
                    drawX = 0;
                    drawY = (height - drawHeight) / 2;
                }
                
                this.ctx.drawImage(bgImage, drawX, drawY, drawWidth, drawHeight);
            }
        }

        // 渲染每个可见且在当前帧范围内的图层
        state.layers.forEach(layer => {
            if (!layer || !layer.visible) return;
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
            case 'image':
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
                // 所有形状统一使用 size x size 的正方形边界
                // 三角形会在这个正方形内居中绘制
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
        // 检查图层是否锁定
        if (layer.locked) {
            console.log('图层已锁定，无法调整大小');
            return;
        }
        
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
        // 检查图层是否锁定
        if (layer.locked) {
            console.log('图层已锁定，无法拖动');
            return;
        }
        
        const bounds = this.getLayerBounds(layer);
        if (!bounds) return;

        const selectedLayers = App.interaction.selectedLayerIds.length > 0 
            ? state.layers.filter(l => App.interaction.selectedLayerIds.includes(l.id))
            : [layer];

        // 保存所有选中图层的起始位置
        const allLayersStartPos = selectedLayers.map(l => ({
            id: l.id,
            x: l.config.x,
            y: l.config.y
        }));

        this.dragState = {
            layerId: layer.id,
            startX: mouseX,
            startY: mouseY,
            layerStartX: layer.config.x,
            layerStartY: layer.config.y,
            allLayersStartPos: allLayersStartPos
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
            case 'image':
                this.renderImageLayer(layer, progress);
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
        const halfSize = scaledSize / 2;

        // 先移动到位置中心
        this.ctx.translate(posX, posY);
        
        // 应用旋转变换（围绕中心点旋转）
        if (anim.rotation && anim.rotation !== 0) {
            this.ctx.rotate(anim.rotation * Math.PI / 180);
        }

        // 绘制形状（以原点为中心）
        this.ctx.beginPath();
        
        if (shape === 'circle') {
            this.ctx.arc(0, 0, halfSize, 0, Math.PI * 2);
            this.ctx.fill();
        } else if (shape === 'rect') {
            this.ctx.fillRect(-halfSize, -halfSize, scaledSize, scaledSize);
        } else if (shape === 'triangle') {
            // 等边三角形
            const h = scaledSize * Math.sqrt(3) / 2;
            const verticalOffset = (scaledSize - h) / 2;
            
            this.ctx.moveTo(0, -halfSize + verticalOffset);
            this.ctx.lineTo(halfSize, halfSize - verticalOffset);
            this.ctx.lineTo(-halfSize, halfSize - verticalOffset);
            this.ctx.closePath();
            this.ctx.fill();
        } else if (shape === 'diamond') {
            // 菱形
            this.ctx.moveTo(0, -halfSize);
            this.ctx.lineTo(halfSize, 0);
            this.ctx.lineTo(0, halfSize);
            this.ctx.lineTo(-halfSize, 0);
            this.ctx.closePath();
            this.ctx.fill();
        } else if (shape === 'pentagon') {
            // 五边形
            for (let i = 0; i < 5; i++) {
                const angle = (i * 2 * Math.PI / 5) - Math.PI / 2;
                const px = halfSize * Math.cos(angle);
                const py = halfSize * Math.sin(angle);
                if (i === 0) {
                    this.ctx.moveTo(px, py);
                } else {
                    this.ctx.lineTo(px, py);
                }
            }
            this.ctx.closePath();
            this.ctx.fill();
        } else if (shape === 'hexagon') {
            // 六边形
            for (let i = 0; i < 6; i++) {
                const angle = (i * 2 * Math.PI / 6) - Math.PI / 2;
                const px = halfSize * Math.cos(angle);
                const py = halfSize * Math.sin(angle);
                if (i === 0) {
                    this.ctx.moveTo(px, py);
                } else {
                    this.ctx.lineTo(px, py);
                }
            }
            this.ctx.closePath();
            this.ctx.fill();
        } else if (shape === 'octagon') {
            // 八边形
            for (let i = 0; i < 8; i++) {
                const angle = (i * 2 * Math.PI / 8) - Math.PI / 8;
                const px = halfSize * Math.cos(angle);
                const py = halfSize * Math.sin(angle);
                if (i === 0) {
                    this.ctx.moveTo(px, py);
                } else {
                    this.ctx.lineTo(px, py);
                }
            }
            this.ctx.closePath();
            this.ctx.fill();
        } else if (shape === 'star') {
            // 五角星
            const outerRadius = halfSize;
            const innerRadius = halfSize * 0.4;
            for (let i = 0; i < 10; i++) {
                const radius = i % 2 === 0 ? outerRadius : innerRadius;
                const angle = (i * Math.PI / 5) - Math.PI / 2;
                const px = radius * Math.cos(angle);
                const py = radius * Math.sin(angle);
                if (i === 0) {
                    this.ctx.moveTo(px, py);
                } else {
                    this.ctx.lineTo(px, py);
                }
            }
            this.ctx.closePath();
            this.ctx.fill();
        } else if (shape === 'heart') {
            // 心形
            const topCurveHeight = halfSize * 0.3;
            this.ctx.moveTo(0, halfSize * 0.3);
            this.ctx.bezierCurveTo(halfSize, -halfSize * 0.5, halfSize, -halfSize * 0.8, 0, -halfSize * 0.5);
            this.ctx.bezierCurveTo(-halfSize, -halfSize * 0.8, -halfSize, -halfSize * 0.5, 0, halfSize * 0.3);
            this.ctx.fill();
        } else if (shape === 'petal') {
            // 花瓣（5个心形花瓣组成的形状）
            const petalRadius = halfSize * 0.7;
            const petalScale = 0.35; // 每个花瓣的大小
            
            for (let i = 0; i < 5; i++) {
                const angle = (i * 2 * Math.PI / 5) - Math.PI / 2;
                const centerX = petalRadius * Math.cos(angle);
                const centerY = petalRadius * Math.sin(angle);
                
                // 绘制心形花瓣
                this.ctx.save();
                this.ctx.translate(centerX, centerY);
                this.ctx.rotate(angle + Math.PI / 2); // 旋转使心形指向外
                
                // 心形绘制
                const heartSize = scaledSize * petalScale;
                this.ctx.moveTo(0, -heartSize * 0.5);
                this.ctx.bezierCurveTo(
                    heartSize * 0.5, -heartSize, 
                    heartSize, -heartSize * 0.5, 
                    0, heartSize * 0.5
                );
                this.ctx.bezierCurveTo(
                    -heartSize, -heartSize * 0.5, 
                    -heartSize * 0.5, -heartSize, 
                    0, -heartSize * 0.5
                );
                
                this.ctx.fill();
                this.ctx.restore();
            }
        } else if (shape === 'clover') {
            // 四叶草
            const leafSize = halfSize * 0.5;
            const leafRadius = halfSize * 0.6;
            
            // 绘制4个叶子
            for (let i = 0; i < 4; i++) {
                const angle = (i * Math.PI / 2) - Math.PI / 4;
                const centerX = leafRadius * Math.cos(angle);
                const centerY = leafRadius * Math.sin(angle);
                
                this.ctx.save();
                this.ctx.translate(centerX, centerY);
                this.ctx.rotate(angle + Math.PI / 2);
                
                // 绘制叶子（心形）
                this.ctx.moveTo(0, -leafSize);
                this.ctx.bezierCurveTo(
                    leafSize * 0.8, -leafSize * 0.8, 
                    leafSize * 0.8, 0, 
                    0, leafSize
                );
                this.ctx.bezierCurveTo(
                    -leafSize * 0.8, 0, 
                    -leafSize * 0.8, -leafSize * 0.8, 
                    0, -leafSize
                );
                
                this.ctx.fill();
                this.ctx.restore();
            }
            
            // 绘制中心
            this.ctx.beginPath();
            this.ctx.arc(0, 0, leafSize * 0.2, 0, Math.PI * 2);
            this.ctx.fill();
        } else if (shape === 'oval') {
            // 椭圆
            this.ctx.ellipse(0, 0, halfSize, halfSize * 0.6, 0, 0, Math.PI * 2);
            this.ctx.fill();
        } else if (shape === 'cross') {
            // 十字形
            const crossWidth = halfSize * 0.4;
            this.ctx.moveTo(-crossWidth, -halfSize);
            this.ctx.lineTo(crossWidth, -halfSize);
            this.ctx.lineTo(crossWidth, -crossWidth);
            this.ctx.lineTo(halfSize, -crossWidth);
            this.ctx.lineTo(halfSize, crossWidth);
            this.ctx.lineTo(crossWidth, crossWidth);
            this.ctx.lineTo(crossWidth, halfSize);
            this.ctx.lineTo(-crossWidth, halfSize);
            this.ctx.lineTo(-crossWidth, crossWidth);
            this.ctx.lineTo(-halfSize, crossWidth);
            this.ctx.lineTo(-halfSize, -crossWidth);
            this.ctx.lineTo(-crossWidth, -crossWidth);
            this.ctx.closePath();
            this.ctx.fill();
        } else if (shape === 'arrow') {
            // 向右箭头
            const arrowWidth = halfSize * 0.4;
            this.ctx.moveTo(-halfSize, -arrowWidth);
            this.ctx.lineTo(0, -arrowWidth);
            this.ctx.lineTo(0, -halfSize);
            this.ctx.lineTo(halfSize, 0);
            this.ctx.lineTo(0, halfSize);
            this.ctx.lineTo(0, arrowWidth);
            this.ctx.lineTo(-halfSize, arrowWidth);
            this.ctx.closePath();
            this.ctx.fill();
        } else if (shape === 'cloud') {
            // 云朵
            const cloudWidth = halfSize * 0.6;
            const cloudHeight = halfSize * 0.5;
            this.ctx.moveTo(-halfSize, 0);
            this.ctx.bezierCurveTo(-halfSize, -cloudHeight, -cloudWidth, -halfSize, -cloudWidth * 0.5, -halfSize);
            this.ctx.bezierCurveTo(0, -halfSize * 1.3, cloudWidth * 0.5, -halfSize, cloudWidth * 0.5, -halfSize);
            this.ctx.bezierCurveTo(cloudWidth, -halfSize, halfSize, -cloudHeight, halfSize, 0);
            this.ctx.bezierCurveTo(halfSize, cloudHeight * 0.3, 0, cloudHeight, -halfSize, 0);
            this.ctx.fill();
        } else if (shape === 'moon') {
            // 月亮
            const moonRadius = halfSize;
            this.ctx.arc(0, 0, moonRadius, -Math.PI / 2, Math.PI / 2);
            this.ctx.bezierCurveTo(0, -halfSize * 0.5, halfSize * 0.8, 0, 0, halfSize);
            this.ctx.arc(0, 0, moonRadius * 0.6, Math.PI / 2, -Math.PI / 2, true);
            this.ctx.fill();
        } else if (shape === 'sun') {
            // 太阳
            this.ctx.arc(0, 0, halfSize * 0.5, 0, Math.PI * 2);
            this.ctx.fill();
            // 太阳光芒
            const rays = 8;
            for (let i = 0; i < rays; i++) {
                const angle = (i * 2 * Math.PI / rays);
                this.ctx.moveTo(0, 0);
                this.ctx.lineTo(Math.cos(angle) * halfSize, Math.sin(angle) * halfSize);
            }
            this.ctx.fill();
        } else if (shape === 'lightning') {
            // 闪电
            this.ctx.moveTo(halfSize * 0.3, -halfSize);
            this.ctx.lineTo(-halfSize * 0.2, 0);
            this.ctx.lineTo(halfSize * 0.1, 0);
            this.ctx.lineTo(-halfSize * 0.3, halfSize);
            this.ctx.lineTo(0, 0);
            this.ctx.lineTo(-halfSize * 0.1, 0);
            this.ctx.closePath();
            this.ctx.fill();
        } else if (shape === 'shield') {
            // 盾牌
            const topWidth = halfSize * 0.7;
            const bottomWidth = halfSize * 0.5;
            const shieldHeight = halfSize * 1.2;
            this.ctx.moveTo(-topWidth, -shieldHeight * 0.4);
            this.ctx.lineTo(topWidth, -shieldHeight * 0.4);
            this.ctx.lineTo(topWidth, 0);
            this.ctx.bezierCurveTo(topWidth, shieldHeight * 0.3, 0, shieldHeight * 0.8, 0, shieldHeight * 0.6);
            this.ctx.bezierCurveTo(0, shieldHeight * 0.8, -topWidth, shieldHeight * 0.3, -topWidth, 0);
            this.ctx.lineTo(-topWidth, -shieldHeight * 0.4);
            this.ctx.closePath();
            this.ctx.fill();
        } else if (shape === 'bubble') {
            // 气泡
            this.ctx.arc(0, 0, halfSize * 0.8, 0, Math.PI * 2);
            this.ctx.fill();
            // 气泡尾巴
            this.ctx.moveTo(-halfSize * 0.5, halfSize * 0.6);
            this.ctx.lineTo(-halfSize, halfSize * 0.9);
            this.ctx.lineTo(-halfSize * 0.2, halfSize * 0.7);
            this.ctx.closePath();
            this.ctx.fill();
        } else {
            // 默认圆形
            this.ctx.arc(0, 0, halfSize, 0, Math.PI * 2);
            this.ctx.fill();
        }

        this.ctx.restore();
    }

    // 渲染图片层
    renderImageLayer(layer, progress) {
        const config = layer.config || {};
        const x = config.x || 0;
        const y = config.y || 0;
        const width = config.width || 100;
        const height = config.height || 100;
        const src = config.src || '';

        const anim = this.calculateAnimation(layer, progress);

        this.ctx.save();
        this.ctx.globalAlpha = Math.max(0, Math.min(1, anim.opacity));

        // 计算位置和尺寸
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

        // 如果有图片URL，尝试加载并绘制
        if (src && src.length > 0) {
            // 检查图片是否已加载
            if (!this.loadedImages) {
                this.loadedImages = {};
            }
            
            if (!this.loadedImages[src]) {
                // 尝试从本地缓存加载
                this.loadedImages[src] = new Image();
                this.loadedImages[src].src = src;
            }

            const img = this.loadedImages[src];
            
            // 如果图片已加载完成，绘制它
            if (img.complete && img.naturalWidth > 0) {
                // 根据 fit 模式绘制
                const fit = config.fit || 'cover';
                
                if (fit === 'cover') {
                    // cover 模式：保持宽高比，裁剪超出部分
                    const imgRatio = img.naturalWidth / img.naturalHeight;
                    const containerRatio = scaledWidth / scaledHeight;
                    
                    let drawWidth, drawHeight, drawX, drawY;
                    
                    if (imgRatio > containerRatio) {
                        drawHeight = scaledHeight;
                        drawWidth = drawHeight * imgRatio;
                        drawX = posX - (drawWidth - scaledWidth) / 2;
                        drawY = posY;
                    } else {
                        drawWidth = scaledWidth;
                        drawHeight = drawWidth / imgRatio;
                        drawX = posX;
                        drawY = posY - (drawHeight - scaledHeight) / 2;
                    }
                    
                    this.ctx.drawImage(img, drawX, drawY, drawWidth, drawHeight);
                } else if (fit === 'contain') {
                    // contain 模式：保持宽高比，完整显示
                    const imgRatio = img.naturalWidth / img.naturalHeight;
                    const containerRatio = scaledWidth / scaledHeight;
                    
                    let drawWidth, drawHeight, drawX, drawY;
                    
                    if (imgRatio > containerRatio) {
                        drawWidth = scaledWidth;
                        drawHeight = drawWidth / imgRatio;
                        drawX = posX;
                        drawY = posY + (scaledHeight - drawHeight) / 2;
                    } else {
                        drawHeight = scaledHeight;
                        drawWidth = drawHeight * imgRatio;
                        drawX = posX + (scaledWidth - drawWidth) / 2;
                        drawY = posY;
                    }
                    
                    this.ctx.drawImage(img, drawX, drawY, drawWidth, drawHeight);
                } else {
                    // stretch 模式：拉伸填充
                    this.ctx.drawImage(img, posX, posY, scaledWidth, scaledHeight);
                }
            } else {
                // 图片加载中，显示占位符
                this.drawPlaceholder(posX, posY, scaledWidth, scaledHeight, '加载中...');
            }
        } else {
            // 没有图片，显示占位符
            this.drawPlaceholder(posX, posY, scaledWidth, scaledHeight, '点击上传图片');
        }

        this.ctx.restore();
    }

    // 绘制占位符
    drawPlaceholder(x, y, width, height, text) {
        this.ctx.fillStyle = '#2a2a3e';
        this.ctx.fillRect(x, y, width, height);
        
        // 边框
        this.ctx.strokeStyle = '#4a4a6e';
        this.ctx.lineWidth = 2;
        this.ctx.strokeRect(x, y, width, height);
        
        // 文本
        this.ctx.fillStyle = '#8a8aae';
        this.ctx.font = '14px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        this.ctx.textAlign = 'center';
        this.ctx.textBaseline = 'middle';
        this.ctx.fillText(text, x + width / 2, y + height / 2);
    }

    // 计算动画属性
    calculateAnimation(layer, progress) {
        // 验证输入参数
        if (!layer || !layer.config) {
            return {
                opacity: 1,
                x: 0,
                y: 0,
                scaleX: 1,
                scaleY: 1,
                rotation: 0
            };
        }

        // 确保 progress 是有效数字
        if (typeof progress !== 'number' || isNaN(progress)) {
            progress = 0;
        }

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

        if (!Array.isArray(animations)) {
            return anim;
        }

        animations.forEach(animation => {
            if (!animation || !animation.type) return;

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
// ========== 代码生成引擎 (PHPCodeGenerator) ==========
/**
 * PHP 代码生成器类
 * 
 * 职责：
 * - 将编辑器状态转换为可执行的 PHP 代码
 * - 生成图层渲染代码
 * - 生成动画配置代码
 * - 处理颜色和配置转换
 * 
 * 不负责：
 * - 渲染逻辑（由 PreviewRenderer 负责）
 * - 状态管理（由 EditorState 负责）
 * - UI 更新（由 UI 函数负责）
 */
class PHPCodeGenerator {
    generate(state) {
        const { composition, layers } = state;

        let code = `<?php\n\n/**\n * PHP Remotion 自动生成的动画配置\n * 生成时间: ${new Date().toLocaleString()}\n */\n\nrequire_once __DIR__ . '/vendor/autoload.php';\n\nuse Yangweijie\\Remotion\\Remotion;\nuse Yangweijie\\Remotion\\Core\\RenderContext;\nuse Yangweijie\\Remotion\\Animation\\Easing;\n\n// 创建合成\n$composition = Remotion::composition(\n    id: '${escapePhpString(composition.id)}',\n    renderer: function (RenderContext $ctx): \\GdImage {\n        $frame = $ctx->getCurrentFrame();\n        $config = $ctx->getVideoConfig();\n        \n        // 创建画布\n        $canvas = Remotion::createCanvas($config->width, $config->height, [26, 26, 46]);\n`;

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

        return `\n        // ${escapePhpString(layer.name)}\n        if ($frame >= ${from} && $frame < ${from + duration}) {\n            $layer = Remotion::colorLayer(${config.width}, ${config.height}, ${r}, ${g}, ${b});\n            $layer->drawOn($canvas, ${config.x}, ${config.y});\n        }\n`;
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

        return `\n        // ${escapePhpString(layer.name)}\n        if ($frame >= ${from} && $frame < ${from + duration}) {\n            $layer = Remotion::gradientLayer(\n                ${config.width}, ${config.height},\n                ['r' => ${start.r}, 'g' => ${start.g}, 'b' => ${start.b}],\n                ['r' => ${end.r}, 'g' => ${end.g}, 'b' => ${end.b}],\n                '${escapePhpString(config.direction)}'\n            );\n            $layer->drawOn($canvas, ${config.x}, ${config.y});\n        }\n`;
    }

    generateTextLayerCode(layer) {
        const { config, from, duration } = layer;
        const hexToRgb = hex => ({
            r: parseInt(hex.slice(1, 3), 16),
            g: parseInt(hex.slice(3, 5), 16),
            b: parseInt(hex.slice(5, 7), 16)
        });
        const color = hexToRgb(config.color);

        return `\n        // ${escapePhpString(layer.name)}\n        if ($frame >= ${from} && $frame < ${from + duration}) {\n            $textLayer = Remotion::textLayer('${escapePhpString(config.text)}', [\n                'fontSize' => ${Math.ceil(config.fontSize / 5)},\n                'r' => ${color.r}, 'g' => ${color.g}, 'b' => ${color.b},\n                'align' => '${escapePhpString(config.align)}',\n            ]);\n            $textLayer->drawOn($canvas, ${config.x}, ${config.y});\n        }\n`;
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
            return `\n        // ${escapePhpString(layer.name)} - 圆形\n        if ($frame >= ${from} && $frame < ${from + duration}) {\n            $color = imagecolorallocate($canvas, ${color.r}, ${color.g}, ${color.b});\n            imagefilledellipse($canvas, ${config.x}, ${config.y}, ${config.size}, ${config.size}, $color);\n        }\n`;
        } else if (config.shape === 'rect') {
            return `\n        // ${escapePhpString(layer.name)} - 矩形\n        if ($frame >= ${from} && $frame < ${from + duration}) {\n            $color = imagecolorallocate($canvas, ${color.r}, ${color.g}, ${color.b});\n            $halfSize = ${config.size} / 2;\n            imagefilledrectangle(\n                $canvas,\n                ${config.x} - $halfSize,\n                ${config.y} - $halfSize,\n                ${config.x} + $halfSize,\n                ${config.y} + $halfSize,\n                $color\n            );\n        }\n`;
        }

        return '';
    }
}

// 初始化编辑器
function initializeEditor() {
    // 初始化主题
    initializeTheme();
    
    App.state = new EditorState();
    App.renderer = new PreviewRenderer('preview-canvas');
    App.codeGenerator = new PHPCodeGenerator();
    
    // 初始化交互状态
    App.interaction.isDraggingLayer = false;
    App.interaction.isResizingLayer = false;
    App.interaction.dragStartX = 0;
    App.interaction.dragStartY = 0;
    App.interaction.resizeHandle = null;
    App.interaction.selectedLayerIds = [];
    
    // 尝试恢复自动保存
    const restored = restoreAutosave();
    if (restored) {
        console.log('已恢复上次保存的状态');
    } else {
        console.log('未找到自动保存或恢复失败，使用默认状态');
    }
    
    // 启动自动保存
    startAutosave();
}

// ========== 兼容层：提供向后兼容的全局变量访问 ==========
// 这些变量用于保持向后兼容，新代码应使用 App 对象
let state, renderer, codeGenerator;
let isDraggingLayer, isResizingLayer;

// 更新兼容层引用
function updateCompatibilityLayer() {
    state = App.state;
    renderer = App.renderer;
    codeGenerator = App.codeGenerator;
    isDraggingLayer = App.interaction.isDraggingLayer;
    isResizingLayer = App.interaction.isResizingLayer;
}

// ========== 对齐工具函数 ==========

/**
 * 对齐图层
 */
function alignLayers(alignment) {
    const selectedLayers = state.getSelectedLayers();
    if (selectedLayers.length < 2) {
        showErrorToast('请至少选择2个图层进行对齐');
        return;
    }

    const { width: canvasWidth, height: canvasHeight } = state.composition;

    switch (alignment) {
        case 'left':
            // 左对齐：所有图层的左侧对齐到最左边的图层
            const minX = Math.min(...selectedLayers.map(l => l.config.x));
            selectedLayers.forEach(layer => {
                const layerWidth = layer.config.width || (layer.config.size || 0);
                state.updateLayer(layer.id, { x: minX }, true);
            });
            break;

        case 'center':
            // 水平居中：所有图层水平居中
            selectedLayers.forEach(layer => {
                const layerWidth = layer.config.width || (layer.config.size || 0);
                const centerX = (canvasWidth - layerWidth) / 2;
                state.updateLayer(layer.id, { x: Math.round(centerX) }, true);
            });
            break;

        case 'right':
            // 右对齐：所有图层的右侧对齐到最右边的图层
            const maxX = Math.max(...selectedLayers.map(l => l.config.x + (l.config.width || l.config.size || 0)));
            selectedLayers.forEach(layer => {
                const layerWidth = layer.config.width || (layer.config.size || 0);
                state.updateLayer(layer.id, { x: Math.round(maxX - layerWidth) }, true);
            });
            break;

        case 'top':
            // 顶对齐：所有图层的顶部对齐到最顶部的图层
            const minY = Math.min(...selectedLayers.map(l => l.config.y));
            selectedLayers.forEach(layer => {
                const layerHeight = layer.config.height || (layer.config.size || 0);
                state.updateLayer(layer.id, { y: minY }, true);
            });
            break;

        case 'middle':
            // 垂直居中：所有图层垂直居中
            selectedLayers.forEach(layer => {
                const layerHeight = layer.config.height || (layer.config.size || 0);
                const centerY = (canvasHeight - layerHeight) / 2;
                state.updateLayer(layer.id, { y: Math.round(centerY) }, true);
            });
            break;

        case 'bottom':
            // 底对齐：所有图层的底部对齐到最底部的图层
            const maxY = Math.max(...selectedLayers.map(l => l.config.y + (l.config.height || l.config.size || 0)));
            selectedLayers.forEach(layer => {
                const layerHeight = layer.config.height || (layer.config.size || 0);
                state.updateLayer(layer.id, { y: Math.round(maxY - layerHeight) }, true);
            });
            break;
    }

    saveState('对齐图层');
    renderPreview();
    updatePropertyInputs(state.getSelectedLayer() || selectedLayers[0], {});
}

// ========== UI 更新模块 ==========
/**
 * UI 更新函数
 * 
 * 职责：
 * - 更新图层列表显示
 * - 更新时间轴显示
 * - 更新属性面板
 * - 触发预览渲染
 * 
 * 特性：
 * - 错误处理：每个更新操作都有 try-catch
 * - 性能优化：渲染操作使用防抖
 */
function updateUI() {
    try {
        updateLayerList();
        updateTimeline();
        updateCompositionSettings();
        updateLayerProperties();
        renderPreview();
    } catch (error) {
        console.error('更新UI失败:', error);
        showErrorToast('界面更新失败: ' + (error.message || '未知错误'));
    }
}

// 更新图层列表
function updateLayerList() {
    const list = document.getElementById('layer-list');
    if (!list) {
        console.error('图层列表元素不存在');
        return;
    }
    
    const currentState = state || App.state;
    if (!currentState) {
        console.error('编辑器状态未初始化');
        list.innerHTML = '';
        return;
    }
    
    list.innerHTML = '';

    [...currentState.layers].reverse().forEach(layer => {
        const isSelected = App.interaction.selectedLayerIds.includes(layer.id) || layer.id === state.selectedLayerId;
        const item = document.createElement('div');
        item.className = `layer-item ${isSelected ? 'active' : ''} ${layer.locked ? 'locked' : ''}`;
        item.innerHTML = `
            <span class="visibility" data-id="${layer.id}">${layer.visible ? '👁' : '🙈'}</span>
            <span class="layer-icon">${getLayerIcon(layer.type)}</span>
            <span class="layer-name">${escapeHtml(layer.name)}</span>
            ${layer.locked ? '<span class="layer-lock-icon">🔒</span>' : ''}
            <span class="layer-delete" data-id="${layer.id}">✕</span>
        `;

        item.addEventListener('click', (e) => {
            if (e.shiftKey) {
                state.toggleLayerSelection(layer.id);
            } else {
                state.clearSelection();
                state.selectLayer(layer.id);
            }
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
            <div class="track-label">${escapeHtml(layer.name)}</div>
            <div class="track-content">
                <div class="track-item ${layer.id === state.selectedLayerId ? 'selected' : ''}"
                     style="left: ${(layer.from / duration) * 100}%; width: ${(layer.duration / duration) * 100}%;"
                     data-id="${layer.id}">
                    ${escapeHtml(layer.name)}
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
            <input type="text" id="layer-name" value="${escapeHtml(layer.name)}">
        </div>
        <div class="form-group">
            <label>开始帧</label>
            <input type="number" id="layer-from" value="${layer.from}" min="0">
        </div>
        <div class="form-group">
            <label>持续帧数</label>
            <input type="number" id="layer-duration" value="${layer.duration}" min="1">
        </div>
        <div class="form-group">
            <label>锁定图层</label>
            <button type="button" id="layer-locked" class="lock-button ${layer.locked ? 'locked' : ''}">
                ${layer.locked ? '🔒 已锁定' : '🔓 未锁定'}
            </button>
            <small style="color: var(--text-secondary); font-size: 11px; display: block; margin-top: 4px;">
                锁定后无法拖拽、缩放或删除
            </small>
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
        case 'image':
            html += `
                <div class="form-group">
                    <label>图片</label>
                    <input type="file" id="prop-image-file" accept="image/*">
                    <input type="hidden" id="prop-src" value="${config.src || ''}">
                </div>
                ${config.src ? `
                    <div class="form-group">
                        <label>预览</label>
                        <img id="prop-image-preview" src="${config.src}" style="max-width: 100%; max-height: 100px; border-radius: 4px; display: block;">
                    </div>
                ` : ''}
                <div class="form-group">
                    <label>填充方式</label>
                    <select id="prop-fit">
                        <option value="cover" ${config.fit === 'cover' ? 'selected' : ''}>覆盖 (Cover)</option>
                        <option value="contain" ${config.fit === 'contain' ? 'selected' : ''}>包含 (Contain)</option>
                        <option value="stretch" ${config.fit === 'stretch' ? 'selected' : ''}>拉伸 (Stretch)</option>
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
        case 'shape':
            html += `
                <div class="form-group">
                    <label>形状</label>
                    <select id="prop-shape">
                        <option value="circle" ${config.shape === 'circle' ? 'selected' : ''}>圆形</option>
                        <option value="rect" ${config.shape === 'rect' ? 'selected' : ''}>矩形</option>
                        <option value="triangle" ${config.shape === 'triangle' ? 'selected' : ''}>三角形</option>
                        <option value="diamond" ${config.shape === 'diamond' ? 'selected' : ''}>菱形</option>
                        <option value="pentagon" ${config.shape === 'pentagon' ? 'selected' : ''}>五边形</option>
                        <option value="hexagon" ${config.shape === 'hexagon' ? 'selected' : ''}>六边形</option>
                        <option value="octagon" ${config.shape === 'octagon' ? 'selected' : ''}>八边形</option>
                        <option value="star" ${config.shape === 'star' ? 'selected' : ''}>星形</option>
                        <option value="heart" ${config.shape === 'heart' ? 'selected' : ''}>心形</option>
                        <option value="petal" ${config.shape === 'petal' ? 'selected' : ''}>花瓣</option>
                        <option value="clover" ${config.shape === 'clover' ? 'selected' : ''}>四叶草</option>
                        <option value="oval" ${config.shape === 'oval' ? 'selected' : ''}>椭圆</option>
                        <option value="cross" ${config.shape === 'cross' ? 'selected' : ''}>十字形</option>
                        <option value="arrow" ${config.shape === 'arrow' ? 'selected' : ''}>箭头</option>
                        <option value="cloud" ${config.shape === 'cloud' ? 'selected' : ''}>云朵</option>
                        <option value="moon" ${config.shape === 'moon' ? 'selected' : ''}>月亮</option>
                        <option value="sun" ${config.shape === 'sun' ? 'selected' : ''}>太阳</option>
                        <option value="lightning" ${config.shape === 'lightning' ? 'selected' : ''}>闪电</option>
                        <option value="shield" ${config.shape === 'shield' ? 'selected' : ''}>盾牌</option>
                        <option value="bubble" ${config.shape === 'bubble' ? 'selected' : ''}>气泡</option>
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

    // 锁定按钮处理
    const lockButton = document.getElementById('layer-locked');
    if (lockButton) {
        lockButton.addEventListener('click', () => {
            const newLockedState = !layer.locked;
            layer.locked = newLockedState;
            lockButton.className = `lock-button ${newLockedState ? 'locked' : ''}`;
            lockButton.textContent = newLockedState ? '🔒 已锁定' : '🔓 未锁定';
            saveState('锁定图层');
        });
    }

    // 图片上传处理
    const imageFileInput = document.getElementById('prop-image-file');
    if (imageFileInput) {
        imageFileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    const src = event.target.result;
                    state.updateLayer(layer.id, { src });
                    renderPreview();
                    updateLayerProperties(); // 刷新预览图
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // 配置属性变更
    const configProps = ['color', 'startColor', 'endColor', 'direction', 'text', 'fontSize', 'align', 'shape', 'size', 'x', 'y', 'width', 'height', 'fit'];
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

// ========== 播放控制模块 ==========
/**
 * 播放控制函数
 * 
 * 职责：
 * - 控制动画播放、暂停、停止
 * - 管理播放定时器
 * - 更新当前帧位置
 * 
 * 特性：
 * - 错误处理：捕获播放过程中的错误
 * - 资源清理：停止时清除定时器
 */

// 渲染预览（带防抖，避免频繁渲染）
const renderPreview = debounce(function() {
    try {
        if (!App.state || !App.renderer) {
            throw new Error('编辑器或渲染器未初始化');
        }
        App.renderer.setSize(App.state.composition.width, App.state.composition.height);
        App.renderer.render(App.state);
    } catch (error) {
        console.error('渲染预览失败:', error);
        showErrorToast('渲染预览失败: ' + (error.message || '未知错误'));
    }
}, 16); // ~60fps

// 播放控制
function play() {
    try {
        if (!App.state) {
            throw new Error('编辑器未初始化');
        }
        if (App.state.isPlaying) return;
        App.state.isPlaying = true;

        const frameInterval = 1000 / App.state.composition.fps;

        // 清除之前的定时器（如果存在）
        if (App.state.playTimer) {
            clearInterval(App.state.playTimer);
        }

        App.state.playTimer = setInterval(() => {
            try {
                App.state.currentFrame++;
                if (App.state.currentFrame >= App.state.composition.durationInFrames) {
                    App.state.currentFrame = 0;
                }
                updateTimeline();
                renderPreview();
            } catch (error) {
                console.error('播放帧更新失败:', error);
                pause();
                showErrorToast('播放出错，已停止');
            }
        }, frameInterval);
    } catch (error) {
        console.error('播放失败:', error);
        showErrorToast('播放失败: ' + (error.message || '未知错误'));
    }
}

function pause() {
    try {
        if (App.state && App.state.playTimer) {
            clearInterval(App.state.playTimer);
            App.state.playTimer = null;
        }
        if (App.state) {
            App.state.isPlaying = false;
        }
    } catch (error) {
        console.error('暂停失败:', error);
    }
}

function stop() {
    try {
        pause();
        if (App.state) {
            App.state.currentFrame = 0;
            updateTimeline();
            renderPreview();
        }
    } catch (error) {
        console.error('停止失败:', error);
        showErrorToast('停止失败: ' + (error.message || '未知错误'));
    }
}

// 导出PHP代码
function exportPHP() {
    try {
        if (!App.state || !App.codeGenerator) {
            throw new Error('编辑器或代码生成器未初始化');
        }
        const code = App.codeGenerator.generate(App.state);
        const preview = document.getElementById('php-code-preview');
        if (!preview) {
            throw new Error('代码预览元素不存在');
        }
        preview.textContent = code;
        const modal = document.getElementById('export-modal');
        if (!modal) {
            throw new Error('导出模态框不存在');
        }
        modal.classList.add('active');
    } catch (error) {
        console.error('导出PHP代码失败:', error);
        showErrorToast('导出失败: ' + (error.message || '未知错误'));
    }
}

// ========== 事件绑定模块 ==========
/**
 * 事件绑定
 * 
 * 职责：
 * - 绑定所有用户交互事件
 * - 处理拖拽、缩放、选择等操作
 * - 响应配置变更
 * 
 * 特性：
 * - 兼容层：保持向后兼容
 * - 状态同步：更新 App 对象和兼容层
 */
document.addEventListener('DOMContentLoaded', () => {
    // 初始化编辑器
    initializeEditor();
    
    // 更新兼容层
    updateCompatibilityLayer();
    
    // 初始化UI
    updateUI();

    // 拖拽功能
    const layerTypeElements = document.querySelectorAll('.layer-type');
    console.log('找到的图层类型元素数量:', layerTypeElements.length);
    
    layerTypeElements.forEach((item, index) => {
        if (!item || !item.dataset) {
            console.error(`无效的图层类型元素 (${index}):`, item);
            return;
        }
        
        console.log(`绑定拖拽事件到图层类型 ${index}:`, item.dataset.type);
        
        item.addEventListener('dragstart', (e) => {
            console.log('开始拖拽:', item.dataset.type, '元素:', item);
            e.dataTransfer.setData('layer-type', item.dataset.type);
            e.dataTransfer.effectAllowed = 'copy';
            item.classList.add('dragging');
        });

        item.addEventListener('dragend', () => {
            console.log('拖拽结束');
            item.classList.remove('dragging');
        });
    });

    // 预览区域放置
    const previewContainer = document.getElementById('preview-container');
    const previewCanvas = document.getElementById('preview-canvas');

    if (!previewContainer) {
        console.error('预览容器元素不存在');
        showErrorToast('预览容器元素不存在');
    }
    if (!previewCanvas) {
        console.error('预览画布元素不存在');
        showErrorToast('预览画布元素不存在');
    }

    if (previewContainer) {
        previewContainer.addEventListener('dragover', (e) => {
            console.log('拖拽经过预览区域');
            e.preventDefault();
            e.dataTransfer.dropEffect = 'copy';
            previewContainer.classList.add('drag-over');
        });

        previewContainer.addEventListener('dragleave', (e) => {
            console.log('拖拽离开预览区域');
            // 只在真正离开容器时才移除样式
            if (e.relatedTarget && !previewContainer.contains(e.relatedTarget)) {
                previewContainer.classList.remove('drag-over');
            }
        });

        previewContainer.addEventListener('drop', (e) => {
            console.log('放置到预览区域');
            e.preventDefault();
            e.stopPropagation();
            previewContainer.classList.remove('drag-over');

            const type = e.dataTransfer.getData('layer-type');
            console.log('放置的图层类型:', type);
            
            if (type) {
                // 确保 state 已初始化
                const currentState = state || App.state;
                if (!currentState) {
                    console.error('编辑器状态未初始化');
                    console.error('state:', state);
                    console.error('App.state:', App.state);
                    showErrorToast('编辑器状态未初始化，请刷新页面');
                    return;
                }
                
                const coords = getCanvasCoordinates(e, previewCanvas);
                console.log('放置坐标:', coords);
                
                try {
                    const layer = currentState.addLayer(type, { x: coords.x, y: coords.y });
                    console.log('添加的图层:', layer);
                    
                    currentState.selectLayer(layer.id);
                    updateUI();
                } catch (error) {
                    console.error('添加图层失败:', error);
                    showErrorToast('添加图层失败: ' + (error.message || '未知错误'));
                }
            } else {
                console.error('无效的图层类型');
                showErrorToast('无法识别图层类型');
            }
        });
    }

    // 获取画布坐标
    function getCanvasCoordinates(e, canvas) {
        if (!canvas) {
            console.error('画布元素不存在');
            return { x: 0, y: 0 };
        }
        
        const rect = canvas.getBoundingClientRect();
        if (!rect) {
            console.error('无法获取画布位置信息');
            return { x: 0, y: 0 };
        }
        
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
                App.interaction.isResizingLayer = true;
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
            // 检查是否按住 Shift 键（多选）
            if (e.shiftKey) {
                state.toggleLayerSelection(clickedLayer.id);
                updateUI();
            } else {
                // 普通选择，清空多选
                state.clearSelection();
                state.selectLayer(clickedLayer.id);
                renderer.startDrag(clickedLayer, mouseX, mouseY);
                isDraggingLayer = true;
                App.interaction.isDraggingLayer = true;
                previewCanvas.style.cursor = 'grabbing';
                updateUI();
            }
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
                    state.updateLayer(layer.id, newConfig, true);  // 缩放过程中不保存历史
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
                const selectedLayers = state.getSelectedLayers();
                if (selectedLayers.length > 0) {
                    // 移动所有选中的图层
                    selectedLayers.forEach(layer => {
                        const layerStartPos = renderer.dragState.allLayersStartPos.find(l => l.id === layer.id);
                        if (layerStartPos) {
                            const dx = newPos.x - renderer.dragState.startX;
                            const dy = newPos.y - renderer.dragState.startY;
                            const newLayerPos = {
                                x: Math.round(layerStartPos.x + dx),
                                y: Math.round(layerStartPos.y + dy)
                            };
                            state.updateLayer(layer.id, newLayerPos, true);
                        }
                    });
                    renderPreview();
                    // 更新第一个选中图层的属性面板
                    updatePropertyInputs(selectedLayers[0], newPos);
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
            App.interaction.isResizingLayer = false;
            saveState('缩放图层');  // 缩放结束时保存状态
        }
        if (isDraggingLayer) {
            renderer.endDrag();
            isDraggingLayer = false;
            App.interaction.isDraggingLayer = false;
            saveState('移动图层');  // 拖拽结束时保存状态
        }
        previewCanvas.style.cursor = 'default';
    });

    // 鼠标离开画布
    previewCanvas.addEventListener('mouseleave', () => {
        if (isResizingLayer) {
            renderer.endResize();
            isResizingLayer = false;
            App.interaction.isResizingLayer = false;
            saveState('缩放图层');  // 缩放结束时保存状态
        }
        if (isDraggingLayer) {
            renderer.endDrag();
            isDraggingLayer = false;
            App.interaction.isDraggingLayer = false;
            saveState('移动图层');  // 拖拽结束时保存状态
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

    // 右键菜单
    const contextMenu = document.getElementById('context-menu');
    
    // 显示右键菜单
    previewCanvas.addEventListener('contextmenu', (e) => {
        e.preventDefault();
        
        const coords = getCanvasCoordinates(e, previewCanvas);
        let rightClickedLayer = null;

        for (const layer of state.layers) {
            if (!layer.visible) continue;
            if (state.currentFrame < layer.from || state.currentFrame >= layer.from + layer.duration) continue;

            if (renderer.hitTest(layer, coords.x, coords.y)) {
                rightClickedLayer = layer;
                break;
            }
        }

        if (rightClickedLayer) {
            state.selectLayer(rightClickedLayer.id);
            updateUI();
            
            // 显示右键菜单
            contextMenu.style.display = 'block';
            contextMenu.style.left = e.clientX + 'px';
            contextMenu.style.top = e.clientY + 'px';
            
            // 保存当前右键点击的图层ID
            contextMenu.dataset.layerId = rightClickedLayer.id;
        }
    });

    // 隐藏右键菜单（点击其他地方）
    document.addEventListener('click', (e) => {
        if (!contextMenu.contains(e.target)) {
            contextMenu.style.display = 'none';
        }
    });

    // 右键菜单 - 删除图层
    document.getElementById('context-menu-delete').addEventListener('click', () => {
        const selectedLayers = state.getSelectedLayers();
        if (selectedLayers.length > 0) {
            // 删除所有选中的图层（从后往前删除，避免索引问题）
            for (let i = selectedLayers.length - 1; i >= 0; i--) {
                state.removeLayer(selectedLayers[i].id);
            }
            state.clearSelection();
            contextMenu.style.display = 'none';
            updateUI();
        }
    });

    // 右键菜单 - 复制图层
    document.getElementById('context-menu-duplicate').addEventListener('click', () => {
        const layerId = contextMenu.dataset.layerId;
        if (layerId) {
            const layer = state.layers.find(l => l.id === layerId);
            if (layer) {
                // 创建副本
                const newLayer = state.addLayer(layer.type, {
                    ...layer.config,
                    name: layer.name + ' (副本)',
                    x: layer.config.x + 20,
                    y: layer.config.y + 20
                });
                newLayer.from = layer.from;
                newLayer.duration = layer.duration;
                
                contextMenu.style.display = 'none';
                state.selectLayer(newLayer.id);
                updateUI();
            }
        }
    });

    // 右键菜单 - 置于顶层
    document.getElementById('context-menu-bring-front').addEventListener('click', () => {
        const layerId = contextMenu.dataset.layerId;
        if (layerId) {
            const index = state.layers.findIndex(l => l.id === layerId);
            if (index > -1) {
                const layer = state.layers.splice(index, 1)[0];
                state.layers.push(layer);
                contextMenu.style.display = 'none';
                updateUI();
            }
        }
    });

    // 右键菜单 - 置于底层
    document.getElementById('context-menu-send-back').addEventListener('click', () => {
        const layerId = contextMenu.dataset.layerId;
        if (layerId) {
            const index = state.layers.findIndex(l => l.id === layerId);
            if (index > -1) {
                const layer = state.layers.splice(index, 1)[0];
                state.layers.unshift(layer);
                contextMenu.style.display = 'none';
                updateUI();
            }
        }
    });

    // 键盘快捷键
    document.addEventListener('keydown', (e) => {
        // Delete 键删除选中的图层
        if (e.key === 'Delete' || e.key === 'Backspace') {
            // 确保不是在输入框中
            if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
                const selectedLayer = state.getSelectedLayer();
                if (selectedLayer) {
                    state.removeLayer(selectedLayer.id);
                    updateUI();
                }
            }
        }
        
        // Ctrl+D 复制图层
        if (e.ctrlKey && e.key === 'd') {
            e.preventDefault();
            const selectedLayer = state.getSelectedLayer();
            if (selectedLayer) {
                const newLayer = state.addLayer(selectedLayer.type, {
                    ...selectedLayer.config,
                    name: selectedLayer.name + ' (副本)',
                    x: selectedLayer.config.x + 20,
                    y: selectedLayer.config.y + 20
                });
                newLayer.from = selectedLayer.from;
                newLayer.duration = selectedLayer.duration;
                state.selectLayer(newLayer.id);
                updateUI();
            }
        }
        
        // Ctrl+Z 撤销
        if (e.ctrlKey && e.key === 'z' && !e.shiftKey) {
            e.preventDefault();
            undo();
        }
        
        // Ctrl+Y 或 Ctrl+Shift+Z 重做
        if ((e.ctrlKey && e.key === 'y') || (e.ctrlKey && e.shiftKey && e.key === 'z')) {
            e.preventDefault();
            redo();
        }
    });

    // 播放控制
    document.getElementById('btn-undo').addEventListener('click', undo);
    document.getElementById('btn-redo').addEventListener('click', redo);
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

    // 背景图片上传
    const backgroundImageInput = document.getElementById('background-image-input');
    if (backgroundImageInput) {
        backgroundImageInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    state.updateComposition({ backgroundImage: event.target.result });
                    renderPreview();
                    console.log('背景图片已设置');
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // 主题切换
    const themeToggleBtn = document.getElementById('btn-theme-toggle');
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            toggleTheme();
            themeToggleBtn.textContent = App.theme.current === 'dark' ? '🌙' : '☀️';
        });
        // 初始化按钮图标
        themeToggleBtn.textContent = App.theme.current === 'dark' ? '🌙' : '☀️';
    }

    // 对齐工具
    document.getElementById('btn-align-left')?.addEventListener('click', () => alignLayers('left'));
    document.getElementById('btn-align-center')?.addEventListener('click', () => alignLayers('center'));
    document.getElementById('btn-align-right')?.addEventListener('click', () => alignLayers('right'));
    document.getElementById('btn-align-top')?.addEventListener('click', () => alignLayers('top'));
    document.getElementById('btn-align-middle')?.addEventListener('click', () => alignLayers('middle'));
    document.getElementById('btn-align-bottom')?.addEventListener('click', () => alignLayers('bottom'));

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

            // 显示动画选择模态框
            const animationModal = document.getElementById('animation-modal');
            if (!animationModal) {
                console.error('动画选择模态框不存在');
                return;
            }

            animationModal.style.display = 'flex';

            // 绑定动画卡片点击事件
            const animationCards = animationModal.querySelectorAll('.animation-card');
            animationCards.forEach(card => {
                // 移除旧的事件监听器
                const newCard = card.cloneNode(true);
                card.parentNode.replaceChild(newCard, card);

                newCard.addEventListener('click', () => {
                    const animType = newCard.dataset.type;
                    const animations = layer.config.animations || [];

                    // 基础动画配置
                    const animConfig = {
                        type: animType,
                        from: state.currentFrame,  // 开始帧
                        duration: 30,               // 持续帧数
                        easing: 'easeOut'
                    };

                    // 为特定动画类型添加默认值
                    if (animType === 'rotate') {
                        animConfig.valueFrom = 0;    // 起始角度
                        animConfig.valueTo = 360;    // 结束角度
                    } else if (animType === 'scale') {
                        animConfig.valueFrom = 0.5;  // 起始缩放
                        animConfig.valueTo = 1.0;    // 结束缩放
                    } else if (animType === 'bounce' || animType === 'spring') {
                        animConfig.valueFrom = 0;
                        animConfig.valueTo = 1;
                    }

                    animations.push(animConfig);
                    state.updateLayer(layer.id, { animations });
                    updateLayerProperties();
                    renderPreview();

                    // 关闭模态框
                    animationModal.style.display = 'none';
                });
            });

            // 绑定关闭按钮
            const closeModalBtn = animationModal.querySelector('.modal-close');
            if (closeModalBtn) {
                closeModalBtn.onclick = () => {
                    animationModal.style.display = 'none';
                };
            }

            // 点击模态框外部关闭
            animationModal.onclick = (e) => {
                if (e.target === animationModal) {
                    animationModal.style.display = 'none';
                }
            };
        });
    }
});