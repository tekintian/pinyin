<?php
/**
 * 快速拼音测试工具
 * 测试各种方法获取生僻字拼音
 */

require_once __DIR__ . '/../vendor/autoload.php';

// 加载未找到的字符
$notFoundPath = __DIR__ . '/../data/diy/not_found_chars.php';
if (!file_exists($notFoundPath)) {
    echo "未找到字符文件不存在\n";
    exit(1);
}

$notFoundChars = require_file($notFoundPath);

// 测试前10个字符
$testChars = array_slice($notFoundChars, 0, 10);

echo "=== 生僻字拼音获取测试 ===\n\n";

foreach ($testChars as $char) {
    echo "字符: {$char}\n";
    echo "Unicode: U+" . strtoupper(dechex(mb_ord($char, 'UTF-8'))) . "\n";
    
    // 方法1：检查是否在基本汉字扩展区
    $codepoint = mb_ord($char, 'UTF-8');
    if ($codepoint >= 0x20000 && $codepoint <= 0x2A6DF) {
        echo "区域: CJK扩展B区\n";
        echo "说明: 多为生僻字，需要专业字典或人工确认\n";
    }
    
    // 方法2：尝试字形分析
    echo "字形分析: ";
    $components = [];
    
    // 简化版字形分析
    if (mb_strpos($char, '口') !== false) $components[] = '口';
    if (mb_strpos($char, '木') !== false) $components[] = '木';
    if (mb_strpos($char, '水') !== false) $components[] = '水';
    if (mb_strpos($char, '火') !== false) $components[] = '火';
    if (mb_strpos($char, '土') !== false) $components[] = '土';
    
    if (!empty($components)) {
        echo "包含部件: " . implode(', ', $components) . "\n";
    } else {
        echo "无法识别常见部件\n";
    }
    
    echo "推荐处理方式:\n";
    echo "1. 使用专业汉字字典查询（如《汉语大字典》）\n";
    echo "2. 咨询语言文字专家\n";
    echo "3. 标记为需要人工确认\n";
    
    echo "\n" . str_repeat("-", 50) . "\n\n";
}

echo "=== 批量处理建议 ===\n\n";
echo "对于这些扩展B区的生僻字，建议：\n";
echo "1. 创建人工确认流程\n";
echo "2. 集成专业汉字数据库\n";
echo "3. 使用字形相似性作为参考\n";
echo "4. 建立用户反馈机制\n\n";

echo "当前可用的自动方法有限，建议优先处理常用扩展字符。\n";
?>