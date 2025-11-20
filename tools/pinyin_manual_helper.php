<?php
/**
 * 拼音人工辅助工具
 * 为无法自动获取拼音的生僻字提供人工处理支持
 */

class PinyinManualHelper {
    
    /**
     * 创建待确认拼音文件结构
     * @param array $chars 汉字数组
     * @param string $outputPath 输出文件路径
     */
    public static function createPendingFile($chars, $outputPath) {
        $content = "<?php\n/**\n * 待确认拼音文件\n * 需要人工确认拼音的生僻字列表\n * 生成时间: " . date('Y-m-d H:i:s') . "\n * 总字符数: " . count($chars) . "\n * \n * 使用说明:\n * 1. 人工查询每个汉字的正确拼音\n * 2. 填写 confirmed_pinyin 字段\n * 3. 设置 confirmed = true\n * 4. 填写 confirmed_by 和 confirmed_at\n * 5. 确认后可以导入到自定义字典\n */\n\nreturn [\n";
        
        foreach ($chars as $index => $char) {
            $unicode = 'U+' . strtoupper(dechex(mb_ord($char, 'UTF-8')));
            
            $content .= "    [\n";
            $content .= "        // 字符信息\n";
            $content .= "        'char' => '" . addslashes($char) . "',\n";
            $content .= "        'unicode' => '" . $unicode . "',\n";
            $content .= "        'codepoint' => " . mb_ord($char, 'UTF-8') . ",\n";
            $content .= "        \n";
            $content .= "        // 自动获取信息（留空）\n";
            $content .= "        'auto_pinyin' => null,\n";
            $content .= "        'auto_source' => null,\n";
            $content .= "        'auto_confidence' => 0,\n";
            $content .= "        \n";
            $content .= "        // 人工确认信息（需要填写）\n";
            $content .= "        'confirmed' => false,\n";
            $content .= "        'confirmed_pinyin' => null,\n";
            $content .= "        'confirmed_by' => null,\n";
            $content .= "        'confirmed_at' => null,\n";
            $content .= "        \n";
            $content .= "        // 参考信息\n";
            $content .= "        'reference_source' => '',\n";
            $content .= "        'notes' => '',\n";
            $content .= "        \n";
            $content .= "        // 处理状态\n";
            $content .= "        'status' => 'pending', // pending, in_progress, completed\n";
            $content .= "        'created_at' => '" . date('Y-m-d H:i:s') . "'\n";
            $content .= "    ]";
            
            // 如果不是最后一个，添加逗号
            if ($index < count($chars) - 1) {
                $content .= ",";
            }
            
            $content .= "\n";
        }
        
        $content .= "];\n";
        
        file_put_contents($outputPath, $content);
        
        return count($chars);
    }
    
    /**
     * 生成处理指南
     * @param string $outputPath 输出文件路径
     */
    public static function generateGuide($outputPath) {
        $guide = "# 生僻字拼音处理指南\n\n";
        $guide .= "## 处理流程\n\n";
        $guide .= "1. **查询工具准备**\n";
        $guide .= "   - 《汉语大字典》（推荐）\n";
        $guide .= "   - 《康熙字典》\n";
        $guide .= "   - 汉字叔叔网站（http://zi.tools）\n";
        $guide .= "   - 国学大师（http://www.guoxuedashi.com）\n\n";
        
        $guide .= "2. **查询步骤**\n";
        $guide .= "   - 复制汉字到查询工具\n";
        $guide .= "   - 记录正确的拼音（带声调）\n";
        $guide .= "   - 记录参考来源\n\n";
        
        $guide .= "3. **填写确认信息**\n";
        $guide .= "   - 修改 `confirmed_pinyin` 字段\n";
        $guide .= "   - 设置 `confirmed = true`\n";
        $guide .= "   - 填写 `confirmed_by`（处理人）\n";
        $guide .= "   - 填写 `confirmed_at`（确认时间）\n\n";
        
        $guide .= "## 字段说明\n\n";
        $guide .= "| 字段名 | 说明 | 示例 |\n";
        $guide .= "|--------|------|------|\n";
        $guide .= "| `char` | 汉字 | '𠮷' |\n";
        $guide .= "| `unicode` | Unicode编码 | 'U+20BB7' |\n";
        $guide .= "| `confirmed_pinyin` | 确认的拼音 | 'jí' |\n";
        $guide .= "| `confirmed_by` | 处理人 | '张三' |\n";
        $guide .= "| `reference_source` | 参考来源 | '《汉语大字典》' |\n\n";
        
        $guide .= "## 批量导入脚本\n\n";
        $guide .= "确认完成后，可以使用以下脚本批量导入到自定义字典：\n\n";
        $guide .= "```php\n";
        $guide .= "// 导入到自定义字典的示例脚本\n";
        $guide .= "\$pendingData = require 'pinyin_pending_confirmation.php';\n";
        $guide .= "\$customDict = [];\n";
        $guide .= "\n";
        $guide .= "foreach (\$pendingData as \$item) {\n";
        $guide .= "    if (\$item['confirmed']) {\n";
        $guide .= "        \$customDict[\$item['char']] = \$item['confirmed_pinyin'];\n";
        $guide .= "    }\n";
        $guide .= "}\n";
        $guide .= "\n";
        $guide .= "// 保存到自定义字典\n";
        $guide .= "file_put_contents('custom_with_tone.php', '<?php\\nreturn ' . var_export(\$customDict, true) . ';\\n');\n";
        $guide .= "```\n\n";
        
        $guide .= "## 注意事项\n\n";
        $guide .= "- 确保拼音准确性，特别是声调\n";
        $guide .= "- 记录参考来源以便复查\n";
        $guide .= "- 定期备份处理进度\n";
        
        file_put_contents($outputPath, $guide);
    }
    
