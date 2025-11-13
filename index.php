<?php

require_once __DIR__ . '/vendor/autoload.php';

use tekintian\pinyin\PinyinConverter;

require_once __DIR__.'/test_helper.php';

// 快速使用示例
function pinyin($text, $separator = ' ', $withTone = false, $specialCharParam = '') {
    static $converter = null;
    if ($converter === null) {
        $converter = new PinyinConverter();
    }
    return $converter->convert($text, $separator, $withTone, $specialCharParam);
}

$testText = '你好！@#￥%……&*（）【】{}|、；‘：“，。、？';       
$result1 = pinyin($testText, ' ', false,  [
    'mode' => 'replace',
    'map' => ['！' => '!', '？' => '?']
]);
vv($result1);

$char = '䶮';
$resultWithTone = pinyin($char, ' ', true);
vv($resultWithTone);
