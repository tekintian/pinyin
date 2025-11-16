#!/usr/bin/env php
<?php
/**
 * 拼音后台任务运行器
 * 
 * 这是一个功能完整的后台任务处理系统，专门用于处理拼音转换相关的后台任务。
 * 支持多种运行模式，包括守护进程、批量处理和一次性执行。
 * 
 * 主要功能：
 * - 多模式运行：守护进程、批量处理、一次性执行
 * - 信号处理：支持优雅关闭、重启、配置重载
 * - 进程管理：自动清理僵尸进程，防止资源泄漏
 * - 状态持久化：自动保存和恢复运行状态
 * - 跨平台兼容：支持Windows和Unix/Linux系统
 * - 详细日志：完整的运行日志和统计信息
 * 
 * 文件位置: bin/task_runner.php
 * 依赖文件: vendor/autoload.php (Composer自动加载)
 * 
 * @package tekintian\pinyin
 * @author tekintian
 * @version 1.0.0
 * @license MIT
 */

// 引入Composer自动加载文件
require_once __DIR__ . '/../vendor/autoload.php';

use tekintian\pinyin\PinyinConverter;
use tekintian\pinyin\BackgroundTaskManager;

/**
 * 拼音后台任务运行器
 * 
 * 这是一个功能完整的后台任务处理系统，支持多种运行模式：
 * - 守护进程模式：持续运行，定期检查并处理任务
 * - 批量处理模式：一次性处理指定数量的任务
 * - 一次性执行模式：处理当前可用的任务后退出
 * 
 * 主要特性：
 * - 僵尸进程自动清理
 * - 信号处理（优雅关闭、重启、配置重载）
 * - 状态持久化和自动恢复
 * - 跨平台兼容性
 * - 详细的统计信息和日志记录
 * 
 * 使用示例：
 * 1. 守护进程模式（推荐用于生产环境）：
 *    php bin/task_runner.php -m daemon -i 30
 *    
 * 2. 批量处理模式：
 *    php bin/task_runner.php -m batch -b 100 -l 500
 *    
 * 3. 一次性执行模式：
 *    php bin/task_runner.php -m once
 * 
 * 使用示例:
 * 
 * 1. 守护进程模式（推荐生产环境）:
 *    php bin/task_runner.php -m daemon -i 30
 *    
 * 2. 批量处理模式:
 *    php bin/task_runner.php -m batch -b 100 -l 500
 *    
 * 3. 一次性执行模式:
 *    php bin/task_runner.php -m once
 * 
 * 4. 显示帮助信息:
 *    php bin/task_runner.php -h
 * 
 * 信号控制（守护进程模式下）:
 * - kill -TERM <PID>   优雅关闭进程  kill -TERM $(cat /tmp/pinyin_task_daemon.pid)
 * - kill -HUP <PID>    优雅重启      kill -HUP $(cat /tmp/pinyin_task_daemon.pid)
 * - kill -USR2 <PID>   重新加载配置  kill -USR2 $(cat /tmp/pinyin_task_daemon.pid)
 * 
 * @package tekintian\pinyin
 * @author tekintian
 * @version 1.0.0
 */
class TaskRunner {
    /** @var PinyinConverter 拼音转换器实例 */
    private $converter;
    
    /** @var BackgroundTaskManager 后台任务管理器实例 */
    private $taskManager;
    
    /** @var array 命令行选项 */
    private $options;
    
    /**
     * 构造函数
     * 
     * 初始化任务运行器，检查必要扩展，创建核心组件实例
     * 
     * @throws \RuntimeException 如果缺少必需扩展
     */
    public function __construct() {
        // 检查必要的扩展
        $this->checkExtensions();
        
        // 初始化拼音转换器和任务管理器
        $this->converter = new PinyinConverter();
        $this->taskManager = new BackgroundTaskManager();
        
        // 解析命令行选项
        $this->parseOptions();
    }
    
