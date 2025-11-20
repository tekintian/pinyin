<?php
/**
 * 智能字典管理工具
 * 提供格式化、验证、修复等多种功能
 */

class SmartDictionaryManager
{
    private $dataDir;
    private $backupDir;
    
    public function __construct()
    {
        $this->dataDir = __DIR__ . '/../data';
        $this->backupDir = __DIR__ . '/../data/backup';
        $this->ensureBackupDir();
    }
    
    /**
     * 确保备份目录存在
     */
    private function ensureBackupDir()
    {
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    /**
     * 获取所有字典文件
     */
    public function getDictionaryFiles($type = 'all')
    {
        $patterns = [];
        
        switch ($type) {
            case 'custom':
                $patterns = ['custom_*.php'];
                break;
            case 'self':
                $patterns = ['self_*.php'];
                break;
            case 'rare':
                $patterns = ['rare_*.php'];
                break;
            default:
                $patterns = ['custom_*.php', 'self_*.php', 'rare_*.php'];
        }
        
        $files = [];
        foreach ($patterns as $pattern) {
            foreach (glob($this->dataDir . '/' . $pattern) as $file) {
                if (strpos(basename($file), 'polyphone_rules') === false) {
                    $files[] = $file;
                }
            }
        }
        
        return $files;
    }
    
    /**
     * 紧凑格式数组导出
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
                if (array_keys($value) === range(0, count($value) - 1)) {
                    $values = [];
                    foreach ($value as $item) {
                        $values[] = is_string($item) ? "'" . addslashes($item) . "'" : $item;
                    }
                    $result .= $indentStr . $key . ' => [' . implode(', ', $values) . '],' . "\n";
                } else {
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
     * 检查文件格式
     */
    public function checkFileFormat($filePath)
    {
        $content = file_get_contents($filePath);
        
        $issues = [];
        
        // 检查是否为紧凑格式
        if (preg_match('/array\s*\(\s*\n\s*\d+\s*=>/', $content)) {
            $issues[] = '非紧凑数组格式';
        }
        
        if (preg_match('/=>\s*\n\s*array\s*\(/', $content)) {
            $issues[] = '多行数组声明';
        }
        
        // 检查语法
        $tempFile = tempnam(sys_get_temp_dir(), 'dict_check_');
        file_put_contents($tempFile, $content);
        
        $output = [];
        $returnCode = 0;
        exec("php -l {$tempFile} 2>&1", $output, $returnCode);
        
        unlink($tempFile);
        
        if ($returnCode !== 0) {
            $issues[] = 'PHP语法错误: ' . implode(' ', $output);
        }
        
        return $issues;
    }
    
    /**
     * 格式化文件
     */
    public function formatFile($filePath, $backup = true)
    {
        $fileName = basename($filePath);
        
        // 检查是否需要格式化
        $issues = $this->checkFileFormat($filePath);
        if (empty($issues)) {
            return ['success' => true, 'message' => '已经是标准格式'];
        }
        
        // 备份（直接复制原文件，不做任何修改）
        if ($backup) {
            $backupPath = $this->backupDir . '/' . $fileName . '.backup.' . date('Y-m-d_H-i-s');
            if (!copy($filePath, $backupPath)) {
                return ['success' => false, 'message' => '备份失败'];
            }
        }
        
        try {
            $data = include $filePath;
            if (!is_array($data)) {
                return ['success' => false, 'message' => '文件内容不是数组'];
            }
            
            $newContent = "<?php\nreturn " . $this->exportCompactArray($data) . ";\n";
            
            if (file_put_contents($filePath, $newContent) === false) {
                return ['success' => false, 'message' => '写入失败'];
            }
            
            return ['success' => true, 'message' => '格式化成功'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 批量格式化
     */
    public function batchFormat($type = 'all')
    {
        $files = $this->getDictionaryFiles($type);
        $results = [];
        
        echo "开始批量格式化 (" . $type . ")...\n";
        echo "找到 " . count($files) . " 个文件\n\n";
        
        foreach ($files as $file) {
            $fileName = basename($file);
            echo "处理: {$fileName}\n";
            
            $result = $this->formatFile($file);
            $results[$fileName] = $result;
            
            if ($result['success']) {
                echo "  ✓ " . $result['message'] . "\n";
            } else {
                echo "  ✗ " . $result['message'] . "\n";
            }
        }
        
        return $results;
    }
    
    /**
     * 验证所有文件
     */
    public function validateAll($type = 'all')
    {
        $files = $this->getDictionaryFiles($type);
        $errors = [];
        
        echo "验证文件格式...\n";
        
        foreach ($files as $file) {
            $fileName = basename($file);
            $issues = $this->checkFileFormat($file);
            
            if (!empty($issues)) {
                $errors[$fileName] = $issues;
                echo "  ✗ {$fileName}: " . implode(', ', $issues) . "\n";
            } else {
                echo "  ✓ {$fileName}\n";
            }
        }
        
        if (empty($errors)) {
            echo "\n✓ 所有文件格式正确\n";
        } else {
            echo "\n✗ 发现 " . count($errors) . " 个问题文件\n";
        }
        
        return $errors;
    }
    
    /**
     * 显示统计信息
     */
    public function showStats()
    {
        $files = $this->getDictionaryFiles();
        $totalSize = 0;
        $totalEntries = 0;
        $formatStats = ['compact' => 0, 'noncompact' => 0];
        
        echo "字典文件统计:\n";
        echo str_repeat("=", 50) . "\n";
        
        foreach ($files as $file) {
            $fileName = basename($file);
            $fileSize = filesize($file);
            $totalSize += $fileSize;
            
            $issues = $this->checkFileFormat($file);
            $format = empty($issues) ? 'compact' : 'noncompact';
            $formatStats[$format]++;
            
            try {
                $data = include $file;
                $entryCount = is_array($data) ? count($data) : 0;
                $totalEntries += $entryCount;
                
                printf("%-25s %8s %6d entries %s\n", 
                    $fileName, 
                    $this->formatBytes($fileSize), 
                    $entryCount,
                    $format === 'compact' ? '✓' : '✗'
                );
            } catch (Exception $e) {
                printf("%-25s %8s %6s entries %s\n", 
                    $fileName, 
                    $this->formatBytes($fileSize), 
                    'N/A',
                    '✗'
                );
            }
        }
        
        echo str_repeat("=", 50) . "\n";
        printf("%-25s %8s %6d entries\n", '总计', $this->formatBytes($totalSize), $totalEntries);
        printf("格式: %d 个紧凑, %d 个非紧凑\n", $formatStats['compact'], $formatStats['noncompact']);
    }
    
    /**
     * 格式化字节大小
     */
    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 1) . ' ' . $units[$pow];
    }
}

// 命令行接口
if (php_sapi_name() === 'cli') {
    $manager = new SmartDictionaryManager();
    
    $command = $argv[1] ?? 'help';
    $type = $argv[2] ?? 'all';
    
    switch ($command) {
        case 'format':
            $manager->batchFormat($type);
            break;
            
        case 'validate':
            $manager->validateAll($type);
            break;
            
        case 'stats':
            $manager->showStats();
            break;
            
        case 'help':
        default:
            echo "智能字典管理工具\n\n";
            echo "用法:\n";
            echo "  php smart_dictionary_manager.php <command> [type]\n\n";
            echo "命令:\n";
            echo "  format   - 格式化字典文件\n";
            echo "  validate - 验证文件格式\n";
            echo "  stats    - 显示统计信息\n";
            echo "  help     - 显示帮助\n\n";
            echo "类型 (可选):\n";
            echo "  all     - 所有字典文件 (默认)\n";
            echo "  custom  - 仅 custom_*.php\n";
            echo "  self    - 仅 self_*.php\n";
            echo "  rare    - 仅 rare_*.php\n\n";
            echo "示例:\n";
            echo "  php smart_dictionary_manager.php format\n";
            echo "  php smart_dictionary_manager.php validate custom\n";
            echo "  php smart_dictionary_manager.php stats\n";
            break;
    }
}