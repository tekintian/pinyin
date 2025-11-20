<?php
/**
 * 修复常量可见性声明
 */

$filePath = __DIR__ . '/../src/Utils/PinyinConstants.php';
$content = file_get_contents($filePath);

// 将所有 const 替换为 public const
$content = preg_replace('/^\s*const\s+/m', '    public const ', $content);

// 写回文件
file_put_contents($filePath, $content);

echo "常量可见性修复完成\n";