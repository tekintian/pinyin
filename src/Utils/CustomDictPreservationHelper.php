<?php

declare(strict_types=1);

namespace tekintian\pinyin\Utils;

// use function tekintian\pinyin\pinyin_compact_array_export;

/**
 * 自定义字典保存助手
 *
 * 解决自定义字典文件保存时注释丢失和不必要的重写问题
 */
class CustomDictPreservationHelper
{
    /**
     * 智能保存自定义字典
     * - 保留现有注释
     * - 只在内容真正改变时才保存
     * - 使用紧凑格式
     *
     * @param string $filePath 文件路径
     * @param array $newData 新的字典数据
     * @param bool $preserveComments 是否保留注释（默认true）
     * @return bool 是否执行了保存操作
     */
    public static function smartSave(string $filePath, array $newData, bool $preserveComments = true): bool
    {
        // 如果文件不存在，直接创建
        if (!file_exists($filePath)) {
            self::createNewFile($filePath, $newData);
            return true;
        }

        // 读取现有文件内容
        $originalContent = file_get_contents($filePath);
        if ($originalContent === false) {
            return false;
        }

        // 提取现有注释（如果需要保留）
        $comments = $preserveComments ? self::extractComments($originalContent) : [];

        // 生成新的内容
        $newContent = self::generateContent($newData, $comments);

        // 比较内容是否真的改变了
        if (self::contentEquals($originalContent, $newContent)) {
            return false; // 内容没有改变，不需要保存
        }

        // 备份原文件（可选）
        self::backupFile($filePath);

        // 保存新内容
        return file_put_contents($filePath, $newContent) !== false;
    }

    /**
     * 从现有文件中提取注释
     */
    private static function extractComments(string $content): array
    {
        $comments = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // 提取文件头部的多行注释
            if (strpos($trimmed, '/*') === 0 || strpos($trimmed, '*') === 0 || strpos($trimmed, '*/') === 0) {
                $comments[] = $line;
                continue;
            }

            // 提取单行注释（但跳过 return 和数组结束部分）
            if (
                strpos($trimmed, '//') === 0 &&
                !preg_match('/return\s+\[|^\s*\];?\s*$/', $trimmed)
            ) {
                $comments[] = $line;
            }
        }

        return $comments;
    }

    /**
     * 生成新的文件内容
     */
    private static function generateContent(array $data, array $comments = []): string
    {
        $content = "<?php\n";

        // 添加保留的注释
        if (!empty($comments)) {
            $content .= implode("\n", $comments) . "\n";
        }

        // 添加数组数据
        $content .= "return " . pinyin_compact_array_export($data) . ";\n";
        return $content;
    }

    /**
     * 比较两个内容是否相等（忽略空白字符差异）
     */
    private static function contentEquals(string $content1, string $content2): bool
    {
        // 标准化空白字符后比较
        $normalize1 = preg_replace('/\s+/', ' ', trim($content1));
        $normalize2 = preg_replace('/\s+/', ' ', trim($content2));

        return $normalize1 === $normalize2;
    }

    /**
     * 创建新文件
     */
    private static function createNewFile(string $filePath, array $data): void
    {
        $content = self::generateContent($data);
        $dir = dirname($filePath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($filePath, $content);
    }

    /**
     * 备份文件
     */
    private static function backupFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            return;
        }

        $backupDir = dirname($filePath) . '/backup';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filename = basename($filePath);
        $timestamp = date('Y-m-d_H-i-s');
        $backupPath = "{$backupDir}/{$filename}_{$timestamp}";

        copy($filePath, $backupPath);
    }

    /**
     * 检查文件是否需要保存（内容是否有变化）
     */
    public static function needsSave(string $filePath, array $newData): bool
    {
        if (!file_exists($filePath)) {
            return true;
        }

        $originalContent = file_get_contents($filePath);
        if ($originalContent === false) {
            return true;
        }

        $comments = self::extractComments($originalContent);
        $newContent = self::generateContent($newData, $comments);

        return !self::contentEquals($originalContent, $newContent);
    }

    /**
     * 添加注释到现有数据
     *
     * @param array $data 字典数据
     * @param array $comments 注释映射 [字符 => 注释]
     * @return array 带注释的数据（用于特殊格式输出）
     */
    public static function addCommentsToData(array $data, array $comments): array
    {
        $result = [];

        foreach ($data as $char => $pinyin) {
            $result[$char] = [
                'pinyin' => $pinyin,
                'comment' => $comments[$char] ?? ''
            ];
        }

        return $result;
    }

    /**
     * 从带注释的数据中提取纯字典数据
     */
    public static function extractPureData(array $dataWithComments): array
    {
        $result = [];

        foreach ($dataWithComments as $char => $item) {
            if (is_array($item) && isset($item['pinyin'])) {
                $result[$char] = $item['pinyin'];
            } else {
                $result[$char] = $item;
            }
        }

        return $result;
    }
}
