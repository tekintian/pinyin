<?php

namespace tekintian\pinyin\Utils;

use tekintian\pinyin\Exception\PinyinException;

/**
 * 文件操作工具类
 * 提供文件和目录的基本操作功能
 */
class FileUtil
{
    /**
     * 递归复制项目根目录下的data文件夹到用户指定目录
     * 默认拷贝当前项目data目录下的所有文件到用户指定的目录
     * 
     * @param string $dst 目标目录路径
     * @return bool 复制结果
     * @throws PinyinException 复制失败时抛出异常
     */
    public static function copyDict(string $dst): bool
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
            self::createDir($dst);
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
                    self::createDir($destinationPath);
                }
                
                // 使用辅助函数递归复制子目录内容
                if (!self::copyDirectory($sourcePath, $destinationPath)) {
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
    
    /**
     * 辅助方法：递归复制目录内容
     * 
     * @param string $src 源目录路径
     * @param string $dst 目标目录路径
     * @return bool 复制结果
     * @throws PinyinException 复制失败时抛出异常
     */
    private static function copyDirectory(string $src, string $dst): bool
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
                    self::createDir($destinationPath);
                }
                
                if (!self::copyDirectory($sourcePath, $destinationPath)) {
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
    /**
     * 递归创建目录
     *
     * @param string $dir 目录路径
     * @param int $mode 目录权限
     * @return bool 创建结果
     * @throws PinyinException 目录创建失败时抛出异常
     */
    public static function createDir(string $dir, int $mode = 0755): bool
    {
        if (is_dir($dir)) {
            return true;
        }

        $parentDir = dirname($dir);
        if (!is_dir($parentDir)) {
            self::createDir($parentDir, $mode);
        }

        if (@mkdir($dir, $mode) === false) {
            throw new PinyinException("Failed to create directory: {$dir}", PinyinException::ERROR_FILE_NOT_FOUND);
        }

        return true;
    }

    /**
     * 读取文件内容
     *
     * @param string $file 文件路径
     * @return string 文件内容
     * @throws PinyinException 文件不存在或读取失败时抛出异常
     */
    public static function readFile(string $file): string
    {
        if (!is_file($file)) {
            throw new PinyinException("File not found: {$file}", PinyinException::ERROR_FILE_NOT_FOUND);
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            throw new PinyinException("Failed to read file: {$file}", PinyinException::ERROR_FILE_NOT_FOUND);
        }

        return $content;
    }

    /**
     * 写入文件内容
     *
     * @param string $file 文件路径
     * @param string $content 文件内容
     * @param bool $append 是否追加内容
     * @return bool 写入结果
     * @throws PinyinException 写入失败时抛出异常
     */
    public static function writeFile(string $file, string $content, bool $append = false): bool
    {
        // 确保目录存在
        $dir = dirname($file);
        if (!is_dir($dir)) {
            self::createDir($dir);
        }

        $flags = $append ? FILE_APPEND : 0;
        if (@file_put_contents($file, $content, $flags) === false) {
            throw new PinyinException("Failed to write to file: {$file}", PinyinException::ERROR_FILE_NOT_FOUND);
        }

        return true;
    }

    /**
     * 检查文件是否存在
     *
     * @param string $file 文件路径
     * @return bool 文件是否存在
     */
    public static function fileExists(string $file): bool
    {
        return is_file($file);
    }

    /**
     * 安全地加载PHP配置文件
     *
     * @param string $file PHP文件路径
     * @param mixed $default 默认值
     * @return mixed 文件内容或默认值
     * @throws PinyinException 文件不存在或加载失败时抛出异常
     */
    public static function requireFile(string $file, $default = [])
    {
        if (!is_file($file)) {
            return $default;
        }

        try {
            $data = require $file;
            return $data;
        } catch (\Throwable $e) {
            throw new PinyinException("Failed to require file: {$file}, error: " . $e->getMessage(), PinyinException::ERROR_DICT_LOAD_FAIL);
        }
    }

    /**
     * 获取文件修改时间
     *
     * @param string $file 文件路径
     * @return int|null 文件修改时间戳，如果文件不存在则返回null
     */
    public static function getFileMTime(string $file): ?int
    {
        if (!is_file($file)) {
            return null;
        }

        return filemtime($file);
    }

    /**
     * 删除文件
     *
     * @param string $file 文件路径
     * @return bool 删除结果
     */
    public static function deleteFile(string $file): bool
    {
        if (!is_file($file)) {
            return true;
        }

        return @unlink($file);
    }

    /**
     * 读取JSON文件并解析为数组
     *
     * @param string $file JSON文件路径
     * @return array 解析后的数组
     * @throws PinyinException 文件不存在、读取失败或解析失败时抛出异常
     */
    public static function readJsonFile(string $file): array
    {
        $content = self::readFile($file);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new PinyinException(
                "Failed to parse JSON file: {$file}, error: " . json_last_error_msg(),
                PinyinException::ERROR_INVALID_INPUT
            );
        }

        return $data;
    }

    /**
     * 将数组数据写入JSON文件
     *
     * @param string $file JSON文件路径
     * @param array $data 要写入的数据
     * @param int $options JSON编码选项
     * @return bool 写入结果
     * @throws PinyinException 写入失败时抛出异常
     */
    public static function writeJsonFile(string $file, array $data, int $options = JSON_UNESCAPED_UNICODE): bool
    {
        $content = json_encode($data, $options);
        if ($content === false) {
            throw new PinyinException(
                "Failed to encode data to JSON, error: " . json_last_error_msg(),
                PinyinException::ERROR_INVALID_INPUT
            );
        }

        return self::writeFile($file, $content);
    }
}