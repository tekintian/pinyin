<?php
/**
 * 修复剩余的代码风格错误
 */

$files = [
    'src/functions.php',
    'src/PinyinConverter.php'
];

foreach ($files as $file) {
    $filePath = __DIR__ . '/../' . $file;
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        
        // 修复functions.php的空格问题
        if ($file === 'src/functions.php') {
            // 修复第一个空格问题
            $content = preg_replace(
                '/(\s+})\s*\n\s*\/\/ 2\. 处理连续纯数字/',
                "$1\n\n            // 2. 处理连续纯数字",
                $content
            );
            
            // 修复第二个空格问题
            $content = preg_replace(
                '/(\s+})\s*\n\s*\/\/ 3\. 处理特殊数字/',
                "$1\n\n            // 3. 处理特殊数字",
                $content
            );
        }
        
        // 修复PinyinConverter.php的空格问题
        if ($file === 'src/PinyinConverter.php') {
            $content = preg_replace(
                '/(\s+})\s*\n\s*\/\/ 对于其他类型的规则/',
                "$1\n\n                // 对于其他类型的规则",
                $content
            );
        }
        
        file_put_contents($filePath, $content);
        echo "修复: $file\n";
    }
}

echo "错误修复完成\n";