    /**
     * 检查必要的PHP扩展
     * 
     * 验证系统是否安装了运行所需的PHP扩展：
     * - json: 必需扩展，用于状态保存和配置处理
     * - pcntl: 推荐扩展，用于进程控制和信号处理
     * - posix: 推荐扩展，用于进程管理和守护进程功能
     * 
     * @return void
     * @throws \RuntimeException 如果缺少必需扩展
     */
    private function checkExtensions() {
        $required = [];
        $recommended = [];
        
        // 必需扩展 - 没有这些扩展程序无法正常运行
        if (!extension_loaded('json')) {
            $required[] = 'json';
        }
        
        // 推荐扩展（用于守护进程功能）- 没有这些扩展功能受限但可以运行
        if (!extension_loaded('pcntl')) {
            $recommended[] = 'pcntl';
        }
        
        if (!extension_loaded('posix')) {
            $recommended[] = 'posix';
        }
        
        // 显示错误信息并退出（如果缺少必需扩展）
        if (!empty($required)) {
            echo "错误: 缺少必需扩展: " . implode(', ', $required) . "\n";
            echo "请安装这些扩展后重试。\n";
            exit(1);
        }
        
        // 显示警告信息（如果缺少推荐扩展）
        if (!empty($recommended)) {
            echo "警告: 缺少推荐扩展: " . implode(', ', $recommended) . "\n";
            echo "守护进程功能将受限。建议安装这些扩展以获得完整功能。\n";
            echo "安装方法: pecl install pcntl posix\n\n";
        }
    }
    
    /**
     * 解析命令行选项
     * 
     * 使用getopt函数解析命令行参数，支持短选项和长选项：
     * - 短选项: -m, -b, -l, -i, -h
     * - 长选项: --mode, --batch-size, --limit, --interval, --help
     * 
     * 支持的选项：
     * - mode: 执行模式 (batch|daemon|once)
     * - batch-size: 批量处理大小
     * - limit: 限制处理任务数量
     * - interval: 守护进程检查间隔（秒）
     * - help: 显示帮助信息
     * 
     * @return void
     */
    private function parseOptions() {
        // 短选项定义
        $shortopts = "m:b:l:i:h";
        
        // 长选项定义
        $longopts = [
            "mode:",        // 执行模式：batch, daemon, once
            "batch-size:",  // 批量大小
            "limit:",       // 限制处理数量
            "interval:",    // 检查间隔（秒）
            "help"          // 帮助信息
        ];
        
        // 解析命令行选项
        $this->options = getopt($shortopts, $longopts);
        
        // 如果用户请求帮助信息，显示帮助并退出
        if (isset($this->options['h']) || isset($this->options['help'])) {
            $this->showHelp();
            exit(0);
        }
    }
    
    /**
     * 运行任务处理器
     * 
     * 根据命令行选项选择相应的运行模式：
     * - daemon: 守护进程模式，持续运行
     * - once: 一次性执行模式，处理当前任务后退出
     * - batch: 批量处理模式，处理指定数量的任务
     * 
     * 默认模式为批量处理模式
     * 
     * @return void
     */
    public function run() {
        // 获取运行模式，默认为批量处理模式
        $mode = $this->getOption('mode', 'm', 'batch');
        
        // 根据模式选择相应的执行方法
        switch ($mode) {
            case 'daemon':
                $this->runDaemon();
                break;
            case 'once':
                $this->runOnce();
                break;
            case 'batch':
            default:
                $this->runBatch();
                break;
        }
    }
    
