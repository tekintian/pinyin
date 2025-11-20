<?php
/**
 * 修复最后的格式问题
 */

$file = __DIR__ . '/../src/functions.php';
$content = file_get_contents($file);

// 修复两个具体的空格问题
$content = str_replace(
    "            }\n\n\n            // 2. 处理连续纯数字",
    "            }\n\n            // 2. 处理连续纯数字",
    $content
);

$content = str_replace(
    "            }\n\n\n            // 3. 处理特殊数字",
    "            }\n\n            // 3. 处理特殊数字",
    $content
);

// 写回文件
file_put_contents($file, $content);

echo "修复完成\n";