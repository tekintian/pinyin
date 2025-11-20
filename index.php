<?php
// 简化输出缓冲处理 - 避免复杂逻辑导致的不稳定行为
if (!headers_sent()) {
    ob_start();
    header('Content-Type: text/html; charset=UTF-8');
}

ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 初始化诊断信息
$diagnostics = [
    'loading_time' => [],
    'errors' => [],
    'file_checks' => []
];

// 记录开始时间
$start_time = microtime(true);

// 尝试加载 Composer 自动加载器
try {
    require_once __DIR__ . '/vendor/autoload.php';
    $diagnostics['loading_time']['autoload'] = microtime(true) - $start_time;
} catch (Exception $e) {
    $diagnostics['errors']['autoload'] = $e->getMessage();
}

// 定义正确的字典路径
$dictPaths = [
    'common_with_tone' => __DIR__ . '/data/common_with_tone.php',
    'common_no_tone' => __DIR__ . '/data/common_no_tone.php',
    'custom_with_tone' => __DIR__ . '/data/custom_with_tone.php',
    'custom_no_tone' => __DIR__ . '/data/custom_no_tone.php',
    'rare_with_tone' => __DIR__ . '/data/rare_with_tone.php',
    'rare_no_tone' => __DIR__ . '/data/rare_no_tone.php'
];

// 检查字典文件是否存在
foreach ($dictPaths as $type => $path) {
    $diagnostics['file_checks'][$type] = file_exists($path);
}

// 尝试初始化 PinyinConverter
$converter = null;
try {
    // 使用正确的字典路径初始化
    $converter = new tekintian\pinyin\PinyinConverter([
        'dict' => [
            'custom' => [
                'with_tone' => $dictPaths['custom_with_tone'],
                'no_tone' => $dictPaths['custom_no_tone']
            ],
            'common' => [
                'with_tone' => $dictPaths['common_with_tone'],
                'no_tone' => $dictPaths['common_no_tone']
            ],
            'rare' => [
                'with_tone' => $dictPaths['rare_with_tone'],
                'no_tone' => $dictPaths['rare_no_tone']
            ]
        ],
        'dict_loading' => [
            'strategy' => 'both',
            'lazy_loading' => false, // 禁用懒加载确保自定义字典被正确加载
            'preload_priority' => ['custom', 'common']
        ]
    ]);
    $diagnostics['loading_time']['converter'] = microtime(true) - $start_time;
} catch (Exception $e) {
    $diagnostics['errors']['converter'] = $e->getMessage();
}

// 尝试简单转换
$conversionResult = null;
$customResult = null;
$dynamicCustomResult = null;
try {
    if ($converter) {
        //$converter->addCustomPinyin('你好', ['hello']);
        // 测试普通转换
        $conversionResult = $converter->convert('你好');
        $diagnostics['loading_time']['conversion'] = microtime(true) - $start_time;

        // 测试字典文件中已有的自定义拼音
        $customResult = $converter->convert('你好');
        $diagnostics['loading_time']['custom'] = microtime(true) - $start_time;

        // 测试动态添加的自定义拼音
        $converter->addCustomPinyin('测试', ['test']);
        $dynamicCustomResult = $converter->convert('测试');
        // 测试移除动态添加的自定义拼音
        $converter->removeCustomPinyin('测试');

        $diyStr='7天开发企业级AI客户服务系统Vue3+Go+Gin+K8s技术栈（含源码+部署文档）';
        $diyResult = $converter->convert($diyStr);
        $slugResult = $converter->getUrlSlug($diyStr);

       // $result = $converter->convert(boolval(1), '', false);
        // $result = $converter->convert('䶮', '', false);
        // $result = $converter->getUrlSlug('Hello World 123');
        // $result = $converter->getUrlSlug('Test U.RL Slug!');

        // 自定义拼音测试
        $converter->addCustomPinyin('测试', 'ce4 shi4', true);
        $result = $converter->convert('测试', ' ', true);
        // 删除测试数据,避免影响其他测试
        $converter->removeCustomPinyin('测试', true);

        $results = $converter->searchByPinyin('zhong');

        $customOptions = [
            'special_char' => [
                'default_mode' => 'delete'
            ]
        ];
        
        $customConverter = new tekintian\pinyin\PinyinConverter($customOptions);
        $result = $customConverter->convert('中国@#$%', ' ', false);


        $diagnostics['loading_time']['dynamic_custom'] = microtime(true) - $start_time;


    }
} catch (Exception $e) {
    $diagnostics['errors']['conversion'] = $e->getMessage();
}

