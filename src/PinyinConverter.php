<?php
namespace tekintian\pinyin;

use tekintian\pinyin\Contracts\ConverterInterface;
use tekintian\pinyin\Exception\PinyinException;
use tekintian\pinyin\Utils\FileUtil;

/**
 * AI自学习汉字转拼音工具
 * 支持：特殊字符精准处理+自定义替换+灵活参数传递
 */
class PinyinConverter implements ConverterInterface {
    /**
     * 配置参数
     * @var array
     */
    private $config = [
        'dict' => [
            'common' => [
                'with_tone' => __DIR__.'/../data/common_with_tone.php',
                'no_tone' => __DIR__.'/../data/common_no_tone.php'
            ],
            'rare' => [
                'with_tone' => __DIR__.'/../data/rare_with_tone.php',
                'no_tone' => __DIR__.'/../data/rare_no_tone.php'
            ],
            'self_learn' => [
                'with_tone' => __DIR__.'/../data/self_learn_with_tone.php',
                'no_tone' => __DIR__.'/../data/self_learn_no_tone.php',
                'frequency' => __DIR__.'/../data/self_learn_frequency.php'
            ],
            'custom' => [
                'with_tone' => __DIR__.'/../data/custom_with_tone.php',
                'no_tone' => __DIR__.'/../data/custom_no_tone.php'
            ],
            'polyphone_rules' => __DIR__.'/../data/polyphone_rules.php',
            'backup' => __DIR__.'/../data/backup/'
        ],
        'special_char' => [
            'default_mode' => 'delete',
            'default_map' => [
                '，' => ',', '。' => '.', '！' => '!', '？' => '?',
                '（' => '(', '）' => ')', '【' => '[', '】' => ']',
                '、' => ',', '；' => ';', '：' => ':'
            ],
            'delete_allow' => 'a-zA-Z0-9_\-+.'
        ],
        'high_freq_cache' => ['size' => 1000],
        'polyphone_priority' => ['行' => 0, '长' => 0, '乐' => 0],
        'self_learn_merge' => [
            'threshold' => 1000,
            'incremental' => true,
            'max_per_merge' => 500,
            'frequency_limit' => 86400,
            'backup_before_merge' => true,
            'sort_by_frequency' => true
        ]
    ];

