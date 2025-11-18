<?php
namespace tekintian\pinyin;

use tekintian\pinyin\Contracts\ConverterInterface;
use tekintian\pinyin\Exception\PinyinException;
use tekintian\pinyin\Utils\PinyinConstants;
use InvalidArgumentException;

/**
 * 汉字转拼音工具
 * 一个功能强大的汉字转拼音工具，支持自定义映射、特殊字符处理、自动学习功能和多音字处理。
 * 基于权威的Unihan数据库，提供完整的汉字拼音转换解决方案。
 * 
 * 支持：特殊字符精准处理+自定义替换+灵活参数传递
 * 
 * @author tekintian
 * @see https://github.com/tekintian/pinyin
 * @link https://dev.tekin.cn
 * @date 2025-11-01 16:00:00
 * @version 1.0.0
 */
class PinyinConverter implements ConverterInterface {
    /**
     * 配置参数
     * @var array
     */
    private $config = [
        'dict' => [
            'custom' => [
                'with_tone' => __DIR__.'/../data/custom_with_tone.php',
                'no_tone' => __DIR__.'/../data/custom_no_tone.php'
            ],
            'common' => [
                'with_tone' => __DIR__.'/../data/common_with_tone.php',
                'no_tone' => __DIR__.'/../data/common_no_tone.php'
            ],
            'rare' => [
                'with_tone' => __DIR__.'/../data/rare_with_tone.php',
                'no_tone' => __DIR__.'/../data/rare_no_tone.php'
            ],
            'unihan' => [
                'with_tone' => __DIR__.'/../data/unihan/cjk_ext_a.php', // 扩展A的生僻字
                'no_tone' => __DIR__.'/../data/unihan/cjk_ext_a_no_tone.php' // 扩展A的生僻字 不带声调
            ],
            'self_learn' => [
                'with_tone' => __DIR__.'/../data/self_learn_with_tone.php',
                'no_tone' => __DIR__.'/../data/self_learn_no_tone.php'
            ],
            'polyphone_rules' => __DIR__.'/../data/polyphone_rules.php',
            'frequency' => __DIR__.'/../data/char_frequency.php',
            'backup' => __DIR__.'/../data/backup/',
            'not_found' => __DIR__.'/../data/diy/not_found_chars.php'
        ],
        'dict_loading' => [
            'strategy' => 'both', // 'both'|'with_tone'|'no_tone' - 字典加载策略
            'lazy_loading' => true, // 是否启用懒加载（默认启用）
            'preload_priority' => ['custom', 'common'], // 预加载优先级（移除自学习字典）
            'lazy_dicts' => ['rare', 'unihan'] // 懒加载的字典类型
        ],
        'custom_dict_persistence' => [
            'enable_delayed_write' => true, // 启用延迟写入
            'max_delayed_entries' => 100, // 最大延迟写入条目数
            'auto_save_interval' => 30, // 自动保存间隔（秒）
            'save_on_destruct' => true // 对象销毁时自动保存
        ],
        'special_char' => [
            'default_mode' => 'delete',
            'default_map' => PinyinConstants::DEFAULT_SPECIAL_CHAR_MAP,
            'delete_allow' => PinyinConstants::DEFAULT_SPECIAL_CHARS_ALLOWED
        ],
        'high_freq_cache' => ['size' => 1000],
        'self_learn_merge' => [
            'threshold' => 1000,
            'batch_threshold' => 50, // 批量处理阈值
            'incremental' => true,
            'max_per_merge' => 500,
            'frequency_limit' => 86400, // 24小时执行一次
            'backup_before_merge' => true,
            'sort_by_frequency' => true,
            'enable_background_task' => true // 启用后台任务处理
        ],
        'background_tasks' => [
            'enable' => true, // 启用后台任务池
            'task_dir' => __DIR__.'/../data/backup/tasks/', // 任务存储目录
            'max_concurrent' => 3, // 最大并发任务数
            'task_types' => [
                'not_found_resolve' => [
                    'description' => '处理未找到拼音的字符',
                    'priority' => 1, // 优先级（1-10，1为最高）
                    'batch_size' => 50, // 批量处理数量
                    'auto_execute' => true // 是否自动执行
                ],
                'self_learn_merge' => [
                    'description' => '自学习字典合并',
                    'priority' => 2,
                    'batch_size' => 100,
                    'auto_execute' => true
                ]
            ]
        ]
    ];

    /**
     * 字典数据缓存
     * @var array
     */
    private $dicts = [
        'custom' => ['with_tone' => null, 'no_tone' => null],
        'common' => ['with_tone' => null, 'no_tone' => null],
        'rare' => ['with_tone' => null, 'no_tone' => null],
        'unihan' => ['with_tone' => null, 'no_tone' => null],
        'self_learn' => ['with_tone' => null, 'no_tone' => null],
        'frequency' => null,
        'polyphone_rules' => null,
        'not_found' => null
    ];

    /**
     * 延迟写入队列
     * @var array
     */
    private $delayedWriteQueue = [
        'with_tone' => [],
        'no_tone' => []
    ];

    /**
     * 最后保存时间
     * @var array
     */
    private $lastSaveTime = [
        'with_tone' => 0,
        'no_tone' => 0
    ];

    /**
     * 延迟写入计数器
     * @var array
     */
    private $delayedWriteCount = [
        'with_tone' => 0,
        'no_tone' => 0
    ];

    /**
     * 新增自学习字缓存
     * @var array
     */
    private $learnedChars = [
        'with_tone' => [],
        'no_tone' => []
    ];

    /**
     * 未找到拼音的字符缓存
     * @var array
     */
    private $notFoundChars = [];

    /**
     * 后台任务队列
     * @var array
     */
    private $backgroundTasks = [];

    /**
     * 任务执行状态
     * @var array
     */
    private $taskExecutionStatus = [];

    /**
     * 所有字和词语使用频率计数（key为字或词，value为调用次数）
     * @var array
     */
    private $charFrequency = [];

    /**
     * 上次合并时间记录
     * @var array
     */
    private $lastMergeTime = [];

    /**
     * 高频转换结果缓存（LRU实现）
     * @var array
     */
    private $cache = [];
    
    /**
     * 缓存访问时间记录（用于LRU淘汰）
     * @var array
     */
    private $cacheAccessTime = [];
    
    /**
     * 启动时间（用于性能监控）
     * @var float
     */
    private $startTime;
    
    /**
     * 总转换次数（用于统计）
     * @var int
     */
    private $totalConversions = 0;

    /**
     * 频率数据是否已修改（需要在析构函数中保存）
     * @var bool
     */
    private $frequencyModified = false;

    /**
     * 特殊字符最终替换映射
     * @var array
     */
    private $finalCharMap = [];

    /**
     * 自定义多字词语缓存（按长度降序）
     * @var array
     */
    private $customMultiWords = [
        'with_tone' => [],
        'no_tone' => []
    ];

    /**
     * 常用字基础拼音映射（兜底）
     * @var array
     */
    private $basicPinyinMap = [
        '开' => ['kāi', 'kai'],
        '发' => ['fā', 'fa'],
        '云' => ['yún', 'yun'],
        '南' => ['nán', 'nan'],
        '系' => ['xì', 'xi'],
        '务' => ['wù', 'wu'],
        '技' => ['jì', 'ji'],
        '术' => ['shù', 'shu'],
        '栈' => ['zhàn', 'zhan'],
        '含' => ['hán', 'han'],
        '源' => ['yuán', 'yuan'],
        '码' => ['mǎ', 'ma'],
        '部' => ['bù', 'bu'],
        '署' => ['shǔ', 'shu'],
        '文' => ['wén', 'wen'],
        '档' => ['dàng', 'dang'],
        '企' => ['qǐ', 'qi'],
        '业' => ['yè', 'ye'],
        '级' => ['jí', 'ji'],
        '客' => ['kè', 'ke'],
        '户' => ['hù', 'hu'],
        '服' => ['fú', 'fu'],
        '软' => ['ruǎn', 'ruan'],
        '件' => ['jiàn', 'jian'],
        '统' => ['tǒng', 'tong']
    ];



    /**
     * 构造函数
     * @param array $options 自定义配置
     */
    public function __construct($options = []) {
        $this->startTime = microtime(true);
        $this->config = array_replace_recursive($this->config, $options);
        $this->cache = [];
        $this->finalCharMap = $this->config['special_char']['default_map'];
        
        if (isset($options['special_char']['custom_map']) && is_array($options['special_char']['custom_map'])) {
            // 这里的自定义映射会覆盖默认映射，如果需要保留默认映射，需要在自定义映射中添加默认映射
            $this->finalCharMap = $options['special_char']['custom_map'];
        }

        $this->initDirectories();
        $this->loadAllDicts();
        $this->initCustomMultiWords();
        $this->loadLastMergeTime();
        $this->checkMergeNeed();
    }

    /**
     * 初始化目录结构
     */
    private function initDirectories() {
        $backupDir = $this->config['dict']['backup'];
        if (!is_file_exists($backupDir)) {
            create_dir($backupDir);
        }

        foreach (['common', 'rare', 'self_learn', 'custom'] as $dictType) {
            foreach (['with_tone', 'no_tone'] as $toneType) {
                $path = $this->config['dict'][$dictType][$toneType];
                if (!is_file_exists($path)) {
                    write_to_file($path, "<?php\nreturn [];\n");
                }
            }
        }

        $polyPath = $this->config['dict']['polyphone_rules'];
        if (!is_file_exists($polyPath)) {
            write_to_file($polyPath, "<?php\nreturn [];\n");
        }
        $freqPath = $this->config['dict']['frequency'];
        if (!is_file_exists($freqPath)) {
            write_to_file($freqPath, "<?php\nreturn [];\n");
        }
        
        $notFoundPath = $this->config['dict']['not_found'];
        if (!is_file_exists($notFoundPath)) {
            write_to_file($notFoundPath, "<?php\nreturn [];\n");
        }
        
        // 初始化自学习字典数据来源文件
        $this->initSelfLearnSources();
    }

