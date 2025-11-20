<?php

/**
 * 拼音转换工具全局函数
 * 包含全局使用的各种拼音转换的独立函数, 文件操作函数,  各种辅助函数，如调试输出、日志记录、性能监控等。
 *
 * @author tekintian
 * @see https://github.com/tekintian/pinyin
 * @link https://dev.tekin.cn
 * @date 2025-11-01 16:00:00
 * @version 1.0.0
 */

use tekintian\pinyin\Exception\PinyinException;
use tekintian\pinyin\Utils\PinyinConstants;

// 添加PHP 7.2兼容的str_contains函数实现
if (!function_exists('str_contains')) {
    /**
     * 检查字符串是否包含指定子串
     * @param string $haystack 主字符串
     * @param string $needle 子字符串
     * @return bool 是否包含
     */
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || mb_strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('require_file')) {
    /**
     * 引入PHP文件，提供更好的错误处理和类型安全
     *
     * @param string $file 文件路径
     * @param mixed $default 默认返回值
     * @param bool $enableCache 是否启用简单缓存（适用于频繁加载的配置文件）
     * @return mixed
     */
    function require_file(string $file, $default = [], bool $enableCache = true)
    {
        // 静态缓存，用于缓存已加载的文件内容
        static $fileCache = [];
        // 启用缓存且文件已在缓存中
        if ($enableCache && isset($fileCache[$file])) {
            return $fileCache[$file];
        }
        // 基本验证：只检查是否为字符串且不为空
        if (!is_string($file) || empty($file)) {
            trigger_error("Invalid file path: empty or not a string", E_USER_WARNING);
            return $default;
        }
        // 移除严格的目录遍历检查，允许相对路径
        // 对于这个项目，我们需要允许使用../的相对路径
        // 检查文件是否存在
        if (!is_file_exists($file)) {
            return $default;
        }
        try {
            // 使用include替代require可以捕获错误
            // 使用输出缓冲捕获可能的意外输出
            ob_start();
            $result = include $file;
            ob_end_clean(); // 清理可能的输出
            // 缓存结果
            if ($enableCache) {
                $fileCache[$file] = $result;
            }
            return $result;
        } catch (Throwable $e) {
            // 记录错误
            if (function_exists('pinyin_debug')) {
                pinyin_debug("Failed to require file: {$file}, Error: {$e->getMessage()}", 'error');
            }
            return $default;
        }
    }
}
if (!function_exists('pinyin_debug')) {
    /**
     * 调试输出函数
     * @param string $message 调试信息
     * @param string $type 信息类型（info, success, error, warning）
     */
    function pinyin_debug($message, $type = 'info')
    {
        if (getenv('APP_DEBUG') === 'true') {
            $prefix = '';
            switch ($type) {
                case 'success':
                    $prefix = '✅ ';
                    break;
                case 'error':
                    $prefix = '❌ ';
                    break;
                case 'warning':
                    $prefix = '⚠️ ';
                    break;
                default:
                    $prefix = 'ℹ️ ';
            }
            // 如果脚本运行模式为 cli直接echo输出,否则使用 errlog记录日志
            if (PHP_SAPI === 'cli') {
                echo $prefix . $message . "\n";
            } else {
                error_log($prefix . $message);
            }
        }
    }
}
if (!function_exists('is_debug_enabled')) {
    /**
     * 检查是否启用调试模式
     * @return bool
     */
    function is_debug_enabled()
    {
        return getenv('APP_DEBUG') === 'true';
    }
}
if (!function_exists('log_file')) {
    /**
     * 记录调试日志到文件
     * @param string $message 日志信息
     * @param string $logFile 日志文件路径
     */
    function log_file($message, $logFile = null)
    {
        if (is_debug_enabled()) {
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "[{$timestamp}] {$message}\n";
            if ($logFile && is_writable(dirname($logFile))) {
                file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
            } else {
                echo $logMessage;
            }
        }
    }
}
// ==================== 文件操作函数 ====================
if (!function_exists('is_file_exists')) {
    /**
     * 检查文件是否存在
     * @param string $file 文件路径
     * @return bool
     */
    function is_file_exists($file)
    {
        return file_exists($file) && is_file($file);
    }
}
if (!function_exists('read_file_data')) {
    /**
     * 读取文件内容
     * @param string $file 文件路径
     * @return string
     */
    function read_file_data($file)
    {
        if (!is_file_exists($file)) {
            return '';
        }
        return file_get_contents($file);
    }
}
if (!function_exists('write_to_file')) {
    /**
     * 写入文件内容
     * @param string $file 文件路径
     * @param string $content 文件内容
     * @param bool $append 是否追加模式
     * @return bool
     */
    function write_to_file($file, $content, $append = false)
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            create_dir($dir);
        }
        $flags = $append ? FILE_APPEND | LOCK_EX : LOCK_EX;
        return file_put_contents($file, $content, $flags) !== false;
    }
}
if (!function_exists('create_dir')) {
    /**
     * 创建目录
     * @param string $dir 目录路径
     * @param int $mode 目录权限
     * @return bool
     */
    function create_dir($dir, $mode = 0755)
    {
        if (is_dir($dir)) {
            return true;
        }
        $parent = dirname($dir);
        if (!is_dir($parent)) {
            create_dir($parent, $mode);
        }
        return mkdir($dir, $mode, true);
    }
}
if (!function_exists('copy_file')) {
    /**
     * 复制单个文件
     *
     * @param string $sourcePath 源文件路径
     * @param string $destinationPath 目标文件路径
     * @return bool 复制结果
     * @throws PinyinException 复制失败时抛出异常
     */
    function copy_file(string $sourcePath, string $destinationPath): bool
    {
        // 确保源文件存在
        if (!is_file($sourcePath)) {
            throw new PinyinException("Source file does not exist: {$sourcePath}", PinyinException::ERROR_FILE_NOT_FOUND);
        }
        // 确保目标目录存在
        $targetDir = dirname($destinationPath);
        if (!is_dir($targetDir)) {
            create_dir($targetDir);
        }
        // 复制文件
        if (!@copy($sourcePath, $destinationPath)) {
            throw new PinyinException("Failed to copy file: {$sourcePath} to {$destinationPath}", PinyinException::ERROR_FILE_NOT_FOUND);
        }
        return true;
    }
}
if (!function_exists('get_file_mtime')) {
    /**
     * 获取文件修改时间
     *
     * @param string $file 文件路径
     * @return int|null 文件修改时间戳，如果文件不存在则返回null
     */
    function get_file_mtime($file)
    {
        if (!is_file($file)) {
            return null;
        }
        return filemtime($file);
    }
}
if (!function_exists('copy_dict')) {
    /**
     * 递归复制项目根目录下的data文件夹到用户指定目录
     * 默认拷贝当前项目data目录下的所有文件到用户指定的目录
     *
     * @param string $dst 目标目录路径
     * @return bool 复制结果
     * @throws PinyinException 复制失败时抛出异常
     */
    function copy_dict(string $dst): bool
    {
        // 获取项目根目录下的data目录路径
        $dataDir = dirname(dirname(__DIR__)) . '/data/';
        // 安全检查：确保源路径确实是data目录并可以访问
        $realDataDir = realpath($dataDir);
        if ($realDataDir === false) {
            throw new PinyinException("Failed to access data directory: {$dataDir}", PinyinException::ERROR_FILE_NOT_FOUND);
        }
        // 验证源目录是否存在且是目录
        if (!is_dir($dataDir)) {
            throw new PinyinException("Source data directory does not exist or is not a directory: {$dataDir}", PinyinException::ERROR_FILE_NOT_FOUND);
        }
        // 确保目标目录存在
        if (!is_dir($dst)) {
            create_dir($dst);
        }
        // 获取源目录中的所有文件和子目录
        $files = scandir($dataDir);
        if ($files === false) {
            throw new PinyinException("Failed to read directory contents: {$dataDir}", PinyinException::ERROR_FILE_NOT_FOUND);
        }
        // 递归复制文件和目录
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $sourcePath = $dataDir . '/' . $file;
            $destinationPath = $dst . '/' . $file;
            if (is_dir($sourcePath)) {
                // 如果是目录，递归创建并复制
                if (!is_dir($destinationPath)) {
                    create_dir($destinationPath);
                }
                // 使用辅助函数递归复制子目录内容
                if (!copy_directory($sourcePath, $destinationPath)) {
                    return false;
                }
            } else {
                // 如果是文件，直接复制
                if (!@copy($sourcePath, $destinationPath)) {
                    throw new PinyinException("Failed to copy file: {$sourcePath} to {$destinationPath}", PinyinException::ERROR_FILE_NOT_FOUND);
                }
            }
        }
        return true;
    }
}
if (!function_exists('copy_directory')) {
    /**
     * 辅助方法：递归复制目录内容
     *
     * @param string $src 源目录路径
     * @param string $dst 目标目录路径
     * @return bool 复制结果
     * @throws PinyinException 复制失败时抛出异常
     */
    function copy_directory(string $src, string $dst): bool
    {
        // 获取源目录中的所有文件和子目录
        $files = scandir($src);
        if ($files === false) {
            throw new PinyinException("Failed to read directory contents: {$src}", PinyinException::ERROR_FILE_NOT_FOUND);
        }
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $sourcePath = $src . '/' . $file;
            $destinationPath = $dst . '/' . $file;
            if (is_dir($sourcePath)) {
                // 如果是目录，递归创建并复制
                if (!is_dir($destinationPath)) {
                    create_dir($destinationPath);
                }
                if (!copy_directory($sourcePath, $destinationPath)) {
                    return false;
                }
            } else {
                // 如果是文件，直接复制
                if (!@copy($sourcePath, $destinationPath)) {
                    throw new PinyinException("Failed to copy file: {$sourcePath} to {$destinationPath}", PinyinException::ERROR_FILE_NOT_FOUND);
                }
            }
        }
        return true;
    }
}
if (!function_exists('delete_file')) {
    /**
     * 删除文件
     *
     * @param string $file 文件路径
     * @return bool 删除结果
     */
    function delete_file(string $file): bool
    {
        if (!is_file($file)) {
            return true;
        }
        return @unlink($file);
    }
}
if (!function_exists('read_json_file')) {
    /**
     * 读取JSON文件并解析为数组
     *
     * @param string $file JSON文件路径
     * @return array 解析后的数组
     * @throws PinyinException 文件不存在、读取失败或解析失败时抛出异常
     */
    function read_json_file(string $file): array
    {
        $content = read_file_data($file);
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new PinyinException(
                "Failed to parse JSON file: {$file}, error: " . json_last_error_msg(),
                PinyinException::ERROR_INVALID_INPUT
            );
        }
        return $data;
    }
}
if (!function_exists('write_json_file')) {
    /**
     * 将数组数据写入JSON文件
     *
     * @param string $file JSON文件路径
     * @param array $data 要写入的数据
     * @param int $options JSON编码选项
     * @return bool 写入结果
     * @throws PinyinException 写入失败时抛出异常
     */
    function write_json_file(string $file, array $data, int $options = JSON_UNESCAPED_UNICODE): bool
    {
        $content = json_encode($data, $options);
        if ($content === false) {
            throw new PinyinException(
                "Failed to encode data to JSON, error: " . json_last_error_msg(),
                PinyinException::ERROR_INVALID_INPUT
            );
        }
        return write_to_file($file, $content);
    }
}
// ==================== 拼音处理函数 ====================
if (!function_exists('convert_to_number_tone')) {
    /**
     * 将拼音转换为数字声调格式（如：zhōng → zhong1）
     *
     * @param string $pinyin 带声调的拼音
     * @return string 数字声调格式的拼音
     */
    function convert_to_number_tone($pinyin)
    {
        $toneMap = [
            'ā' => 'a1', 'á' => 'a2', 'ǎ' => 'a3', 'à' => 'a4',
            'ē' => 'e1', 'é' => 'e2', 'ě' => 'e3', 'è' => 'e4',
            'ī' => 'i1', 'í' => 'i2', 'ǐ' => 'i3', 'ì' => 'i4',
            'ō' => 'o1', 'ó' => 'o2', 'ǒ' => 'o3', 'ò' => 'o4',
            'ū' => 'u1', 'ú' => 'u2', 'ǔ' => 'u3', 'ù' => 'u4',
            'ǖ' => 'v1', 'ǘ' => 'v2', 'ǚ' => 'v3', 'ǜ' => 'v4',
            'ü' => 'v',
        ];
        return strtr($pinyin, $toneMap);
    }
}
if (!function_exists('convert_from_number_tone')) {
    /**
     * 将数字声调格式转换为带声调符号的拼音（支持单/多元音组合，兼容v代替ü，支持轻声）
     * 例：zhong1→zhōng、ce4→cè、nv3→nǚ、iao4→iào、ma5→ma
     *
     * @param string $pinyin 数字声调格式的拼音
     * @return string 带声调符号的拼音
     */
    function convert_from_number_tone($pinyin)
    {
        // 空值或无数字声调，直接返回
        if (empty($pinyin) || !preg_match('/[1-5]/', $pinyin)) {
            return $pinyin;
        }
        // 扩展映射表：覆盖单元音、多元音组合、v/ü、轻声（按"长匹配优先"原则排序）
        $toneMap = [
            // 多元音组合（长度3）
            'iao1' => 'iāo', 'iao2' => 'iáo', 'iao3' => 'iǎo', 'iao4' => 'iào', 'iao5' => 'iao',
            'iou1' => 'iōu', 'iou2' => 'ióu', 'iou3' => 'iǒu', 'iou4' => 'iòu', 'iou5' => 'iou',
            'uai1' => 'uāi', 'uai2' => 'uái', 'uai3' => 'uǎi', 'uai4' => 'uài', 'uai5' => 'uai',
            'uei1' => 'uēi', 'uei2' => 'uéi', 'uei3' => 'uěi', 'uei4' => 'uèi', 'uei5' => 'uei',
            // 多元音组合（长度2）
            'ai1' => 'āi', 'ai2' => 'ái', 'ai3' => 'ǎi', 'ai4' => 'ài', 'ai5' => 'ai',
            'ei1' => 'ēi', 'ei2' => 'éi', 'ei3' => 'ěi', 'ei4' => 'èi', 'ei5' => 'ei',
            'ui1' => 'uī',  'ui2' => 'uí',  'ui3' => 'uǐ',  'ui4' => 'uì',  'ui5' => 'ui',  // 对应uei
            'ao1' => 'āo', 'ao2' => 'áo', 'ao3' => 'ǎo', 'ao4' => 'ào', 'ao5' => 'ao',
            'ou1' => 'ōu', 'ou2' => 'óu', 'ou3' => 'ǒu', 'ou4' => 'òu', 'ou5' => 'ou',
            'ia1' => 'iā', 'ia2' => 'iá', 'ia3' => 'iǎ', 'ia4' => 'ià', 'ia5' => 'ia',
            'ie1' => 'iē', 'ie2' => 'ié', 'ie3' => 'iě', 'ie4' => 'iè', 'ie5' => 'ie',
            'ua1' => 'uā', 'ua2' => 'uá', 'ua3' => 'uǎ', 'ua4' => 'uà', 'ua5' => 'ua',
            'uo1' => 'uō', 'uo2' => 'uó', 'uo3' => 'uǒ', 'uo4' => 'uò', 'uo5' => 'uo',
        'üe1' => 'üē', 'üe2' => 'üé', 'üe3' => 'üě', 'üe4' => 'üè', 'üe5' => 'üe',
        've1' => 'üē',  've2' => 'üé',  've3' => 'üě',  've4' => 'üè',  've5' => 'üe',  // v代替ü
            'iu1' => 'iū',  'iu2' => 'iú',  'iu3' => 'iǔ',  'iu4' => 'iù',  'iu5' => 'iu',  // 对应iou
            // 单元音（长度2）
            'a1' => 'ā', 'a2' => 'á', 'a3' => 'ǎ', 'a4' => 'à', 'a5' => 'a',
            'o1' => 'ō', 'o2' => 'ó', 'o3' => 'ǒ', 'o4' => 'ò', 'o5' => 'o',
            'e1' => 'ē', 'e2' => 'é', 'e3' => 'ě', 'e4' => 'è', 'e5' => 'e',
            'i1' => 'ī', 'i2' => 'í', 'i3' => 'ǐ', 'i4' => 'ì', 'i5' => 'i',
            'u1' => 'ū', 'u2' => 'ú', 'u3' => 'ǔ', 'u4' => 'ù', 'u5' => 'u',
            'ü1' => 'ǖ', 'ü2' => 'ǘ', 'ü3' => 'ǚ', 'ü4' => 'ǜ', 'ü5' => 'ü',
            'v1' => 'ǖ',  'v2' => 'ǘ',  'v3' => 'ǚ',  'v4' => 'ǜ',  'v5' => 'ü',   // v代替ü
        ];
        // 按键的长度从长到短排序（确保长组合优先匹配，如iao4优先于a4）
        uksort($toneMap, function ($a, $b) {
            $lenA = strlen($a);
            $lenB = strlen($b);
            return $lenB - $lenA; // 长键在前
        });
        // 执行替换（strtr会按最长匹配优先原则替换）
        return strtr($pinyin, $toneMap);
    }
}
if (!function_exists('format_pinyin_array')) {
    /**
     * 格式化拼音数组（区分单字和多字空格）
     * @param array $data 原始数据
     * @return array 格式化后的数据
     */
    function format_pinyin_array($data)
    {
        $formatted = [];
        foreach ($data as $char => $pinyin) {
            if (empty($char)) {
                continue;
            }
            $wordLen = mb_strlen($char, 'UTF-8');
            $pinyinArr = is_array($pinyin) ? $pinyin : [$pinyin];
            $pinyinArr = array_map(function ($item) use ($wordLen) {
                $trimmed = trim($item);
                // 对于单字，完全去除空格
                return pinyin_process_spaces($trimmed, $wordLen === 1);
            }, $pinyinArr);
            $formatted[$char] = array_filter($pinyinArr) ?: [$char];
        }
        return $formatted;
    }
}
if (!function_exists('remove_tone')) {
    /**
     * 移除拼音中的声调
     * @param string|array $pinyin 带声调的拼音
     * @return string|array 无声调的拼音
     */
    function remove_tone($pinyin)
    {
        if (is_array($pinyin)) {
            return array_map('remove_tone', $pinyin);
        }
        // 声调映射表
        $toneMap = [
            'ā' => 'a', 'á' => 'a', 'ǎ' => 'a', 'à' => 'a',
            'ō' => 'o', 'ó' => 'o', 'ǒ' => 'o', 'ò' => 'o',
            'ē' => 'e', 'é' => 'e', 'ě' => 'e', 'è' => 'e',
            'ī' => 'i', 'í' => 'i', 'ǐ' => 'i', 'ì' => 'i',
            'ū' => 'u', 'ú' => 'u', 'ǔ' => 'u', 'ù' => 'u',
            'ǖ' => 'v', 'ǘ' => 'v', 'ǚ' => 'v', 'ǜ' => 'v',
            'ü' => 'v',
        ];
        return strtr($pinyin, $toneMap);
    }
}
if (!function_exists('is_valid_pinyin')) {
    /**
     * 验证拼音格式是否有效
     * @param string $pinyin 拼音字符串
     * @param bool $strict 是否严格模式
     * @return bool
     */
    function is_valid_pinyin($pinyin, $strict = false)
    {
        if (mb_strlen($pinyin) === 0) {
            return false;
        }
        // 基本拼音格式验证
        $basicValid = preg_match('/^[a-zāáǎàōóǒòēéěèīíǐìūúǔùüǖǘǚǜ]+$/i', $pinyin);
        if (!$strict || !$basicValid) {
            return $basicValid;
        }
        // 严格验证：检查声调组合规则
        return validate_pinyin_tone_rules($pinyin);
    }
}
if (!function_exists('pinyin_batch_process')) {
/**
 * 批量处理拼音数组
 *
 * @param array $pinyinArray 拼音数组
 * @param callable $processor 处理函数
 * @return array 处理后的数组
 */
    function pinyin_batch_process($pinyinArray, $processor)
    {
        return array_map($processor, $pinyinArray);
    }
}
if (!function_exists('pinyin_sort')) {
    /**
     * 拼音排序（按字母顺序）
     *
     * @param array $pinyinArray 拼音数组
     * @param bool $ignoreTone 是否忽略声调
     * @return array 排序后的数组
     */
    function pinyin_sort($pinyinArray, $ignoreTone = true)
    {
        $processedArray = $ignoreTone
        ? pinyin_batch_process($pinyinArray, 'remove_tone')
        : $pinyinArray;
        array_multisort($processedArray, $pinyinArray);
        return $pinyinArray;
    }
}
if (!function_exists('check_pinyin_format_consistency')) {
    /**
     * 检查拼音格式一致性
     * @param array $pinyinArray 拼音数组
     * @param bool $withTone 是否带声调
     * @return array 一致性检查结果
     */
    function check_pinyin_format_consistency($pinyinArray, $withTone)
    {
        $result = [
            'consistent' => true,
            'suggestions' => [],
        ];
        $firstPinyin = $pinyinArray[0];
        $firstHasSpace = str_contains($firstPinyin, ' ');
        foreach ($pinyinArray as $pinyin) {
            $hasSpace = str_contains($pinyin, ' ');
            if ($hasSpace !== $firstHasSpace) {
                $result['consistent'] = false;
                $result['suggestions'] = array_map(function ($p) {
                    return trim($p); // 简化处理，直接返回清理后的拼音
                }, $pinyinArray);
                break;
            }
        }
        return $result;
    }
}
if (!function_exists('validate_pinyin_tone_rules')) {
    /**
     * 验证拼音声调组合规则
     *
     * @param string $pinyin 待验证的拼音
     * @return bool 是否符合声调规则
     */
    function validate_pinyin_tone_rules($pinyin)
    {
        // 检查是否包含多个声调符号
        $toneChars = ['ā', 'á', 'ǎ', 'à', 'ē', 'é', 'ě', 'è', 'ī', 'í', 'ǐ', 'ì',
            'ō', 'ó', 'ǒ', 'ò', 'ū', 'ú', 'ǔ', 'ù', 'ǖ', 'ǘ', 'ǚ', 'ǜ'];
        $toneCount = 0;
        foreach ($toneChars as $toneChar) {
            if (mb_substr_count($pinyin, $toneChar) > 0) {
                $toneCount++;
            }
        }
        // 一个拼音最多只能有一个声调符号
        return $toneCount <= 1;
    }
}
if (!function_exists('pinyin_validate')) {
    /**
     * 验证单个拼音的有效性
     * @param string $pinyin 拼音
     * @param bool $withTone 是否带声调
     * @return array 问题列表
     */
    function pinyin_validate($pinyin, $withTone)
    {
        $issues = [];
        // 检查是否为空
        if (empty(trim($pinyin))) {
            $issues[] = '拼音为空';
            return $issues;
        }
        // 使用 is_valid_pinyin 进行更严格的验证
        if (!is_valid_pinyin($pinyin, true)) {
            $issues[] = '拼音格式无效或不符合声调规则';
        }
        // 检查声调一致性
        if ($withTone) {
            // 应该包含声调符号
            if (!preg_match('/[āáǎàēéěèīíǐìōóǒòūúǔùǖǘǚǜü]/u', $pinyin)) {
                $issues[] = '带声调模式下缺少声调符号';
            }
        } else {
            // 不应该包含声调符号
            if (preg_match('/[āáǎàēéěèīíǐìōóǒòūúǔùǖǘǚǜü]/u', $pinyin)) {
                $issues[] = '无声调模式下包含声调符号';
            }
        }
        // 检查空格使用（单字不应该有空格，多字应该有空格）
        $wordLen = mb_strlen($pinyin, 'UTF-8');
        if ($wordLen === 1 && str_contains($pinyin, ' ')) {
            $issues[] = '单字拼音不应包含空格';
        }
        return $issues;
    }
}
if (!function_exists('pinyin_format_array')) {
    /**
     * 格式化拼音数组
     * @param array $data 拼音数据数组
     * @return array 格式化后的数组
     */
    function pinyin_format_array($data)
    {
        if (!is_array($data)) {
            return [];
        }
        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = array_map('trim', $value);
            } else {
                $result[$key] = trim($value);
            }
        }
        return $result;
    }
}
if (!function_exists('pinyin_export_polyphone_rules')) {
    /**
     * 专门处理多音字规则数组的格式化导出
     * @param array $array 多音字规则数组
     * @param int $indentLevel 缩进级别
     * @return string 格式化后的PHP数组代码
     */
    function pinyin_export_polyphone_rules($array, $indentLevel = 0)
    {
        // 安全检查
        if (!is_array($array)) {
            return '[]';
        }
        if (empty($array)) {
            return '[]';
        }
        $indent = str_repeat('    ', $indentLevel);
        $nextIndent = str_repeat('    ', $indentLevel + 1);
        $result = "[
";
        foreach ($array as $key => $value) {
            $result .= $nextIndent . "'$key' => [\n";
            // 安全检查：确保$value是数组
            if (is_array($value)) {
                foreach ($value as $rule) {
                    // 安全检查：确保$rule是数组
                    if (is_array($rule)) {
                        $ruleItems = [];
                        foreach ($rule as $k => $v) {
                            // 安全处理值
                            $vStr = is_string($v) ? str_replace("'", "\\'", $v) : (string)$v;
                            $ruleItems[] = "'$k' => '$vStr'";
                        }
                        $ruleStr = implode(', ', $ruleItems);
                        $result .= $nextIndent . "    [$ruleStr],\n";
                    }
                }
            }
            $result = rtrim($result, ",\n") . "\n" . $nextIndent . "],\n";
        }
        return rtrim($result, ",\n") . "\n" . $indent . "]";
    }
}
if (!function_exists('pinyin_compact_array_export')) {
    function pinyin_compact_array_export($array, $indentLevel = 0)
    {
        // 首先检查输入是否为数组
        if (!is_array($array)) {
            return '[]';
        }
        if (empty($array)) {
            return '[]';
        }
        $isAssoc = array_keys($array) !== range(0, count($array) - 1);
        $indent = str_repeat('    ', $indentLevel);

        // 处理关联数组 - 统一为自学习字典格式
        if ($isAssoc) {
            $result = $indent . "[
    ";
            foreach ($array as $key => $value) {
                $keyStr = "'" . str_replace("'", "\\'", $key) . "' => ";

                // 对于值的处理保持一致
                if (is_array($value)) {
                    // 检查是否为简单字符串数组（更宽松的验证，允许更多字符）
                    $isSimpleStringArray = true;
                    foreach ($value as $v) {
                        if (!is_string($v)) {
                            $isSimpleStringArray = false;
                            break;
                        }
                        // 允许数字、字母、声调、空格和下划线等常见字符
                        if (!preg_match('/^[a-z0-9āáǎàēéěèīíǐìōóǒòūúǔùǖǘǚǜü\s_]+$/ui', $v)) {
                            $isSimpleStringArray = false;
                            break;
                        }
                    }

                    // 对于简单字符串数组，始终使用紧凑格式（单行）
                    if ($isSimpleStringArray) {
                        $values = array_map(function ($v) {
                            return "'" . str_replace("'", "\\'", $v) . "'";
                        }, $value);
                        $valueStr = "[" . implode(', ', $values) . "]";
                    } else {
                        // 对于复杂数组，递归处理
                        $valueStr = pinyin_compact_array_export($value, $indentLevel + 1);
                    }
                } else {
                    // 安全处理非字符串值
                    $valueStr = is_string($value) ? "'" . str_replace("'", "\\'", $value) . "'" : var_export($value, true);
                }

                // 保持一致的缩进格式
                $result .= $indent . "    " . $keyStr . $valueStr . ",\n";
            }
            return rtrim($result, ",\n") . "\n" . $indent . "]";
        } else {
            // 处理索引数组
            $isSimpleStringArray = true;
            foreach ($array as $value) {
                // 只需要检查是否都是字符串，不再严格限制内容
                if (!is_string($value)) {
                    $isSimpleStringArray = false;
                    break;
                }
            }

            if ($isSimpleStringArray) {
                // 简单字符串数组的紧凑格式
                $values = array_map(function ($v) {
                    return "'" . str_replace("'", "\\'", $v) . "'";
                }, $array);
                return "[" . implode(', ', $values) . "]";
            } else {
                // 普通索引数组
                $nextIndent = str_repeat('    ', $indentLevel + 1);
                $result = $indent . "[
    ";
                foreach ($array as $value) {
                    if (is_array($value)) {
                        $valueStr = pinyin_compact_array_export($value, $indentLevel + 1);
                    } else {
                        // 安全处理非字符串值
                        $valueStr = is_string($value) ? "'" . str_replace("'", "\\'", $value) . "'" : var_export($value, true);
                    }
                    $result .= $nextIndent . $valueStr . ",\n";
                }
                return rtrim($result, ",\n") . "\n" . $indent . "]";
            }
        }
    }
}
if (!function_exists('pinyin_export_not_found_chars')) {
    /**
     * 专门导出 not_found_chars.php 格式的数组（每个字单独一行）
     * @param array $chars 汉字数组
     * @return string 格式化后的PHP数组代码
     */
    function pinyin_export_not_found_chars($chars)
    {
        // 安全检查
        if (!is_array($chars)) {
            return '[]';
        }
        if (empty($chars)) {
            return '[]';
        }
        $result = "[";
        $first = true;
        foreach ($chars as $char) {
            if ($first) {
                $result .= "\n    ";
                $first = false;
            } else {
                $result .= ",\n    ";
            }
            $result .= "'" . str_replace("'", "\\'", $char) . "'";
        }
        $result .= "\n];";
        return $result;
    }
}
if (!function_exists('pinyin_similarity')) {
    /**
     * 拼音相似度比较
     *
     * @param string $pinyin1 第一个拼音
     * @param string $pinyin2 第二个拼音
     * @param bool $ignoreTone 是否忽略声调
     * @return float 相似度（0-1之间）
     */
    function pinyin_similarity($pinyin1, $pinyin2, $ignoreTone = true)
    {
        if ($ignoreTone) {
            $pinyin1 = remove_tone($pinyin1);
            $pinyin2 = remove_tone($pinyin2);
        }
        $len1 = mb_strlen($pinyin1);
        $len2 = mb_strlen($pinyin2);
        if ($len1 === 0 || $len2 === 0) {
            return 0.0;
        }
        // 简单的编辑距离相似度计算
        $maxLen = max($len1, $len2);
        $distance = levenshtein($pinyin1, $pinyin2);
        return 1 - ($distance / $maxLen);
    }
}
if (!function_exists('pinyin_normalize_format')) {
    /**
     * 标准化拼音格式
     * @param string $pinyin 拼音
     * @param bool $withTone 是否带声调
     * @return string 标准化后的拼音
     */
    function pinyin_normalize_format($pinyin, $withTone)
    {
        $pinyin = trim($pinyin);
        // 移除非法字符
        $pinyin = preg_replace('/[^a-zāáǎàōóǒòēéěèīíǐìūúǔùüǖǘǚǜ\s]/iu', '', $pinyin);
        // 处理声调
        if (!$withTone) {
            $pinyin = remove_tone($pinyin);
        }
        // 标准化空格（多个空格合并为一个）
        $pinyin = preg_replace('/\s+/', ' ', $pinyin);
        // 统一大小写（小写）
        $pinyin = mb_strtolower($pinyin);
        return $pinyin;
    }
}
if (!function_exists('pinyin_parse_options')) {
    /**
     * 解析拼音选项
     * @param mixed $pinyin 拼音数据
     * @return array 拼音选项数组
     */
    function pinyin_parse_options($pinyin)
    {
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
}
if (!function_exists('get_first_pinyin')) {
    /**
     * 获取拼音数组中的第一个有效拼音
     *
     * @param array $pinyinArray 拼音数组
     * @return string 第一个拼音
     */
    function get_first_pinyin($pinyinArray)
    {
        foreach ($pinyinArray as $pinyin) {
            if (!empty(trim($pinyin))) {
                return $pinyin;
            }
        }
        return '';
    }
}
if (!function_exists('chinese_number_to_arabic')) {
    /**
     * 将字符串中的中文数字（含大小写、单位、特殊数字）转换为阿拉伯数字
     * 支持场景：
     * - 连续纯数字（如“一二三”→123，“一伍壹九”→1519）
     * - 带单位数字（如“十”→10，“壹萬”→10000，“一百二十”→120）
     * - 特殊数字（如“廿”→20，“卅”→30，“卌”→40）
     * - 自动处理数字与其他字符的分隔（如“zhong十guo”→“zhong 10 guo”）
     *
     * @param string $str 待转换的字符串（支持含中文、英文、标点等混合内容）
     * @return string 转换后的字符串
     */
    function chinese_number_to_arabic($str)
    {
        // 中文数字映射表：包含小写、大写、单位及特殊数字
        $numMap = [
            // 小写数字及单位
            '零' => 0, '一' => 1, '二' => 2, '三' => 3, '四' => 4,
            '五' => 5, '六' => 6, '七' => 7, '八' => 8, '九' => 9,
            '十' => 10, '百' => 100, '千' => 1000, '万' => 10000, '亿' => 100000000,
            // 大写数字及单位（财务常用）
            '壹' => 1, '贰' => 2, '叁' => 3, '肆' => 4, '伍' => 5,
            '陆' => 6, '柒' => 7, '捌' => 8, '玖' => 9,
            '拾' => 10, '佰' => 100, '仟' => 1000, '萬' => 10000, '億' => 100000000,
            // 特殊数字（廿=20，卅=30，卌=40，常见于日期或计数）
            '廿' => 20, '卅' => 30, '卌' => 40,
        ];
        // 正则匹配规则（按优先级排序，避免短匹配干扰长匹配）
        // 1. 带单位的组合数字（如“一百二十”“壹萬”）
        // 2. 连续2个及以上纯数字（如“一二三”“一伍壹九”）
        // 3. 特殊数字（廿、卅、卌）
        // 4. 单个单位（如“十”“萬”）
        // 5. 单个数字（如“一”“伍”）
        $pattern = '/
        (?:[零壹贰叁肆伍陆柒捌玖一二三四五六七八九]+[拾佰仟萬億十百千万亿]+)+[零壹贰叁肆伍陆柒捌玖一二三四五六七八九]*  # 带单位组合数字
        |[零壹贰叁肆伍陆柒捌玖一二三四五六七八九]{2,}  # 连续纯数字（长度≥2）
        |廿|卅|卌  # 特殊数字
        |[拾佰仟萬億十百千万亿]  # 单个单位
        |[零壹贰叁肆伍陆柒捌玖一二三四五六七八九]  # 单个数字
        /xu'; // x修饰符：允许正则换行注释；u修饰符：支持UTF-8中文
        // 步骤1：给所有匹配到的数字单元前后添加临时空格，避免与其他字符粘连
        // 例如“zhong十guo”→“zhong 十 guo”，为后续替换后的分隔做准备
        $str = preg_replace($pattern, ' $0 ', $str);
        // 步骤2：使用回调函数替换中文数字为阿拉伯数字
        $result = preg_replace_callback($pattern, function ($matches) use ($numMap) {
            $chineseNum = $matches[0]; // 当前匹配的中文数字（如“十”“壹萬”）
            $arabicNum = ''; // 转换后的阿拉伯数字
            // 1. 处理带单位的组合数字（如“十”“一百二十”“壹萬”）
            if (preg_match('/[拾佰仟萬億十百千万亿]/u', $chineseNum)) {
                $total = 0; // 总结果
                $current = 0; // 临时累加值（用于计算当前段位）
                // 遍历每个字符解析
                for ($i = 0; $i < mb_strlen($chineseNum, 'UTF-8'); $i++) {
                    $char = mb_substr($chineseNum, $i, 1, 'UTF-8'); // 单个中文数字/单位
                    $val = $numMap[$char]; // 转换为对应数值
                    if ($val >= 10) {
                        // 遇到单位（十/百/千/万/亿等，值≥10）
                        $current = $current == 0 ? 1 : $current; // 处理单独的“十”→1×10
                        $current *= $val; // 临时值乘以单位（如“二十”→2×10=20）
                        // 万和亿是大单位，直接计入总数（段位结算）
                        if ($val >= 10000) {
                            $total += $current;
                            $current = 0;
                        }
                    } else {
                        // 遇到数字（零-九/壹-玖，值<10）
                        $current += $val; // 累加临时值（如“一百二”→1×100 + 2=102）
                    }
                }
                $arabicNum = (string) ($total + $current); // 总结果 = 已结算段位 + 剩余临时值
            } elseif (mb_strlen($chineseNum, 'UTF-8') >= 2) {
                // 2. 处理连续纯数字（长度≥2，如“一二三”→123，“一伍壹九”→1519）
                $arabicStr = '';
                // 逐个字符转换并拼接（如“一伍壹九”→“1”+“5”+“1”+“9”=“1519”）
                for ($i = 0; $i < mb_strlen($chineseNum, 'UTF-8'); $i++) {
                    $char = mb_substr($chineseNum, $i, 1, 'UTF-8');
                    $arabicStr .= $numMap[$char];
                }
                $arabicNum = $arabicStr;
            } else {
                // 3. 处理特殊数字（廿、卅、卌）和单个数字（如“一”→1，“伍”→5）
                $arabicNum = (string) $numMap[$chineseNum];
            }
            return $arabicNum;
        }, $str);
        // 步骤3：清理多余空格（合并连续空格），并去除首尾空格
        $result = preg_replace('/\s+/', ' ', $result); // 连续空格→单个空格
        return trim($result); // 去除首尾空格
    }
}
if (!function_exists('pinyin_process_spaces')) {
    /**
     * 处理拼音中的空格
     *
     * @param string $pinyin 原始拼音
     * @param bool $removeAll 是否移除所有空格
     * @return string 处理后的拼音
     */
    function pinyin_process_spaces($pinyin, $removeAll = false)
    {
        if ($removeAll) {
            return str_replace(' ', '', $pinyin);
        }
        // 保留单词间的单个空格，移除多余空格
        $pinyin = preg_replace('/\s+/', ' ', $pinyin);
        return trim($pinyin);
    }
}
if (!function_exists('clean_pinyin')) {
    /**
     * 清理拼音字符串
     *
     * @param string $pinyin 原始拼音
     * @param bool $removeAllSpaces 是否移除所有空格
     * @return string 清理后的拼音
     */
    function clean_pinyin($pinyin, $removeAllSpaces = false)
    {
        if ($removeAllSpaces) {
            return pinyin_process_spaces($pinyin, true);
        }
        return pinyin_process_spaces($pinyin, false);
    }
}
if (!function_exists('is_homophone')) {
    /**
     * 检查两个拼音是否同音（忽略声调差异）
     *
     * @param string $pinyin1 第一个拼音
     * @param string $pinyin2 第二个拼音
     * @return bool 是否同音
     */
    function is_homophone($pinyin1, $pinyin2)
    {
        return remove_tone($pinyin1) === remove_tone($pinyin2);
    }
}
if (!function_exists('pinyin_get_initial')) {
    /**
     * 获取拼音的首字母（用于拼音缩写）
     *
     * @param string $pinyin 拼音字符串
     * @return string 首字母
     */
    function pinyin_get_initial($pinyin)
    {
        if (empty($pinyin)) {
            return '';
        }
        // 移除声调并取第一个字符
        $noTone = remove_tone($pinyin);
        return mb_substr($noTone, 0, 1);
    }
}
if (!function_exists('filter_pure_chinese')) {
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
     * @param bool $preserveOrder 是否保持原顺序（true：提取汉字，false：删除非汉字）
     * @return string 过滤后仅包含纯汉字的字符串
     */
    function filter_pure_chinese($text, $preserveOrder = true)
    {
        // 使用 PinyinConstants 统一管理汉字范围
        if ($preserveOrder) {
            // 提取所有匹配的纯汉字，拼接为字符串（保持原顺序）
            $pattern = PinyinConstants::getChinesePattern('full');
            preg_match_all($pattern, $text, $matches);
            return implode('', $matches[0]);
        } else {
            // 替换非纯汉字为空白
            $pattern = PinyinConstants::getChinesePattern('full', true);
            return preg_replace($pattern, '', $text);
        }
    }
}
if (!function_exists('must_string')) {
    /**
     * 确保输入文本为字符串类型
     *
     * 如果无法转换为字符串，则返回空字符串
     * 如果输入为数组，则转换为字符串并用空格分隔
     *
     * @param mixed $text 输入文本
     * @param string $default 默认值（可选）
     * @return string 转换后的字符串
     */
    function must_string($text, string $default = '')
    {
        if (is_string($text)) {
            return trim($text);
        }
        if (is_array($text)) {
            return implode(' ', $text);
        }
        // 检查是否为无法转换为有效字符串的类型
        if (
            is_resource($text) ||
            (is_object($text) && !method_exists($text, '__toString'))
        ) {
            pinyin_debug('Input text must be a string or convertible to string', 'error');
            return $default;
        }
        // 安全转换（此时转换结果是可预期的）
        return (string) $text;
    }
}
if (!function_exists('split_pinyin_tone')) {
    /**
     * 将带声调的拼音字符拆分为基础字母和声调
     *
     * @param string $char 带声调的单个拼音字符（如 'ō' 'cè' 中的 'è'）
     * @return array 包含 'letter'（基础字母）和 'tone'（声调1-4，0为轻声）的数组
     */
    function split_pinyin_tone($char)
    {
        // 处理空字符或非拼音字符
        if (empty($char)) {
            return ['letter' => '', 'tone' => 0];
        }
        // 带声调的拼音字符映射表（键：带声调字符，值：[基础字母, 声调]）
        // 覆盖所有常见带声调的拼音元音（a/o/e/i/u/ü）
        $toneMap = [
            // a 系列
            'ā' => ['a', 1], 'á' => ['a', 2], 'ǎ' => ['a', 3], 'à' => ['a', 4],
            // o 系列
            'ō' => ['o', 1], 'ó' => ['o', 2], 'ǒ' => ['o', 3], 'ò' => ['o', 4],
            // e 系列
            'ē' => ['e', 1], 'é' => ['e', 2], 'ě' => ['e', 3], 'è' => ['e', 4],
            // i 系列
            'ī' => ['i', 1], 'í' => ['i', 2], 'ǐ' => ['i', 3], 'ì' => ['i', 4],
            // u 系列
            'ū' => ['u', 1], 'ú' => ['u', 2], 'ǔ' => ['u', 3], 'ù' => ['u', 4],
            // ü 系列（注意：ü的Unicode编码是U+00FC，带声调的是U+01D6等）
            'ǖ' => ['ü', 1], 'ǘ' => ['ü', 2], 'ǚ' => ['ü', 3], 'ǜ' => ['ü', 4],
        ];
        // 若字符在映射表中，直接返回拆分结果
        if (isset($toneMap[$char])) {
            return [
                'letter' => $toneMap[$char][0],
                'tone' => $toneMap[$char][1]
            ];
        }
        // 若不在映射表中（可能是无声调字符或辅音），默认声调为0（轻声）
        return [
            'letter' => $char,
            'tone' => 0
        ];
    }
}
if (!function_exists('pinyin_trim')) {
    function pinyin_trim($str, $sep = ' ')
    {
        return trim($str, " \n\r\t\v\0" . $sep);
    }
}
