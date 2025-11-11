<?php

require_once __DIR__ . '/vendor/autoload.php';

use tekintian\pinyin\PinyinConverter;
// use SebastianBergmann\CodeCoverage\Node\File;
// use tekintian\pinyin\Utils\FileUtil;

require_once __DIR__.'/test_helper.php';

// 快速使用示例
function pinyin($text, $separator = ' ', $withTone = false, $specialCharParam = '') {
    static $converter = null;
    if ($converter === null) {
        $converter = new PinyinConverter();
    }
    return $converter->convert($text, $separator, $withTone, $specialCharParam);
}

$converter = new PinyinConverter();

$testText = '你好！@#￥%……&*（）【】{}|、；‘：“，。、？';
        
$result1 = $converter->convert($testText, ' ', false,  [
    'mode' => 'replace',
    'map' => ['！' => '!', '？' => '?']
]);


vd($result1);