    /**
     * 初始化自学习字典数据来源文件
     */
    private function initSelfLearnSources() {
        $sourceLogFile = $this->config['dict']['backup'] . '/self_learn_sources.json';
        if (!is_file_exists($sourceLogFile)) {
            write_to_file($sourceLogFile, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        
        $mergeLogFile = $this->config['dict']['backup'] . '/immediate_merge_log.json';
        if (!is_file_exists($mergeLogFile)) {
            write_to_file($mergeLogFile, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 根据配置策略加载字典
     */
    private function loadAllDicts() {
        $strategy = $this->config['dict_loading']['strategy'];
        $lazyLoading = $this->config['dict_loading']['lazy_loading'];
        $preloadPriority = $this->config['dict_loading']['preload_priority'];
        
        // 加载多音字规则（总是需要）
        $this->loadPolyphoneRules();
        
        // 加载自学习频率（总是需要）
        $this->loadFrequency();
        
        // 根据懒加载配置决定加载策略
        if ($lazyLoading) {
            // 懒加载模式：只预加载常用字典
            $this->loadDictsByStrategy($strategy, $preloadPriority);
        } else {
            // 全量加载模式：加载所有字典类型（排除self_learn）
            $this->loadDictsByStrategy($strategy, ['custom', 'common', 'rare', 'unihan']);
        }
        
        // 异步检查字典迁移需求（不阻塞主流程）
        $this->asyncCheckMigration();
    }
    
    /**
     * 异步检查字典迁移需求
     */
    private function asyncCheckMigration() {
        // 在非CLI环境中禁用后台任务，避免pcntl_fork导致HTTP头信息泄露
        if (!$this->isCliEnvironment()) {
            return;
        }
        
        // 检查是否启用后台任务且pcntl扩展可用
        if (!$this->config['background_tasks']['enable'] || !function_exists('pcntl_fork')) {
            return;
        }
        
        // 创建子进程执行迁移检查
        $pid = pcntl_fork();
        
        if ($pid == -1) {
            // 创建进程失败，记录错误日志
            error_log("[PinyinConverter] 创建迁移检查子进程失败");
            return;
        } elseif ($pid == 0) {
            // 子进程：执行迁移检查并安全退出
            $this->executeMigrationInChildProcess();
        } else {
            // 父进程：非阻塞等待子进程，避免僵尸进程
            $this->handleParentProcess($pid);
        }
    }
    
    /**
     * 检查是否为CLI环境
     */
    private function isCliEnvironment(): bool {
        return php_sapi_name() === 'cli';
    }
    
    /**
     * 在子进程中执行迁移检查
     */
    private function executeMigrationInChildProcess() {
        // 子进程断开与父进程的连接
        if (function_exists('posix_setsid')) {
            posix_setsid();
        }
        
        // 执行迁移检查
        $this->checkAndExecuteMigration();
        
        // 安全退出子进程
        exit(0);
    }
    
    /**
     * 父进程处理逻辑
     */
    private function handleParentProcess(int $childPid) {
        // 设置信号处理，避免僵尸进程
        pcntl_signal(SIGCHLD, SIG_IGN);
        
        // 可选：记录子进程创建日志
        if ($this->config['background_tasks']['enable_logging'] ?? false) {
            error_log("[PinyinConverter] 创建迁移检查子进程，PID: {$childPid}");
        }
    }
    
    /**
     * 根据策略加载字典
     * @param string $strategy 加载策略
     * @param array $dictTypes 要加载的字典类型
     */
    private function loadDictsByStrategy(string $strategy, array $dictTypes) {
        switch ($strategy) {
            case 'with_tone':
                $this->loadDictsByType(true, $dictTypes);
                break;
            case 'no_tone':
                $this->loadDictsByType(false, $dictTypes);
                break;
            case 'both':
            default:
                $this->loadDictsByType(true, $dictTypes);
                $this->loadDictsByType(false, $dictTypes);
                break;
        }
    }
    
    /**
     * 检查并执行字典迁移（低频字符从常用字典迁移到生僻字字典）
     */
    private function checkAndExecuteMigration() {
        // 在测试环境中禁用字典迁移
        if ($this->isTestingEnvironment()) {
            return;
        }
        
        // 检查是否需要迁移
        $lastMigrationTime = $this->getLastMigrationTime();
        $currentTime = time();
        
        // 动态迁移频率：根据系统负载调整（24小时到72小时）
        $migrationFrequency = $this->getDynamicMigrationFrequency();
        
        if (($currentTime - $lastMigrationTime) >= $migrationFrequency) {
            foreach (['with_tone', 'no_tone'] as $toneType) {
                $this->migrateLowFrequencyChars($toneType);
            }
            $this->updateLastMigrationTime();
            
            // 记录迁移日志
            if ($this->config['background_tasks']['enable_logging'] ?? false) {
                error_log("[PinyinConverter] 字典迁移检查完成，下次检查时间: " . 
                         date('Y-m-d H:i:s', $currentTime + $migrationFrequency));
            }
        }
    }
    
    /**
     * 检查是否为测试环境
     */
    private function isTestingEnvironment(): bool {
        return defined('PHPUNIT_RUNNING') || 
               getenv('APP_ENV') === 'testing' ||
               getenv('PHP_ENV') === 'testing';
    }
    
    /**
     * 获取上次迁移时间
     * @return int 时间戳
     */
    private function getLastMigrationTime() {
        $path = $this->config['dict']['backup'] . "/last_migration.txt";
        return is_file_exists($path) ? (int)read_file_data($path) : 0;
    }
    
    /**
     * 更新迁移时间记录
     */
    private function updateLastMigrationTime() {
        $path = $this->config['dict']['backup'] . "/last_migration.txt";
        write_to_file($path, time());
    }

    /**
     * 按类型和优先级加载字典
     * @param bool $withTone 是否带声调
     * @param array $dictTypes 要加载的字典类型
     */
    private function loadDictsByType(bool $withTone, array $dictTypes) {
        foreach ($dictTypes as $dictType) {
            if (in_array($dictType, ['common', 'rare', 'unihan', 'custom'])) {
                $this->loadDictToRam($dictType, $withTone);
            }
        }
    }
    /**
     * 加载字典到内存
     * @param string $dictType 字典类型 如 common, rare, unihan, custom
     * @param bool $withTone 是否带声调
     */
    private function loadDictToRam($dictType, $withTone) {
        $type = $withTone ? 'with_tone' : 'no_tone';
        if ($this->dicts[$dictType][$type] !== null) {
            return;
        }
        $path = $this->config['dict'][$dictType][$type];
        // 注意这里加载的字典数据就是原始数据， 是格式化后的数据
        $data = is_file_exists($path) ? require_file($path) : [];
        
        // 确保数据是数组格式
        if (!is_array($data)) {
            $data = [];
        }

        $this->dicts[$dictType][$type] = $data;
    }
    
    /**
     * 懒加载字典（按需加载）
     * @param string $dictType 字典类型
     * @param bool $withTone 是否带声调
     */
    private function lazyLoadDict(string $dictType, bool $withTone) {
        if (!$this->config['dict_loading']['lazy_loading']) {
            // 当懒加载被禁用时，直接加载字典
            $this->loadDictsByType($withTone, [$dictType]);
            return;
        }
        
        // 检查是否为懒加载字典
        $lazyDicts = $this->config['dict_loading']['lazy_dicts'];
        if (in_array($dictType, $lazyDicts)) {
            $this->loadDictsByType($withTone, [$dictType]);
        }
    }

    /**
     * 初始化自定义多字词语缓存
     */
    private function initCustomMultiWords() {
        foreach (['with_tone', 'no_tone'] as $type) {
            $words = [];
            $customDict = $this->dicts['custom'][$type];
            
            // 确保自定义字典是数组
            if (!is_array($customDict)) {
                $this->customMultiWords[$type] = $words;
                continue;
            }
            
            foreach ($customDict as $word => $pinyin) {
                $wordLen = mb_strlen($word, 'UTF-8');
                if ($wordLen > 1 && trim($word) !== '') {
                    $words[] = [
                        'word' => $word,
                        'length' => $wordLen,
                        'pinyin' => $pinyin
                    ];
                }
            }
            usort($words, function ($a, $b) {
                return $b['length'] - $a['length'];
            });
            $this->customMultiWords[$type] = $words;
        }
    }

    /**
     * 加载自定义字典
     * @param bool $withTone 是否带声调
     */
    private function loadCustomDict($withTone) {
        $type = $withTone ? 'with_tone' : 'no_tone';
        if ($this->dicts['custom'][$type] !== null) {
            return;
        }
        $path = $this->config['dict']['custom'][$type];
        $data = is_file_exists($path) ? require_file($path) : [];
        $this->dicts['custom'][$type] = is_array($data) ? $data : [];
    }

    /**
     * 动态添加自定义拼音（区分单字/多字空格处理）
     * @param string $char 汉字/词语
     * @param array|string $pinyin 拼音
     * @param bool $withTone 是否带声调
     */
    public function addCustomPinyin($char, $pinyin, $withTone = false) {
        $type = $withTone ? 'with_tone' : 'no_tone';
        $this->loadCustomDict($withTone);
        $wordLen = mb_strlen($char, 'UTF-8');

        $pinyinArray = is_array($pinyin) ? $pinyin : [$pinyin];
        $pinyinArray = array_map(function($item) use ($wordLen) {
            $clean = preg_replace('/[^\p{L}\p{M} ]/u', '', $item);
            return pinyin_process_spaces($clean); // 简化处理，直接返回清理后的字符串
        }, $pinyinArray);

        $pinyinArray = array_filter($pinyinArray);
        if (empty($pinyinArray)) {
            throw new PinyinException("自定义拼音不能为空或包含无效字符", PinyinException::ERROR_INVALID_INPUT);
        }

        // 先更新内存中的字典数据，确保即时生效
        $this->dicts['custom'][$type][$char] = $pinyinArray;
        
        // 延迟写入：将修改记录到队列中，而不是立即写入文件
        $this->delayedWriteQueue[$type][$char] = $pinyinArray;
        $this->delayedWriteCount[$type]++;
        
        // 检查是否需要立即写入（达到阈值或超过时间间隔）
        $this->checkDelayedWrite($type);
        
        // 最后更新多字词语缓存
        $this->initCustomMultiWords();
        
        pinyin_debug("已添加自定义拼音：{$char} → " . implode('/', $pinyinArray), 'success');
    }

    /**
     * 检查并执行延迟写入
     * @param string $type 字典类型（with_tone/no_tone）
     */
    private function checkDelayedWrite($type) {
        $config = $this->config['custom_dict_persistence'];
        
        // 检查是否达到最大延迟条目数
        if ($this->delayedWriteCount[$type] >= $config['max_delayed_entries']) {
            $this->flushDelayedWrite($type);
            return;
        }
        
        // 检查是否超过自动保存间隔
        $currentTime = time();
        if (($currentTime - $this->lastSaveTime[$type]) >= $config['auto_save_interval']) {
            $this->flushDelayedWrite($type);
            return;
        }
    }

    /**
     * 执行延迟写入（将队列中的修改写入文件）
     * @param string $type 字典类型（with_tone/no_tone）
     */
    private function flushDelayedWrite($type) {
        if (empty($this->delayedWriteQueue[$type])) {
            return;
        }
        
        $path = $this->config['dict']['custom'][$type];
        write_to_file($path, "<?php\nreturn " . pinyin_compact_array_export($this->dicts['custom'][$type]) . ";\n");
        
        // 清空队列并更新最后保存时间
        $this->delayedWriteQueue[$type] = [];
        $this->delayedWriteCount[$type] = 0;
        $this->lastSaveTime[$type] = time();
        
        pinyin_debug("已执行延迟写入：{$type} 字典", 'success');
    }

    /**
     * 强制保存所有延迟写入
     */
    public function saveCustomDicts() {
        $this->flushDelayedWrite('with_tone');
        $this->flushDelayedWrite('no_tone');
    }

    /**
     * 删除自定义拼音
     * @param string $char 汉字/词语
     * @param bool $withTone 是否带声调
     */
    public function removeCustomPinyin($char, $withTone = false) {
        $type = $withTone ? 'with_tone' : 'no_tone';
        $this->loadCustomDict($withTone);

        if (isset($this->dicts['custom'][$type][$char])) {
            unset($this->dicts['custom'][$type][$char]);
            $path = $this->config['dict']['custom'][$type];
            write_to_file($path, "<?php\nreturn " . pinyin_compact_array_export($this->dicts['custom'][$type]) . ";\n");
            $this->initCustomMultiWords();
            pinyin_debug("已删除自定义拼音：{$char}", 'success');
        }
    }

    /**
     * 检查和修复自定义字典
     * @param bool $withTone 是否带声调
     * @param bool $autoFix 是否自动修复问题
     * @param bool $verbose 是否显示详细信息
     * @return array 检查结果
     */
    public function checkAndFixCustomDict($withTone = false, $autoFix = false, $verbose = false) {
        $type = $withTone ? 'with_tone' : 'no_tone';
        $this->loadCustomDict($withTone);
        
        $results = [
            'total_entries' => 0,
            'valid_entries' => 0,
            'issues_found' => 0,
            'issues_fixed' => 0,
            'details' => []
        ];
        
        $customDict = $this->dicts['custom'][$type];
        $results['total_entries'] = count($customDict);
        
        foreach ($customDict as $char => $pinyinArray) {
            $entryResults = $this->checkCustomDictEntry($char, $pinyinArray, $withTone);
            
            if ($entryResults['is_valid']) {
                $results['valid_entries']++;
            } else {
                $results['issues_found']++;
                
                if ($autoFix) {
                    $fixResult = $this->fixCustomDictEntry($char, $pinyinArray, $withTone);
                    if ($fixResult['fixed']) {
                        $results['issues_fixed']++;
                        $entryResults['fix_applied'] = $fixResult['new_pinyin'];
                    }
                }
            }
            
            if ($verbose || !$entryResults['is_valid']) {
                $results['details'][] = $entryResults;
            }
        }
        
        // 如果进行了修复，保存字典
        if ($autoFix && $results['issues_fixed'] > 0) {
            $path = $this->config['dict']['custom'][$type];
            write_to_file($path, "<?php\nreturn " . pinyin_compact_array_export($this->dicts['custom'][$type]) . ";\n");
            $this->initCustomMultiWords();
        }
        
        return $results;
    }
    
    /**
     * 检查单个自定义字典条目
     * @param string $char 汉字/词语
     * @param array $pinyinArray 拼音数组
     * @param bool $withTone 是否带声调
     * @return array 检查结果
     */
    private function checkCustomDictEntry($char, $pinyinArray, $withTone) {
        $result = [
            'char' => $char,
            'pinyin' => $pinyinArray,
            'is_valid' => true,
            'issues' => [],
            'suggestions' => []
        ];
        
        // 1. 检查字符是否为空
        if (empty(trim($char))) {
            $result['is_valid'] = false;
            $result['issues'][] = '字符为空';
            $result['suggestions'][] = '删除该条目';
        }
        
        // 2. 检查拼音数组是否为空
        if (empty($pinyinArray)) {
            $result['is_valid'] = false;
            $result['issues'][] = '拼音数组为空';
            $result['suggestions'][] = '删除该条目或添加有效拼音';
        }
        
        // 3. 检查每个拼音的有效性
        foreach ($pinyinArray as $index => $pinyin) {
            $pinyinIssues = pinyin_validate($pinyin, $withTone);
            if (!empty($pinyinIssues)) {
                $result['is_valid'] = false;
                $result['issues'] = array_merge($result['issues'], $pinyinIssues);
                $result['suggestions'][] = "修正第" . ($index + 1) . "个拼音: {$pinyin}";
            }
        }
        
        // 4. 检查重复定义（在不同字典中）
        $duplicateSources = $this->checkDuplicateDefinition($char, $withTone);
        if (!empty($duplicateSources)) {
            $result['issues'][] = "在其他字典中存在重复定义: " . implode(', ', $duplicateSources);
            $result['suggestions'][] = '考虑删除自定义定义，使用系统默认拼音';
        }
        
        // 5. 检查拼音格式一致性
        if (count($pinyinArray) > 1) {
            $formatConsistency = check_pinyin_format_consistency($pinyinArray, $withTone);
            if (!$formatConsistency['consistent']) {
                $result['is_valid'] = false;
                $result['issues'][] = '多音字拼音格式不一致';
                $result['suggestions'][] = '统一拼音格式: ' . implode(' 或 ', $formatConsistency['suggestions']);
            }
        }
        
        return $result;
    }
    
    /**
     * 检查重复定义
     * @param string $char 汉字/词语
     * @param bool $withTone 是否带声调
     * @return array 重复来源列表
     */
    private function checkDuplicateDefinition($char, $withTone) {
        $type = $withTone ? 'with_tone' : 'no_tone';
        $duplicateSources = [];
        
        // 检查常用字典
        if ($this->dicts['common'][$type] !== null && isset($this->dicts['common'][$type][$char])) {
            $duplicateSources[] = '常用字典';
        }
        
        // 检查生僻字字典
        if ($this->dicts['rare'][$type] !== null && isset($this->dicts['rare'][$type][$char])) {
            $duplicateSources[] = '生僻字字典';
        }
        
        // 检查自学习字典
        if ($this->dicts['self_learn'][$type] !== null && isset($this->dicts['self_learn'][$type][$char])) {
            $duplicateSources[] = '自学习字典';
        }
        
        return $duplicateSources;
    }
    
    
    
    /**
     * 修复自定义字典条目
     * @param string $char 汉字/词语
     * @param array $pinyinArray 拼音数组
     * @param bool $withTone 是否带声调
     * @return array 修复结果
     */
    private function fixCustomDictEntry($char, $pinyinArray, $withTone) {
        $type = $withTone ? 'with_tone' : 'no_tone';
        $result = ['fixed' => false, 'new_pinyin' => null];
        
        // 如果字符为空，删除该条目
        if (empty(trim($char))) {
            unset($this->dicts['custom'][$type][$char]);
            $result['fixed'] = true;
            return $result;
        }
        
        // 如果拼音数组为空，删除该条目
        if (empty($pinyinArray)) {
            unset($this->dicts['custom'][$type][$char]);
            $result['fixed'] = true;
            return $result;
        }
        
        // 修复每个拼音
        $fixedPinyinArray = [];
        foreach ($pinyinArray as $pinyin) {
            $fixedPinyin = pinyin_normalize_format($pinyin, $withTone);
            if (!empty(trim($fixedPinyin))) {
                $fixedPinyinArray[] = $fixedPinyin;
            }
        }
        
        // 如果修复后仍有有效拼音，更新字典
        if (!empty($fixedPinyinArray)) {
            $this->dicts['custom'][$type][$char] = $fixedPinyinArray;
            $result['fixed'] = true;
            $result['new_pinyin'] = $fixedPinyinArray;
        } else {
            // 如果没有有效拼音，删除该条目
            unset($this->dicts['custom'][$type][$char]);
            $result['fixed'] = true;
        }
        
        return $result;
    }
    /**
     * 加载字词频率数据
     */
    private function loadFrequency() {
        if ($this->dicts['frequency'] !== null) {
            return;
        }
        $path = $this->config['dict']['frequency'];
        $data = is_file_exists($path) ? require_file($path) : [];
        $this->dicts['frequency'] = is_array($data) ? $data : [];
        $this->charFrequency = $this->dicts['frequency'];
    }

    /**
     * 保存字词频率数据
     */
    private function saveFrequency() {
        $path = $this->config['dict']['frequency'];
        write_to_file($path, "<?php\nreturn " . pinyin_compact_array_export($this->charFrequency) . ";\n");
        $this->dicts['frequency'] = $this->charFrequency;
    }

    /**
     * 加载上次合并时间记录
     */
    private function loadLastMergeTime() {
        $this->lastMergeTime = [
            'with_tone' => $this->getLastMergeTimeFile('with_tone'),
            'no_tone' => $this->getLastMergeTimeFile('no_tone')
        ];
    }

    /**
     * 获取指定声调类型的上次合并时间
     * @param string $toneType 声调类型
     * @return int 时间戳
     */
    private function getLastMergeTimeFile($toneType) {
        $path = $this->config['dict']['backup'] . "/last_merge_{$toneType}.txt";
        return is_file_exists($path) ? (int)read_file_data($path) : 0;
    }

    /**
     * 更新合并时间记录
     * @param string $toneType 声调类型
     */
    private function updateLastMergeTime($toneType) {
        $now = time();
        $path = $this->config['dict']['backup'] . "/last_merge_{$toneType}.txt";
        write_to_file($path, $now);
        $this->lastMergeTime[$toneType] = $now;
    }

    /**
     * 检查是否允许合并
     * @param string $toneType 声调类型
     * @return bool 是否允许
     */
    private function canMerge($toneType) {
        $now = time();
        $lastTime = $this->lastMergeTime[$toneType];
        return ($now - $lastTime) >= $this->config['self_learn_merge']['frequency_limit'];
    }

    /**
     * 备份字典文件
     * @param string $type 字典类型
     * @param string $toneType 声调类型
     */
    private function backupDict($type, $toneType) {
        if (!$this->config['self_learn_merge']['backup_before_merge']) {
            return;
        }
        $sourcePath = $this->config['dict'][$type][$toneType];
        if (!is_file_exists($sourcePath)) {
            return;
        }
        $backupDir = $this->config['dict']['backup'];
        $filename = basename($sourcePath, '.php') . '_' . date('YmdHis') . '.php';
        copy_file($sourcePath, $backupDir . '/' . $filename);
    }

    /**
     * 检查是否需要合并自学习字典
     */
    public function checkMergeNeed() {
        $needMerge = [];
        foreach (['with_tone', 'no_tone'] as $toneType) {
            // 确保字典已加载
            if ($this->dicts['self_learn'][$toneType] === null) {
                $this->loadSelfLearnDict($toneType === 'with_tone');
            }
            $selfLearnCount = count($this->dicts['self_learn'][$toneType]);
            if ($selfLearnCount >= $this->config['self_learn_merge']['threshold'] && $this->canMerge($toneType)) {
                $needMerge[$toneType] = true;
            }
        }
        if (!empty($needMerge)) {
            error_log("[PinyinConverter] 需要合并的字典：" . implode(',', array_keys($needMerge)));
        }
        return !empty($needMerge);
    }

    /**
     * 执行自学习字典合并
     * @return array 合并结果
     */
    public function executeMerge() {
        $result = ['success' => [], 'fail' => []];
        foreach (['with_tone', 'no_tone'] as $toneType) {
            try {
                $selfLearnCount = count($this->dicts['self_learn'][$toneType]);
                if ($selfLearnCount < $this->config['self_learn_merge']['threshold'] || !$this->canMerge($toneType)) {
                    continue;
                }

                $mergeCount = $this->config['self_learn_merge']['incremental']
                    ? min($selfLearnCount - $this->config['self_learn_merge']['threshold'] + 1, $this->config['self_learn_merge']['max_per_merge'])
                    : $selfLearnCount;

                $this->mergeToCommonDict($toneType, $mergeCount);
                $this->cleanupAfterMerge($toneType, $mergeCount);
                $this->updateLastMergeTime($toneType);
                $result['success'][] = $toneType;
            } catch (PinyinException $e) {
                $result['fail'][] = [
                    'toneType' => $toneType,
                    'error' => $e->getMessage()
                ];
            }
        }
        return $result;
    }

    /**
     * 将自学习字典内容合并到常用字典
     * @param string $toneType 声调类型
     * @param int $mergeCount 合并条目数
     */
    private function mergeToCommonDict($toneType, $mergeCount) {
        $this->backupDict('common', $toneType);
        $this->backupDict('self_learn', $toneType);

        $commonPath = $this->config['dict']['common'][$toneType];
        $commonData = require_file($commonPath);
        // 确保 $commonData 是数组类型
        if (!is_array($commonData)) {
            $commonData = [];
        }
        $commonData = pinyin_format_array($commonData);
        
        $selfLearnData = $this->dicts['self_learn'][$toneType];
        $sortedChars = $this->sortSelfLearnByFrequency($selfLearnData, $toneType);

        $mergedChars = [];
        foreach ($sortedChars as $char) {
            if (count($mergedChars) >= $mergeCount) {
                break;
            }
            if (!isset($commonData[$char])) {
                $commonData[$char] = $selfLearnData[$char];
                $mergedChars[] = $char;
                
                // 同步更新另一个声调类型的字典
                $this->syncToOtherToneType($char, $toneType);
            }
        }

        if ($this->config['self_learn_merge']['sort_by_frequency']) {
            $commonData = $this->sortCommonDictByFrequency($commonData, $toneType);
        }

        write_to_file($commonPath, "<?php\nreturn " . pinyin_compact_array_export($commonData) . ";\n");
        $this->dicts['common'][$toneType] = $commonData;
    }
    
    /**
     * 同步更新另一个声调类型的字典
     * @param string $char 汉字
     * @param string $sourceToneType 源声调类型
     */
    private function syncToOtherToneType($char, $sourceToneType) {
        $targetToneType = $sourceToneType === 'with_tone' ? 'no_tone' : 'with_tone';
        
        // 检查目标字典中是否已存在该字符
        $targetCommonPath = $this->config['dict']['common'][$targetToneType];
        $targetCommonData = require_file($targetCommonPath);
        // 确保 $targetCommonData 是数组类型
        if (!is_array($targetCommonData)) {
            $targetCommonData = [];
        }
        $targetCommonData = pinyin_format_array($targetCommonData);
        
        if (isset($targetCommonData[$char])) {
            return; // 目标字典中已存在，无需同步
        }
        
        // 从源字典获取拼音并转换为目标声调类型
        $sourcePinyin = $this->dicts['self_learn'][$sourceToneType][$char] ?? null;
        if (!$sourcePinyin) {
            return; // 源字典中没有该字符的拼音
        }
        
        // 转换拼音到目标声调类型
        $targetPinyin = $this->convertPinyinTone($sourcePinyin, $sourceToneType, $targetToneType, $char);
        
        if (!empty($targetPinyin)) {
            // 添加到目标字典
            $targetCommonData[$char] = $targetPinyin;
            
            // 保存目标字典
            write_to_file($targetCommonPath, "<?php\nreturn " . pinyin_compact_array_export($targetCommonData) . ";\n");
            $this->dicts['common'][$targetToneType] = $targetCommonData;
        }
    }
    

    
    /**
     * 查找带声调的拼音
     * @param string $char 汉字
     * @param string $noTonePinyin 无声调拼音
     * @return string|null 带声调的拼音
     */
    private function findPinyinWithTone($char, $noTonePinyin) {
        // 1. 首先尝试从常用字典查找
        if ($this->dicts['common']['with_tone'] !== null) {
            foreach ($this->dicts['common']['with_tone'] as $commonChar => $pinyinArray) {
                if ($commonChar === $char) {
                    foreach ($pinyinArray as $pinyin) {
                        $noToneVersion = remove_tone($pinyin);
                        if ($noToneVersion === $noTonePinyin) {
                            return $pinyin; // 找到匹配的带声调拼音
                        }
                    }
                }
            }
        }
        
        // 2. 尝试从生僻字字典查找
        if ($this->dicts['rare']['with_tone'] !== null) {
            foreach ($this->dicts['rare']['with_tone'] as $rareChar => $pinyinArray) {
                if ($rareChar === $char) {
                    foreach ($pinyinArray as $pinyin) {
                        $noToneVersion = remove_tone($pinyin);
                        if ($noToneVersion === $noTonePinyin) {
                            return $pinyin;
                        }
                    }
                }
            }
        }
        
        // 3. 尝试从Unihan字典查询（如果可用）
        $unihanPinyin = $this->queryUnihanForPinyin($char);
        if ($unihanPinyin) {
            $noToneVersion = remove_tone($unihanPinyin);
            if ($noToneVersion === $noTonePinyin) {
                return $unihanPinyin;
            }
        }
        
        // 4. 使用拼音声调规则库（如果可用）
        $ruleBasedPinyin = $this->applyPinyinToneRules($char, $noTonePinyin);
        if ($ruleBasedPinyin) {
            return $ruleBasedPinyin;
        }
        
        // 5. 如果都找不到，返回null表示需要人工干预
        return null;
    }
    
    /**
     * 从Unihan字典查询拼音
     * @param string $char 汉字
     * @return string|null 带声调的拼音
     */
    private function queryUnihanForPinyin($char) {
        // 检查Unihan字典文件是否存在
        $unihanFiles = [
            __DIR__.'/../data/unihan/all_unihan_pinyin.php'
        ];
        
        foreach ($unihanFiles as $file) {
            if (is_file_exists($file)) {
                $unihanData = require_file($file);
                // 确保 $unihanData 是数组类型
                if (!is_array($unihanData)) {
                    continue;
                }
                if (isset($unihanData[$char])) {
                    $pinyin = is_array($unihanData[$char]) ? $unihanData[$char][0] : $unihanData[$char];
                    return $pinyin;
                }
            }
        }
        
        return null;
    }
    
    /**
     * 应用拼音声调规则
     * @param string $char 汉字
     * @param string $noTonePinyin 无声调拼音
     * @return string|null 带声调的拼音
     */
    private function applyPinyinToneRules($char, $noTonePinyin) {
        // 这里可以添加拼音声调规则库
        // 例如：基于汉字部首、笔画数、常见读音等规则
        
        // 简单的规则：基于常见多音字的默认声调
        $commonToneRules = [
            '行' => ['xíng', 'háng'],
            '长' => ['cháng', 'zhǎng'],
            '乐' => ['lè', 'yuè'],
            '重' => ['zhòng', 'chóng'],
            '中' => ['zhōng', 'zhòng'],
            '为' => ['wéi', 'wèi'],
            '和' => ['hé', 'hè', 'huó', 'huò'],
            '着' => ['zhe', 'zháo', 'zhuó'],
            '了' => ['le', 'liǎo'],
            '得' => ['de', 'dé', 'děi']
        ];
        
        if (isset($commonToneRules[$char])) {
            foreach ($commonToneRules[$char] as $withTonePinyin) {
                if (remove_tone($withTonePinyin) === $noTonePinyin) {
                    return $withTonePinyin;
                }
            }
        }
        
        // 更复杂的规则：基于拼音音节和常见声调分布
        return $this->applyStatisticalToneRules($char, $noTonePinyin);
    }
    
    /**
     * 应用统计声调规则
     * @param string $char 汉字
     * @param string $noTonePinyin 无声调拼音
     * @return string|null 带声调的拼音
     */
    private function applyStatisticalToneRules($char, $noTonePinyin) {
        // 基于汉字使用频率和声调分布的统计规则
        // 这里可以扩展为更复杂的机器学习模型
        
        // 简单的统计：为每个无声调拼音分配最常见的声调
        $commonToneDistribution = [
            'a' => 'ā', 'o' => 'ō', 'e' => 'ē', 'i' => 'ī', 'u' => 'ū', 'v' => 'ǖ',
            'ai' => 'āi', 'ei' => 'ēi', 'ao' => 'āo', 'ou' => 'ōu',
            'an' => 'ān', 'en' => 'ēn', 'ang' => 'āng', 'eng' => 'ēng',
            'er' => 'ēr', 'yi' => 'yī', 'wu' => 'wū', 'yu' => 'yū'
        ];
        
        // 尝试为无声调拼音添加最常见的声调
        foreach ($commonToneDistribution as $noTone => $withTone) {
            if ($noTonePinyin === $noTone) {
                return $withTone;
            }
        }
        
        // 如果无法确定，返回null
        return null;
    }
    
    /**
     * 改进的拼音声调转换方法
     * @param array $sourcePinyin 源拼音数组
     * @param string $sourceToneType 源声调类型
     * @param string $targetToneType 目标声调类型
     * @param string $char 汉字（用于查找正确的声调）
     * @return array 转换后的拼音数组
     */
    private function convertPinyinTone($sourcePinyin, $sourceToneType, $targetToneType, $char) {
        if ($sourceToneType === $targetToneType) {
            return $sourcePinyin; // 相同类型，无需转换
        }
        
        $convertedPinyin = [];
        foreach ($sourcePinyin as $pinyin) {
            if ($sourceToneType === 'with_tone' && $targetToneType === 'no_tone') {
                // 带声调转无声调：去除声调符号（简单可靠）
                $convertedPinyin[] = remove_tone($pinyin);
            } else if ($sourceToneType === 'no_tone' && $targetToneType === 'with_tone') {
                // 无声调转带声调：需要复杂的查找逻辑
                $withTonePinyin = $this->findPinyinWithTone($char, $pinyin);
                if ($withTonePinyin) {
                    $convertedPinyin[] = $withTonePinyin;
                } else {
                    // 如果找不到正确的声调，记录日志并保留无声调版本
                    error_log("[PinyinConverter] 无法为汉字 '{$char}' 找到正确的声调，拼音: {$pinyin}");
                    $convertedPinyin[] = $pinyin; // 保留原拼音，可能需要人工干预
                }
            }
        }
        
        return array_unique($convertedPinyin);
    }

    /**
     * 按使用频率排序自学习汉字
     * @param array $selfLearnData 自学习数据
     * @param string $toneType 声调类型
     * @return array 排序后的汉字列表
     */
    private function sortSelfLearnByFrequency($selfLearnData, $toneType) {
        $chars = array_keys($selfLearnData);
        usort($chars, function ($a, $b) {
            $freqA = $this->charFrequency[$a] ?? 0;
            $freqB = $this->charFrequency[$b] ?? 0;
            return $freqB - $freqA;
        });
        return $chars;
    }

    /**
     * 按使用频率排序常用字典
     * @param array $commonData 常用字典数据
     * @param string $toneType 声调类型
     * @return array 排序后的数据
     */
    private function sortCommonDictByFrequency($commonData, $toneType) {
        $chars = array_keys($commonData);
        usort($chars, function ($a, $b) {
            $freqA = $this->charFrequency[$a] ?? 0;
            $freqB = $this->charFrequency[$b] ?? 0;
            return $freqB - $freqA;
        });
        $sorted = [];
        foreach ($chars as $char) {
            $sorted[$char] = $commonData[$char];
        }
        return $sorted;
    }

    /**
     * 合并后清理自学习字典
     * @param string $toneType 声调类型
     * @param int $mergeCount 合并条目数
     */
    private function cleanupAfterMerge($toneType, $mergeCount) {
        $withTone = $toneType === 'with_tone';
        $selfLearnData = $this->dicts['self_learn'][$toneType];
        $sortedChars = $this->sortSelfLearnByFrequency($selfLearnData, $toneType);
        $charsToClean = array_slice($sortedChars, 0, $mergeCount);

        foreach ($charsToClean as $char) {
            unset($selfLearnData[$char]);
        }
        $selfLearnPath = $this->config['dict']['self_learn'][$toneType];
        write_to_file($selfLearnPath, "<?php\nreturn " . pinyin_compact_array_export($selfLearnData) . ";\n");
        $this->dicts['self_learn'][$toneType] = $selfLearnData;
        $this->learnedChars[$toneType] = array_diff_key($this->learnedChars[$toneType], array_flip($charsToClean));

        foreach ($charsToClean as $char) {
            unset($this->charFrequency[$toneType][$char]);
        }
        $this->saveFrequency();
    }

    /**
     * 获取动态迁移频率
     * @return int 迁移频率（秒）
     */
    private function getDynamicMigrationFrequency() {
        // 使用配置中的频率限制作为基础频率
        $baseFrequency = $this->config['self_learn_merge']['frequency_limit'] ?? 86400;
        
        // 根据系统负载调整频率
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg()[0] ?? 0;
            if ($load > 2.0) {
                // 高负载时延长到基础频率的3倍
                return $baseFrequency * 3;
            } elseif ($load > 1.0) {
                // 中等负载时延长到基础频率的2倍
                return $baseFrequency * 2;
            }
        }
        
        return $baseFrequency;
    }
    
    /**
     * 迁移常用字典中调用频率低的字符到生僻字字典
     * @param string $toneType 声调类型
     */
    private function migrateLowFrequencyChars($toneType) {
        $commonPath = $this->config['dict']['common'][$toneType];
        $commonData = require_file($commonPath);
        // 确保 $commonData 是数组类型
        if (!is_array($commonData)) {
            $commonData = [];
        }
  
        $rarePath = $this->config['dict']['rare'][$toneType];
        $rareData = is_file_exists($rarePath) ? require_file($rarePath) : [];
        // 确保 $rareData 是数组类型
        if (!is_array($rareData)) {
            $rareData = [];
        }
        
        // 计算常用字典中每个字符的平均频率
        $totalFrequency = 0;
        $charCount = count($commonData);
        
        foreach ($commonData as $char => $pinyin) {
            $freq = $this->charFrequency[$char] ?? 0;
            $totalFrequency += $freq;
        }
        
        $averageFrequency = $charCount > 0 ? $totalFrequency / $charCount : 0;
        
        // 迁移频率低于平均值的字符到生僻字字典（使用配置化的阈值）
        $migratedChars = [];
        $migrationThreshold = $this->config['self_learn_merge']['migration_threshold'] ?? 0.3;
        $minFrequency = $this->config['self_learn_merge']['min_migration_frequency'] ?? 10;
        
        foreach ($commonData as $char => $pinyin) {
            $freq = $this->charFrequency[$char] ?? 0;
            // 使用配置化的阈值：低于平均值一定比例且频率小于最小迁移频率的字符才迁移
            if ($freq < $averageFrequency * $migrationThreshold && $freq < $minFrequency) {
                $rareData[$char] = $pinyin;
                $migratedChars[] = $char;
            }
        }
        
        // 从常用字典中删除迁移的字符
        foreach ($migratedChars as $char) {
            unset($commonData[$char]);
        }
        
        // 保存更新后的字典
        if (!empty($migratedChars)) {
            write_to_file($commonPath, "<?php\nreturn " . pinyin_compact_array_export($commonData) . ";\n");
            write_to_file($rarePath, "<?php\nreturn " . pinyin_compact_array_export($rareData) . ";\n");
            
            // 更新内存中的字典数据
            $this->dicts['common'][$toneType] = $commonData;
            $this->dicts['rare'][$toneType] = $rareData;
            
            // 记录迁移日志
            $this->logMigration($migratedChars, $toneType);
        }
    }
    
    /**
     * 记录迁移日志
     * @param array $migratedChars 迁移的字符
     * @param string $toneType 声调类型
     */
    private function logMigration($migratedChars, $toneType) {
        $logFile = $this->config['dict']['backup'] . '/migration_log.json';
        $log = [];
        
        if (is_file_exists($logFile)) {
            $content = read_file_data($logFile);
            $log = json_decode($content, true) ?: [];
        }
        
        $log[] = [
            'timestamp' => time(),
            'tone_type' => $toneType,
            'migrated_chars' => $migratedChars,
            'migrated_count' => count($migratedChars)
        ];
        
        write_to_file($logFile, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * 加载多音字规则字典
     */
    private function loadPolyphoneRules() {
        if ($this->dicts['polyphone_rules'] !== null) {
            return;
        }
        $path = $this->config['dict']['polyphone_rules'];
        $data = is_file_exists($path) ? require_file($path) : [];
        $this->dicts['polyphone_rules'] = is_array($data) ? $data : [];
    }

    /**
     * 加载未找到拼音的字符文件
     */
    private function loadNotFoundChars() {
        if ($this->dicts['not_found'] !== null) {
            return;
        }
        $path = $this->config['dict']['not_found'];
        $data = is_file_exists($path) ? require_file($path) : [];
        $this->dicts['not_found'] = is_array($data) ? $data : [];
    }

    /**
     * 加载自学习字典
     * @param bool $withTone 是否带声调
     */
    private function loadSelfLearnDict($withTone) {
        $type = $withTone ? 'with_tone' : 'no_tone';
        if ($this->dicts['self_learn'][$type] !== null) {
            return;
        }
        $path = $this->config['dict']['self_learn'][$type];
        $data = is_file_exists($path) ? require_file($path) : [];
        $this->dicts['self_learn'][$type] = is_array($data) ? $data : [];
    }

    /**
     * 获取单个汉字的拼音（单字去空格，多字保留）
     * @param string $char 汉字
     * @param bool $withTone 是否带声调
     * @param array $context 上下文
     * @param array $tempMap 临时映射
     * @return string 拼音
     */
    private function getCharPinyin($char, $withTone, $context = [], $tempMap = [])
    {
        $type = $withTone ? 'with_tone' : 'no_tone';
        
        // 这里直接使用特殊字符中的 delete_allow  配置项, 既 数字/字母和允许的特殊字符直接返回
        // 但排除非汉字字符，让汉字进入拼音转换流程
        if (preg_match('/^['.$this->config['special_char']['delete_allow'].']+$/', $char) && !PinyinConstants::isInChineseRange($char, 'full')) {
            return $char;
        }

        // 临时映射（单字处理）- 最高优先级
        if (isset($tempMap[$char])) {
            return clean_pinyin($tempMap[$char], true);
        }

        // 多音字规则检查 - 第二优先级（基于上下文的智能选择）
        $this->loadPolyphoneRules();
        
        if (isset($this->dicts['polyphone_rules'][$char])) {
            // 先获取所有可能的拼音选项用于规则匹配
            $pinyinArray = $this->getAllPinyinOptions($char, $withTone);
            $matchedPinyin = $this->matchPolyphoneRule($char, $pinyinArray, $context, $withTone);
            
            if ($matchedPinyin !== null) {
                // 根据withTone参数决定是否去除声调
                if (!$withTone && preg_match('/[āáǎàēéěèīíǐìōóǒòūúǔùǖǘǚǜü]/u', $matchedPinyin)) {
                    $matchedPinyin = remove_tone($matchedPinyin);
                }
                
                return clean_pinyin($matchedPinyin, true);
            }
        }

        // 自定义字典（区分单字/多字）- 第三优先级
        if ($this->dicts['custom'][$type] === null) {
            $this->lazyLoadDict('custom', $withTone);
        }
        
        if (isset($this->dicts['custom'][$type][$char])) {
            $pinyin = get_first_pinyin($this->dicts['custom'][$type][$char]);
            return clean_pinyin($pinyin, mb_strlen($char, 'UTF-8') === 1);
        }
        
        // 其他字典（按照common_xxx, rare_xxx的顺序）
        $pinyinArray = $this->getAllPinyinOptions($char, $withTone);
        $pinyin = get_first_pinyin($pinyinArray);

        // 根据withTone参数决定是否去除声调
        if (!$withTone && preg_match('/[āáǎàēéěèīíǐìōóǒòūúǔùǖǘǚǜü]/u', $pinyin)) {
            $pinyin = remove_tone($pinyin);
        }
        
        // 修复：如果没有找到拼音，返回汉字本身
        $result = !empty(trim($pinyin)) ? clean_pinyin($pinyin, true) : $char;
        
        // 更新字符使用频率（仅在成功获取拼音时）
        if ($result !== $char && preg_match('/^[a-zāáǎàōóǒòēéěèīíǐìūúǔùüǖǘǚǜ]+$/i', $result)) {
            $this->updateCharFrequency($char, $type);
        }
        
        return $result;
    }
    
    /**
     * 获取所有可能的拼音选项
     * @param string $char 汉字
     * @param bool $withTone 是否带声调
     * @return array 拼音数组
     */
    private function getAllPinyinOptions($char, $withTone) {
        $type = $withTone ? 'with_tone' : 'no_tone';

        // 1. 自定义字典 - 最高优先级
        if ($this->dicts['custom'][$type] === null) {
            $this->lazyLoadDict('custom', $withTone);
        }
        if (isset($this->dicts['custom'][$type][$char])) {
            $pinyin = $this->dicts['custom'][$type][$char];
            return pinyin_parse_options($pinyin);
        }

        // 2. 多音字规则检查
        $this->loadPolyphoneRules();
        if (isset($this->dicts['polyphone_rules'][$char])) {
            // 先获取其他字典的拼音选项用于规则匹配
            $otherPinyinOptions = $this->getOtherPinyinOptions($char, $withTone);
            $matchedPinyin = $this->matchPolyphoneRule($char, $otherPinyinOptions, [], $withTone);
            if ($matchedPinyin !== null) {
                return [$matchedPinyin];
            }
        }

        // 3. 常用字典 来自Unihan数据库的CJK基本汉字 20923个汉字 的前3500个汉字, 这个会在使用过程中通过后台任务根据调用频率动态调整
        if ($this->dicts['common'][$type] === null) {
            $this->loadDictToRam('common',$withTone);
        }
        if (isset($this->dicts['common'][$type][$char])) {
            $pinyin = $this->dicts['common'][$type][$char];
            return pinyin_parse_options($pinyin);
        }

        // 4. 自定义生僻字字典（并记录到自学习字典）- 懒加载  注意这里的生僻字典是来自Unihan数据库的CJK基本汉字 20923个汉字
        // 这个字典在使用过程中也是通过后台任务根据调用频率动态调整 有可能会加入其他的生僻字 如一些历史字 罕见字 等
        if ($this->dicts['rare'][$type] === null) {
            $this->lazyLoadDict('rare', $withTone);
        }
        if (isset($this->dicts['rare'][$type][$char])) {
            $rawPinyin = $this->dicts['rare'][$type][$char];
            // 记录生僻字到自学习字典（但不立即加载自学习字典）
            $this->migrateToSelfLearn($char, $rawPinyin, $withTone, 'rare');
            return pinyin_parse_options($rawPinyin);
        }

        // 5. unihan字典 这个字典的规则同 4自定义生僻字字典, 不同的是这个字典里面的数据来源unicode官方,使用过程不会做调整
        // 但是会将这里的字记录到自学习字典里面 然后通过后台任务动态调整到常用或者4的字典中
        if ($this->dicts['unihan'][$type] === null) {
            $this->lazyLoadDict('unihan', $withTone);
        }
        if (isset($this->dicts['unihan'][$type][$char])) {
            $rawPinyin = $this->dicts['unihan'][$type][$char];
            // 记录生僻字到自学习字典（但不立即加载自学习字典）
            $this->migrateToSelfLearn($char, $rawPinyin, $withTone, 'unihan');
           return pinyin_parse_options($rawPinyin);
           // return is_array($rawPinyin) ? $rawPinyin : [$rawPinyin]; // 简化处理，直接返回拼音数组

        }
        // 6. 基础映射表（作为最后的兜底）
        if (isset($this->basicPinyinMap[$char])) {
            return $withTone ? [$this->basicPinyinMap[$char][0]] : [$this->basicPinyinMap[$char][1]];
        }

        // 7. 在所有字典中都找不到的字符，保存到未找到字符文件
        $this->saveNotFoundChar($char);

        return [$char];
    }

    /**
     * 获取除自定义字典外的其他拼音选项（用于多音字规则匹配）
     * @param string $char 汉字
     * @param bool $withTone 是否带声调
     * @return array 拼音数组
     */
    private function getOtherPinyinOptions($char, $withTone) {
        $type = $withTone ? 'with_tone' : 'no_tone';
        $options = [];

        // 自学习字典
        if ($this->dicts['self_learn'][$type] === null) {
            $this->loadSelfLearnDict($withTone);
        }
        if (isset($this->dicts['self_learn'][$type][$char])) {
            $options = array_merge($options, pinyin_parse_options($this->dicts['self_learn'][$type][$char]));
        }

        // 常用字典
        if ($this->dicts['common'][$type] === null) {
            $this->loadDictToRam('common',$withTone);
        }
        if (isset($this->dicts['common'][$type][$char])) {
            $options = array_merge($options, pinyin_parse_options($this->dicts['common'][$type][$char]));
        }

        // 生僻字字典
        if ($this->dicts['rare'][$type] === null) {
            $this->lazyLoadDict('rare', $withTone);
        }
        if (isset($this->dicts['rare'][$type][$char])) {
            $options = array_merge($options, pinyin_parse_options($this->dicts['rare'][$type][$char]));
        }

        // 基础映射表
        if (isset($this->basicPinyinMap[$char])) {
            $options[] = $withTone ? $this->basicPinyinMap[$char][0] : $this->basicPinyinMap[$char][1];
        }

        return array_unique($options);
    }

    /**
     * 智能多音字规则匹配（增强版）
     * @param string $char 汉字
     * @param array $pinyinArray 拼音选项
     * @param array $context 上下文
     * @param bool $withTone 是否带声调
     * @return string|null 匹配的拼音
     */
    private function matchPolyphoneRule($char, $pinyinArray, $context, $withTone) {
        $rules = $this->dicts['polyphone_rules'][$char] ?? [];
        if (empty($rules)) {
            return null;
        }
    
        $prevChar = $context['prev'] ?? '';
        $nextChar = $context['next'] ?? '';
        $word = $context['word'] ?? '';
        $fullText = $context['full_text'] ?? '';
        $position = $context['position'] ?? 0;
        
        $bestMatch = null;
        $bestScore = 0;
    
        foreach ($rules as $rule) {
            $ruleType = $rule['type'] ?? '';
            $target = $rule['char'] ?? $rule['word'] ?? '';
            $rulePinyin = $rule['pinyin'] ?? '';
            $ruleWeight = $rule['weight'] ?? 1.0;
            $rulePattern = $rule['pattern'] ?? '';
    
            if (empty($rulePinyin)) {
                continue;
            }
            
            // 修复：当不需要声调时，先移除规则拼音中的声调再进行匹配
            $checkPinyin = $withTone ? $rulePinyin : remove_tone($rulePinyin);
            if (!in_array($checkPinyin, $pinyinArray)) {
                continue;
            }
            
            $score = 0;
            
            // 1. 精确匹配规则
            if ($ruleType === 'word' && $word === $target) {
                $score = 1.0 * $ruleWeight;
            } elseif ($ruleType === 'post' && $nextChar === $target) {
                $score = 0.8 * $ruleWeight;
            } elseif ($ruleType === 'pre' && $prevChar === $target) {
                $score = 0.8 * $ruleWeight;
            }
            
            // 2. 正则表达式模式匹配
            if (!empty($rulePattern) && $fullText) {
                if (preg_match($rulePattern, $fullText)) {
                    $score = max($score, 0.9 * $ruleWeight);
                }
            }
            
            // 3. 基于位置的规则（句首、句尾等）
            if ($ruleType === 'sentence_start' && $position === 0) {
                $score = max($score, 0.7 * $ruleWeight);
            } elseif ($ruleType === 'sentence_end' && $position === mb_strlen($fullText) - 1) {
                $score = max($score, 0.7 * $ruleWeight);
            }
            
            // 4. 基于频率统计的规则
            if ($ruleType === 'frequency' && isset($this->charFrequency[$char])) {
                $freqStats = $this->charFrequency[$char];
                if (isset($freqStats[$rulePinyin])) {
                    $freqScore = $freqStats[$rulePinyin] / array_sum($freqStats);
                    $score = max($score, $freqScore * $ruleWeight);
                }
            }
            
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $rulePinyin;
            }
        }
        
        // 设置匹配阈值，避免低分匹配
        return $bestScore >= 0.5 ? $bestMatch : null;
    }
    
    /**
     * 添加智能多音字规则
     * @param string $char 汉字
     * @param array $rule 规则配置
     */
    public function addPolyphoneRule($char, array $rule) {
        $this->loadPolyphoneRules();
        
        if (!isset($this->dicts['polyphone_rules'][$char])) {
            $this->dicts['polyphone_rules'][$char] = [];
        }
        
        // 验证规则格式
        if (!$this->validatePolyphoneRule($rule)) {
            throw new PinyinException("多音字规则格式无效", PinyinException::ERROR_INVALID_INPUT);
        }
        
        $this->dicts['polyphone_rules'][$char][] = $rule;
        
        // 保存到文件
        $path = $this->config['dict']['polyphone_rules'];
        write_to_file($path, "<?php\nreturn " . pinyin_compact_array_export($this->dicts['polyphone_rules']) . ";\n");
    }
    
    /**
     * 验证多音字规则格式
     * @param array $rule 规则配置
     * @return bool 是否有效
     */
    private function validatePolyphoneRule(array $rule): bool {
        $required = ['type', 'pinyin'];
        foreach ($required as $field) {
            if (!isset($rule[$field]) || empty($rule[$field])) {
                return false;
            }
        }
        
        $validTypes = ['word', 'pre', 'post', 'sentence_start', 'sentence_end', 'frequency', 'pattern'];
        if (!in_array($rule['type'], $validTypes)) {
            return false;
        }
        
        // 验证拼音格式
        if (!is_valid_pinyin($rule['pinyin'])) {
            return false;
        }
        
        return true;
    }
    /**
     * 记录自学习字典的数据来源
     * @param string $char 汉字
     * @param string $sourceType 来源类型（'rare' 表示来自生僻字字典）
     * @param string $toneType 声调类型
     */
    private function logSelfLearnSource($char, $sourceType, $toneType) {
        $sourceLogFile = $this->config['dict']['backup'] . '/self_learn_sources.json';
        $sources = [];
        
        if (is_file_exists($sourceLogFile)) {
            $content = read_file_data($sourceLogFile);
            $sources = json_decode($content, true) ?: [];
        }
        
        $key = $char . '_' . $toneType;
        if (!isset($sources[$key])) {
            $sources[$key] = [
                'char' => $char,
                'tone_type' => $toneType,
                'source' => $sourceType,
                'learned_at' => date('Y-m-d H:i:s'),
                'frequency' => 0
            ];
        }
        
        write_to_file($sourceLogFile, json_encode($sources, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    /**
     * 将生僻字动态迁移到自学习字典
     * @param string $char 汉字
     * @param array|string $rawPinyin 拼音
     * @param bool $withTone 是否带声调
     */
    private function migrateToSelfLearn($char, $rawPinyin, $withTone, $sourceType='rare') {
        $type = $withTone ? 'with_tone' : 'no_tone';
        
        // 检查是否已经在自学习字典中
        if (isset($this->dicts['self_learn'][$type][$char]) || isset($this->learnedChars[$type][$char])) {
            return;
        }
        
        // 将生僻字添加到自学习字典
        $pinyinArray = is_array($rawPinyin) ? $rawPinyin : [$rawPinyin];
        if (!$withTone) {
            $pinyinArray = array_map([$this, 'removeTone'], $pinyinArray);
        }
        
        $this->learnedChars[$type][$char] = $pinyinArray;
        $this->dicts['self_learn'][$type][$char] = $pinyinArray;
        $this->charFrequency[$char] = 1; // 初始频率设为1
        
        // 记录来源
        $this->logSelfLearnSource($char, $sourceType, $type);
        
        // 检查是否需要立即合并到常用字典（高频生僻字）
        $this->checkImmediateMerge($char, $type);
    }
    
    /**
     * 检查是否需要立即合并到常用字典
     * @param string $char 汉字
     * @param string $type 声调类型
     */
    private function checkImmediateMerge($char, $type) {
        // 如果字符使用频率达到阈值，立即合并到常用字典
        $frequencyThreshold = $this->config['self_learn_merge']['immediate_threshold'] ?? 10;
        
        $currentFrequency = $this->charFrequency[$char] ?? 0;
        if ($currentFrequency >= $frequencyThreshold) {
            $this->immediateMergeToCommon($char, $type);
        }
    }
    
    /**
     * 立即将高频自学习字合并到常用字典
     * @param string $char 汉字
     * @param string $type 声调类型
     */
    private function immediateMergeToCommon($char, $type) {
        if (!isset($this->dicts['self_learn'][$type][$char])) {
            return;
        }
        
        // 备份字典
        $this->backupDict('common', $type);
        $this->backupDict('self_learn', $type);
        
        // 加载常用字典
        $commonPath = $this->config['dict']['common'][$type];
        $commonData = require_file($commonPath);
        // 确保 $commonData 是数组类型
        if (!is_array($commonData)) {
            $commonData = [];
        }
        
        // 如果常用字典中还没有这个字，就添加进去
        if (!isset($commonData[$char])) {
            $commonData[$char] = $this->dicts['self_learn'][$type][$char];
            
            // 保存更新后的常用字典
            write_to_file($commonPath, "<?php\nreturn " . pinyin_compact_array_export($commonData) . ";\n");
            $this->dicts['common'][$type] = $commonData;
            
            // 从自学习字典中移除
            unset($this->dicts['self_learn'][$type][$char]);
            unset($this->learnedChars[$type][$char]);
            
            // 保存更新后的自学习字典
            $selfLearnPath = $this->config['dict']['self_learn'][$type];
            write_to_file($selfLearnPath, "<?php\nreturn " . pinyin_compact_array_export($this->dicts['self_learn'][$type]) . ";\n");
            
            // 记录合并日志
            $this->logImmediateMerge($char, $type);
        }
    }
    
    /**
     * 记录立即合并日志
     * @param string $char 汉字
     * @param string $type 声调类型
     */
    private function logImmediateMerge($char, $type) {
        $mergeLogFile = $this->config['dict']['backup'] . '/immediate_merge_log.json';
        $log = [];
        
        if (is_file_exists($mergeLogFile)) {
            $content = read_file_data($mergeLogFile);
            $log = json_decode($content, true) ?: [];
        }
        
        $log[] = [
            'char' => $char,
            'tone_type' => $type,
            'merged_at' => date('Y-m-d H:i:s'),
            'frequency' => $this->charFrequency[$type][$char] ?? 0
        ];
        
        write_to_file($mergeLogFile, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * 更新字符/词语使用频率（统计所有转换的字词）
     * @param string $char 汉字字符或词语
     * @param string $type 拼音类型（'with_tone' 或 'no_tone'）
     */
    private function updateCharFrequency($char, $type) {
        // 确保频率数据已加载
        $this->loadFrequency();
        
        // 统计所有字和词语的使用频率，不区分声调
        // key为字或词本身，value为调用次数
        if (!isset($this->charFrequency[$char])) {
            $this->charFrequency[$char] = 0;
        }
        $this->charFrequency[$char]++;
        
        // 标记频率数据已修改，需要在析构函数中保存
        $this->frequencyModified = true;
    }

    /**
     * 创建后台任务
     * @param string $taskType 任务类型
     * @param array $taskData 任务数据
     * @param int $priority 优先级
     * @return bool 是否成功
     */
    private function createBackgroundTask(string $taskType, array $taskData, int $priority = 5): bool
    {
        if (!$this->config['background_tasks']['enable']) {
            return false;
        }
        
        try {
            $taskManager = new BackgroundTaskManager($this->config['background_tasks']);
            return $taskManager->createTask($taskType, $taskData, $priority);
        } catch (\Exception $e) {
            error_log("[PinyinConverter] 创建后台任务失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 保存未找到拼音的字符到文件
     * @param string $char 未找到拼音的字符
     */
    private function saveNotFoundChar($char) {
        $this->loadNotFoundChars();
        
        // 如果字符已经存在，则不重复保存
        if (in_array($char, $this->dicts['not_found'])) {
            return;
        }
        
        // 添加到缓存
        $this->notFoundChars[] = $char;
        $this->dicts['not_found'][] = $char;
        
        // 保存到文件
        $path = $this->config['dict']['not_found'];
        $existing = require_file($path);
        $existing = is_array($existing) ? $existing : [];
        
        // 去重并保存
        $merged = array_unique(array_merge($existing, $this->notFoundChars));
        write_to_file($path, "<?php\nreturn " . pinyin_compact_array_export($merged) . ";\n");
        
        // 创建后台任务记录
        $this->createBackgroundTask('not_found_resolve', ['char' => $char]);
        
        // 清空缓存，避免重复保存
        $this->notFoundChars = [];
    }

    /**
     * 保存自学习内容到文件
     */
    private function saveLearnedChars() {
        foreach (['with_tone', 'no_tone'] as $type) {
            if (empty($this->learnedChars[$type])) {
                continue;
            }
            $path = $this->config['dict']['self_learn'][$type];
            $existing = require_file($path);
            // 确保 $existing 是数组类型
            if (!is_array($existing)) {
                $existing = [];
            }
            $merged = array_merge($existing, $this->learnedChars[$type]);
            write_to_file($path, "<?php\nreturn " . pinyin_compact_array_export($merged) . ";\n");
            $this->dicts['self_learn'][$type] = $merged;
            $this->learnedChars[$type] = [];
        }
        $this->saveFrequency();
        $this->checkMergeNeed();
    }

    /**
     * 移除拼音中的声调
     * @param string $pinyin 带声调拼音
     * @return string 无声调拼音
     */
    private function removeTone($pinyin) {
        return remove_tone($pinyin);
    }

    /**
     * 处理特殊字符（过滤残留）
     * @param string $char 特殊字符
     * @param array $charConfig 处理配置
     * @return string 处理后的字符
     */
    private function handleSpecialChar($char, $charConfig) {
        $mode = $charConfig['mode'];
        $customMap = $charConfig['map'];
    
        // 基本汉字/数字/字母 连字符 - _ 直接返回（优先级最高）
        if (preg_match('#^['.PinyinConstants::FULL_CHINESE_RANGE.'0-9a-zA-Z_-]$#u', $char)) {
            return $char;
        }
    
        switch ($mode) {
            case 'delete':
                $deleteAllow = $this->config['special_char']['delete_allow'];
                return preg_match("/^[{$deleteAllow}]$/", $char) ? $char : '';
            case 'keep':
                return $char;
            case 'replace':  // 处理 replace 模式：替换指定符号，删除未指定的符号
                 // 如果用户通过参数指定了map，只使用该map
                if (!empty($customMap)) {
                    $replaced = $customMap[$char] ?? null;
                    return $replaced !== null ? $replaced : $char;
                }
                // 如果用户没有指定map，使用系统默认映射
                $replaced = $this->finalCharMap[$char] ?? null;
                return $replaced !== null ? $replaced : $char;
            default:
                return '';
        }
    }

    /**
     * 解析特殊字符处理参数
     * @param string|array $specialCharParam 处理参数
     * @return array 标准化配置
     */
    private function parseCharParam($specialCharParam) {
        $defaultMode = $this->config['special_char']['default_mode'];
        if (is_string($specialCharParam)) {
            return [
                'mode' => in_array($specialCharParam, ['keep', 'delete', 'replace']) ? $specialCharParam : $defaultMode,
                'map' => []
            ];
        }
        if (is_array($specialCharParam)) {
            return [
                'mode' => isset($specialCharParam['mode']) && in_array($specialCharParam['mode'], ['keep', 'delete', 'replace'])
                    ? $specialCharParam['mode']
                    : $defaultMode,
                'map' => isset($specialCharParam['map']) && is_array($specialCharParam['map'])
                    ? $specialCharParam['map']
                    : []
            ];
        }
        return ['mode' => $defaultMode, 'map' => []];
    }

    /**
     * 替换自定义多字词语为拼音（保留字间空格）
     * @param string $text 原始文本
     * @param bool $withTone 是否带声调
     * @param string $separator 分隔符
     * @return array [替换后的文本, 已处理的词语集合]
     */
    private function replaceCustomMultiWords($text, $withTone, $separator) {
        $type = $withTone ? 'with_tone' : 'no_tone';
        $result = $text;
        $processedWords = [];

        foreach ($this->customMultiWords[$type] as $item) {
            $word = $item['word'];
            if (in_array($word, $processedWords)) continue;
            
            // 检查文本中是否包含该词语
            if (strpos($result, $word) !== false) {
                $pinyin = get_first_pinyin($item['pinyin']);
                // 将拼音中的空格替换为实际分隔符
                $processedPinyin = str_replace(' ', $separator, $pinyin);
                // 使用特殊标记来保护拼音字符串不被后续处理拆分
                $protectedPinyin = "[[CUSTOM_PINYIN:{$processedPinyin}]]";
                
                // 优化：使用一个正则表达式处理所有边界情况
                $result = preg_replace_callback(
                    '/([a-zA-Z0-9]?)(' . preg_quote($word, '/') . ')([a-zA-Z0-9]?)/u',
                    function($matches) use ($separator, $protectedPinyin) {
                        $before = $matches[1];
                        $after = $matches[3];
                        
                        if ($before && $after) {
                            return $before . $separator . $protectedPinyin . $separator . $after;
                        } elseif ($before) {
                            return $before . $separator . $protectedPinyin;
                        } elseif ($after) {
                            return $protectedPinyin . $separator . $after;
                        } else {
                            return $protectedPinyin;
                        }
                    },
                    $result
                );
                
                // 记录自定义词语的使用频率
                $this->updateCharFrequency($word, $type);
                
                $processedWords[] = $word;
            }
        }

        return [$result, $processedWords];
    }



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
        string $text,
        string $separator = ' ',
        bool $withTone = false,
        $specialCharParam = [],
        array $polyphoneTempMap = []
    ): string {
        // 输入类型验证
        if (!is_string($text)) {
            throw new InvalidArgumentException('Input text must be a string');
        }
        
        // 增加转换计数
        $this->totalConversions++;
        
        $charConfig = $this->parseCharParam($specialCharParam);
        // 仅在 delete 模式下执行预处理（避免干扰 replace 模式）
        if ($charConfig['mode'] === 'delete') {
            // 删除模式下，只保留纯中文, 字母拼音数字字符和用户定义的允许的特殊字符 
            $text = preg_replace('/[^'. PinyinConstants::FULL_CHINESE_RANGE .  $this->config['special_char']['delete_allow'].']+/u', ' ', $text);
        }

        $cacheKey = md5(json_encode([$text, $separator, $withTone, $charConfig, $polyphoneTempMap]));

        // 缓存检查
        if (isset($this->cache[$cacheKey])) {
            // 移到数组末尾，实现LRU策略
            $value = $this->cache[$cacheKey];
            unset($this->cache[$cacheKey]);
            $this->cache[$cacheKey] = $value;
            return $value;
        }

        // 多字词语替换
        list($textAfterMultiWords, $processedWords) = $this->replaceCustomMultiWords($text, $withTone, $separator);
        
        // 检查是否已经完成了自定义多字词语的替换
        if (!preg_match(PinyinConstants::getChinesePattern('full'), $textAfterMultiWords)) {
            $textAfterMultiWords = str_replace(['[[CUSTOM_PINYIN:', ']]'], '', $textAfterMultiWords);
            $textAfterMultiWords = str_replace('[[SEPARATOR]]', $separator, $textAfterMultiWords);
            return $textAfterMultiWords;
        }

        // 处理自定义多字词语的保护标记
        $result = [];
        $customPinyinMatches = [];
        
        preg_match_all('/\[\[CUSTOM_PINYIN:([^\]]+)\]\]/', $textAfterMultiWords, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $customPinyinMatches[$match[0]] = $match[1];
        }
        
        if (!empty($customPinyinMatches)) {
            $textAfterMultiWords = preg_replace_callback(
                '/(\[\[CUSTOM_PINYIN:[^\]]+\]\])([^\p{Han}])/u',
                function($matches) {
                    return $matches[1] . '[[SEPARATOR]]' . $matches[2];
                },
                $textAfterMultiWords
            );
            
            $parts = preg_split('/(\[\[CUSTOM_PINYIN:[^\]]+\]\])/', $textAfterMultiWords, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            
            $previousWasCustomPinyin = false;
            
            foreach ($parts as $part) {
                if (isset($customPinyinMatches[$part])) {
                    if ($previousWasCustomPinyin) {
                        $result[] = '';
                    }
                    $result[] = $customPinyinMatches[$part];
                    $previousWasCustomPinyin = true;
                } else {
                    $len = mb_strlen($part, 'UTF-8');
                    $currentWord = ''; 

                    if ($previousWasCustomPinyin && $len > 0) {
                        $result[] = '';
                    }
                    $previousWasCustomPinyin = false;
                    
                    if (strpos($part, '[[SEPARATOR]]') !== false) {
                        $part = str_replace('[[SEPARATOR]]', '', $part);
                        $result[] = '';
                    }
                    
                    $this->processTextPart($part, $withTone, $charConfig, $polyphoneTempMap, $result);
                }
            }
        } else {
            // 没有自定义拼音标记的情况
            $this->processTextPart($textAfterMultiWords, $withTone, $charConfig, $polyphoneTempMap, $result);
        }

        // 过滤空值并拼接
        $filtered = array_filter($result, function ($item) {
            return $item !== '';
        });
        $finalResult = implode($separator, $filtered);
        
        $finalResult = str_replace('[[SEPARATOR]]', $separator, $finalResult);

        // 合并连续分隔符
        if ($separator !== '') {
            $finalResult = preg_replace(
                '/' . preg_quote($separator, '/') . '+/',
                $separator,
                $finalResult
            );
        }

        //$finalResult = str_replace('%', '', $finalResult);

        // 如果需要带声调，转换为数字声调格式
        if ($withTone) {
            $finalResult = convert_to_number_tone($finalResult);
        }
        
        // 缓存结果
        $this->cache[$cacheKey] = $finalResult;
        
        // 维护LRU顺序并控制缓存大小
        if (count($this->cache) > $this->config['high_freq_cache']['size']) {
            // 删除第一个元素（最旧的）
            reset($this->cache);
            unset($this->cache[key($this->cache)]);
        }

        return $finalResult;
    }
    
    /**
     * 处理文本部分，提取重复的字符处理逻辑
     * @param string $text 要处理的文本部分
     * @param bool $withTone 是否保留声调
     * @param array $charConfig 字符配置
     * @param array $polyphoneTempMap 临时多音字映射表
     * @param array &$result 结果数组（引用传递）
     * @param string &$currentWord 累积单词（引用传递）
     */
    private function processTextPart($text, $withTone, $charConfig, $polyphoneTempMap, &$result) {
        $len = mb_strlen($text, 'UTF-8');
        $currentWord = ''; // 在方法内部定义变量
        $specialCharsBuffer = ''; // 新增：特殊字符缓冲区
        
        // 预处理：获取所有字符
        $chars = [];
        for ($i = 0; $i < $len; $i++) {
            $chars[] = mb_substr($text, $i, 1, 'UTF-8');
        }
        
        for ($i = 0; $i < $len; $i++) {
            $char = $chars[$i];
            $prevChar = $i > 0 ? $chars[$i - 1] : '';
            $nextChar = $i < $len - 1 ? $chars[$i + 1] : '';
            
            // 检测是否为汉字
            $isHan = preg_match(PinyinConstants::getChinesePattern('full'), $char) ? true : false;
    
            if ($isHan) {
                // 处理累积的特殊字符
                if ($specialCharsBuffer !== '') {
                    $result[] = $specialCharsBuffer;
                    $specialCharsBuffer = '';
                }
                
                if ($currentWord !== '') {
                    $result[] = $currentWord;
                    $currentWord = '';
                    if ($i > 0 && !preg_match(PinyinConstants::getChinesePattern('full'), $prevChar)) {
                        $result[] = '';
                    }
                }
                
                $context = [
                    'prev' => $prevChar,
                    'next' => $nextChar,
                    'word' => $prevChar . $char . $nextChar
                ];
                $pinyin = $this->getCharPinyin($char, $withTone, $context, $polyphoneTempMap);
                $result[] = $pinyin;
            } else {
                // 所有非汉字字符强制走 handleSpecialChar
                $handled = $this->handleSpecialChar($char, $charConfig);
                
                if ($handled !== '') {
                    // 字母数字及允许的符号累积为单词，其他特殊字符累积到特殊字符缓冲区
                    if (preg_match('/^[\\p{L}\\p{N}]+$/u', $handled) || $handled === '-' || $handled === '.') {
                        // 先处理之前的特殊字符缓冲区
                        if ($specialCharsBuffer !== '') {
                            $result[] = $specialCharsBuffer;
                            $specialCharsBuffer = '';
                        }
                        $currentWord .= $handled;
                    } else {
                        // 处理累积单词
                        if ($currentWord !== '') {
                            $result[] = $currentWord;
                            $currentWord = '';
                        }
                        $specialCharsBuffer .= $handled; // 添加到特殊字符缓冲区，而不是直接添加到结果数组
                    }
                } else {
                    // 处理累积单词
                    if ($currentWord !== '') {
                        $result[] = $currentWord;
                        $currentWord = '';
                    }
                    // 处理累积的特殊字符
                    if ($specialCharsBuffer !== '') {
                        $result[] = $specialCharsBuffer;
                        $specialCharsBuffer = '';
                    }
                }
            }
        }
        
        // 处理剩余的特殊字符
        if ($specialCharsBuffer !== '') {
            $result[] = $specialCharsBuffer;
        }
        
        if ($currentWord !== '') {
            $result[] = $currentWord;
        }
    }
    

    /**
     * 批量转换文本数组为拼音
     * @param array $texts 文本数组
     * @param string $separator 拼音分隔符
     * @param bool $withTone 是否保留声调
     * @param array|string $specialCharParam 特殊字符处理参数
     * @return array 转换后的拼音数组
     */
    public function batchConvert(array $texts, string $separator = ' ', bool $withTone = false, $specialCharParam = []): array
    {
        $results = [];
        foreach ($texts as $text) {
            $results[] = $this->convert($text, $separator, $withTone, $specialCharParam);
        }
        return $results;
    }

    /**
     * 智能缓存管理（LRU淘汰策略）
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     */
    private function smartCacheSet($key, $value) {
        $maxSize = $this->config['high_freq_cache']['size'];
        
        // 如果缓存已满，淘汰最久未使用的项
        if (count($this->cache) >= $maxSize && !isset($this->cache[$key])) {
            // 找到最久未使用的键
            $oldestKey = null;
            $oldestTime = PHP_INT_MAX;
            
            foreach ($this->cacheAccessTime as $cacheKey => $accessTime) {
                if ($accessTime < $oldestTime) {
                    $oldestTime = $accessTime;
                    $oldestKey = $cacheKey;
                }
            }
            
            if ($oldestKey !== null) {
                unset($this->cache[$oldestKey]);
                unset($this->cacheAccessTime[$oldestKey]);
            }
        }
        
        $this->cache[$key] = $value;
        $this->cacheAccessTime[$key] = time();
    }
    
    /**
     * 智能缓存获取
     * @param string $key 缓存键
     * @return mixed|null 缓存值
     */
    private function smartCacheGet($key) {
        if (isset($this->cache[$key])) {
            // 更新访问时间
            $this->cacheAccessTime[$key] = time();
            return $this->cache[$key];
        }
        return null;
    }
    
    /**
     * 清理过期缓存
     * @param int $ttl 缓存生存时间（秒）
     */
    public function clearExpiredCache($ttl = 3600) {
        $currentTime = time();
        foreach ($this->cacheAccessTime as $key => $accessTime) {
            if ($currentTime - $accessTime > $ttl) {
                unset($this->cache[$key]);
                unset($this->cacheAccessTime[$key]);
            }
        }
    }

    /**
     * 性能监控和优化建议
     * @return array 性能分析报告
     */
    public function getPerformanceReport(): array {
        $report = [
            'cache_efficiency' => $this->analyzeCacheEfficiency(),
            'memory_usage' => $this->analyzeMemoryUsage(),
            'dict_loading' => $this->analyzeDictLoading(),
            'optimization_suggestions' => $this->generateOptimizationSuggestions(),
            'execution_time' => microtime(true) - $this->startTime,
            'timestamp' => time()
        ];
        
        return $report;
    }
    
    /**
     * 分析缓存效率
     * @return array 缓存效率分析
     */
    private function analyzeCacheEfficiency(): array {
        $totalAccesses = count($this->cacheAccessTime);
        $recentAccesses = 0;
        $currentTime = time();
        
        foreach ($this->cacheAccessTime as $accessTime) {
            if ($currentTime - $accessTime < 3600) { // 1小时内
                $recentAccesses++;
            }
        }
        
        $hitRate = $totalAccesses > 0 ? ($recentAccesses / $totalAccesses) * 100 : 0;
        
        // 使用配置化的缓存效率阈值
        $highThreshold = $this->config['high_freq_cache']['efficiency_high'] ?? 70;
        $mediumThreshold = $this->config['high_freq_cache']['efficiency_medium'] ?? 40;
        
        return [
            'total_entries' => count($this->cache),
            'recent_hit_rate' => round($hitRate, 2),
            'cache_size_limit' => $this->config['high_freq_cache']['size'],
            'efficiency_level' => $hitRate > $highThreshold ? 'high' : ($hitRate > $mediumThreshold ? 'medium' : 'low')
        ];
    }
    
    /**
     * 分析内存使用情况
     * @return array 内存使用分析
     */
    private function analyzeMemoryUsage(): array {
        $memoryInfo = [];
        
        foreach ($this->dicts as $dictType => $toneTypes) {
            if (is_array($toneTypes)) {
                foreach ($toneTypes as $toneType => $data) {
                    if ($data !== null) {
                        $memoryInfo[$dictType][$toneType] = count($data);
                    }
                }
            }
        }
        
        $totalEntries = array_sum(array_map('count', array_filter($this->dicts, 'is_array')));
        
        return [
            'total_dict_entries' => $totalEntries,
            'dict_details' => $memoryInfo,
            'cache_entries' => count($this->cache),
            'estimated_memory_mb' => round($totalEntries * 0.1 + count($this->cache) * 0.05, 2) // 估算值
        ];
    }
    
    /**
     * 分析字典加载情况
     * @return array 字典加载分析
     */
    private function analyzeDictLoading(): array {
        $loadedDicts = [];
        $lazyDicts = [];
        
        foreach ($this->dicts as $dictType => $toneTypes) {
            if (is_array($toneTypes)) {
                $loaded = false;
                foreach ($toneTypes as $toneType => $data) {
                    if ($data !== null) {
                        $loaded = true;
                        break;
                    }
                }
                
                if ($loaded) {
                    $loadedDicts[] = $dictType;
                } elseif (in_array($dictType, $this->config['dict_loading']['lazy_dicts'])) {
                    $lazyDicts[] = $dictType;
                }
            }
        }
        
        return [
            'loaded_dicts' => $loadedDicts,
            'lazy_dicts' => $lazyDicts,
            'loading_strategy' => $this->config['dict_loading']['strategy'],
            'lazy_loading_enabled' => $this->config['dict_loading']['lazy_loading']
        ];
    }
    
    /**
     * 生成优化建议
     * @return array 优化建议列表
     */
    private function generateOptimizationSuggestions(): array {
        $suggestions = [];
        
        // 缓存优化建议
        $cacheEfficiency = $this->analyzeCacheEfficiency();
        if ($cacheEfficiency['efficiency_level'] === 'low') {
            $suggestions[] = [
                'type' => 'cache',
                'priority' => 'high',
                'suggestion' => '考虑增加缓存大小或优化缓存策略',
                'details' => '当前缓存命中率较低，可能影响性能'
            ];
        }
        
        // 内存优化建议
        $memoryUsage = $this->analyzeMemoryUsage();
        if ($memoryUsage['estimated_memory_mb'] > 50) {
            $suggestions[] = [
                'type' => 'memory',
                'priority' => 'medium',
                'suggestion' => '考虑启用懒加载策略减少内存占用',
                'details' => '当前内存使用较高，懒加载可以按需加载字典'
            ];
        }
        
        // 字典加载优化建议
        $dictLoading = $this->analyzeDictLoading();
        if (!$dictLoading['lazy_loading_enabled'] && count($dictLoading['loaded_dicts']) > 3) {
            $suggestions[] = [
                'type' => 'loading',
                'priority' => 'medium',
                'suggestion' => '启用懒加载策略提升启动速度',
                'details' => '当前全量加载字典，启动较慢'
            ];
        }
        
        return $suggestions;
    }

    /**
     * 获取拼音统计信息
     * @param bool $retJson 是否返回json格式
     * @return string|array 统计信息
     */
    public function getStatistics($retJson=false)
    {
        $stats = [
            'total_conversions' => $this->totalConversions ?? 0,
            'dictionaries' => [],
            'cache' => [
                'size' => count($this->cache),
                'max_size' => $this->config['high_freq_cache']['size']
            ],
            'learning' => [
                'learned_chars' => array_sum(array_map('count', $this->learnedChars)),
                'self_learn_size' => array_sum(array_map('count', $this->dicts['self_learn'])),
                'not_found_chars' => count($this->dicts['not_found'] ?? [])
            ]
        ];

        // 统计各字典大小
        foreach (['common', 'rare', 'custom', 'unihan'] as $dictType) {
            foreach (['with_tone', 'no_tone'] as $toneType) {
                $size = count($this->dicts[$dictType][$toneType] ?? []);
                if ($size > 0) {
                    $stats['dictionaries'][$dictType][$toneType] = $size;
                }
            }
        }

        return $retJson?json_encode($stats):$stats;
    }

    /**
     * 搜索拼音匹配的汉字
     * @param string $pinyin 拼音
     * @param bool $exactMatch 是否精确匹配
     * @param int $limit 返回结果数量限制
     * @return array 匹配的汉字数组
     */
    public function searchByPinyin(string $pinyin, bool $exactMatch = true, int $limit = 10): array
    {
        $results = [];
        $searchPinyin =pinyin_normalize_format($pinyin, false);
        
        // 搜索所有字典
        foreach (['common', 'rare', 'custom'] as $dictType) {
            foreach (['with_tone', 'no_tone'] as $toneType) {
                if ($this->dicts[$dictType][$toneType] === null) {
                    $this->loadDictToRam($dictType, $toneType === 'with_tone');
                }
                
                foreach ($this->dicts[$dictType][$toneType] as $char => $pinyinArray) {
                    $pinyinOptions = pinyin_parse_options($pinyinArray);
                    
                    foreach ($pinyinOptions as $option) {
                        $normalizedOption = pinyin_normalize_format($option, false);
                        
                        if ($exactMatch) {
                            if ($normalizedOption === $searchPinyin) {
                                $results[] = $char;
                                break;
                            }
                        } else {
                            $similarity = pinyin_similarity($normalizedOption, $searchPinyin);
                            $similarityThreshold = $this->config['search']['similarity_threshold'] ?? 0.7;
                            if ($similarity > $similarityThreshold) {
                                $results[] = ['char' => $char, 'similarity' => $similarity];
                                break;
                            }
                        }
                    }
                    
                    if (count($results) >= $limit) {
                        break 3;
                    }
                }
            }
        }
        
        return array_slice($results, 0, $limit);
    }

    /**
     * 转换为URL Slug
     * @param string $text 文本
     * @param string $separator 分隔符
     * @return string URL Slug
     */
    public function getUrlSlug($text, $separator = '-') {
        $separator = $separator ?: '-';

        // 这里直接使用系统环境变量 ENABLE_CHINESE_TO_ARABIC 判断是否启用中文数字转阿拉伯数字功能，默认启用
        $enableChineseToArabic = getenv('ENABLE_CHINESE_TO_ARABIC') ?? true;
        if ($enableChineseToArabic) {
            // 中文数字转阿拉伯数字
            $text = chinese_number_to_arabic($text);
        }
        
        // 对于包含特殊字符的文本，先预处理特殊字符 ，将所有非字母、数字、空格的字符替换为分隔符
        // \p{L} 匹配 所有语言的字母（包括中文、日文、俄文等 Unicode 字母）
        // \p{N} 匹配 所有语言的数字（如阿拉伯数字、中文数字等），覆盖范围极广。
        $processedText = preg_replace('/[^\p{L}\p{N}\s]/u', $separator, $text);
        
        // 修复：对于纯英文或纯数字文本，先保留空格作为单词分隔符
        if (preg_match('/^[a-zA-Z\s]+$/', $processedText) || preg_match('/^[0-9\s]+$/', $processedText)) {
            // 纯英文或纯数字文本，将空格转换为分隔符
            $pinyin = strtolower($processedText);
            $pinyin = preg_replace('/\s+/', $separator, $pinyin);
        } else {
            // 混合文本，使用正常的转换逻辑
            $pinyin = $this->convert($processedText, $separator, false, 'delete');
            // 确保只保留字母、数字和分隔符
            $pinyin = preg_replace('/[^a-z0-9' . preg_quote($separator, '/') . ']/i', '', $pinyin);
            $pinyin = strtolower($pinyin);
        }
        
        // 修复：正确处理连续分隔符和首尾分隔符
        $pinyin = trim(preg_replace('/' . preg_quote($separator, '/') . '+/', $separator, $pinyin), $separator);
        
        return $pinyin;
    }

    /**
     * 析构函数：保存自学习内容和频率数据
     */
    public function __destruct() {
        if ($this->config['custom_dict_persistence']['save_on_destruct']) {
            $this->saveCustomDicts();
        }
        
        $this->saveLearnedChars();
        
        // 保存频率数据（如果已修改）
        if ($this->frequencyModified) {
            $this->saveFrequency();
        }
    }
}