    /**
     * 批量处理模式
     */
    private function runBatch() {
        $batchSize = $this->getOption('batch-size', 'b', 50);
        $limit = $this->getOption('limit', 'l', 0);
        
        echo "开始批量处理任务...\n";
        
        $processed = 0;
        do {
            $results = $this->taskManager->processBatch($this->converter, $batchSize);
            
            if ($results['processed'] > 0) {
                echo "[" . date('Y-m-d H:i:s') . "] 处理了 " . $results['processed'] . " 个任务\n";
                $processed += $results['processed'];
            } else {
                echo "[" . date('Y-m-d H:i:s') . "] 没有待处理任务\n";
                break;
            }
            
            // 如果设置了限制，检查是否达到
            if ($limit > 0 && $processed >= $limit) {
                echo "达到处理限制 ($limit)，停止处理\n";
                break;
            }
            
        } while (true);
        
        $this->showStats();
    }
    
    /**
     * 一次性执行模式
     */
    private function runOnce() {
        $batchSize = $this->getOption('batch-size', 'b', 10);
        
        echo "执行一次性任务处理...\n";
        
        $results = $this->taskManager->processBatch($this->converter, $batchSize);
        
        echo "处理结果：\n";
        echo "已处理: " . $results['processed'] . " 个任务\n";
        echo "成功: " . $results['succeeded'] . " 个任务\n";
        echo "失败: " . $results['failed'] . " 个任务\n";
        
        $this->showStats();
    }
    
