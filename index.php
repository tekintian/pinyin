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

// $testText = '你好！@#￥%……&*（）【】{}|、；‘：“，。、？';       
// $result1 = $converter->convert($testText, ' ', false,  [
//     'mode' => 'replace',
//     'map' => ['！' => '!', '？' => '?']
// ]);
// vv($result1);

// $char = '䶮';
// $resultWithTone = $converter->convert($char, ' ', true);
// vv($resultWithTone);

$options = [
    'dict_loading' => [
        'lazy_loading' => true,
        'preload_priority' => ['custom', 'common']
    ],
    'special_char' => [
        'default_mode' => 'keep'
    ]
];
$customConverter = new PinyinConverter($options);
// 验证配置生效
$result = $customConverter->convert('你好！', ' ', false);

vv($result);

$rareChars = ['䶮', '䲜'];
        
foreach ($rareChars as $char) {
    $resultWithTone = $converter->convert($char, ' ', true);
    $resultWithoutTone = $converter->convert($char, ' ', false);
    
   vv([$resultWithTone, $resultWithoutTone]);

}
