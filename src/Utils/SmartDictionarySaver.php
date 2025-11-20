<?php

namespace tekintian\pinyin\Utils;

/**
 * 智能字典保存器
 * 提供优雅高效的字典文件保存方案
 */
class SmartDictionarySaver
{
    /**
     * 保存字典文件，智能选择保存策略
     * @param string $filePath 文件路径
     * @param array $data 数据
     * @param array $options 选项
     * @return bool
     */
    public static function save($filePath, $data, $options = [])
    {
        $defaultOptions = [
            'preserve_comments' => true,
            'compact_format' => true,
            'backup' => true,
            'file_type' => 'auto' // auto, polyphone, dictionary
        ];

        $options = array_merge($defaultOptions, $options);

        // 自动检测文件类型
        if ($options['file_type'] === 'auto') {
            $options['file_type'] = self::detectFileType($filePath);
        }

        // 创建备份
        if ($options['backup']) {
            self::createBackup($filePath);
        }

        // 根据文件类型选择保存策略
        switch ($options['file_type']) {
            case 'polyphone':
                return self::savePolyphoneRules($filePath, $data, $options);

            case 'dictionary':
                return self::saveDictionary($filePath, $data, $options);

            default:
                return self::saveGeneric($filePath, $data, $options);
        }
    }

    /**
     * 检测文件类型
     * @param string $filePath 文件路径
     * @return string 文件类型
     */
    private static function detectFileType($filePath)
    {
        $filename = basename($filePath);

        if (strpos($filename, 'polyphone') !== false) {
            return 'polyphone';
        }

        if (
            strpos($filename, 'custom') !== false ||
            strpos($filename, 'self_learn') !== false ||
            strpos($filename, 'rare') !== false
        ) {
            return 'dictionary';
        }

        return 'generic';
    }

    /**
     * 保存多音字规则
     * @param string $filePath 文件路径
     * @param array $data 数据
     * @param array $options 选项
     * @return bool
     */
    private static function savePolyphoneRules($filePath, $data, $options)
    {
        if ($options['preserve_comments']) {
            return DictionaryPreservationHelper::preservePolyphoneRules($filePath, $data);
        } else {
            $content = "<?php\nreturn " . self::exportPolyphoneRules($data) . ";\n";
            return file_put_contents($filePath, $content) !== false;
        }
    }

    /**
     * 保存字典文件
     * @param string $filePath 文件路径
     * @param array $data 数据
     * @param array $options 选项
     * @return bool
     */
    private static function saveDictionary($filePath, $data, $options)
    {
        if ($options['compact_format']) {
            $content = "<?php\nreturn " . self::compactArrayExport($data) . ";\n";
        } else {
            $content = "<?php\nreturn " . var_export($data, true) . ";\n";
        }

        return file_put_contents($filePath, $content) !== false;
    }

    /**
     * 保存通用文件
     * @param string $filePath 文件路径
     * @param array $data 数据
     * @param array $options 选项
     * @return bool
     */
    private static function saveGeneric($filePath, $data, $options)
    {
        $content = "<?php\nreturn " . var_export($data, true) . ";\n";
        return file_put_contents($filePath, $content) !== false;
    }

    /**
     * 紧凑格式的数组导出
     * @param array $array 数组
     * @return string
     */
    private static function compactArrayExport($array)
    {
        if (empty($array)) {
            return '[]';
        }

        $isAssoc = array_keys($array) !== range(0, count($array) - 1);

        if (!$isAssoc) {
            // 索引数组
            $items = array_map(function ($item) {
                return is_string($item) ? "'" . str_replace("'", "\\'", $item) . "'" : var_export($item, true);
            }, $array);
            return '[' . implode(', ', $items) . ']';
        } else {
            // 关联数组
            $result = "[\n";
            foreach ($array as $key => $value) {
                $keyStr = "'" . str_replace("'", "\\'", $key) . "' => ";

                if (is_array($value)) {
                    // 检查是否为简单拼音数组
                    $isSimplePinyinArray = true;
                    foreach ($value as $v) {
                        if (!is_string($v) || !preg_match('/^[a-zāáǎàēéěèīíǐìōóǒòūúǔùǖǘǚǜü\s]+$/ui', $v)) {
                            $isSimplePinyinArray = false;
                            break;
                        }
                    }

                    if ($isSimplePinyinArray) {
                        $values = array_map(function ($v) {
                            return "'" . str_replace("'", "\\'", $v) . "'";
                        }, $value);
                        $valueStr = "[" . implode(', ', $values) . "]";
                    } else {
                        $valueStr = self::compactArrayExport($value);
                    }
                } else {
                    $valueStr = is_string($value) ? "'" . str_replace("'", "\\'", $value) . "'" : var_export($value, true);
                }

                $result .= "    " . $keyStr . $valueStr . ",\n";
            }
            $result .= "]";
            return $result;
        }
    }

    /**
     * 导出多音字规则
     * @param array $rules 规则
     * @return string
     */
    private static function exportPolyphoneRules($rules)
    {
        if (empty($rules)) {
            return '[]';
        }

        $result = "[\n";

        foreach ($rules as $char => $charRules) {
            $result .= "    '{$char}' => [\n";

            if (is_array($charRules)) {
                foreach ($charRules as $rule) {
                    $result .= "        [";

                    $items = [];
                    foreach ($rule as $key => $value) {
                        $valueStr = is_string($value) ? "'" . str_replace("'", "\\'", $value) . "'" : $value;
                        $items[] = "'{$key}' => {$valueStr}";
                    }

                    $result .= implode(', ', $items) . "],\n";
                }
            }

            $result .= "    ],\n";
        }

        $result .= "]";

        return $result;
    }

    /**
     * 创建备份
     * @param string $filePath 文件路径
     */
    private static function createBackup($filePath)
    {
        if (!file_exists($filePath)) {
            return;
        }

        $backupDir = dirname($filePath) . '/backup/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filename = basename($filePath, '.php');
        $backupPath = $backupDir . $filename . '_' . date('Y-m-d_H-i-s') . '.php';
        copy($filePath, $backupPath);
    }

    /**
     * 批量保存字典文件
     * @param array $files 文件列表 ['path' => $data, ...]
     * @param array $options 选项
     * @return array 结果
     */
    public static function batchSave($files, $options = [])
    {
        $results = [];

        foreach ($files as $filePath => $data) {
            $results[$filePath] = self::save($filePath, $data, $options);
        }

        return $results;
    }

    /**
     * 验证字典文件格式
     * @param string $filePath 文件路径
     * @return array 验证结果
     */
    public static function validateDictionary($filePath)
    {
        $result = [
            'valid' => false,
            'error' => null,
            'stats' => null
        ];

        if (!file_exists($filePath)) {
            $result['error'] = '文件不存在';
            return $result;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            $result['error'] = '无法读取文件';
            return $result;
        }

        // 检查是否为有效的PHP文件
        if (strpos($content, '<?php') !== 0) {
            $result['error'] = '不是有效的PHP文件';
            return $result;
        }

        // 尝试加载数据
        try {
            $data = require $filePath;

            if (!is_array($data)) {
                $result['error'] = '文件内容不是数组';
                return $result;
            }

            $result['valid'] = true;
            $result['stats'] = [
                'total_entries' => count($data),
                'file_size' => filesize($filePath),
                'last_modified' => filemtime($filePath)
            ];
        } catch (ParseError $e) {
            $result['error'] = 'PHP语法错误: ' . $e->getMessage();
        } catch (Exception $e) {
            $result['error'] = '加载错误: ' . $e->getMessage();
        }

        return $result;
    }
}
