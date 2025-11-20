<?php
/**
 * 拼音自动获取工具
 * 专门为未找到拼音的汉字获取真实拼音，并保存到待确认文件
 */

class PinyinAutoFetcher {
    
    /**
     * 使用百度拼音API获取真实拼音
     * @param string $char 汉字
     * @return string|null 拼音
     */
    public static function getPinyinFromBaidu($char) {
        $url = "https://pinyin.baidu.com/api/pinyin?text=" . urlencode($char);
        
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                return null;
            }
            
            $data = json_decode($response, true);
            if (isset($data['data'][0]['pinyin'])) {
                return $data['data'][0]['pinyin'];
            }
        } catch (Exception $e) {
            // 忽略网络错误
        }
        
        return null;
    }
    
    /**
     * 使用字形相似性推测拼音（基于常见汉字读音）
     * @param string $char 汉字
     * @return string|null 推测的拼音
     */
    public static function guessPinyinFromStructure($char) {
        // 常见汉字读音映射（基于字形相似性）
        $similarChars = [
            '吉' => 'ji', '士' => 'shi', '口' => 'kou', '木' => 'mu',
            '水' => 'shui', '火' => 'huo', '土' => 'tu', '金' => 'jin',
            '人' => 'ren', '心' => 'xin', '手' => 'shou', '足' => 'zu'
        ];
        
        // 检查是否包含常见部件
        foreach ($similarChars as $component => $pinyin) {
            if (mb_strpos($char, $component) !== false) {
                return $pinyin;
            }
        }
        
        return null;
    }
    
    /**
     * 综合获取拼音
     * @param string $char 汉字
     * @return array 拼音结果
     */
    public static function fetchPinyin($char) {
        $result = [
            'char' => $char,
            'unicode' => 'U+' . strtoupper(dechex(mb_ord($char, 'UTF-8'))),
            'pinyin' => null,
            'source' => 'not_found',
            'confidence' => 0,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // 方法1：百度拼音API（最高优先级）
        $baiduPinyin = self::getPinyinFromBaidu($char);
        if ($baiduPinyin && $baiduPinyin !== $char) {
            $result['pinyin'] = $baiduPinyin;
            $result['source'] = 'baidu_api';
            $result['confidence'] = 85;
            return $result;
        }
        
        // 方法2：字形相似性推测
        $guessPinyin = self::guessPinyinFromStructure($char);
        if ($guessPinyin) {
            $result['pinyin'] = $guessPinyin;
            $result['source'] = 'structure_guess';
            $result['confidence'] = 40;
            return $result;
        }
        
        return $result;
    }
    
    /**
     * 批量获取拼音
     * @param array $chars 汉字数组
     * @return array 拼音结果
     */
    public static function batchFetchPinyin($chars) {
        $results = [];
        
        foreach ($chars as $index => $char) {
            echo "处理进度: " . ($index + 1) . "/" . count($chars) . " - {$char}\n";
            
            $result = self::fetchPinyin($char);
            $results[] = $result;
            
            // 显示结果
            if ($result['pinyin']) {
                echo "  ✓ {$char} -> {$result['pinyin']} [{$result['source']}]\n";
            } else {
                echo "  ✗ {$char} -> 未找到拼音\n";
            }
            
            // 添加延迟避免请求过快
            if ($index % 5 === 0 && $index > 0) {
                sleep(2);
            }
        }
        
        return $results;
    }
    
    /**
     * 保存到待确认文件（追加模式）
     * @param array $results 拼音结果
     * @param string $outputPath 输出文件路径
     */
    public static function saveToPendingFile($results, $outputPath) {
        // 如果文件不存在，创建初始结构
        if (!file_exists($outputPath)) {
            $initialContent = "<?php\n/**\n * 待确认拼音文件\n * 自动获取的拼音需要人工确认后才能使用\n * 生成时间: " . date('Y-m-d H:i:s') . "\n */\n\nreturn [\n];\n";
            file_put_contents($outputPath, $initialContent);
        }
        
        // 读取现有内容
        $existingData = require $outputPath;
        
        // 合并新结果（避免重复）
        $mergedData = $existingData;
        $newCount = 0;
        
        foreach ($results as $result) {
            $char = $result['char'];
            
            // 检查是否已存在
            $exists = false;
            foreach ($mergedData as $item) {
                if ($item['char'] === $char) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists && $result['pinyin']) {
                $mergedData[] = $result;
                $newCount++;
            }
        }
        
        // 重新生成文件内容
        $content = "<?php\n/**\n * 待确认拼音文件\n * 自动获取的拼音需要人工确认后才能使用\n * 最后更新: " . date('Y-m-d H:i:s') . "\n * 总条目数: " . count($mergedData) . "\n */\n\nreturn [\n";
        
        foreach ($mergedData as $item) {
            $content .= "    [\n";
            $content .= "        'char' => '" . addslashes($item['char']) . "',\n";
            $content .= "        'unicode' => '" . $item['unicode'] . "',\n";
            $content .= "        'pinyin' => '" . addslashes($item['pinyin']) . "',\n";
            $content .= "        'source' => '" . $item['source'] . "',\n";
            $content .= "        'confidence' => " . $item['confidence'] . ",\n";
            $content .= "        'timestamp' => '" . $item['timestamp'] . "',\n";
            $content .= "        'confirmed' => false,\n";
            $content .= "        'confirmed_pinyin' => null,\n";
            $content .= "        'confirmed_by' => null,\n";
            $content .= "        'confirmed_at' => null\n";
            $content .= "    ],\n";
        }
        
        $content .= "];\n";
        
        file_put_contents($outputPath, $content);
        
        return $newCount;
    }
    
    /**
     * 生成统计报告
     * @param array $results 拼音结果
     */
    public static function generateReport($results) {
        $total = count($results);
        $success = 0;
        $sources = [];
        
        foreach ($results as $result) {
            if ($result['pinyin']) {
                $success++;
                $source = $result['source'];
                $sources[$source] = ($sources[$source] ?? 0) + 1;
            }
        }
        
        return [
            'total' => $total,
            'success' => $success,
            'success_rate' => $total > 0 ? round(($success / $total) * 100, 2) : 0,
            'sources' => $sources
        ];
    }
}

