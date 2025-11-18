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

if (!function_exists('require_file')) {
    /**
     * 引入PHP文件
     * @param string $file 文件路径
     * @param mixed $default 默认返回值
     * @return mixed
     */
    function require_file($file, $default = [])
    {
        if (!is_file_exists($file)) {
            return $default;
        }
        return require_once $file;
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
            echo $prefix . $message . "\n";
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
        // 声调符号到数字的映射
        $toneToNumber = [
            'ā' => '1', 'á' => '2', 'ǎ' => '3', 'à' => '4',
            'ē' => '1', 'é' => '2', 'ě' => '3', 'è' => '4',
            'ī' => '1', 'í' => '2', 'ǐ' => '3', 'ì' => '4',
            'ō' => '1', 'ó' => '2', 'ǒ' => '3', 'ò' => '4',
            'ū' => '1', 'ú' => '2', 'ǔ' => '3', 'ù' => '4',
            'ǖ' => '1', 'ǘ' => '2', 'ǚ' => '3', 'ǜ' => '4',
        ];
        
        // 声调符号到无声调字母的映射
        $toneToPlain = [
            'ā' => 'a', 'á' => 'a', 'ǎ' => 'a', 'à' => 'a',
            'ē' => 'e', 'é' => 'e', 'ě' => 'e', 'è' => 'e',
            'ī' => 'i', 'í' => 'i', 'ǐ' => 'i', 'ì' => 'i',
            'ō' => 'o', 'ó' => 'o', 'ǒ' => 'o', 'ò' => 'o',
            'ū' => 'u', 'ú' => 'u', 'ǔ' => 'u', 'ù' => 'u',
            'ǖ' => 'v', 'ǘ' => 'v', 'ǚ' => 'v', 'ǜ' => 'v',
            'ü' => 'v',
        ];
        
        // 按空格分割处理多个拼音
        $pinyins = explode(' ', $pinyin);
        $result = [];
        
        foreach ($pinyins as $py) {
            if (empty($py)) {
                $result[] = '';
                continue;
            }
            
            $toneNumber = '';
            $plainPinyin = '';
            
            // 查找声调符号并确定声调数字
            for ($i = 0; $i < mb_strlen($py, 'UTF-8'); $i++) {
                $char = mb_substr($py, $i, 1, 'UTF-8');
                
                if (isset($toneToNumber[$char])) {
                    $toneNumber = $toneToNumber[$char];
                    $plainPinyin .= $toneToPlain[$char];
                } else {
                    $plainPinyin .= $char;
                }
            }
            
            // 如果有声调，在末尾添加数字
            if ($toneNumber) {
                $result[] = $plainPinyin . $toneNumber;
            } else {
                $result[] = $plainPinyin;
            }
        }
        
        return implode(' ', $result);
    }
}

if (!function_exists('convert_from_number_tone')) {
    /**
     * 将数字声调格式转换为带声调符号的拼音（如：zhong1 → zhōng）
     *
     * @param string $pinyin 数字声调格式的拼音
     * @return string 带声调符号的拼音
     */
    function convert_from_number_tone($pinyin)
    {
        $reverseMap = [
            'a1' => 'ā', 'a2' => 'á', 'a3' => 'ǎ', 'a4' => 'à',
            'e1' => 'ē', 'e2' => 'é', 'e3' => 'ě', 'e4' => 'è',
            'i1' => 'ī', 'i2' => 'í', 'i3' => 'ǐ', 'i4' => 'ì',
            'o1' => 'ō', 'o2' => 'ó', 'o3' => 'ǒ', 'o4' => 'ò',
            'u1' => 'ū', 'u2' => 'ú', 'u3' => 'ǔ', 'u4' => 'ù',
            'v1' => 'ǖ', 'v2' => 'ǘ', 'v3' => 'ǚ', 'v4' => 'ǜ',
            'v' => 'ü',
        ];

        // 按长度从长到短排序，优先匹配长模式
        uksort($reverseMap, function ($a, $b) {
            return strlen($b) - strlen($a);
        });

        return strtr($pinyin, $reverseMap);
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
     * @param string $pinyin 带声调的拼音
     * @return string 无声调的拼音
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
                $result['suggestions'] = array_map(function ($p) use ($withTone) {
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

if (!function_exists('pinyin_compact_array_export')) {
    /**
     * 紧凑数组导出（用于生成PHP数组代码）
     * @param array $array 数组数据
     * @return string PHP数组代码
     */
    function pinyin_compact_array_export($array)
    {
        if (empty($array)) {
            return '[]';
        }

        $isAssoc = array_keys($array) !== range(0, count($array) - 1);
        $items = [];

        foreach ($array as $key => $value) {
            $keyStr = $isAssoc ? "'" . str_replace("'", "\\'", $key) . "' => " : '';

            if (is_array($value)) {
                $valueItems = array_map(function ($item) {
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
            }
            // 2. 处理连续纯数字（长度≥2，如“一二三”→123，“一伍壹九”→1519）
            elseif (mb_strlen($chineseNum, 'UTF-8') >= 2) {
                $arabicStr = '';
                // 逐个字符转换并拼接（如“一伍壹九”→“1”+“5”+“1”+“9”=“1519”）
                for ($i = 0; $i < mb_strlen($chineseNum, 'UTF-8'); $i++) {
                    $char = mb_substr($chineseNum, $i, 1, 'UTF-8');
                    $arabicStr .= $numMap[$char];
                }
                $arabicNum = $arabicStr;
            }
            // 3. 处理特殊数字（廿、卅、卌）和单个数字（如“一”→1，“伍”→5）
            else {
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
