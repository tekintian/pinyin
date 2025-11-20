<?php

namespace tekintian\pinyin\Utils;

/**
 * 拼音转换常量管理类
 * 统一管理项目中所有常用的常量、正则表达式和配置信息
 */
class PinyinConstants
{
    // ==================== 汉字 Unicode 范围常量 ====================

    /**
     * 基本汉字范围 (CJK Unified Ideographs)
     */
    public const BASIC_CHINESE_RANGE = '\x{4E00}-\x{9FFF}';

    /**
     * 扩展A区汉字 (CJK Extension A)
     */
    public const EXT_A_CHINESE_RANGE = '\x{3400}-\x{4DBF}';

    /**
     * 扩展B区汉字 (CJK Extension B)
     */
    public const EXT_B_CHINESE_RANGE = '\x{20000}-\x{2A6DF}';

    /**
     * 扩展C区汉字 (CJK Extension C)
     */
    public const EXT_C_CHINESE_RANGE = '\x{2A700}-\x{2B73F}';

    /**
     * 扩展D区汉字 (CJK Extension D)
     */
    public const EXT_D_CHINESE_RANGE = '\x{2B740}-\x{2B81F}';

    /**
     * 扩展E区汉字 (CJK Extension E)
     */
    public const EXT_E_CHINESE_RANGE = '\x{2B820}-\x{2CEAF}';

    /**
     * 兼容汉字范围 (CJK Compatibility Ideographs)
     */
    public const COMPATIBLE_CHINESE_RANGE = '\x{F900}-\x{FAFF}\x{2F800}-\x{2FA1F}';

    /**
     * 完整的汉字 Unicode 范围（包含所有扩展区）
     */
    public const FULL_CHINESE_RANGE = '\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}\x{20000}-\x{2A6DF}\x{2A700}-\x{2B73F}\x{2B740}-\x{2B81F}\x{2B820}-\x{2CEAF}\x{F900}-\x{FAFF}\x{2F800}-\x{2FA1F}';

    /**
     * 基本汉字范围（GB2312标准，约20902个常用汉字）
     */
    public const GB2312_CHINESE_RANGE = '\x{4E00}-\x{9FA5}';

     /**
     * 匹配字母数字的范围
     */
    public const ALPHANUMERIC_RANGE = 'a-zA-Z0-9';

    // ==================== 常用正则表达式模式 ====================

    /**
     * 匹配纯汉字的正则模式模板（需要配合 sprintf 使用）
     */
    public const PATTERN_PURE_CHINESE_TEMPLATE = '/[%s]/u';

    /**
     * 匹配非汉字的正则模式模板（需要配合 sprintf 使用）
     */
    public const PATTERN_NON_CHINESE_TEMPLATE = '/[^%s]/u';

    /**
     * 匹配字母数字的正则模式
     */
    public const PATTERN_ALPHANUMERIC = '/[a-zA-Z0-9]/';

    /**
     * 匹配拼音声调的正则模式
     */
    public const PATTERN_PINYIN_TONE = '/[āáǎàēéěèīíǐìōóǒòūúǔùǖǘǚǜ]/u';

    /**
     * 匹配无声调拼音的正则模式
     */
    public const PATTERN_PINYIN_NO_TONE = '/^[a-z]+[1-5]?$/i';

    // ==================== 特殊字符配置 ====================

    /**
     * 默认允许的特殊字符（在删除模式下保留）
     */
    public const DEFAULT_SPECIAL_CHARS_ALLOWED = 'a-zA-Z0-9_\-+.';

    /**
     * 默认特殊字符映射表
     */
    public const DEFAULT_SPECIAL_CHAR_MAP = [
        '，' => ',', '。' => '.', '！' => '!', '？' => '?',
        '；' => ';', '：' => ':', '（' => '(', '）' => ')',
        '【' => '[', '】' => ']', '《' => '<', '》' => '>',
        '「' => '"', '」' => '"', '『' => "'", '』' => "'",
        '、' => ',', '～' => '~', '￥' => '¥', '＄' => '$'
    ];

    // ==================== 拼音相关常量 ====================

    /**
     * 拼音声调数字映射
     */
    public const PINYIN_TONE_MAP = [
        'a' => ['ā' => 'a1', 'á' => 'a2', 'ǎ' => 'a3', 'à' => 'a4'],
        'e' => ['ē' => 'e1', 'é' => 'e2', 'ě' => 'e3', 'è' => 'e4'],
        'i' => ['ī' => 'i1', 'í' => 'i2', 'ǐ' => 'i3', 'ì' => 'i4'],
        'o' => ['ō' => 'o1', 'ó' => 'o2', 'ǒ' => 'o3', 'ò' => 'o4'],
        'u' => ['ū' => 'u1', 'ú' => 'u2', 'ǔ' => 'u3', 'ù' => 'u4'],
        'ü' => ['ǖ' => 'v1', 'ǘ' => 'v2', 'ǚ' => 'v3', 'ǜ' => 'v4']
    ];

    /**
     * 无声调拼音到有声调拼音的默认映射
     */
    public const DEFAULT_TONE_MAPPING = [
        'a' => 'ā', 'e' => 'ē', 'i' => 'ī', 'o' => 'ō', 'u' => 'ū', 'v' => 'ǖ'
    ];

    // ==================== 工具方法 ====================

    /**
     * 获取完整的汉字匹配正则表达式
     *
     * @param string $rangeType 范围类型：full（完整）、basic（基本）、gb2312（国标）
     * @param bool $negative 是否取反（匹配非汉字）
     * @return string 正则表达式
     */
    public static function getChinesePattern($rangeType = 'full', $negative = false)
    {
        $range = self::getChineseRange($rangeType);
        $pattern = $negative ? self::PATTERN_NON_CHINESE_TEMPLATE : self::PATTERN_PURE_CHINESE_TEMPLATE;
        return sprintf($pattern, $range);
    }

    /**
     * 获取指定类型的汉字范围
     *
     * @param string $rangeType 范围类型
     * @return string Unicode范围字符串
     */
    public static function getChineseRange($rangeType = 'full')
    {
        switch ($rangeType) {
            case 'basic':
                return self::BASIC_CHINESE_RANGE;
            case 'gb2312':
                return self::GB2312_CHINESE_RANGE;
            case 'ext_a':
                return self::EXT_A_CHINESE_RANGE;
            case 'ext_b':
                return self::EXT_B_CHINESE_RANGE;
            case 'ext_c':
                return self::EXT_C_CHINESE_RANGE;
            case 'ext_d':
                return self::EXT_D_CHINESE_RANGE;
            case 'ext_e':
                return self::EXT_E_CHINESE_RANGE;
            case 'compatible':
                return self::COMPATIBLE_CHINESE_RANGE;
            case 'full':
            default:
                return self::FULL_CHINESE_RANGE;
        }
    }
    /**
     * 检查字符是否在指定的汉字范围内
     *
     * @param string $char 要检查的字符
     * @param string $rangeType 范围类型
     * @return bool 是否在范围内
     */
    public static function isInChineseRange($char, $rangeType = 'full')
    {
        $pattern = self::getChinesePattern($rangeType, false);
        return preg_match($pattern, $char) === 1;
    }

    /**
     * 获取拼音声调映射
     *
     * @param string $vowel 元音字母
     * @return array|null 声调映射数组
     */
    public static function getPinyinToneMap($vowel = null)
    {
        if ($vowel === null) {
            return self::PINYIN_TONE_MAP;
        }

        return isset(self::PINYIN_TONE_MAP[$vowel]) ? self::PINYIN_TONE_MAP[$vowel] : null;
    }
}