// 输出 HTML
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>拼音测试</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .test { margin-bottom: 20px; padding: 10px; border: 1px solid #ddd; }
        pre { background: #f5f5f5; padding: 10px; overflow: auto; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>拼音转换测试</h1>
    
    <div class="test">
        <h2>初始化状态</h2>
        <?php if (isset($diagnostics['errors']['autoload'])): ?>
            <p class="error">❌ 自动加载器错误: <?php echo htmlspecialchars($diagnostics['errors']['autoload']); ?></p>
        <?php endif; ?>
        <?php if (isset($diagnostics['errors']['converter'])): ?>
            <p class="error">❌ PinyinConverter 初始化失败: <?php echo htmlspecialchars($diagnostics['errors']['converter']); ?></p>
        <?php else: ?>
            <p class="success">✅ PinyinConverter 初始化成功</p>
        <?php endif; ?>
    </div>
    
    <div class="test">
        <h2>简单转换测试</h2>
        <?php if (isset($diagnostics['errors']['conversion'])): ?>
            <p class="error">❌ 转换失败: <?php echo htmlspecialchars($diagnostics['errors']['conversion']); ?></p>
        <?php else: ?>
            <p class="success">✅ 普通转换结果: <?php echo htmlspecialchars($conversionResult); ?></p>
        <?php endif; ?>
        <br/>
        <center><?php echo htmlspecialchars($diyStr); ?></center>
        <center><?php echo htmlspecialchars($diyResult); ?></center>
        <center><?php echo htmlspecialchars($slugResult); ?></center>
        <br/>
        <h3>字典文件自定义拼音测试</h3>
        <?php if ($customResult !== 'ni hao'): ?>
            <p class="error">❌ 字典文件自定义拼音未生效: <?php echo htmlspecialchars($customResult); ?></p>
        <?php else: ?>
            <p class="success">✅ 字典文件自定义拼音生效: <?php echo htmlspecialchars($customResult); ?></p>
        <?php endif; ?>
        
        <h3>动态添加自定义拼音测试</h3>
        <?php if ($dynamicCustomResult !== 'test'): ?>
            <p class="error">❌ 动态添加自定义拼音未生效: <?php echo htmlspecialchars($dynamicCustomResult); ?></p>
        <?php else: ?>
            <p class="success">✅ 动态添加自定义拼音生效: <?php echo htmlspecialchars($dynamicCustomResult); ?></p>
        <?php endif; ?>
    </div>
    
    <div class="test">
        <h2>字典文件检查</h2>
        <table>
            <tr>
                <th>字典类型</th>
                <th>路径</th>
                <th>状态</th>
            </tr>
            <?php foreach ($diagnostics['file_checks'] as $type => $exists): ?>
                <tr>
                    <td><?php echo htmlspecialchars($type); ?></td>
                    <td><?php echo htmlspecialchars($dictPaths[$type]); ?></td>
                    <td><?php echo $exists ? '<span class="success">✅ 存在</span>' : '<span class="error">❌ 缺少</span>'; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    
    <div class="test">
        <h2>性能信息</h2>
        <p>总加载时间: <?php echo (microtime(true) - $start_time) * 1000; ?> ms</p>
        <?php if (!empty($diagnostics['loading_time'])): ?>
            <ul>
                <?php foreach ($diagnostics['loading_time'] as $phase => $time): ?>
                    <li><?php echo ucfirst($phase); ?>: <?php echo $time * 1000; ?> ms</li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    
    <div class="test">
        <h2>环境信息</h2>
        <p>PHP 版本: <?php echo PHP_VERSION; ?></p>
        <p>服务器: <?php echo $_SERVER['SERVER_SOFTWARE']; ?></p>
    </div>
</body>
</html>