    /**
     * 字典数据缓存
     * @var array
     */
    private $dicts = [
        'common' => ['with_tone' => null, 'no_tone' => null],
        'rare' => ['with_tone' => null, 'no_tone' => null],
        'self_learn' => ['with_tone' => null, 'no_tone' => null],
        'self_learn_frequency' => null,
        'custom' => ['with_tone' => null, 'no_tone' => null],
        'polyphone_rules' => null
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
     * 自学习字使用频率计数
     * @var array
     */
    private $charFrequency = [];

    /**
     * 上次合并时间记录
     * @var array
     */
    private $lastMergeTime = [];

    /**
     * 高频转换结果缓存
     * @var array
     */
    private $cache = [];

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
        $this->config = array_replace_recursive($this->config, $options);
        $this->cache = [];
        $this->finalCharMap = $this->config['special_char']['default_map'];
        
        if (isset($options['special_char']['custom_map']) && is_array($options['special_char']['custom_map'])) {
            $this->finalCharMap = array_merge($this->finalCharMap, $options['special_char']['custom_map']);
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
        if (!FileUtil::fileExists($backupDir)) {
            FileUtil::createDir($backupDir);
        }

foreach (['common', 'rare', 'self_learn', 'custom'] as $dictType) {
            foreach (['with_tone', 'no_tone'] as $toneType) {
                $path = $this->config['dict'][$dictType][$toneType];
                if (!FileUtil::fileExists($path)) {
                    FileUtil::writeFile($path, "<?php\nreturn [];\n");
                }
            }
        }

        $polyPath = $this->config['dict']['polyphone_rules'];
        if (!FileUtil::fileExists($polyPath)) {
            FileUtil::writeFile($polyPath, "<?php\nreturn [];\n");
        }
        $freqPath = $this->config['dict']['self_learn']['frequency'];
        if (!FileUtil::fileExists($freqPath)) {
            FileUtil::writeFile($freqPath, "<?php\nreturn [];\n");
        }
    }

    /**
     * 一次性加载所有字典
     */
    private function loadAllDicts() {
        $this->loadSelfLearnDict(true);
        $this->loadSelfLearnDict(false);
        $this->loadSelfLearnFrequency();
        $this->loadCustomDict(true);
        $this->loadCustomDict(false);
        $this->loadCommonDict(true);
        $this->loadCommonDict(false);
        $this->loadRareDict(true);
        $this->loadRareDict(false);
        $this->loadPolyphoneRules();
    }

    /**
     * 初始化自定义多字词语缓存
     */
    private function initCustomMultiWords() {
        foreach (['with_tone', 'no_tone'] as $type) {
            $words = [];
            foreach ($this->dicts['custom'][$type] as $word => $pinyin) {
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
        $data = FileUtil::fileExists($path) ? FileUtil::requireFile($path) : [];
        $this->dicts['custom'][$type] = is_array($data) ? $this->formatPinyinArray($data) : [];
    }

    /**
     * 处理字符串中的空格
     * @param string $text 要处理的文本
     * @param bool $removeAllSpaces 是否完全删除空格
     * @return string 处理后的文本
     */
    private function processSpaces($text, $removeAllSpaces = false) {
        if ($removeAllSpaces) {
            return preg_replace('/\s+/', '', $text);
        }
        return preg_replace('/\s+/', ' ', $text);
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
            return trim($this->processSpaces($clean, $wordLen === 1));
        }, $pinyinArray);

        $pinyinArray = array_filter($pinyinArray);
        if (empty($pinyinArray)) {
            throw new PinyinException("自定义拼音不能为空或包含无效字符", PinyinException::ERROR_INVALID_INPUT);
        }

        $this->dicts['custom'][$type][$char] = $pinyinArray;
        $path = $this->config['dict']['custom'][$type];
        FileUtil::writeFile($path, "<?php\nreturn " . $this->shortArrayExport($this->dicts['custom'][$type]) . ";\n");
        $this->initCustomMultiWords();
        echo "\n✅ 已添加自定义拼音：{$char} → " . implode('/', $pinyinArray);
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
            FileUtil::writeFile($path, "<?php\nreturn " . $this->shortArrayExport($this->dicts['custom'][$type]) . ";\n");
            $this->initCustomMultiWords();
            echo "\n✅ 已删除自定义拼音：{$char}";
        }
    }

    /**
     * 加载自学习字频率数据
     */
    private function loadSelfLearnFrequency() {
        if ($this->dicts['self_learn_frequency'] !== null) {
            return;
        }
        $path = $this->config['dict']['self_learn']['frequency'];
        $data = FileUtil::fileExists($path) ? FileUtil::requireFile($path) : [];
        $this->dicts['self_learn_frequency'] = is_array($data) ? $data : [];
        $this->charFrequency = $this->dicts['self_learn_frequency'];
    }

    /**
     * 保存自学习字频率数据
     */
    private function saveSelfLearnFrequency() {
        $path = $this->config['dict']['self_learn']['frequency'];
        FileUtil::writeFile($path, "<?php\nreturn " . $this->shortArrayExport($this->charFrequency) . ";\n");
        $this->dicts['self_learn_frequency'] = $this->charFrequency;
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
        return FileUtil::fileExists($path) ? (int)FileUtil::readFile($path) : 0;
    }

    /**
     * 更新合并时间记录
     * @param string $toneType 声调类型
     */
    private function updateLastMergeTime($toneType) {
        $now = time();
        $path = $this->config['dict']['backup'] . "/last_merge_{$toneType}.txt";
        FileUtil::writeFile($path, $now);
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
        if (!FileUtil::fileExists($sourcePath)) {
            return;
        }
        $backupDir = $this->config['dict']['backup'];
        $filename = basename($sourcePath, '.php') . '_' . date('YmdHis') . '.php';
        FileUtil::copyFile($sourcePath, $backupDir . '/' . $filename);
    }

