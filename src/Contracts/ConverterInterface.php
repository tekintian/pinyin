<?php 
namespace tekintian\pinyin\Contracts;

/**
 * 拼音转换接口
 * 
 * @author tekintian
 * @see https://github.com/tekintian/pinyin
 * @link https://dev.tekin.cn
 * @date 2025-11-01 16:00:00
 * @version 1.0.0
 */
interface ConverterInterface
{
     /**
     * 转换文本为拼音（最终处理）
     *
     * @param string $text 要转换的文本
     * @param string $separator 拼音分隔符，默认为空格
     * @param bool $withTone 是否保留声调，默认为false
     * @param array|string $specialCharParam 特殊字符处理参数，默认为空数组
     * @param array $polyphoneTempMap 临时多音字映射表，默认为空数组
     * @return string 转换后的拼音字符串
     */
    public function convert(
        $text,
        string $separator = ' ',
        bool $withTone = false,
        $specialCharParam = [],
        array $polyphoneTempMap = []
    ): string;

     /**
     * 转换为URL Slug
     * @param string $text 文本
     * @param string $separator 分隔符
     * @return string URL Slug
     */
    public function getUrlSlug($text, $separator = '-');

    /**
     * 动态添加自定义拼音（区分单字/多字空格处理）
     * @param string $char 汉字/词语
     * @param array|string $pinyin 拼音
     * @param bool $withTone 是否带声调
     */
    public function addCustomPinyin($char, $pinyin, $withTone = false);

    /**
     * 删除自定义拼音
     * @param string $char 汉字/词语
     * @param bool $withTone 是否带声调
     */
    public function removeCustomPinyin($char, $withTone = false);

    /**
     * 强制保存所有延迟写入
     */
    public function saveCustomDicts();

    /**
     * 检查和修复自定义字典
     * @param bool $withTone 是否带声调
     * @param bool $autoFix 是否自动修复问题
     * @param bool $verbose 是否显示详细信息
     * @return array 检查结果
     */
    public function checkAndFixCustomDict($withTone = false, $autoFix = false, $verbose = false);

    /**
     * 检查是否需要合并自学习字典
     */
    public function checkMergeNeed();

    /**
     * 执行自学习字典合并
     * @return array 合并结果
     */
    public function executeMerge();

    /**
     * 添加智能多音字规则
     * @param string $char 汉字
     * @param array $rule 规则配置
     */
    public function addPolyphoneRule($char, array $rule);

    /**
     * 批量转换文本数组为拼音
     * @param array $texts 文本数组
     * @param string $separator 拼音分隔符
     * @param bool $withTone 是否保留声调
     * @param array|string $specialCharParam 特殊字符处理参数
     * @return array 转换后的拼音数组
     */
    public function batchConvert(array $texts, string $separator = ' ', bool $withTone = false, $specialCharParam = []): array;

    /**
     * 清理过期缓存
     * @param int $ttl 缓存生存时间（秒）
     */
    public function clearExpiredCache($ttl = 3600);

    /**
     * 性能监控和优化建议
     * @return array 性能分析报告
     */
    public function getPerformanceReport(): array;

    /**
     * 获取拼音统计信息
     * @param bool $retJson 是否返回json格式
     * @return string|array 统计信息
     */
    public function getStatistics($retJson=false);

    /**
     * 搜索拼音匹配的汉字
     * @param string $pinyin 拼音
     * @param bool $exactMatch 是否精确匹配
     * @param int $limit 返回结果数量限制
     * @return array 匹配的汉字数组
     */
    public function searchByPinyin(string $pinyin, bool $exactMatch = true, int $limit = 10): array;
    
}