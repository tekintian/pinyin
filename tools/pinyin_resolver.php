<?php
/**
 * 拼音解析工具
 * 为未找到拼音的汉字提供多种拼音获取方案
 */

class PinyinResolver {
    
    /**
     * 方法1：使用本地扩展汉字数据库（如果可用）
     */
    public static function getFromLocalExtendedDict($char) {
        // 这里可以集成扩展的汉字数据库
        // 例如：CJK统一汉字扩展字符集
        return null;
    }
    
    /**
     * 方法2：使用字形相似性推测
     * 基于汉字结构和部首推测读音
     */
    public static function guessFromGlyphSimilarity($char) {
        // 常见构字部件的读音映射
        $componentPinyin = [
            // 常见部首
            '口' => 'kou', '木' => 'mu', '水' => 'shui', '火' => 'huo', '土' => 'tu',
            '金' => 'jin', '人' => 'ren', '心' => 'xin', '手' => 'shou', '足' => 'zu',
            '日' => 'ri', '月' => 'yue', '山' => 'shan', '石' => 'shi', '田' => 'tian',
            
            // 常见声旁
            '工' => 'gong', '可' => 'ke', '古' => 'gu', '胡' => 'hu', '者' => 'zhe',
            '也' => 'ye', '巴' => 'ba', '包' => 'bao', '方' => 'fang', '亢' => 'kang'
        ];
        
        // 简化版：直接检查是否包含常见声旁
        foreach ($componentPinyin as $component => $pinyin) {
            if (mb_strpos($char, $component) !== false) {
                return $pinyin;
            }
        }
        
        return null;
    }
    
    /**
     * 方法3：基于Unicode区块的统计推测
     * 不同扩展区的汉字有不同的读音规律
     */
    public static function guessFromUnicodeBlock($char) {
        $codepoint = mb_ord($char, 'UTF-8');
        
        // 扩展B区（20000-2A6DF）：多为生僻字，读音复杂
        if ($codepoint >= 0x20000 && $codepoint <= 0x2A6DF) {
            return '?'; // 需要人工确认
        }
        
        // 其他扩展区类似处理
        return null;
    }
    
    /**
     * 方法4：使用在线查询（备选方案）
     */
    public static function queryOnline($char) {
        $apis = [
            // 百度拼音API
            function($char) {
                $url = "https://pinyin.baidu.com/api/pinyin?text=" . urlencode($char);
                try {
                    $response = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 5]]));
                    $data = json_decode($response, true);
                    return $data['data'][0]['pinyin'] ?? null;
                } catch (Exception $e) {
                    return null;
                }
            },
            
            // 汉字叔叔字典（备选）
            function($char) {
                // 这里可以集成其他在线字典
                return null;
            }
        ];
        
        foreach ($apis as $api) {
            $result = $api($char);
            if ($result) {
                return $result;
            }
        }
        
        return null;
    }
    
    /**
     * 综合所有方法获取拼音
     */
    public static function resolvePinyin($char) {
        $methods = [
            ['method' => 'queryOnline', 'priority' => 90],
            ['method' => 'guessFromGlyphSimilarity', 'priority' => 50],
            ['method' => 'guessFromUnicodeBlock', 'priority' => 30]
        ];
        
        foreach ($methods as $methodInfo) {
            $result = call_user_func([__CLASS__, $methodInfo['method']], $char);
            if ($result && $result !== '?') {
                return [
                    'pinyin' => $result,
                    'source' => $methodInfo['method'],
                    'confidence' => $methodInfo['priority']
                ];
            }
        }
        
        return null;
    }
    
    /**
     * 批量解析拼音
     */
    public static function batchResolve($chars) {
        $results = [];
        
        foreach ($chars as $index => $char) {
            echo "处理进度: " . ($index + 1) . "/" . count($chars) . " - {$char}\n";
            
            $result = self::resolvePinyin($char);
            $results[$char] = $result ?: [
                'pinyin' => null,
                'source' => 'not_found',
                'confidence' => 0
            ];
            
            // 添加延迟避免请求过快
            if ($index % 10 === 0) {
                sleep(1);
            }
        }
        
        return $results;
    }
    
    /**
     * 生成报告
     */
    public static function generateReport($results) {
        $report = [
            'total' => count($results),
            'resolved' => 0,
            'unresolved' => 0,
            'by_source' => []
        ];
        
        foreach ($results as $char => $result) {
            if ($result['pinyin']) {
                $report['resolved']++;
                $source = $result['source'];
                $report['by_source'][$source] = ($report['by_source'][$source] ?? 0) + 1;
            } else {
                $report['unresolved']++;
            }
        }
        
        return $report;
    }
}

// 命令行使用
if (php_sapi_name() === 'cli' && isset($argv[0]) && basename($argv[0]) === 'pinyin_resolver.php') {
    
    require_once __DIR__ . '/../vendor/autoload.php';
    
    // 加载未找到的字符
    $notFoundPath = __DIR__ . '/../data/diy/not_found_chars.php';
    if (!file_exists($notFoundPath)) {
        echo "未找到字符文件不存在: {$notFoundPath}\n";
        exit(1);
    }
    
    $notFoundChars = require $notFoundPath;
    echo "发现 " . count($notFoundChars) . " 个未找到拼音的汉字\n\n";
    
    // 开始解析
    $results = PinyinResolver::batchResolve($notFoundChars);
    
    // 生成报告
    $report = PinyinResolver::generateReport($results);
    
    echo "\n=== 解析报告 ===\n";
    echo "总字符数: {$report['total']}\n";
    echo "成功解析: {$report['resolved']}\n";
    echo "未解析: {$report['unresolved']}\n";
    
    if (!empty($report['by_source'])) {
        echo "解析来源统计:\n";
        foreach ($report['by_source'] as $source => $count) {
            echo "  {$source}: {$count}\n";
        }
    }
    
    // 保存结果
    $outputFile = __DIR__ . '/../data/diy/pinyin_resolve_results.php';
    file_put_contents($outputFile, "<?php\nreturn " . var_export($results, true) . ";\n");
    echo "\n结果已保存到: {$outputFile}\n";
    
    // 显示部分结果示例
    echo "\n=== 示例结果 ===\n";
    $sampleCount = 0;
    foreach ($results as $char => $result) {
        if ($result['pinyin'] && $sampleCount < 10) {
            echo "{$char} -> {$result['pinyin']} [{$result['source']}]\n";
            $sampleCount++;
        }
    }
}