    /**
     * 检查是否需要合并自学习字典
     */
    private function checkMergeNeed() {
        $needMerge = [];
        foreach (['with_tone', 'no_tone'] as $toneType) {
            $selfLearnCount = count($this->dicts['self_learn'][$toneType]);
            if ($selfLearnCount >= $this->config['self_learn_merge']['threshold'] && $this->canMerge($toneType)) {
                $needMerge[$toneType] = true;
            }
        }
        if (!empty($needMerge)) {
            error_log("[PinyinConverter] 需要合并的字典：" . implode(',', array_keys($needMerge)));
        }
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
        $commonData = FileUtil::requireFile($commonPath);
        $commonData = $this->formatPinyinArray($commonData);

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
            }
        }

        if ($this->config['self_learn_merge']['sort_by_frequency']) {
            $commonData = $this->sortCommonDictByFrequency($commonData, $toneType);
        }

        FileUtil::writeFile($commonPath, "<?php\nreturn " . $this->shortArrayExport($commonData) . ";\n");
        $this->dicts['common'][$toneType] = $commonData;
    }

    /**
     * 按使用频率排序自学习汉字
     * @param array $selfLearnData 自学习数据
     * @param string $toneType 声调类型
     * @return array 排序后的汉字列表
     */
    private function sortSelfLearnByFrequency($selfLearnData, $toneType) {
        $chars = array_keys($selfLearnData);
        usort($chars, function ($a, $b) use ($toneType) {
            $freqA = $this->charFrequency[$toneType][$a] ?? 0;
            $freqB = $this->charFrequency[$toneType][$b] ?? 0;
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
        usort($chars, function ($a, $b) use ($toneType) {
            $freqA = $this->charFrequency[$toneType][$a] ?? 0;
            $freqB = $this->charFrequency[$toneType][$b] ?? 0;
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
        FileUtil::writeFile($selfLearnPath, "<?php\nreturn " . $this->shortArrayExport($selfLearnData) . ";\n");
        $this->dicts['self_learn'][$toneType] = $selfLearnData;
        $this->learnedChars[$toneType] = array_diff_key($this->learnedChars[$toneType], array_flip($charsToClean));

        foreach ($charsToClean as $char) {
            unset($this->charFrequency[$toneType][$char]);
        }
        $this->saveSelfLearnFrequency();
    }

    /**
     * 迁移常用字典中调用频率低的字符到生僻字字典
     * @param string $toneType 声调类型
     */
    private function migrateLowFrequencyChars($toneType) {
        $commonPath = $this->config['dict']['common'][$toneType];
        $commonData = FileUtil::requireFile($commonPath);
        $commonData = $this->formatPinyinArray($commonData);
        
        $rarePath = $this->config['dict']['rare'][$toneType];
        $rareData = FileUtil::fileExists($rarePath) ? FileUtil::requireFile($rarePath) : [];
        $rareData = $this->formatPinyinArray($rareData);
        
        // 计算常用字典中每个字符的平均频率
        $totalFrequency = 0;
        $charCount = count($commonData);
        
        foreach ($commonData as $char => $pinyin) {
            $freq = $this->charFrequency[$toneType][$char] ?? 0;
            $totalFrequency += $freq;
        }
        
        $averageFrequency = $charCount > 0 ? $totalFrequency / $charCount : 0;
        
        // 迁移频率低于平均值的字符到生僻字字典
        $migratedChars = [];
        foreach ($commonData as $char => $pinyin) {
            $freq = $this->charFrequency[$toneType][$char] ?? 0;
            if ($freq < $averageFrequency * 0.5) { // 低于平均值50%的字符
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
            FileUtil::writeFile($commonPath, "<?php\nreturn " . $this->shortArrayExport($commonData) . ";\n");
            FileUtil::writeFile($rarePath, "<?php\nreturn " . $this->shortArrayExport($rareData) . ";\n");
            
            // 更新内存中的字典数据
            $this->dicts['common'][$toneType] = $commonData;
            $this->dicts['rare'][$toneType] = $rareData;
        }
    }

    /**
     * 加载多音字规则字典
     */
    private function loadPolyphoneRules() {
        if ($this->dicts['polyphone_rules'] !== null) {
            return;
        }
        $path = $this->config['dict']['polyphone_rules'];
        $data = FileUtil::fileExists($path) ? FileUtil::requireFile($path) : [];
        $this->dicts['polyphone_rules'] = is_array($data) ? $data : [];
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
        $data = FileUtil::fileExists($path) ? FileUtil::requireFile($path) : [];
        $this->dicts['self_learn'][$type] = is_array($data) ? $this->formatPinyinArray($data) : [];
    }

    /**
     * 格式化拼音数组（区分单字和多字空格）
     * @param array $data 原始数据
     * @return array 格式化后的数据
     */
    private function formatPinyinArray($data) {
        $formatted = [];
        foreach ($data as $char => $pinyin) {
            if (empty($char)) continue;
            $wordLen = mb_strlen($char, 'UTF-8');
            $pinyinArr = is_array($pinyin) ? $pinyin : [$pinyin];
            
            $pinyinArr = array_map(function($item) use ($wordLen) {
                $trimmed = trim($item);
                // 对于单字，完全去除空格
                return $this->processSpaces($trimmed, $wordLen === 1);
            }, $pinyinArr);
            
            $formatted[$char] = array_filter($pinyinArr) ?: [$char];
        }
        return $formatted;
    }

    /**
     * 加载常用字字典
     * @param bool $withTone 是否带声调
     */
    private function loadCommonDict($withTone) {
        $type = $withTone ? 'with_tone' : 'no_tone';
        if ($this->dicts['common'][$type] !== null) {
            return;
        }
        $path = $this->config['dict']['common'][$type];
        $rawData = FileUtil::fileExists($path) ? FileUtil::requireFile($path) : [];
        $commonData = [];
        foreach ($rawData as $key => $value) {
            if (is_string($key)) {
                $commonData[$key] = is_array($value) ? $value : [$value];
            } elseif (is_numeric($key) && is_array($value) && count($value) >= 2) {
                $char = $value[0];
                $pinyin = $value[1];
                $commonData[$char] = is_array($pinyin) ? $pinyin : [$pinyin];
            }
        }
        $this->dicts['common'][$type] = $this->formatPinyinArray($commonData);
    }

    /**
     * 加载生僻字字典
     * @param bool $withTone 是否带声调
     */
    private function loadRareDict($withTone) {
        $type = $withTone ? 'with_tone' : 'no_tone';
        if ($this->dicts['rare'][$type] !== null) {
            return;
        }
        $path = $this->config['dict']['rare'][$type];
        $rawData = FileUtil::fileExists($path) ? FileUtil::requireFile($path) : [];
        $rareData = [];
        foreach ($rawData as $key => $value) {
            if (is_string($key)) {
                $rareData[$key] = is_array($value) ? $value : [$value];
            } elseif (is_numeric($key) && is_array($value) && count($value) >= 2) {
                $char = $value[0];
                $pinyin = $value[1];
                $rareData[$char] = is_array($pinyin) ? $pinyin : [$pinyin];
            }
        }
        $this->dicts['rare'][$type] = $this->formatPinyinArray($rareData);
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
        if (preg_match('/^['.$this->config['special_char']['delete_allow'].']+$/', $char)) {
            return $char;
        }

        // 临时映射（单字处理）- 最高优先级
        if (isset($tempMap[$char])) {
            return $this->cleanPinyin($tempMap[$char], true);
        }

        // 多音字规则检查 - 第二优先级
        $this->loadPolyphoneRules();
        
        if (isset($this->dicts['polyphone_rules'][$char])) {
            // 先获取所有可能的拼音选项用于规则匹配
            $pinyinArray = $this->getAllPinyinOptions($char, $withTone);
            $matchedPinyin = $this->matchPolyphoneRule($char, $pinyinArray, $context, $withTone);
            
            if ($matchedPinyin !== null) {
                // 根据withTone参数决定是否去除声调
                if (!$withTone && preg_match('/[āáǎàēéěèīíǐìōóǒòūúǔùǖǘǚǜü]/u', $matchedPinyin)) {
                    $matchedPinyin = $this->removeTone($matchedPinyin);
                }
                
                return $this->cleanPinyin($matchedPinyin, true);
            }
        }

        // 自定义字典（区分单字/多字）- 第三优先级
        if ($this->dicts['custom'][$type] === null) {
            $this->loadCustomDict($withTone);
        }
        
        if (isset($this->dicts['custom'][$type][$char])) {
            $pinyin = $this->getFirstPinyin($this->dicts['custom'][$type][$char]);
            return $this->cleanPinyin($pinyin, mb_strlen($char, 'UTF-8') === 1);
        }
        
        // 其他字典（按照self_xxx, common_xxx, rare_xxx的顺序）
        $pinyinArray = $this->getAllPinyinOptions($char, $withTone);
        $pinyin = $this->getFirstPinyin($pinyinArray);

        // 根据withTone参数决定是否去除声调
        if (!$withTone && preg_match('/[āáǎàēéěèīíǐìōóǒòūúǔùǖǘǚǜü]/u', $pinyin)) {
            $pinyin = $this->removeTone($pinyin);
        }
        
        // 修复：如果没有找到拼音，返回汉字本身
        return !empty(trim($pinyin)) ? $this->cleanPinyin($pinyin, true) : $char;
    }
    
    /**
     * 清理拼音字符串，去除多余空格
     * @param string $pinyin 拼音字符串
     * @param bool $removeAllSpaces 是否移除所有空格（单字时为true，多字时为false）
     * @return string 清理后的拼音字符串
     */
    private function cleanPinyin($pinyin, $removeAllSpaces = false) {
        if ($removeAllSpaces) {
            return $this->processSpaces($pinyin, true);
        }
        return $pinyin;
    }
    /**
     * 获取所有可能的拼音选项
     * @param string $char 汉字
     * @param bool $withTone 是否带声调
     * @return array 拼音数组
     */
    private function getAllPinyinOptions($char, $withTone) {
        $type = $withTone ? 'with_tone' : 'no_tone';

        // 1. 自学习字典
        if ($this->dicts['self_learn'][$type] === null) {
            $this->loadSelfLearnDict($withTone);
        }
        if (isset($this->dicts['self_learn'][$type][$char])) {
            $pinyin = $this->dicts['self_learn'][$type][$char];
            return $this->parsePinyinOptions($pinyin);
        }

        // 2. 常用字典
        if ($this->dicts['common'][$type] === null) {
            $this->loadCommonDict($withTone);
        }
        if (isset($this->dicts['common'][$type][$char])) {
            $pinyin = $this->dicts['common'][$type][$char];
            return $this->parsePinyinOptions($pinyin);
        }

        // 3. 生僻字字典（并自动增加到自学习字典）
        if ($this->dicts['rare'][$type] === null) {
            $this->loadRareDict($withTone);
        }
        if (isset($this->dicts['rare'][$type][$char])) {
            $rawPinyin = $this->dicts['rare'][$type][$char];
            $this->learnChar($char, $rawPinyin, $withTone);
            return $this->parsePinyinOptions($rawPinyin);
        }

        // 4. 基础映射表（作为最后的兜底）
        if (isset($this->basicPinyinMap[$char])) {
            return $withTone ? [$this->basicPinyinMap[$char][0]] : [$this->basicPinyinMap[$char][1]];
        }

        return [$char];
    }

    /**
     * 解析拼音选项
     * @param mixed $pinyin 拼音数据
     * @return array 拼音选项数组
     */
    private function parsePinyinOptions($pinyin) {
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

    /**
     * 匹配多音字规则
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
    
        foreach ($rules as $rule) {
            $ruleType = $rule['type'] ?? '';
            $target = $rule['char'] ?? $rule['word'] ?? '';
            $rulePinyin = $rule['pinyin'] ?? '';
    
            if (empty($rulePinyin)) {
                continue;
            }
            
            // 修复：当不需要声调时，先移除规则拼音中的声调再进行匹配
            $checkPinyin = $withTone ? $rulePinyin : $this->removeTone($rulePinyin);
            if (!in_array($checkPinyin, $pinyinArray)) {
                continue;
            }
    
            if ($ruleType === 'word' && $word === $target) {
                return $rulePinyin;
            }
            if ($ruleType === 'post' && $nextChar === $target) {
                return $rulePinyin;
            }
            if ($ruleType === 'pre' && $prevChar === $target) {
                return $rulePinyin;
            }
        }
    
        return null;
    }

    /**
     * 自动学习生僻字
     * @param string $char 汉字
     * @param array|string $rawPinyin 拼音
     * @param bool $withTone 是否带声调
     */
    private function learnChar($char, $rawPinyin, $withTone) {
        $type = $withTone ? 'with_tone' : 'no_tone';
        if (isset($this->dicts['self_learn'][$type][$char]) || isset($this->learnedChars[$type][$char])) {
            return;
        }

        $pinyinArray = is_array($rawPinyin) ? $rawPinyin : [$rawPinyin];
        if (!$withTone) {
            $pinyinArray = array_map([$this, 'removeTone'], $pinyinArray);
        }

        $this->learnedChars[$type][$char] = $pinyinArray;
        $this->dicts['self_learn'][$type][$char] = $pinyinArray;
        $this->charFrequency[$type][$char] = 0;
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
            $existing = FileUtil::requireFile($path);
            $existing = $this->formatPinyinArray($existing);
            $merged = array_merge($existing, $this->learnedChars[$type]);
            FileUtil::writeFile($path, "<?php\nreturn " . $this->shortArrayExport($merged) . ";\n");
            $this->dicts['self_learn'][$type] = $merged;
            $this->learnedChars[$type] = [];
        }
        $this->saveSelfLearnFrequency();
        $this->checkMergeNeed();
    }

    /**
     * 获取拼音数组中的第一个有效拼音
     * @param array $pinyinArray 拼音数组
     * @return string 第一个拼音
     */
    private function getFirstPinyin($pinyinArray) {
        foreach ($pinyinArray as $pinyin) {
            if (!empty(trim($pinyin))) {
                return trim($pinyin);
            }
        }
        return '';
    }

    /**
     * 移除拼音中的声调
     * @param string $pinyin 带声调拼音
     * @return string 无声调拼音
     */
    private function removeTone($pinyin) {
        $toneMap = [
            'ā' => 'a', 'á' => 'a', 'ǎ' => 'a', 'à' => 'a',
            'ō' => 'o', 'ó' => 'o', 'ǒ' => 'o', 'ò' => 'o',
            'ē' => 'e', 'é' => 'e', 'ě' => 'e', 'è' => 'e',
            'ī' => 'i', 'í' => 'i', 'ǐ' => 'i', 'ì' => 'i',
            'ū' => 'u', 'ú' => 'u', 'ǔ' => 'u', 'ù' => 'u',
            'ü' => 'v', 'ǖ' => 'v', 'ǘ' => 'v', 'ǚ' => 'v', 'ǜ' => 'v',
            'ń' => 'n', 'ň' => 'n', '' => 'm'
        ];
        return strtr($pinyin, $toneMap);
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
    
        // 汉字/数字/字母直接返回（优先级最高）
        if (preg_match('#^[\x{4e00}-\x{9fa5}\p{N}\p{L}]$#u', $char)) {
            return $char;
        }
    
        // 处理 replace 模式：替换指定符号，删除未指定的符号
        if ($mode === 'replace') {
            // 1. 优先使用自定义映射，其次使用默认映射
            $replaced = $customMap[$char] ?? $this->finalCharMap[$char] ?? null;
            // 2. 若有映射结果，保留；否则删除该符号
            return $replaced !== null ? $replaced : '';
        }
    
        // 以下为 delete/keep 模式的逻辑（不变）
        $blockedChars = ['%', '~', '!', '^', '&', '*', '`', '|', '\\', '{', '}', '<', '>', '【', '】', '、', '。', '，', '；', '：', '“', '”', '‘', '’', '（', '）'];
        if (in_array($char, $blockedChars)) {
            return '';
        }
    
        if (ord($char) < 32 || ord($char) > 126) {
            return '';
        }
    
        switch ($mode) {
            case 'delete':
                $deleteAllow = $this->config['special_char']['delete_allow'];
                return preg_match("/^[{$deleteAllow}]$/", $char) ? $char : '';
            case 'keep':
                return $char;
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
                    $pinyin = $this->getFirstPinyin($item['pinyin']);
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
                    
                    $processedWords[] = $word;
                }
        }

        return [$result, $processedWords];
    }

    /**
     * 紧凑数组序列化（用于字典文件）
     */
    private function shortArrayExport($array) {
        if (empty($array)) return '[]';
        $isAssoc = array_keys($array) !== range(0, count($array) - 1);
        $items = [];

        foreach ($array as $key => $value) {
            $keyStr = $isAssoc ? "'" . str_replace("'", "\\'", $key) . "' => " : '';
            if (is_array($value)) {
                $valueItems = array_map(function($item) {
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
        $charConfig = $this->parseCharParam($specialCharParam);
        // 仅在 delete 模式下执行预处理（避免干扰 replace 模式）
        if ($charConfig['mode'] === 'delete') {
            // 删除模式下，只保留中文字符和用户定义的特殊字符 
            // 注意这里使用了 \\x{4e00}-\\x{9fa5} 来匹配Unicode 编码里面的普通汉字 仅包含 GB2312 标准中的 “基本汉字集”（约 20902 个简体 / 繁体常用汉字），不包含汉字标点符号等
            // 而 \\p{Han} 属于 Unicode 属性类（\\p{属性名}），Han 是 Unicode 标准中定义的 “汉字属性”，表示 “所有具有汉字特征的字符”。
            $text = preg_replace('/[^\\x{4e00}-\\x{9fa5}'.$this->config['special_char']['delete_allow'].']+/u', ' ', $text);
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
        if (!preg_match('/\p{Han}/u', $textAfterMultiWords)) {
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

        $finalResult = str_replace('%', '', $finalResult);

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
            $isHan = preg_match('/\p{Han}/u', $char) ? true : false;
    
            if ($isHan) {
                if ($currentWord !== '') {
                    $result[] = $currentWord;
                    $currentWord = '';
                    if ($i > 0 && !preg_match('/\p{Han}/u', $prevChar)) {
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
                    // 字母数字及允许的符号累积为单词，其他替换结果直接添加
                    if (preg_match('/^[\\p{L}\\p{N}]+$/u', $handled) || $handled === '-' || $handled === '.') {
                        $currentWord .= $handled;
                    } else {
                        if ($currentWord !== '') {
                            $result[] = $currentWord;
                            $currentWord = '';
                        }
                        $result[] = $handled;
                    }
                } else {
                    // 处理累积单词
                    if ($currentWord !== '') {
                        $result[] = $currentWord;
                        $currentWord = '';
                    }
                }
            }
        }
        
        if ($currentWord !== '') {
            $result[] = $currentWord;
        }
    }
    

    /**
     * 转换为URL Slug
     * @param string $text 文本
     * @param string $separator 分隔符
     * @return string URL Slug
     */
    public function getUrlSlug(string $text, string $separator = '-'): string {
        $separator = $separator ?: '-';
        
        // 对于包含特殊字符的文本，先预处理特殊字符
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
     * 析构函数：保存自学习内容
     */
    public function __destruct() {
        $this->saveLearnedChars();
    }
}