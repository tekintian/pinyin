<?php
/**
 * 批量修复测试文件的命名空间
 */

$testFiles = [
    'tests/Unit/EdgeCaseTest.php',
    'tests/Unit/CustomDictionaryTest.php',
    'tests/Unit/PolyphoneTest.php',
    'tests/Unit/BasicConversionTest.php',
    'tests/Unit/SpecialCharacterTest.php',
    'tests/ComprehensivePinyinConverterTest.php',
    'tests/legacy/PinyinConverterTest.php',
    'tests/legacy/UnitTest.php',
    'tests/Performance/PerformanceTest.php',
    'tests/Integration/CompleteWorkflowTest.php'
];

foreach ($testFiles as $file) {
    $filePath = __DIR__ . '/../' . $file;
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        
        // 添加命名空间
        if (strpos($content, 'namespace ') === false) {
            $content = preg_replace(
                '/^<\?php\s*\n/',
                "<?php\n\nnamespace tekintian\\pinyin\\Tests;\n",
                $content
            );
            
            file_put_contents($filePath, $content);
            echo "修复: $file\n";
        }
    }
}

echo "命名空间修复完成\n";