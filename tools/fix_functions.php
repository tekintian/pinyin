<?php
/**
 * 修复 functions.php 中的格式问题
 */

$file = __DIR__ . '/../src/functions.php';
$content = file_get_contents($file);

// 修复缺少空格的问题
$content = preg_replace('/(\s+})\s*$/m', "$1\n\n", $content);

// 写回文件
file_put_contents($file, $content);

echo "修复完成\n";