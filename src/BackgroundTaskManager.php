<?php

namespace tekintian\pinyin;

use tekintian\pinyin\Utils\AutoPinyinFetcher;
use tekintian\pinyin\Utils\PinyinConstants;

/**
 * 后台任务管理器
 * 负责管理各种后台任务，包括未找到字符处理、自学习字典合并等
 */
class BackgroundTaskManager
{
    /**
     * 配置
     * @var array
     */
    private $config;

    /**
     * 构造函数
     * @param array $config 配置数组
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'enable' => true,
            'task_dir' => __DIR__ . '/../data/backup/tasks/',
            'max_concurrent' => 3,
            'task_types' => []
        ], $config);

        // 确保任务目录存在
        if (!is_file_exists($this->config['task_dir'])) {
            create_dir($this->config['task_dir']);
        }
    }

    /**
     * 创建后台任务
     * @param string $taskType 任务类型
     * @param array $taskData 任务数据
     * @param int $priority 优先级（1-10，1为最高）
     * @return bool 是否成功
     */
    public function createTask($taskType, $taskData, $priority = 5)
    {
        if (!$this->config['enable']) {
            return false;
        }

        $task = [
            'id' => uniqid($taskType . '_', true),
            'type' => $taskType,
            'data' => $taskData,
            'priority' => $priority,
            'created_at' => time(),
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3
        ];

        // 保存任务到文件
        $taskFile = $this->config['task_dir'] . $task['id'] . '.json';

        try {
            write_to_file($taskFile, json_encode($task, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return true;
        } catch (\Exception $e) {
            error_log("[BackgroundTaskManager] 创建任务失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取待处理任务
     * @param int $limit 限制数量
     * @return array 任务列表
     */
    public function getPendingTasks($limit = 10)
    {
        $tasks = [];

        if (!is_file_exists($this->config['task_dir'])) {
            return $tasks;
        }

        $files = glob($this->config['task_dir'] . '*.json');

        foreach ($files as $file) {
            try {
                $content = read_file_data($file);
                $task = json_decode($content, true);

                if ($task && $task['status'] === 'pending' && $task['attempts'] < $task['max_attempts']) {
                    $tasks[] = $task;
                }
            } catch (\Exception $e) {
                // 忽略损坏的任务文件
                continue;
            }

            if (count($tasks) >= $limit) {
                break;
            }
        }

        // 按优先级排序
        usort($tasks, function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });

        return $tasks;
    }

    /**
     * 执行任务
     * @param array $task 任务数据
     * @param PinyinConverter $converter 拼音转换器实例
     * @return bool 是否成功
     */
    public function executeTask($task, PinyinConverter $converter)
    {
        $taskFile = $this->config['task_dir'] . $task['id'] . '.json';

        try {
            // 更新任务状态为执行中
            $task['status'] = 'running';
            $task['started_at'] = time();
            $task['attempts']++;
            write_to_file($taskFile, json_encode($task, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // 执行任务
            $success = $this->executeTaskByType($task, $converter);

            // 更新任务状态
            $task['status'] = $success ? 'completed' : 'failed';
            $task['completed_at'] = time();

            if ($success) {
                // 删除已完成的任务文件
                delete_file($taskFile);
            } else {
                // 保存失败的任务状态
                write_to_file($taskFile, json_encode($task, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            return $success;
        } catch (\Exception $e) {
            error_log("[BackgroundTaskManager] 执行任务失败: " . $e->getMessage());

            // 更新任务状态为失败
            $task['status'] = 'failed';
            $task['completed_at'] = time();
            $task['error'] = $e->getMessage();

            try {
                write_to_file($taskFile, json_encode($task, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } catch (\Exception $writeError) {
                // 忽略写入错误
            }

            return false;
        }
    }

    /**
     * 根据任务类型执行具体任务
     * @param array $task 任务数据
     * @param PinyinConverter $converter 拼音转换器实例
     * @return bool 是否成功
     */
    private function executeTaskByType($task, PinyinConverter $converter)
    {
        switch ($task['type']) {
            case 'not_found_resolve':
                return $this->resolveNotFoundChar($task['data'], $converter);

            case 'self_learn_merge':
                return $this->executeSelfLearnMerge($task['data'], $converter);

            default:
                error_log("[BackgroundTaskManager] 未知任务类型: " . $task['type']);
                return false;
        }
    }

    /**
     * 处理未找到拼音的字符
     * @param array $data 任务数据
     * @param PinyinConverter $converter 拼音转换器实例
     * @return bool 是否成功
     */
    private function resolveNotFoundChar($data, PinyinConverter $converter)
    {
        if (!isset($data['char'])) {
            return false;
        }

        $char = $data['char'];

        // 尝试从外部API获取拼音
        $pinyin = $this->fetchPinyinFromExternalSource($char);

        if ($pinyin) {
            // 根据字符性质分配到合适的字典
            $this->addCharToAppropriateDict($char, $pinyin, $converter);

            // 从未找到字符文件中移除
            $this->removeCharFromNotFound($char);

            error_log("[BackgroundTaskManager] 成功为字符 '{$char}' 获取拼音: {$pinyin}");
            return true;
        }

        return false;
    }

    /**
     * 根据字符性质添加到合适的字典
     * @param string $char 汉字
     * @param string $pinyin 拼音
     * @param PinyinConverter $converter 拼音转换器实例
     */
    private function addCharToAppropriateDict($char, $pinyin, PinyinConverter $converter)
    {
        // 判断字符性质
        $charType = $this->classifyCharType($char);

        switch ($charType) {
            case 'common':
                // 常用字 - 添加到常用字典
                $this->addToCommonDict($char, $pinyin, $converter);
                break;

            case 'rare':
                // 生僻字 - 添加到生僻字字典
                $this->addToRareDict($char, $pinyin, $converter);
                break;

            case 'cjk_extended':
                // CJK扩展字符 - 添加到生僻字字典
                $this->addToRareDict($char, $pinyin, $converter);
                break;

            case 'user_defined':
                // 用户自定义字符 - 添加到自定义字典
                $converter->addCustomPinyin($char, $pinyin);
                break;

            default:
                // 默认添加到生僻字字典
                $this->addToRareDict($char, $pinyin, $converter);
                break;
        }
    }

    /**
     * 判断字符类型
     * @param string $char 汉字
     * @return string 字符类型
     */
    private function classifyCharType($char)
    {
        // 使用 PinyinConstants 统一管理汉字范围判断
        if (PinyinConstants::isInChineseRange($char, 'basic')) {
            // 检查是否在常用汉字范围内
            if ($this->isCommonHanChar($char)) {
                return 'common';
            }
            return 'rare';
        }

        // 检查是否为扩展汉字
        if (
            PinyinConstants::isInChineseRange($char, 'ext_a') ||
            PinyinConstants::isInChineseRange($char, 'ext_b') ||
            PinyinConstants::isInChineseRange($char, 'ext_c') ||
            PinyinConstants::isInChineseRange($char, 'ext_d') ||
            PinyinConstants::isInChineseRange($char, 'ext_e') ||
            PinyinConstants::isInChineseRange($char, 'compatible')
        ) {
            return 'cjk_extended';
        }

        // 其他字符（如用户自定义符号等）
        return 'user_defined';
    }

    /**
     * 获取字符的Unicode码点
     * @param string $char 汉字
     * @return int Unicode码点
     * @deprecated 暂未使用，保留供未来功能使用
     */
    // private function getCharCodePoint($char)
    // {
    //     $code = unpack('N', mb_convert_encoding($char, 'UCS-4BE', 'UTF-8'));
    //     return $code[1] ?? 0;
    // }

    /**
     * 判断是否为常用汉字
     * @param string $char 汉字
     * @return bool 是否为常用汉字
     */
    private function isCommonHanChar($char)
    {
        // 使用常用字典来判断是否为常用汉字
        $commonDictPath = __DIR__ . '/../data/common_with_tone.php';

        if (!is_file_exists($commonDictPath)) {
            return false;
        }

        try {
            $commonDict = require_file($commonDictPath);
            $commonDict = is_array($commonDict) ? $commonDict : [];

            // 如果字符在常用字典中，则认为是常用汉字
            return isset($commonDict[$char]);
        } catch (\Exception $e) {
            error_log("[BackgroundTaskManager] 加载常用字典失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 添加到常用字典
     * @param string $char 汉字
     * @param string $pinyin 拼音
     * @param PinyinConverter $converter 拼音转换器实例
     */
    private function addToCommonDict($char, $pinyin, PinyinConverter $converter)
    {
        // 使用PinyinConverter的配置路径
        $this->addToDictFile('common', $char, $pinyin, $converter);
    }

    /**
     * 添加到生僻字字典
     * @param string $char 汉字
     * @param string $pinyin 拼音
     * @param PinyinConverter $converter 拼音转换器实例
     */
    private function addToRareDict($char, $pinyin, PinyinConverter $converter)
    {
        // 添加到生僻字字典
        $this->addToDictFile('rare', $char, $pinyin, $converter);
    }

    /**
     * 添加到字典文件
     * @param string $dictType 字典类型
     * @param string $char 汉字
     * @param string $pinyin 拼音
     * @param PinyinConverter $converter 拼音转换器实例
     */
    private function addToDictFile($dictType, $char, $pinyin, PinyinConverter $converter)
    {
        // 使用默认字典文件路径（与PinyinConverter保持一致）
        $dictFiles = [
            'common' => [
                'with_tone' => __DIR__ . '/../data/common_with_tone.php',
                'no_tone' => __DIR__ . '/../data/common_no_tone.php'
            ],
            'rare' => [
                'with_tone' => __DIR__ . '/../data/rare_with_tone.php',
                'no_tone' => __DIR__ . '/../data/rare_no_tone.php'
            ]
        ];

        if (!isset($dictFiles[$dictType])) {
            return;
        }

        foreach ($dictFiles[$dictType] as $toneType => $filePath) {
            if (!is_file_exists($filePath)) {
                // 如果文件不存在，尝试创建空字典文件
                try {
                    write_to_file($filePath, "<?php\nreturn [];\n");
                } catch (\Exception $e) {
                    error_log("[BackgroundTaskManager] 创建字典文件失败: " . $e->getMessage());
                    continue;
                }
            }

            try {
                $dictData = require_file($filePath);
                $dictData = is_array($dictData) ? $dictData : [];

                // 如果字符已存在，跳过
                if (isset($dictData[$char])) {
                    continue;
                }

                // 添加字符到字典
                $dictData[$char] = $toneType === 'with_tone' ? $pinyin : $this->removeToneFromPinyin($pinyin);

                // 使用全局函数的紧凑数组导出方法保持格式一致
                if (method_exists($converter, 'pinyin_compact_array_export')) {
                    $exportContent = pinyin_compact_array_export($dictData);
                } else {
                    // 如果PinyinConverter的方法不可用，使用兼容的紧凑格式
                    $exportContent = $this->compatibleShortArrayExport($dictData);
                }

                // 保存字典文件，保持与PinyinConverter一致的格式
                write_to_file($filePath, "<?php\nreturn " . $exportContent . ";\n");

                error_log("[BackgroundTaskManager] 字符 '{$char}' 已添加到 {$dictType} 字典 ({$toneType})");
            } catch (\Exception $e) {
                error_log("[BackgroundTaskManager] 添加到字典失败: " . $e->getMessage());
            }
        }
    }

    /**
     * 兼容的紧凑数组序列化（用于字典文件）
     * 保持与PinyinConverter一致的格式
     * @param array $array 要序列化的数组
     * @return string 序列化后的字符串
     */
    private function compatibleShortArrayExport($array)
    {
        // 使用 pinyin_compact_array_export 统一管理数组序列化
        return pinyin_compact_array_export($array);
    }

    /**
     * 移除拼音中的声调
     * @param string $pinyin 带声调拼音
     * @return string 无声调拼音
     */
    private function removeToneFromPinyin($pinyin)
    {
        return remove_tone($pinyin);
    }

    /**
     * 执行自学习字典合并
     * @param array $data 任务数据
     * @param PinyinConverter $converter 拼音转换器实例
     * @return bool 是否成功
     */
    private function executeSelfLearnMerge($data, PinyinConverter $converter)
    {
        try {
            // 检查是否存在executeMerge方法
            if (method_exists($converter, 'executeMerge')) {
                $result = $converter->executeMerge();
                return !empty($result['success']);
            } else {
                // 如果不存在executeMerge方法，记录警告并返回成功
                error_log("[BackgroundTaskManager] 警告：PinyinConverter缺少executeMerge方法");
                return true;
            }
        } catch (\Exception $e) {
            error_log("[BackgroundTaskManager] 自学习字典合并失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 从外部源获取拼音
     * @param string $char 汉字
     * @return string|null 拼音或null
     */
    private function fetchPinyinFromExternalSource(string $char): ?string
    {
        // 1. 首先尝试从官方权威字典 data/unihan/all_unihan_pinyin.php 中查找
        $pinyin = $this->fetchFromUnihanDict($char);
        if ($pinyin) {
            return $pinyin;
        }

        // 2. 如果官方字典中找不到，再调用外部API获取
        return $this->fetchFromExternalAPI($char);
    }

    /**
     * 从Unihan权威字典中获取拼音
     * @param string $char 汉字
     * @return string|null 拼音或null
     */
    private function fetchFromUnihanDict(string $char): ?string
    {
        $unihanDictPath = __DIR__ . '/../data/unihan/all_unihan_pinyin.php';

        if (!is_file_exists($unihanDictPath)) {
            return null;
        }

        try {
            $unihanDict = require_file($unihanDictPath);
            $unihanDict = is_array($unihanDict) ? $unihanDict : [];

            // 在Unihan字典中查找字符
            if (isset($unihanDict[$char])) {
                $pinyinData = $unihanDict[$char];

                // 返回第一个拼音（通常是最常用的）
                if (is_array($pinyinData) && !empty($pinyinData)) {
                    return $pinyinData[0];
                } elseif (is_string($pinyinData)) {
                    return $pinyinData;
                }
            }

            return null;
        } catch (\Exception $e) {
            error_log("[BackgroundTaskManager] 加载Unihan字典失败: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 从外部API获取拼音
     * @param string $char 汉字
     * @return string|null 拼音或null
     */
    private function fetchFromExternalAPI(string $char): ?string
    {
        try {
            // 使用项目中已有的 AutoPinyinFetcher 类
            $fetcher = new AutoPinyinFetcher();

            // 按照用户指定的调用顺序：先尝试 getPinyinFromDictAPI，如果失败则尝试 getPinyinFromZdic
            $result = $fetcher->getPinyinFromDictAPI($char) ?? $fetcher->getPinyinFromZdic($char);

            if ($result && isset($result['pinyin'])) {
                // 处理拼音结果，如果是数组则取第一个
                $pinyin = $result['pinyin'];
                if (is_array($pinyin) && !empty($pinyin)) {
                    return $pinyin[0];
                }
                return $pinyin;
            }

            return null;
        } catch (\Exception $e) {
            error_log("[BackgroundTaskManager] 调用外部API获取拼音失败: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 从未找到字符文件中移除字符
     * @param string $char 字符
     */
    private function removeCharFromNotFound($char)
    {
        $notFoundPath = __DIR__ . '/../data/diy/not_found_chars.php';

        if (!is_file_exists($notFoundPath)) {
            return;
        }

        try {
            $notFoundChars = require_file($notFoundPath);
            $notFoundChars = is_array($notFoundChars) ? $notFoundChars : [];

            // 移除字符
            $notFoundChars = array_filter($notFoundChars, function ($c) use ($char) {
                return $c !== $char;
            });

            // 重新索引数组
            $notFoundChars = array_values($notFoundChars);

            // 保存文件
            write_to_file($notFoundPath, "<?php\nreturn " . var_export($notFoundChars, true) . ";\n");
        } catch (\Exception $e) {
            error_log("[BackgroundTaskManager] 从未找到字符文件移除字符失败: " . $e->getMessage());
        }
    }

    /**
     * 批量处理任务
     * @param PinyinConverter $converter 拼音转换器实例
     * @param int $batchSize 批量大小
     * @return array 处理结果
     */
    public function processBatch(PinyinConverter $converter, $batchSize = 10)
    {
        $results = [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0
        ];

        $tasks = $this->getPendingTasks($batchSize);

        foreach ($tasks as $task) {
            $success = $this->executeTask($task, $converter);

            $results['processed']++;
            if ($success) {
                $results['succeeded']++;
            } else {
                $results['failed']++;
            }

            // 限制并发数
            if ($results['processed'] >= $this->config['max_concurrent']) {
                break;
            }
        }

        return $results;
    }

    /**
     * 获取任务统计信息
     * @return array 统计信息
     */
    public function getStats()
    {
        $stats = [
            'total' => 0,
            'pending' => 0,
            'running' => 0,
            'completed' => 0,
            'failed' => 0
        ];

        if (!is_file_exists($this->config['task_dir'])) {
            return $stats;
        }

        $files = glob($this->config['task_dir'] . '*.json');

        foreach ($files as $file) {
            try {
                $content = read_file_data($file);
                $task = json_decode($content, true);

                if ($task) {
                    $stats['total']++;
                    $stats[$task['status']]++;
                }
            } catch (\Exception $e) {
                // 忽略损坏的文件
                continue;
            }
        }

        return $stats;
    }
}
