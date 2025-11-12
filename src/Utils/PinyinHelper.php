<?php

namespace tekintian\pinyin\Utils;

/**
 * 拼音工具助手类
 * 提供通用的拼音处理功能，避免代码重复和风格不一致
 */
class PinyinHelper
{
    // 使用 PinyinConstants 类统一管理常量，避免重复定义
    // 所有汉字范围、正则模式等常量都统一在 PinyinConstants 中定义
    
    /**
     * 过滤非纯汉字，仅保留Unicode标准中的纯汉字
     * 包括基本汉字、扩展A-G区汉字、兼容汉字等
     * 
     * 过滤范围包括：
     * - 基本汉字：U+4E00 - U+9FFF
     * - 扩展A区：U+3400 - U+4DBF
     * - 扩展B-G区：U+20000 - U+2FA1F
     * - 兼容汉字：U+F900 - U+FAFF 和 U+2F800 - U+2FA1F
     * 
     * 注意：不包含标点符号、数字、空格等非汉字字符
     * 
     * @param string $text 待处理的原始文本
     * @return string 过滤后仅包含纯汉字的字符串
     */
    public static function filterPureChinese($text) {
        // 使用 PinyinConstants 统一管理汉字范围
        $pattern = PinyinConstants::getChinesePattern('full');
        // 提取所有匹配的纯汉字，拼接为字符串
        preg_match_all($pattern, $text, $matches);
        return implode('', $matches[0]);
    }
    /**
    * 剔除文本中的非纯汉字（保留纯汉字）
    * @param string $text 待处理文本
    * @return string 过滤后的文本
    */
    public static function removeNonChinese($text) {
       // 使用 PinyinConstants 统一管理汉字范围
       $pattern = PinyinConstants::getChinesePattern('full', true);
       // 替换非纯汉字为空白
       return preg_replace($pattern, '', $text);
   }
    /**
     * 移除拼音中的声调
     * 
     * @param string $pinyin 带声调的拼音
     * @return string 无声调的拼音
     */
    public static function removeTone($pinyin)
    {
        // 使用 PinyinConstants 统一管理声调映射
        $toneMap = [];
        foreach (PinyinConstants::getPinyinToneMap() as $vowel => $tones) {
            foreach ($tones as $toneChar => $noTone) {
                $toneMap[$toneChar] = $vowel;
            }
        }
        // 保留ü
        $toneMap['ü'] = 'ü';
        
        return strtr($pinyin, $toneMap);
    }

    /**
     * 验证拼音格式是否有效
     * 
     * @param string $pinyin 待验证的拼音
     * @return bool 是否有效
     */
    public static function isValidPinyin($pinyin)
    {
        // 基本拼音格式验证
        return preg_match('/^[a-zāáǎàōóǒòēéěèīíǐìūúǔùüǖǘǚǜ]+$/i', $pinyin) && 
               mb_strlen($pinyin) > 0;
    }

    /**
     * 标准化拼音格式
     * @param string $pinyin 拼音
     * @param bool $withTone 是否带声调
     * @return string 标准化后的拼音
     */
    public static function normalizePinyinFormat($pinyin, $withTone) {
        $pinyin = trim($pinyin);
        
        // 移除非法字符
        $pinyin = preg_replace('/[^a-zāáǎàōóǒòēéěèīíǐìūúǔùüǖǘǚǜ\s]/iu', '', $pinyin);
        
        // 处理声调
        if (!$withTone) {
            $pinyin = self::removeTone($pinyin);
        }
        
        // 标准化空格（多个空格合并为一个）
        $pinyin = preg_replace('/\s+/', ' ', $pinyin);

        // 统一大小写（小写）
        $pinyin = mb_strtolower($pinyin);
        
        return $pinyin;
    }

    /**
     * 清理拼音字符串
     * 
     * @param string $pinyin 原始拼音
     * @param bool $removeAllSpaces 是否移除所有空格
     * @return string 清理后的拼音
     */
    public static function cleanPinyin($pinyin, $removeAllSpaces = false)
    {
        if ($removeAllSpaces) {
            return self::processSpaces($pinyin, true);
        }
        
        return self::processSpaces($pinyin, false);
    }

