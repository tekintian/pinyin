<?php
/**
 * 快速字典格式化脚本
 * 简化版本，专注于格式转换
 */

/**
 * 紧凑格式数组导出
 */
function exportCompactArray($array, $indent = 0)
{
    $result = "[\n";
    $indentStr = str_repeat('    ', $indent + 1);
    
    foreach ($array as $key => $value) {
        if (is_string($key)) {
            $key = "'" . addslashes($key) . "'";
        }
        
        if (is_array($value)) {
            // 索引数组 - 紧凑格式
            if (array_keys($value) === range(0, count($value) - 1)) {
                $values = [];
                foreach ($value as $item) {
                    $values[] = is_string($item) ? "'" . addslashes($item) . "'" : $item;
                }
                $result .= $indentStr . $key . ' => [' . implode(', ', $values) . '],' . "\n";
            } else {
                // 嵌套关联数组
                $result .= $indentStr . $key . ' => ' . exportCompactArray($value, $indent + 1) . ",\n";
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
 * 格式化单个文件
 */
function formatFile($filePath)
{
    $fileName = basename($filePath);
    echo "处理: {$fileName}\n";
    
    // 检查是否需要格式化
    $content = file_get_contents($filePath);
    if (!preg_match('/array\s*\(\s*\n\s*\d+\s*=>/', $content)) {
        echo "  ✓ 已是紧凑格式\n";
        return false;
    }
    
    // 备份到统一目录
    $backupDir = dirname($filePath) . '/backup';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    $backupPath = $backupDir . '/' . basename($filePath) . '.backup.' . date('Y-m-d_H-i-s');
    copy($filePath, $backupPath);
    
    // 转换格式
    $data = include $filePath;
    $newContent = "<?php\nreturn " . exportCompactArray($data) . ";\n";
    
    file_put_contents($filePath, $newContent);
    echo "  ✓ 格式化完成\n";
    return true;
}

// 获取目标文件
$dataDir = __DIR__ . '/../data';
$patterns = ['custom_*.php', 'self_*.php', 'rare_*.php'];
$files = [];

foreach ($patterns as $pattern) {
    foreach (glob($dataDir . '/' . $pattern) as $file) {
        if (strpos(basename($file), 'polyphone_rules') === false) {
            $files[] = $file;
        }
    }
}

echo "找到 " . count($files) . " 个字典文件\n\n";

$modified = 0;
foreach ($files as $file) {
    if (formatFile($file)) {
        $modified++;
    }
}

echo "\n完成! 修改了 {$modified} 个文件\n";