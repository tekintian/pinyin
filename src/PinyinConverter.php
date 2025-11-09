<?php
namespace tekintian\pinyin;

/**
 * 汉字转拼音工具（最终稳定版）
 * 支持：多字词语空格保留、单字拼音去空格、特殊字符过滤、自定义词典
 */
class PinyinConverter {
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
     * @var \SplObjectStorage
     */
    private $cache;

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
        $this->cache = new \SplObjectStorage();
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
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        foreach (['common', 'rare', 'self_learn', 'custom'] as $dictType) {
            foreach (['with_tone', 'no_tone'] as $toneType) {
                $path = $this->config['dict'][$dictType][$toneType];
                if (!file_exists($path)) {
                    file_put_contents($path, "<?php\nreturn [];\n");
                }
            }
        }

        $polyPath = $this->config['dict']['polyphone_rules'];
        if (!file_exists($polyPath)) {
            file_put_contents($polyPath, "<?php\nreturn [];\n");
        }
        $freqPath = $this->config['dict']['self_learn']['frequency'];
        if (!file_exists($freqPath)) {
            file_put_contents($freqPath, "<?php\nreturn [];\n");
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
        $data = file_exists($path) ? require $path : [];
        $this->dicts['custom'][$type] = is_array($data) ? $this->formatPinyinArray($data) : [];
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
            if ($wordLen === 1) {
                return trim(preg_replace('/\s+/', '', $clean));
            } else {
                return trim(preg_replace('/\s+/', ' ', $clean));
            }
        }, $pinyinArray);

        $pinyinArray = array_filter($pinyinArray);
        if (empty($pinyinArray)) {
            throw new \Exception("自定义拼音不能为空或包含无效字符");
        }

        $this->dicts['custom'][$type][$char] = $pinyinArray;
        $path = $this->config['dict']['custom'][$type];
        file_put_contents($path, "<?php\nreturn " . $this->shortArrayExport($this->dicts['custom'][$type]) . ";\n");
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
            file_put_contents($path, "<?php\nreturn " . $this->shortArrayExport($this->dicts['custom'][$type]) . ";\n");
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
        $data = file_exists($path) ? require $path : [];
        $this->dicts['self_learn_frequency'] = is_array($data) ? $data : [];
        $this->charFrequency = $this->dicts['self_learn_frequency'];
    }

    /**
     * 保存自学习字频率数据
     */
    private function saveSelfLearnFrequency() {
        $path = $this->config['dict']['self_learn']['frequency'];
        file_put_contents($path, "<?php\nreturn " . $this->shortArrayExport($this->charFrequency) . ";\n");
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
        return file_exists($path) ? (int)file_get_contents($path) : 0;
    }

    /**
     * 更新合并时间记录
     * @param string $toneType 声调类型
     */
    private function updateLastMergeTime($toneType) {
        $now = time();
        $path = $this->config['dict']['backup'] . "/last_merge_{$toneType}.txt";
        file_put_contents($path, $now);
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
        if (!file_exists($sourcePath)) {
            return;
        }
        $backupDir = $this->config['dict']['backup'];
        $filename = basename($sourcePath, '.php') . '_' . date('YmdHis') . '.php';
        copy($sourcePath, $backupDir . '/' . $filename);
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
            } catch (\Exception $e) {
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
        $commonData = file_exists($commonPath) ? require $commonPath : [];
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

        file_put_contents($commonPath, "<?php\nreturn " . $this->shortArrayExport($commonData) . ";\n");
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
        file_put_contents($selfLearnPath, "<?php\nreturn " . $this->shortArrayExport($selfLearnData) . ";\n");
        $this->dicts['self_learn'][$toneType] = $selfLearnData;
        $this->learnedChars[$toneType] = array_diff_key($this->learnedChars[$toneType], array_flip($charsToClean));

        foreach ($charsToClean as $char) {
            unset($this->charFrequency[$toneType][$char]);
        }
        $this->saveSelfLearnFrequency();
    }

    /**
     * 加载多音字规则字典
     */
    private function loadPolyphoneRules() {
        if ($this->dicts['polyphone_rules'] !== null) {
            return;
        }
        $path = $this->config['dict']['polyphone_rules'];
        $data = file_exists($path) ? require $path : [];
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
        $data = file_exists($path) ? require $path : [];
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
                // 对于单字，保留空格分隔的多音字拼音
                if ($wordLen === 1) {
                    return preg_replace('/\s+/', ' ', $trimmed);
                } else {
                    return preg_replace('/\s+/', ' ', $trimmed);
                }
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
$data = file_exists($path) ? require $path : [];
        $this->dicts['common'][$type] = $this->formatPinyinArray($data);
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
        $rawData = file_exists($path) ? require $path : [];
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
    private function getCharPinyin($char, $withTone, $context = [], $tempMap = []) {
        $type = $withTone ? 'with_tone' : 'no_tone';
    
        // 数字/字母直接返回
        if (ctype_alnum($char)) {
            return $char;
        }
    
        // 临时映射（单字处理） - 最高优先级
        if (isset($tempMap[$char])) {
            // 直接使用用户指定的拼音，不根据withTone参数修改
            $pinyin = $tempMap[$char];
            return preg_replace('/\s+/', '', $pinyin);
        }
    
        // 自定义字典（区分单字/多字）
        if (isset($this->dicts['custom'][$type][$char])) {
            $pinyin = $this->getFirstPinyin($this->dicts['custom'][$type][$char]);
            return mb_strlen($char, 'UTF-8') === 1 
                ? preg_replace('/\s+/', '', $pinyin)
                : $pinyin;
        }
    
        // 其他字典（单字处理）
        $pinyinArray = $this->getAllPinyinOptions($char, $withTone);
        $pinyin = count($pinyinArray) <= 1 
            ? $this->getFirstPinyin($pinyinArray)
            : ($this->matchPolyphoneRule($char, $pinyinArray, $context, $withTone) ?? $pinyinArray[0]);
    
        // 修复：根据withTone参数决定是否去除声调
        if (!$withTone && preg_match('/[āáǎàēéěèīíǐìōóǒòūúǔùǖǘǚǜü]/u', $pinyin)) {
            $pinyin = $this->removeTone($pinyin);
        }
        
        return preg_replace('/\s+/', '', $pinyin);
    }

    /**
     * 获取所有可能的拼音选项
     * @param string $char 汉字
     * @param bool $withTone 是否带声调
     * @return array 拼音数组
     */
    private function getAllPinyinOptions($char, $withTone) {
        $type = $withTone ? 'with_tone' : 'no_tone';

        // 1. 自定义字典（最高优先级）
        $this->loadCustomDict($withTone);
        if (isset($this->dicts['custom'][$type][$char])) {
            $pinyin = $this->dicts['custom'][$type][$char];
            return $this->parsePinyinOptions($pinyin);
        }

        // 2. 基础映射表
        if (isset($this->basicPinyinMap[$char])) {
            return $withTone ? [$this->basicPinyinMap[$char][0]] : [$this->basicPinyinMap[$char][1]];
        }

        // 3. 自学习字典
        if (isset($this->dicts['self_learn'][$type][$char])) {
            $pinyin = $this->dicts['self_learn'][$type][$char];
            return $this->parsePinyinOptions($pinyin);
        }

        // 4. 常用字典
        $this->loadCommonDict($withTone);
        if (isset($this->dicts['common'][$type][$char])) {
            $pinyin = $this->dicts['common'][$type][$char];
            return $this->parsePinyinOptions($pinyin);
        }

        // 5. 生僻字字典（并自动增加到自学习字典）
        $this->loadRareDict($withTone);
        if (isset($this->dicts['rare'][$type][$char])) {
            $rawPinyin = $this->dicts['rare'][$type][$char];
            $this->learnChar($char, $rawPinyin, $withTone);
            return $this->parsePinyinOptions($rawPinyin);
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
            $existing = require $path;
            $existing = $this->formatPinyinArray($existing);
            $merged = array_merge($existing, $this->learnedChars[$type]);
            file_put_contents($path, "<?php\nreturn " . $this->shortArrayExport($merged) . ";\n");
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

        // 汉字/数字/字母直接返回
        if (preg_match('/\p{Han}|\p{N}|\p{L}/u', $char)) {
            return $char;
        }

        // 明确过滤不需要的字符
        $blockedChars = ['%', '~', '!', '^', '&', '*', '`', '|', '\\', '{', '}', '<', '>', '【', '】', '、', '。', '，', '；', '：', '“', '”', '‘', '’', '（', '）'];
        if (in_array($char, $blockedChars)) {
            return '';
        }

        // 过滤不可见字符
        if (ord($char) < 32 || ord($char) > 126) {
            return '';
        }

        switch ($mode) {
            case 'replace':
                return $customMap[$char] ?? $this->finalCharMap[$char] ?? '';
            case 'delete':
                return preg_match("/^[{$this->config['special_char']['delete_allow']}]$/", $char) ? $char : '';
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

        // 首先，按照词语长度降序排序，优先替换长词语
        usort($this->customMultiWords[$type], function($a, $b) {
            return mb_strlen($b['word']) - mb_strlen($a['word']);
        });

        foreach ($this->customMultiWords[$type] as $item) {
            $word = $item['word'];
            if (in_array($word, $processedWords)) continue;
            
            // 检查文本中是否包含该词语
            if (strpos($result, $word) !== false) {
                $pinyin = $this->getFirstPinyin($item['pinyin']);
                // 使用特殊标记来保护拼音字符串不被后续处理拆分
                $protectedPinyin = "[[CUSTOM_PINYIN:{$pinyin}]]";
                
                // 使用正则表达式进行替换
                // 先处理词语后接非中文字符的情况
                $result = preg_replace(
                    '/(' . preg_quote($word, '/') . ')([^\p{Han}])/u',
                    $protectedPinyin . $separator . '$2', // 直接使用实际分隔符
                    $result
                );
                
                // 再处理词语出现在文本末尾的情况
                $result = str_replace($word, $protectedPinyin, $result);
                
                $processedWords[] = $word;
            }
        }

        return [$result, $processedWords];
    }

    /**
     * 自定义短数组序列化
     * @param array $array 数组
     * @param int $indent 缩进
     * @return string 短数组字符串
     */
    private function shortArrayExport($array, $indent = 4) {
        if (empty($array)) {
            return '[]';
        }

        $isAssoc = array_keys($array) !== range(0, count($array) - 1);
        $spaces = str_repeat(' ', $indent);
        $result = "[" . "\n";

        foreach ($array as $key => $value) {
            $keyStr = $isAssoc ? (is_string($key) ? "'{$key}'" : $key) . " => " : '';

            if (is_array($value)) {
                $valueStr = $this->shortArrayExport($value, $indent + 4);
            } elseif (is_string($value)) {
                $valueStr = "'" . str_replace("'", "\'", $value) . "'";
            } else {
                $valueStr = var_export($value, true);
            }

            $result .= "{$spaces}{$keyStr}{$valueStr},\n";
        }

        $result .= str_repeat(' ', $indent - 4) . "]";
        return $result;
    }

    /**
     * 转换文本为拼音（最终处理）
     */
    public function convert(
        $text,
        $separator = ' ',
        $withTone = false,
        $specialCharParam = '',
        $polyphoneTempMap = []
    ) {
        $text = preg_replace('/[\x00-\x1F\x7F%]/', '', $text);
        $charConfig = $this->parseCharParam($specialCharParam);
        $cacheKey = md5(json_encode([$text, $separator, $withTone, $charConfig, $polyphoneTempMap]));

        // 缓存检查
        foreach ($this->cache as $item) {
            if ($item->key === $cacheKey) {
                $this->cache->detach($item);
                $this->cache->attach($item);
                return $item->value;
            }
        }

        // 多字词语替换
        list($textAfterMultiWords, $processedWords) = $this->replaceCustomMultiWords($text, $withTone, $separator);

        // 检查是否已经完成了自定义多字词语的替换
        // 如果文本中不再包含汉字，说明已经完成了自定义多字词语的替换，直接返回结果
        if (!preg_match('/\p{Han}/u', $textAfterMultiWords)) {
            // 移除保护标记，但保留分隔符标记用于后续处理
            $textAfterMultiWords = str_replace(['[[CUSTOM_PINYIN:', ']]'], '', $textAfterMultiWords);
            // 将分隔符标记转换为实际分隔符
            $textAfterMultiWords = str_replace('[[SEPARATOR]]', $separator, $textAfterMultiWords);
            return $textAfterMultiWords;
        }

        // 处理自定义多字词语的保护标记
        $result = [];
        $customPinyinMatches = [];
        
        // 提取所有自定义多字词语的保护标记
        preg_match_all('/\[\[CUSTOM_PINYIN:([^\]]+)\]\]/', $textAfterMultiWords, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $customPinyinMatches[$match[0]] = $match[1];
        }
        
        // 如果有自定义多字词语，先处理它们
        if (!empty($customPinyinMatches)) {
            // 使用更简单的逻辑：在自定义拼音后面跟着非中文字符时添加分隔符占位符
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
                    // 这是自定义多字词语的拼音，直接添加到结果中
                    // 如果前一个部分是自定义拼音，需要在它们之间添加分隔符占位符
                    if ($previousWasCustomPinyin) {
                        $result[] = '';
                    }
                    $result[] = $customPinyinMatches[$part];
                    $previousWasCustomPinyin = true;
                } else {
                    // 这是普通文本，需要进一步处理
                    $len = mb_strlen($part, 'UTF-8');
                    $currentWord = ''; // 用于累积连续的字母和数字
                    
                    // 如果前一个部分是自定义拼音，需要在自定义拼音和普通文本之间添加分隔符占位符
                    if ($previousWasCustomPinyin && $len > 0) {
                        $result[] = '';
                    }
                    $previousWasCustomPinyin = false;
                    
                    // 处理分隔符标记 - 在自定义拼音和后续字符之间添加分隔符占位符
                    if (strpos($part, '[[SEPARATOR]]') !== false) {
                        // 如果包含分隔符标记，先处理分隔符
                        $part = str_replace('[[SEPARATOR]]', '', $part);
                        // 在自定义拼音和后续字符之间添加分隔符占位符
                        $result[] = '';
                    }
                    
                    for ($i = 0; $i < $len; $i++) {
                        $char = mb_substr($part, $i, 1, 'UTF-8');
                        $isHan = preg_match('/\p{Han}/u', $char) ? true : false;

                        if ($isHan) {
                            // 处理累积的英文单词或数字
                            if ($currentWord !== '') {
                                $result[] = $currentWord;
                                $currentWord = '';
                                // 在英文单词和中文拼音之间添加分隔符占位符
                                $result[] = '';
                            }
                            
                            $context = [
                                'prev' => ($i > 0) ? mb_substr($part, $i - 1, 1, 'UTF-8') : '',
                                'next' => ($i < $len - 1) ? mb_substr($part, $i + 1, 1, 'UTF-8') : '',
                                'word' => ($i > 0 ? mb_substr($part, $i - 1, 1, 'UTF-8') : '') . $char . ($i < $len - 1 ? mb_substr($part, $i + 1, 1, 'UTF-8') : '')
                            ];
                            $pinyin = $this->getCharPinyin($char, $withTone, $context, $polyphoneTempMap);
                            $result[] = $pinyin;
                        } else {
                            $handled = $this->handleSpecialChar($char, $charConfig);
                            
                            // 如果是字母、数字或版本号中的点号，累积到当前单词中
                            if ($handled !== '' && (ctype_alnum($handled) || $handled === '-' || $handled === '.')) {
                                $currentWord .= $handled;
                            } else {
                                // 处理累积的英文单词或数字
                                if ($currentWord !== '') {
                                    $result[] = $currentWord;
                                    $currentWord = '';
                                }
                                
                                // 处理特殊字符
                                if ($handled !== '') {
                                    $result[] = $handled;
                                }
                            }
                        }
                    }
                    
                    // 处理末尾的累积单词
                    if ($currentWord !== '') {
                        $result[] = $currentWord;
                        $currentWord = '';
                    }
                }
            }
        } else {
            // 没有自定义多字词语，使用原来的字符处理逻辑
            $len = mb_strlen($textAfterMultiWords, 'UTF-8');
            $currentWord = ''; // 用于累积连续的字母和数字
            
            for ($i = 0; $i < $len; $i++) {
                $char = mb_substr($textAfterMultiWords, $i, 1, 'UTF-8');
                $isHan = preg_match('/\p{Han}/u', $char) ? true : false;

                if ($isHan) {
                    // 处理累积的英文单词或数字
                    if ($currentWord !== '') {
                        $result[] = $currentWord;
                        $currentWord = '';
                        // 在英文单词和中文拼音之间添加分隔符占位符
                        $result[] = '';
                    }
                    
                    $context = [
                        'prev' => ($i > 0) ? mb_substr($textAfterMultiWords, $i - 1, 1, 'UTF-8') : '',
                        'next' => ($i < $len - 1) ? mb_substr($textAfterMultiWords, $i + 1, 1, 'UTF-8') : '',
                        'word' => ($i > 0 ? mb_substr($textAfterMultiWords, $i - 1, 1, 'UTF-8') : '') . $char . ($i < $len - 1 ? mb_substr($textAfterMultiWords, $i + 1, 1, 'UTF-8') : '')
                    ];
                    $pinyin = $this->getCharPinyin($char, $withTone, $context, $polyphoneTempMap);
                    $result[] = $pinyin;
                } else {
                    // 直接处理非汉字字符，不通过 handleSpecialChar 拆分英文单词
                    if (ctype_alnum($char) || $char === '-' || $char === '.') {
                        $currentWord .= $char;
                    } else {
                        // 处理累积的英文单词或数字
                        if ($currentWord !== '') {
                            $result[] = $currentWord;
                            $currentWord = '';
                        }
                        
                        // 处理特殊字符
                        $handled = $this->handleSpecialChar($char, $charConfig);
                        if ($handled !== '') {
                            $result[] = $handled;
                        }
                    }
                }
            }
            // 处理末尾的累积单词
            if ($currentWord !== '') {
                $result[] = $currentWord;
                $currentWord = '';
            }
        }
        
        // 处理末尾的累积单词
        if ($currentWord !== '') {
            $result[] = $currentWord;
        }

        // 过滤空值并拼接
        $filtered = array_filter($result, function ($item) {
            return $item !== ''; // 只过滤真正的空字符串，保留空字符串分隔符占位符
        });
        $finalResult = implode($separator, $filtered);
        
        // 处理分隔符标记 - 将[[SEPARATOR]]转换为实际分隔符
        $finalResult = str_replace('[[SEPARATOR]]', $separator, $finalResult);

        // 合并连续分隔符
        if ($separator !== '') {
            $finalResult = preg_replace(
                '/' . preg_quote($separator, '/') . '+/',
                $separator,
                $finalResult
            );
        }

        // 最终清理
        $finalResult = str_replace('%', '', $finalResult);

        // 缓存结果
        $cacheItem = (object)['key' => $cacheKey, 'value' => $finalResult];
        $this->cache->attach($cacheItem);
        if ($this->cache->count() > $this->config['high_freq_cache']['size']) {
            $this->cache->rewind();
            $this->cache->detach($this->cache->current());
        }

        return $finalResult;
    }

    /**
     * 转换为URL Slug
     * @param string $text 文本
     * @param string $separator 分隔符
     * @return string URL Slug
     */
    public function getUrlSlug($text, $separator = '-') {
        $separator = $separator ?: '-';
        // 修复：使用正确的特殊字符处理模式
        $pinyin = $this->convert($text, $separator, false, 'delete');
        // 修复：确保只保留字母、数字和分隔符
        $pinyin = preg_replace('/[^a-z0-9' . preg_quote($separator, '/') . ']/i', '', $pinyin);
        $pinyin = strtolower($pinyin);
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