    /**
     * 处理拼音中的空格
     * 
     * @param string $pinyin 原始拼音
     * @param bool $removeAll 是否移除所有空格
     * @return string 处理后的拼音
     */
    public static function processSpaces($pinyin, $removeAll = false)
    {
        if ($removeAll) {
            return str_replace(' ', '', $pinyin);
        }
        
        // 保留单词间的单个空格，移除多余空格
        $pinyin = preg_replace('/\s+/', ' ', $pinyin);
        return trim($pinyin);
    }

    /**
     * 获取拼音数组中的第一个有效拼音
     * 
     * @param array $pinyinArray 拼音数组
     * @return string 第一个拼音
     */
    public static function getFirstPinyin($pinyinArray)
    {
        foreach ($pinyinArray as $pinyin) {
            if (!empty(trim($pinyin))) {
                return $pinyin;
            }
        }
        
        return '';
    }
    /**
     * 解析拼音选项
     * @param mixed $pinyin 拼音数据
     * @return array 拼音选项数组
     */
    public static function parsePinyinOptions($pinyin) {
        if (is_array($pinyin)) {
            // 如果已经是数组，处理每个元素
            $result = [];
            foreach ($pinyin as $item) {
                if (is_string($item) && str_contains($item, ' ')) {
                    // 如果数组元素包含空格分隔的拼音，拆分它们
                    $result = array_merge($result, explode(' ', $item));
                } else {
                    $result[] = $item;
                }
            }
            return array_unique(array_filter($result));
        }
        
        if (is_string($pinyin)) {
            // 如果是字符串，按空格拆分
            return array_unique(array_filter(explode(' ', $pinyin)));
        }
        
        return [$pinyin];
    }
    /**
     * 检查拼音格式一致性
     * 
     * @param array $pinyinArray 拼音数组
     * @param bool $withTone 是否带声调
     * @return array 一致性检查结果
     */
    public static function checkPinyinFormatConsistency($pinyinArray, $withTone)
    {
        $result = [
            'consistent' => true,
            'issues' => [],
            'suggested_format' => null
        ];
        
        if (empty($pinyinArray)) {
            return $result;
        }
        
        $firstPinyin = self::normalizePinyinFormat($pinyinArray[0], $withTone);
        
        foreach ($pinyinArray as $index => $pinyin) {
            $normalized = self::normalizePinyinFormat($pinyin, $withTone);
            
            if ($normalized !== $firstPinyin) {
                $result['consistent'] = false;
                $result['issues'][] = [
                    'index' => $index,
                    'original' => $pinyin,
                    'normalized' => $normalized,
                    'expected' => $firstPinyin
                ];
            }
        }
        
        if (!$result['consistent']) {
            $result['suggested_format'] = $firstPinyin;
        }
        
        return $result;
    }

     /**
     * 格式化拼音数组（区分单字和多字空格）
     * @param array $data 原始数据
     * @return array 格式化后的数据
     */
    public static function formatPinyinArray($data) {
        $formatted = [];
        foreach ($data as $char => $pinyin) {
            if (empty($char)) continue;
            $wordLen = mb_strlen($char, 'UTF-8');
            $pinyinArr = is_array($pinyin) ? $pinyin : [$pinyin];
            
            $pinyinArr = array_map(function($item) use ($wordLen) {
                $trimmed = trim($item);
                // 对于单字，完全去除空格
                return self::processSpaces($trimmed, $wordLen === 1);
            }, $pinyinArr);
            
            $formatted[$char] = array_filter($pinyinArr) ?: [$char];
        }
        return $formatted;
    }
    
    /**
     * 紧凑数组序列化（统一实现，用于字典文件）
     * 
     * @param array $array 要序列化的数组
     * @return string 序列化后的PHP数组字符串
     */
    public static function compactArrayExport($array)
    {
        if (empty($array)) return '[]';
        
        $isAssoc = array_keys($array) !== range(0, count($array) - 1);
        $items = [];

        foreach ($array as $key => $value) {
            $keyStr = $isAssoc ? "'" . str_replace("'", "\\'", $key) . "' => " : '';
            
            if (is_array($value)) {
                $valueItems = array_map(function($item) {
                    return "'" . str_replace("'", "\\'", $item) . "'";
                }, $value);
                $valueStr = '[' . implode(',', $valueItems) . ']';
            } else {
                $valueStr = "'" . str_replace("'", "\\'", $value) . "'";
            }
            
            $items[] = $keyStr . $valueStr;
        }
        
        return "[\n    " . implode(",\n    ", $items) . "\n]";
    }
}