// 命令行使用
if (php_sapi_name() === 'cli' && isset($argv[0]) && basename($argv[0]) === 'pinyin_auto_fetch.php') {
    
    require_once __DIR__ . '/../vendor/autoload.php';
    
    // 加载未找到的字符
    $notFoundPath = __DIR__ . '/../data/diy/not_found_chars.php';
    if (!file_exists($notFoundPath)) {
        echo "未找到字符文件不存在: {$notFoundPath}\n";
        exit(1);
    }
    
    $notFoundChars = require $notFoundPath;
    echo "=== 拼音自动获取工具 ===\n";
    echo "发现 " . count($notFoundChars) . " 个未找到拼音的汉字\n\n";
    
    // 询问用户要处理多少个字符
    echo "请输入要处理的字符数量（0表示全部）: ";
    $handle = fopen("php://stdin", "r");
    $input = trim(fgets($handle));
    fclose($handle);
    
    $limit = intval($input);
    if ($limit <= 0 || $limit > count($notFoundChars)) {
        $limit = count($notFoundChars);
    }
    
    $charsToProcess = array_slice($notFoundChars, 0, $limit);
    
    echo "开始处理前 {$limit} 个字符...\n\n";
    
    // 开始获取拼音
    $results = PinyinAutoFetcher::batchFetchPinyin($charsToProcess);
    
    // 生成报告
    $report = PinyinAutoFetcher::generateReport($results);
    
    echo "\n=== 处理报告 ===\n";
    echo "总处理数: {$report['total']}\n";
    echo "成功获取: {$report['success']}\n";
    echo "成功率: {$report['success_rate']}%\n";
    
    if (!empty($report['sources'])) {
        echo "来源统计:\n";
        foreach ($report['sources'] as $source => $count) {
            echo "  {$source}: {$count}\n";
        }
    }
    
    // 保存到待确认文件
    $pendingFile = __DIR__ . '/../data/diy/pinyin_pending_confirmation.php';
    $newCount = PinyinAutoFetcher::saveToPendingFile($results, $pendingFile);
    
    echo "\n新添加到待确认文件: {$newCount} 条记录\n";
    echo "待确认文件: {$pendingFile}\n";
    
    // 显示成功获取的示例
    echo "\n=== 成功获取的拼音示例 ===\n";
    $sampleCount = 0;
    foreach ($results as $result) {
        if ($result['pinyin'] && $sampleCount < 5) {
            echo "{$result['char']} ({$result['unicode']}) -> {$result['pinyin']} [{$result['source']}]\n";
            $sampleCount++;
        }
    }
    
    echo "\n=== 后续操作建议 ===\n";
    echo "1. 人工审核 {$pendingFile} 中的拼音准确性\n";
    echo "2. 确认正确的拼音后，可以导入到自定义字典\n";
    echo "3. 对于未获取到拼音的字符，需要其他方法处理\n";
}