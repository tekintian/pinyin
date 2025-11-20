<?php
/**
 * 字典格式标准化脚本
 * 将 custom_xxx, self_xxx, rare_xxx 字典文件统一转换为紧凑格式
 */

// 不依赖 PinyinConverter，直接处理

class DictionaryFormatStandardizer
{
    private $converter;
    private $processedFiles = 0;
    private $modifiedFiles = 0;
    
    public function __construct()
    {
        // 不需要转换器实例
    }
    
    /**
     * 获取所有需要标准化的字典文件
     */
    private function getDictionaryFiles()
    {
        $dataDir = __DIR__ . '/../data';
        $patterns = [
            'custom_*.php',
            'self_*.php', 
            'rare_*.php'
        ];
        
        $files = [];
        foreach ($patterns as $pattern) {
            foreach (glob($dataDir . '/' . $pattern) as $file) {
                // 排除 polyphone_rules.php
                if (strpos(basename($file), 'polyphone_rules') === false) {
                    $files[] = $file;
                }
            }
        }
        
        return $files;
    }
    
    /**
     * 检查文件是否需要格式化
     */
    private function needsFormatting($filePath)
    {
        $content = file_get_contents($filePath);
        
        // 检查是否包含非紧凑格式的数组结构
        return preg_match('/array\s*\(\s*\n\s*\d+\s*=>/', $content) ||
               preg_match('/=>\s*\n\s*array\s*\(/', $content);
    }
    
    /**
     * 标准化单个字典文件
     */
    public function standardizeFile($filePath)
    {
        $this->processedFiles++;
        $fileName = basename($filePath);
        
        echo "处理文件: {$fileName}\n";
        
        if (!$this->needsFormatting($filePath)) {
            echo "  ✓ 已经是紧凑格式，跳过\n";
            return false;
        }
        
        try {
            // 加载数据
            $data = include $filePath;
            if (!is_array($data)) {
                echo "  ✗ 文件格式错误，跳过\n";
                return false;
            }
            
            // 备份原文件到统一目录
            $backupDir = dirname($filePath) . '/backup';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            $backupPath = $backupDir . '/' . basename($filePath) . '.backup.' . date('Y-m-d_H-i-s');
            if (!copy($filePath, $backupPath)) {
                echo "  ✗ 备份失败，跳过\n";
                return false;
            }
            
            // 生成紧凑格式内容
            $content = "<?php\nreturn " . $this->exportCompactArray($data) . ";\n";
            
            // 写入文件
            if (file_put_contents($filePath, $content) === false) {
                echo "  ✗ 写入失败\n";
                return false;
            }
            
            $this->modifiedFiles++;
            echo "  ✓ 格式化完成，备份: backup/" . basename($backupPath) . "\n";
            return true;
            
        } catch (Exception $e) {
            echo "  ✗ 处理失败: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * 以紧凑格式导出数组
     */
    private function exportCompactArray($array, $indent = 0)
    {
        $result = "[\n";
        $indentStr = str_repeat('    ', $indent + 1);
        
        foreach ($array as $key => $value) {
            if (is_string($key)) {
                $key = "'" . addslashes($key) . "'";
            }
            
            if (is_array($value)) {
                // 检查是否为索引数组（字典值）
                if (array_keys($value) === range(0, count($value) - 1)) {
                    // 紧凑格式：['key' => ['value1', 'value2']]
                    $values = [];
                    foreach ($value as $item) {
                        $values[] = is_string($item) ? "'" . addslashes($item) . "'" : $item;
                    }
                    $result .= $indentStr . $key . ' => [' . implode(', ', $values) . '],' . "\n";
                } else {
                    // 嵌套关联数组，递归处理
                    $result .= $indentStr . $key . ' => ' . $this->exportCompactArray($value, $indent + 1) . ",\n";
                }
            } elseif (is_string($value)) {
                $result .= $indentStr . $key . ' => \'' . addslashes($value) . '\',' . "\n";
            } else {
                $result .= $indentStr . $key . ' => ' . var_export($value, true) . ",\n";
            }
        }
        
        $result .= str_repeat('    ', $indent) . ']';
        return $result;
    }
    
    /**
     * 批量标准化所有字典文件
     */
    public function standardizeAll()
    {
        echo "开始字典格式标准化...\n";
        echo "目标文件: custom_*.php, self_*.php, rare_*.php\n\n";
        
        $files = $this->getDictionaryFiles();
        
        if (empty($files)) {
            echo "未找到需要处理的字典文件\n";
            return;
        }
        
        echo "找到 " . count($files) . " 个字典文件:\n";
        foreach ($files as $file) {
            echo "  - " . basename($file) . "\n";
        }
        echo "\n";
        
        foreach ($files as $file) {
            $this->standardizeFile($file);
        }
        
        echo "\n处理完成!\n";
        echo "总文件数: {$this->processedFiles}\n";
        echo "已修改: {$this->modifiedFiles}\n";
        
        if ($this->modifiedFiles > 0) {
            echo "\n提示: 所有原文件已备份到 data/backup/ 目录\n";
            echo "如需恢复，请使用备份文件替换原文件\n";
        }
    }
    
    /**
     * 验证标准化后的文件格式
     */
    public function verifyStandardization()
    {
        echo "\n验证标准化结果...\n";
        
        $files = $this->getDictionaryFiles();
        $errors = [];
        
        foreach ($files as $file) {
            try {
                $data = include $file;
                if (!is_array($data)) {
                    $errors[] = basename($file) . ": 不是有效的数组格式";
                }
            } catch (Exception $e) {
                $errors[] = basename($file) . ": " . $e->getMessage();
            }
        }
        
        if (empty($errors)) {
            echo "✓ 所有文件格式验证通过\n";
        } else {
            echo "✗ 发现格式问题:\n";
            foreach ($errors as $error) {
                echo "  - {$error}\n";
            }
        }
    }
}

// 执行标准化
if (php_sapi_name() === 'cli') {
    $standardizer = new DictionaryFormatStandardizer();
    $standardizer->standardizeAll();
    $standardizer->verifyStandardization();
}