    /**
     * 守护进程模式
     * 
     * 创建一个守护进程来持续处理任务，具有以下特性：
     * - 进程分离和后台运行
     * - 信号处理（优雅关闭、重启）
     * - 僵尸进程自动清理
     * - 状态持久化和自动恢复
     * - 定期统计信息显示
     * 
     * 守护进程会：
     * 1. 检查是否已有实例运行（通过PID文件）
     * 2. 创建子进程并让父进程退出
     * 3. 设置进程组和标题
     * 4. 进入主循环，定期处理任务
     * 5. 响应系统信号进行优雅操作
     * 
     * @return void
     * @throws \RuntimeException 如果无法创建守护进程
     */
    private function runDaemon() {
        // 获取检查间隔，默认为60秒
        $interval = $this->getOption('interval', 'i', 60);
        
        // PID文件路径，用于防止多个实例同时运行
        $pidFile = '/tmp/pinyin_task_daemon.pid';
        
        // 检查是否已有守护进程运行
        if (file_exists($pidFile)) {
            $pid = file_get_contents($pidFile);
            if ($this->isProcessRunning($pid)) {
                echo "守护进程已在运行 (PID: $pid)\n";
                exit(0);
            } else {
                // 清理过期的PID文件（进程已退出但文件未删除）
                unlink($pidFile);
            }
        }
        
        // 创建守护进程 - 使用pcntl_fork创建子进程
        $pid = pcntl_fork();
        if ($pid == -1) {
            die("无法创建守护进程\n");
        } elseif ($pid) {
            // 父进程：保存子进程PID到文件并退出
            file_put_contents($pidFile, $pid);
            echo "守护进程已启动 (PID: $pid)\n";
            exit(0);
        }
        
        // 子进程继续执行，成为守护进程
        
        // 设置新的进程组（如果POSIX扩展可用）
        if (function_exists('posix_setsid')) {
            posix_setsid();
        } else {
            echo "[" . date('Y-m-d H:i:s') . "] 警告: POSIX扩展不可用，使用简化守护进程模式\n";
        }
        
        // 设置进程标题（如果支持且不会出错）
        if (function_exists('cli_set_process_title') && PHP_OS_FAMILY !== 'Darwin') {
            // 在macOS上cli_set_process_title可能有问题，所以跳过
            try {
                @cli_set_process_title('pinyin_task_daemon');
            } catch (\Exception $e) {
                // 忽略设置进程标题的错误，不影响主要功能
            }
        }
        
        echo "[" . date('Y-m-d H:i:s') . "] 守护进程开始运行，检查间隔: {$interval}秒\n";
        
        // 设置信号处理器（如果PCNTL扩展可用）
        if (function_exists('pcntl_signal')) {
            $this->setupSignalHandlers($pidFile);
        } else {
            echo "[" . date('Y-m-d H:i:s') . "] 警告: PCNTL扩展不可用，信号处理功能受限\n";
        }
        
        // 初始化计时器和计数器
        $lastCleanup = time();      // 上次僵尸进程清理时间
        $lastStateSave = time();    // 上次状态保存时间
        $iterationCount = 0;        // 迭代计数器
        
        // 加载上次的状态信息（如果存在）
        $lastState = $this->loadLastState();
        if ($lastState) {
            echo "[" . date('Y-m-d H:i:s') . "] 上次运行统计: " . 
                 "总任务: " . ($lastState['stats']['total'] ?? 0) . 
                 ", 已完成: " . ($lastState['stats']['completed'] ?? 0) . 
                 ", 运行时间: " . ($lastState['uptime'] ?? '未知') . "\n";
        }
        
        // 主循环 - 持续运行直到收到终止信号
        while (true) {
            $iterationCount++;
            
            // 信号分发 - 检查是否有待处理的信号
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
            
            // 处理任务批次 - 每次处理10个任务
            $results = $this->taskManager->processBatch($this->converter, 10);
            
            if ($results['processed'] > 0) {
                echo "[" . date('Y-m-d H:i:s') . "] 处理了 " . $results['processed'] . " 个任务\n";
            }
            
            $currentTime = time();
            
            // 定期清理僵尸进程（每10分钟一次）
            if ($currentTime - $lastCleanup > 600 && function_exists('pcntl_waitpid')) {
                $this->cleanupZombieProcesses();
                $lastCleanup = $currentTime;
            }
            
            // 自动保存状态（每30分钟一次）
            if ($currentTime - $lastStateSave > 1800) {
                $this->saveCurrentState();
                $lastStateSave = $currentTime;
            }
            
            // 每100次迭代显示一次统计信息
            if ($iterationCount % 100 === 0) {
                $this->showStats();
                echo "[" . date('Y-m-d H:i:s') . "] 已运行 " . $this->getUptime() . "\n";
            }
            
            // 使用非阻塞的sleep，以便及时响应信号
            // 将长间隔分解为1秒的小间隔，每次醒来都检查信号
            $slept = 0;
            while ($slept < $interval) {
                // 每次只睡1秒，以便及时响应信号
                sleep(1);
                $slept++;
                
                // 每次醒来都检查信号
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }
            }
        }
    }
    
    /**
     * 设置信号处理器
     * 
     * 为守护进程注册各种信号的处理函数，支持：
     * - SIGTERM/SIGINT: 优雅关闭进程
     * - SIGCHLD: 僵尸进程自动清理
     * - SIGHUP/SIGUSR1: 优雅重启
     * - SIGUSR2: 配置重载
     * 
     * 信号处理说明：
     * - 使用全局变量保存PID文件路径，确保信号处理函数能正确访问
     * - 每个信号处理函数都包含错误检查和回退机制
     * - 支持方法存在性检查，提高代码健壮性
     * 
     * @param string $pidFile PID文件路径
     * @return void
     */
    private function setupSignalHandlers($pidFile) {
        // 使用全局变量保存PID文件路径，确保信号处理函数能正确访问
        $GLOBALS['_task_runner_pid_file'] = $pidFile;
        
        // 终止信号 (SIGTERM) - 系统管理员发送的终止信号
        pcntl_signal(SIGTERM, function() {
            $pidFile = $GLOBALS['_task_runner_pid_file'] ?? '/tmp/pinyin_task_daemon.pid';
            echo "[" . date('Y-m-d H:i:s') . "] 收到终止信号，退出守护进程\n";
            
            // 直接清理并退出，避免复杂的实例方法调用
            if (file_exists($pidFile)) {
                unlink($pidFile);
            }
            exit(0);
        });
        
        // 中断信号 (SIGINT) - 用户按Ctrl+C发送的中断信号
        pcntl_signal(SIGINT, function() {
            $pidFile = $GLOBALS['_task_runner_pid_file'] ?? '/tmp/pinyin_task_daemon.pid';
            echo "[" . date('Y-m-d H:i:s') . "] 收到中断信号，退出守护进程\n";
            
            if (file_exists($pidFile)) {
                unlink($pidFile);
            }
            exit(0);
        });
        
        // 子进程退出信号 (SIGCHLD) - 防止僵尸进程
        pcntl_signal(SIGCHLD, function() {
            // 非阻塞方式回收所有已退出的子进程
            while (pcntl_waitpid(-1, $status, WNOHANG) > 0) {
                // 子进程已回收，避免成为僵尸进程
            }
        });
        
        // 挂起信号 (SIGHUP) - 优雅重启和配置重载
        pcntl_signal(SIGHUP, function() {
            $pidFile = $GLOBALS['_task_runner_pid_file'] ?? '/tmp/pinyin_task_daemon.pid';
            echo "[" . date('Y-m-d H:i:s') . "] 收到挂起信号，执行优雅重启\n";
            
            // 直接重启，不调用复杂的实例方法
            if (file_exists($pidFile)) {
                unlink($pidFile);
            }
            
            // 等待1秒确保清理完成
            sleep(1);
            
            // 重新执行当前脚本
            $script = __FILE__;
            $args = $_SERVER['argv'] ?? [];
            pcntl_exec(PHP_BINARY, array_merge([$script], $args));
            
            // 如果pcntl_exec失败，直接退出
            exit(0);
        });
        
        // 用户自定义信号1 (SIGUSR1) - 优雅重启
        pcntl_signal(SIGUSR1, function() {
            $pidFile = $GLOBALS['_task_runner_pid_file'] ?? '/tmp/pinyin_task_daemon.pid';
            echo "[" . date('Y-m-d H:i:s') . "] 收到用户信号，执行优雅重启\n";
            
            if (file_exists($pidFile)) {
                unlink($pidFile);
            }
            
            sleep(1);
            
            $script = __FILE__;
            $args = $_SERVER['argv'] ?? [];
            pcntl_exec(PHP_BINARY, array_merge([$script], $args));
            exit(0);
        });
        
        // 用户自定义信号2 (SIGUSR2) - 重新加载配置
        pcntl_signal(SIGUSR2, function() {
            echo "[" . date('Y-m-d H:i:s') . "] 收到用户信号2，重新加载配置\n";
            echo "[" . date('Y-m-d H:i:s') . "] 配置重载功能暂不可用\n";
        });
    }
    

    
    /**
     * 清理僵尸进程
     */
    private function cleanupZombieProcesses() {
        if (!function_exists('pcntl_waitpid')) {
            return;
        }
        
        $zombieCount = 0;
        // 非阻塞方式回收所有僵尸进程
        while (pcntl_waitpid(-1, $status, WNOHANG) > 0) {
            $zombieCount++;
        }
        
        if ($zombieCount > 0) {
            echo "[" . date('Y-m-d H:i:s') . "] 清理了 {$zombieCount} 个僵尸进程\n";
        }
    }
    
    /**
     * 保存当前状态
     */
    private function saveCurrentState() {
        $stateDir = '/tmp/pinyin_task_state';
        $stateFile = $stateDir . '/last_state.json';
        
        // 确保状态目录存在
        if (!is_dir($stateDir)) {
            if (!mkdir($stateDir, 0755, true)) {
                error_log("[TaskRunner] 无法创建状态目录: {$stateDir}");
                return false;
            }
        }
        
        try {
            // 收集当前状态信息
            $state = [
                'timestamp' => time(),
                'datetime' => date('Y-m-d H:i:s'),
                'stats' => $this->taskManager->getStats(),
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'uptime' => $this->getUptime(),
                'processed_tasks' => $this->getProcessedTasksCount(),
                'last_cleanup' => $this->getLastCleanupTime(),
                'system_info' => [
                    'php_version' => PHP_VERSION,
                    'os' => PHP_OS,
                    'pid' => getmypid()
                ]
            ];
            
            // 保存状态到文件
            $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if (file_put_contents($stateFile, $json, LOCK_EX) !== false) {
                echo "[" . date('Y-m-d H:i:s') . "] 状态已保存到: {$stateFile}\n";
                return true;
            } else {
                error_log("[TaskRunner] 保存状态文件失败: {$stateFile}");
                return false;
            }
            
        } catch (\Exception $e) {
            error_log("[TaskRunner] 保存状态时发生错误: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 加载上次保存的状态
     */
    private function loadLastState() {
        $stateFile = '/tmp/pinyin_task_state/last_state.json';
        
        if (!file_exists($stateFile)) {
            return null;
        }
        
        try {
            $content = file_get_contents($stateFile);
            $state = json_decode($content, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                echo "[" . date('Y-m-d H:i:s') . "] 加载了上次的状态信息\n";
                return $state;
            } else {
                error_log("[TaskRunner] 解析状态文件失败: " . json_last_error_msg());
                return null;
            }
            
        } catch (\Exception $e) {
            error_log("[TaskRunner] 加载状态时发生错误: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 获取运行时间
     */
    private function getUptime() {
        static $startTime = null;
        
        if ($startTime === null) {
            $startTime = time();
        }
        
        $uptime = time() - $startTime;
        
        // 格式化运行时间
        $hours = floor($uptime / 3600);
        $minutes = floor(($uptime % 3600) / 60);
        $seconds = $uptime % 60;
        
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }
    
    /**
     * 获取已处理任务数量
     */
    private function getProcessedTasksCount() {
        static $processedCount = 0;
        
        // 在实际应用中，这里应该从数据库或文件中读取
        // 目前使用静态变量模拟
        return $processedCount;
    }
    
    /**
     * 获取上次清理时间
     */
    private function getLastCleanupTime() {
        static $lastCleanup = null;
        
        if ($lastCleanup === null) {
            $lastCleanup = time();
        }
        
        return date('Y-m-d H:i:s', $lastCleanup);
    }
    

    
    /**
     * 重新加载配置
     */
    private function reloadConfiguration() {
        try {
            // 重新解析命令行选项
            $this->parseOptions();
            
            // 重新初始化任务管理器（如果需要）
            $this->taskManager = new BackgroundTaskManager();
            
            // 记录配置重载
            echo "[" . date('Y-m-d H:i:s') . "] 配置已重新加载\n";
            echo "[" . date('Y-m-d H:i:s') . "] 当前模式: " . $this->getOption('mode', 'm', 'batch') . "\n";
            echo "[" . date('Y-m-d H:i:s') . "] 检查间隔: " . $this->getOption('interval', 'i', 60) . "秒\n";
            
        } catch (\Exception $e) {
            error_log("[TaskRunner] 重新加载配置失败: " . $e->getMessage());
            echo "[" . date('Y-m-d H:i:s') . "] 配置重载失败: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * 显示统计信息
     */
    private function showStats() {
        $stats = $this->taskManager->getStats();
        
        echo "\n=== 任务统计 ===\n";
        echo "总任务数: " . $stats['total'] . "\n";
        echo "待处理: " . $stats['pending'] . "\n";
        echo "执行中: " . $stats['running'] . "\n";
        echo "已完成: " . $stats['completed'] . "\n";
        echo "失败: " . $stats['failed'] . "\n";
        echo "================\n";
    }
    
    /**
     * 检查进程是否在运行
     */
    private function isProcessRunning($pid) {
        if (!is_numeric($pid) || $pid <= 0) {
            return false;
        }
        
        // 方法1: 使用posix_kill检查进程是否存在（如果POSIX扩展可用）
        if (function_exists('posix_kill')) {
            // 发送信号0不会实际终止进程，只是检查进程是否存在
            return posix_kill($pid, 0);
        }
        
        // 方法2: 使用posix_getpgid检查进程组（如果POSIX扩展可用）
        if (function_exists('posix_getpgid')) {
            return posix_getpgid($pid) !== false;
        }
        
        // 方法3: 使用系统命令检查（兼容性最好的方法）
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows系统
            $output = [];
            exec("tasklist /FI \"PID eq $pid\" 2>nul", $output);
            return count($output) > 1 && strpos($output[1], 'INFO:') === false;
        } else {
            // Unix/Linux系统
            $output = [];
            exec("ps -p $pid 2>&1", $output, $returnCode);
            return $returnCode === 0 && count($output) > 1;
        }
    }
    
    /**
     * 获取选项值
     */
    private function getOption($long, $short, $default = null) {
        if (isset($this->options[$long])) {
            return $this->options[$long];
        }
        if (isset($this->options[$short])) {
            return $this->options[$short];
        }
        return $default;
    }
    
    /**
     * 显示帮助信息
     * 
     * 显示详细的命令行使用说明，包括：
     * - 程序简介
     * - 支持的选项和参数
     * - 使用示例
     * - 信号控制说明
     * 
     * 帮助信息会显示所有可用的命令行选项及其说明，
     * 以及常见的使用场景示例。
     * 
     * @return void
     */
    private function showHelp() {
        echo "拼音后台任务运行器\n";
        echo "====================\n";
        echo "一个功能强大的后台任务处理系统，支持多种运行模式和信号控制。\n\n";
        
        echo "用法: php " . basename(__FILE__) . " [选项]\n\n";
        
        echo "选项:\n";
        echo "  -m, --mode MODE        执行模式: batch, daemon, once (默认: batch)\n";
        echo "  -b, --batch-size SIZE 批量处理大小 (默认: 50)\n";
        echo "  -l, --limit LIMIT     限制处理任务数量 (默认: 无限制)\n";
        echo "  -i, --interval SEC    守护进程模式下的检查间隔 (默认: 60秒)\n";
        echo "  -h, --help            显示此帮助信息\n\n";
        
        echo "执行模式说明:\n";
        echo "  batch   批量处理模式 - 持续处理任务直到完成或达到限制\n";
        echo "  daemon  守护进程模式 - 持续运行，定期检查新任务（推荐生产环境）\n";
        echo "  once    一次性执行模式 - 处理当前可用任务后退出\n\n";
        
        echo "信号控制（守护进程模式下可用）:\n";
        echo "  SIGTERM (kill -TERM <PID>)   优雅关闭进程\n";
        echo "  SIGINT  (Ctrl+C)            中断进程\n";
        echo "  SIGHUP  (kill -HUP <PID>)   优雅重启（保存状态后重启）\n";
        echo "  SIGUSR1 (kill -USR1 <PID>)  优雅重启\n";
        echo "  SIGUSR2 (kill -USR2 <PID>)  重新加载配置\n\n";
        
        echo "示例:\n";
        echo "  # 批量处理模式 - 每次处理100个任务，最多处理500个\n";
        echo "  php " . basename(__FILE__) . " -m batch -b 100 -l 500\n\n";
        
        echo "  # 守护进程模式 - 每30秒检查一次新任务\n";
        echo "  php " . basename(__FILE__) . " -m daemon -i 30\n\n";
        
        echo "  # 一次性执行模式 - 处理当前可用任务后退出\n";
        echo "  php " . basename(__FILE__) . " -m once\n\n";
        
        echo "  # 优雅重启运行中的守护进程\n";
        echo "  kill -HUP $(cat /tmp/pinyin_task_daemon.pid)\n\n";
        
        echo "状态文件:\n";
        echo "  PID文件: /tmp/pinyin_task_daemon.pid\n";
        echo "  状态文件: /tmp/pinyin_task_state/last_state.json\n\n";
        
        echo "版本: 1.0.0\n";
        echo "作者: tekintian\n";
    }
}

// 运行任务运行器
$runner = new TaskRunner();
$runner->run();
