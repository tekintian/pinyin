<?php
require_once __DIR__ . '/../vendor/autoload.php';

use tekintian\pinyin\Utils\AutoPinyinFetcher;

// 使用示例
if (php_sapi_name() === 'cli' && isset($argv[0]) && basename($argv[0]) === 'auto_pinyin_fetcher.php') {
    
    echo "=== 自动拼音获取工具启动 ===\n";
    
    require_once __DIR__ . '/../vendor/autoload.php';
    
    // 加载未找到的字符
    $notFoundPath = __DIR__ . '/../data/diy/not_found_chars.php';
    echo "检查文件: {$notFoundPath}\n";
    
    if (file_exists($notFoundPath)) {
        $notFoundChars = require $notFoundPath;
        echo "加载了 " . count($notFoundChars) . " 个未找到拼音的字符\n";
        
        echo "开始为 " . count($notFoundChars) . " 个未找到拼音的汉字获取拼音...\n";
        
        // 获取拼音
        echo "正在获取拼音，请稍候...\n";
        echo "测试第一个字符: {$notFoundChars[0]}\n";
        
        // 测试单个字符
        $testResult = AutoPinyinFetcher::getPinyinFromZdic($notFoundChars[0]);
        if ($testResult) {
            // 将拼音数组转换为字符串显示
            $pinyinDisplay = is_array($testResult['pinyin']) ? implode(',', $testResult['pinyin']) : $testResult['pinyin'];
            echo "测试成功: {$notFoundChars[0]} -> {$pinyinDisplay} [{$testResult['source']}]\n";
        } else {
            echo "测试失败: 无法从汉典网获取拼音\n";
        }
        
        $results = AutoPinyinFetcher::batchGetPinyin($notFoundChars, true);
        
        // 统计结果
        $successCount = 0;
        $processedCount = 0;
        $totalCount = count($results);
        
        foreach ($results as $result) {
            $processedCount++;
            if ($result['pinyin'] && $result['pinyin'] !== '?') {
                $successCount++;
                // 将拼音数组转换为字符串显示
                $pinyinDisplay = is_array($result['pinyin']) ? implode(',', $result['pinyin']) : $result['pinyin'];
                echo "[{$processedCount}/{$totalCount}] ✓ {$result['char']} ({$result['unicode']}) -> {$pinyinDisplay} [{$result['source']}]\n";
            } else {
                echo "[{$processedCount}/{$totalCount}] ✗ {$result['char']} ({$result['unicode']}) -> 未找到拼音 [{$result['source']}]\n";
            }
        }
        
        echo "\n成功获取拼音的汉字: {$successCount}/" . count($notFoundChars) . "\n";
        
        // 生成待确认字典（不覆盖当前自定义字典）
        if ($successCount > 0) {
            $pendingDictPath = __DIR__ . '/../data/diy/pending_confirm_with_tone.php';
            AutoPinyinFetcher::generateCustomDict($results, $pendingDictPath, true);
            echo "待确认字典已生成: {$pendingDictPath}\n";
            echo "注意：获取的拼音已保存到待确认字典中，请人工确认后再合并到自定义字典\n";
        }
        
    } else {
        echo "未找到字符文件不存在: {$notFoundPath}\n";
    }
}