    /**
     * 生成进度统计
     * @param string $pendingFilePath 待确认文件路径
     */
    public static function generateStats($pendingFilePath) {
        if (!file_exists($pendingFilePath)) {
            return null;
        }
        
        $data = require $pendingFilePath;
        
        $stats = [
            'total' => count($data),
            'confirmed' => 0,
            'pending' => 0,
            'in_progress' => 0,
            'by_confirmer' => []
        ];
        
        foreach ($data as $item) {
            if ($item['confirmed']) {
                $stats['confirmed']++;
                $confirmer = $item['confirmed_by'] ?? 'unknown';
                $stats['by_confirmer'][$confirmer] = ($stats['by_confirmer'][$confirmer] ?? 0) + 1;
            } else {
                $stats['pending']++;
            }
            
            if ($item['status'] === 'in_progress') {
                $stats['in_progress']++;
            }
        }
        
        return $stats;
    }
}

// 命令行使用
if (php_sapi_name() === 'cli' && isset($argv[0]) && basename($argv[0]) === 'pinyin_manual_helper.php') {
    
    require_once __DIR__ . '/../vendor/autoload.php';
    
    // 加载未找到的字符
    $notFoundPath = __DIR__ . '/../data/diy/not_found_chars.php';
    if (!file_exists($notFoundPath)) {
        echo "未找到字符文件不存在: {$notFoundPath}\n";
        exit(1);
    }
    
    $notFoundChars = require $notFoundPath;
    
    echo "=== 拼音人工辅助工具 ===\n";
    echo "发现 " . count($notFoundChars) . " 个未找到拼音的汉字\n\n";
    
    // 创建待确认文件
    $pendingFile = __DIR__ . '/../data/diy/pinyin_pending_confirmation.php';
    $count = PinyinManualHelper::createPendingFile($notFoundChars, $pendingFile);
    
    echo "✓ 已创建待确认文件: {$pendingFile}\n";
    echo "✓ 包含 {$count} 个需要人工确认的汉字\n\n";
    
    // 生成处理指南
    $guideFile = __DIR__ . '/../data/diy/pinyin_processing_guide.md';
    PinyinManualHelper::generateGuide($guideFile);
    
    echo "✓ 已生成处理指南: {$guideFile}\n\n";
    
    // 显示示例
    echo "=== 文件结构示例 ===\n";
    echo "每个汉字的数据结构包含以下字段：\n";
    echo "- char: 汉字\n";
    echo "- unicode: Unicode编码\n";
    echo "- confirmed_pinyin: 需要人工填写的拼音\n";
    echo "- confirmed_by: 处理人\n";
    echo "- reference_source: 参考来源\n";
    echo "- status: 处理状态\n\n";
    
    echo "=== 后续操作 ===\n";
    echo "1. 人工查询每个汉字的正确拼音\n";
    echo "2. 修改 {$pendingFile} 文件中的对应字段\n";
    echo "3. 参考 {$guideFile} 中的处理指南\n";
    echo "4. 确认完成后可以批量导入到自定义字典\n\n";
    
    echo "=== 推荐查询工具 ===\n";
    echo "1. 汉字叔叔: http://zi.tools\n";
    echo "2. 国学大师: http://www.guoxuedashi.com\n";
    echo "3. 《汉语大字典》（纸质版）\n";
    echo "4. 《康熙字典》（纸质版）\n\n";
}