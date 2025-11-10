<?php
/**
 * 对齐JS逻辑的拼音字典生成工具（紧凑数组格式版）
 * 核心：字典紧凑格式，多音字规则模板为“单规则一行”的友好紧凑格式
 */
class PinyinDictGenerator {
    // 基础配置
    private $sourcePath;
    private $dictDir;
    private $totalEntries = 0;
    private $rawEntries = [];

    // 声调映射表
    private $toneMap = [
        'ā' => 'a', 'á' => 'a', 'ǎ' => 'a', 'à' => 'a',
        'ō' => 'o', 'ó' => 'o', 'ǒ' => 'o', 'ò' => 'o',
        'ē' => 'e', 'é' => 'e', 'ě' => 'e', 'è' => 'e',
        'ī' => 'i', 'í' => 'i', 'ǐ' => 'i', 'ì' => 'i',
        'ū' => 'u', 'ú' => 'u', 'ǔ' => 'u', 'ù' => 'u',
        'ü' => 'v', 'ǖ' => 'v', 'ǘ' => 'v', 'ǚ' => 'v', 'ǜ' => 'v',
        'ń' => 'n', 'ň' => 'n', '' => 'm'
    ];

    // 生成参数
    private $commonCount = 3500;
    private $autoFix = false;
    private $errorLog = [];
    private $metadata = [];
    private $outputArray = true; // 固定数组格式

    /**
     * 构造函数
     */
    public function __construct($sourcePath, $options = []) {
        $this->sourcePath = $sourcePath;
        $this->dictDir = rtrim($options['dictDir'] ?? './data', '/') . '/';
        $this->commonCount = $options['commonCount'] ?? 3500;
        $this->autoFix = $options['autoFix'] ?? false;

        $this->checkSourceFile();
        $this->createDictDir();
        $this->rawEntries = $this->parseSource();
        $this->totalEntries = count($this->rawEntries);
        echo "📥 成功解析数据源：{$this->sourcePath}（共 {$this->totalEntries} 条记录）\n";
    }

    /**
     * 基础文件校验
     */
    private function checkSourceFile() {
        if (!file_exists($this->sourcePath)) {
            throw new Exception("数据源文件不存在：{$this->sourcePath}");
        }
        if (!is_readable($this->sourcePath)) {
            throw new Exception("数据源文件不可读：{$this->sourcePath}");
        }
        // 编码检测与修复
        $content = file_get_contents($this->sourcePath);
        $encoding = mb_detect_encoding($content, ['UTF-8', 'GBK', 'GB2312']);
        if ($encoding !== 'UTF-8' && $this->autoFix) {
            file_put_contents($this->sourcePath, mb_convert_encoding($content, 'UTF-8', $encoding));
            echo "🔧 已自动将数据源转为UTF-8编码\n";
        }
    }

    /**
     * 创建字典目录
     */
    private function createDictDir() {
        if (!is_dir($this->dictDir)) {
            mkdir($this->dictDir, 0755, true);
            echo "📂 已创建字典目录：{$this->dictDir}\n";
        }
    }

    /**
     * 解析数据源
     */
    private function parseSource() {
        $ext = strtolower(pathinfo($this->sourcePath, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'js':
                return $this->parseJsSource();
            case 'json':
                return $this->parseJsonSource();
            case 'txt':
                return $this->parseTxtSource();
            default:
                throw new Exception("不支持的格式：{$ext}（支持.js/.json/.txt）");
        }
    }

    /**
     * 解析JS数据源
     */
    private function parseJsSource() {
        $content = file_get_contents($this->sourcePath);
        $pattern = '/(var|const|let)\s+pinyin_dict_withtone\s*=\s*(["\'])(.*?)\2\s*;?/is';
        if (!preg_match($pattern, $content, $matches)) {
            $pattern2 = '/(var|const|let)\s+pinyin_dict_withtone\s*=\s*\[([^\]]*)\]\s*;?/is';
            if (!preg_match($pattern2, $content, $matches2)) {
                throw new Exception("未找到pinyin_dict_withtone变量");
            }
            $entries = explode(',', $matches2[2]);
        } else {
            $entries = explode(',', $matches[3]);
        }
        return array_filter($entries, fn($item) => trim($item) !== '');
    }

    /**
     * 解析JSON数据源
     */
    private function parseJsonSource() {
        $content = file_get_contents($this->sourcePath);
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON解析错误：" . json_last_error_msg());
        }
        ksort($data);
        $entries = [];
        foreach ($data as $char => $pinyin) {
            if (empty($char) || empty($pinyin)) continue;
            $index = mb_ord($char, 'UTF-8') - 19968;
            if ($index >= 0) $entries[$index] = $pinyin;
        }
        ksort($entries);
        $maxIndex = end(array_keys($entries)) ?? 0;
        $filled = [];
        for ($i = 0; $i <= $maxIndex; $i++) {
            $filled[$i] = $entries[$i] ?? '';
        }
        return $filled;
    }

    /**
     * 解析TXT数据源
     */
    private function parseTxtSource() {
        $entries = [];
        $handle = fopen($this->sourcePath, 'r');
        $lineNum = 0;
        while (($line = fgets($handle)) !== false) {
            $lineNum++;
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;
            $parts = preg_split('/\s+/u', $line, 2);
            $char = $parts[0] ?? '';
            $pinyin = $parts[1] ?? '';
            if (empty($char)) {
                $this->errorLog[] = "第{$lineNum}行：缺失汉字";
                continue;
            }
            $index = mb_ord($char, 'UTF-8') - 19968;
            if ($index >= 0) {
                $entries[$index] = $pinyin;
            } else {
                $this->errorLog[] = "第{$lineNum}行：汉字{$char}超出常用Unicode范围";
            }
        }
        fclose($handle);
        return $entries;
    }

    /**
     * 数据校验
     */
    private function validateEntries() {
        $valid = [];
        $this->errorLog = [];
        foreach ($this->rawEntries as $index => $pinyin) {
            $pinyin = trim($pinyin);
            $char = mb_chr($index + 19968, 'UTF-8');
            if (!$char) {
                $this->errorLog[] = "索引{$index}无法转为有效汉字";
                $valid[$index] = '';
                continue;
            }
            $pinyin = preg_replace('/[^\p{L}\sāáǎàōóǒòēéěèīíǐìūúǔùüǖǘǚǜ]/u', '', $pinyin);
            $valid[$index] = $pinyin;
        }
        if (!empty($this->errorLog)) {
            $logPath = $this->dictDir . 'errors.json';
            file_put_contents($logPath, json_encode($this->errorLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo "\n⚠️  检测到" . count($this->errorLog) . "条无效数据，日志：{$logPath}\n";
        }
        return $valid;
    }

    /**
     * 去声调
     */
    private function removeTone($pinyin) {
        return strtr($pinyin, $this->toneMap);
    }

    /**
     * 拼音处理（去重、格式转换）
     */
    private function processPinyin($pinyin) {
        $pinyin = trim($pinyin);
        if (empty($pinyin)) return [];

        // 多音字分隔
        if (!str_contains($pinyin, ' ') && preg_match_all('/[a-zāáǎàōóǒòēéěèīíǐìūúǔùüǖǘǚǜ]+/iu', $pinyin, $matches)) {
            $pinyins = $matches[0];
        } else {
            $pinyins = explode(' ', $pinyin);
        }

        // 去重过滤
        return array_values(array_filter(array_unique($pinyins)));
    }

    /**
     * 拆分常用字/生僻字
     */
    private function splitCommonAndRare($validEntries) {
        $common = array_slice($validEntries, 0, $this->commonCount);
        $rare = array_slice($validEntries, $this->commonCount);
        echo "\n🔍 拆分完成：常用字{$this->commonCount}条，生僻字" . count($rare) . "条\n";
        return [$common, $rare];
    }

    /**
     * 生成常用字字典
     */
    private function generateCommonDicts($commonEntries) {
        $withTone = [];
        $noTone = [];
        foreach ($commonEntries as $index => $pinyin) {
            $char = mb_chr($index + 19968, 'UTF-8');
            if (!$char) continue;
            
            $processedWithTone = $this->processPinyin($pinyin);
            if (empty($processedWithTone)) continue;

            $withTone[$char] = $processedWithTone;
            // 去声调处理
            $noToneRaw = $this->removeTone(implode(' ', $processedWithTone));
            $noTone[$char] = $this->processPinyin($noToneRaw);
        }
        $this->writeDict('common_with_tone.php', $withTone, '带声调');
        $this->writeDict('common_no_tone.php', $noTone, '不带声调');
        return [$withTone, $noTone];
    }

    /**
     * 生成生僻字字典
     */
    private function generateRareDicts($rareEntries) {
        $withTone = [];
        $noTone = [];
        foreach ($rareEntries as $index => $pinyin) {
            $pinyin = trim($pinyin);
            if (empty($pinyin)) continue;
            $charIndex = $index + $this->commonCount + 19968;
            $char = mb_chr($charIndex, 'UTF-8');
            if (!$char) continue;

            $processedWithTone = $this->processPinyin($pinyin);
            $withTone[$char] = $processedWithTone;
            
            $noToneRaw = $this->removeTone(implode(' ', $processedWithTone));
            $noTone[$char] = $this->processPinyin($noToneRaw);
        }
        $this->writeDict('rare_with_tone.php', $withTone, '带声调生僻字');
        $this->writeDict('rare_no_tone.php', $noTone, '不带声调生僻字');
        return [$withTone, $noTone];
    }

    /**
     * 核心：紧凑数组序列化（用于字典文件）
     */
    private function compactArrayExport($array) {
        if (empty($array)) return '[]';
        $isAssoc = array_keys($array) !== range(0, count($array) - 1);
        $items = [];

        foreach ($array as $key => $value) {
            $keyStr = $isAssoc ? "'" . str_replace("'", "\'", $key) . "' => " : '';
            if (is_array($value)) {
                $valueItems = array_map(function($item) {
                    return "'" . str_replace("'", "\'", $item) . "'";
                }, $value);
                $valueStr = '[' . implode(',', $valueItems) . ']';
            } else {
                $valueStr = "'" . str_replace("'", "\'", $value) . "'";
            }
            $items[] = $keyStr . $valueStr;
        }
        return "[\n    " . implode(",\n    ", $items) . "\n]";
    }

    /**
     * 写入紧凑格式字典
     */
    private function writeDict($filename, $data, $desc) {
        $path = $this->dictDir . $filename;
        $content = "<?php\n/** 紧凑格式{$desc}字典 生成时间：{$this->metadata['generated_at']} 条目数：" . count($data) . " **/\nreturn ";
        $content .= $this->compactArrayExport($data) . ";\n";

        if (file_put_contents($path, $content) === false) {
            throw new Exception("写入{$desc}字典失败：{$path}");
        }
        echo "\n📝 生成{$desc}字典：{$filename}";
    }

    /**
     * 生成辅助文件：多音字规则模板为“单规则一行”的紧凑友好格式
     */
    private function generateAuxFiles() {
        $this->metadata['generated_at'] = date('Y-m-d H:i:s');
        $this->metadata['source'] = realpath($this->sourcePath);
        $this->metadata['common_count'] = $this->commonCount;
        $this->metadata['total_entries'] = $this->totalEntries;
        
        // 生成元数据
        file_put_contents($this->dictDir . 'metadata.json', json_encode($this->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // 核心调整：多音字规则模板 - 单条规则紧凑一行，整体结构清晰
        $polyContent = <<<PHP
<?php
/** 多音字规则模板（紧凑友好格式，单规则一行，方便修改）
 * type支持三种类型：
 * 1. word: 匹配完整词语  2. pre: 匹配前置汉字  3. post: 匹配后置汉字
 * pinyin 为带声调的准确读音 在转换为不带音标时会自动去声调
 */
return [
    // 行：xíng（动作/行为） vs háng（行业/行列）
    '行' => [
        ['type' => 'post', 'char' => '为', 'pinyin' => 'xíng'], // 行为
        ['type' => 'post', 'char' => '动', 'pinyin' => 'xíng'], // 行动
        ['type' => 'post', 'char' => '走', 'pinyin' => 'xíng'], // 行走
        ['type' => 'post', 'char' => '者', 'pinyin' => 'xíng'],  // 行者
        ['type' => 'pre', 'char' => '银', 'pinyin' => 'háng'],  // 银行
        ['type' => 'post', 'char' => '业', 'pinyin' => 'háng'],  // 行业
        ['type' => 'post', 'char' => '列', 'pinyin' => 'háng'],  // 行列
        ['type' => 'word', 'word' => '行话', 'pinyin' => 'háng'], // 行话（行业术语）
    ],

    // 长：cháng（长度） vs zhǎng（生长/领导）
    '长' => [
        ['type' => 'post', 'char' => '度', 'pinyin' => 'cháng'], // 长度
        ['type' => 'post', 'char' => '短', 'pinyin' => 'cháng'], // 长短
        ['type' => 'post', 'char' => '城', 'pinyin' => 'cháng'], // 长城
        ['type' => 'post', 'char' => '大', 'pinyin' => 'zhǎng'], // 长大
        ['type' => 'post', 'char' => '进', 'pinyin' => 'zhǎng'], // 长进
        ['type' => 'pre', 'char' => '校', 'pinyin' => 'zhǎng'],  // 校长
        ['type' => 'word', 'word' => '长期', 'pinyin' => 'cháng'], // 长期
    ],

    // 乐：lè（快乐） vs yuè（音乐）
    '乐' => [
        ['type' => 'post', 'char' => '趣', 'pinyin' => 'lè'],   // 乐趣
        ['type' => 'post', 'char' => '观', 'pinyin' => 'lè'],   // 乐观
        ['type' => 'pre', 'char' => '音', 'pinyin' => 'yuè'],   // 音乐
        ['type' => 'post', 'char' => '器', 'pinyin' => 'yuè'],  // 乐器
        ['type' => 'word', 'word' => '乐园', 'pinyin' => 'lè'], // 乐园
        ['type' => 'word', 'word' => '乐谱', 'pinyin' => 'yuè'], // 乐谱
    ],

    // 发：fā（发生） vs fà（毛发）
    '发' => [
        ['type' => 'post', 'char' => '生', 'pinyin' => 'fā'],   // 发生
        ['type' => 'post', 'char' => '展', 'pinyin' => 'fā'],   // 发展
        ['type' => 'post', 'char' => '现', 'pinyin' => 'fā'],   // 发现
        ['type' => 'pre', 'char' => '头', 'pinyin' => 'fà'],    // 头发
        ['type' => 'post', 'char' => '型', 'pinyin' => 'fà'],   // 发型
        ['type' => 'word', 'word' => '发布', 'pinyin' => 'fā'], // 发布（技术：发布版本）
    ],

    // 重：zhòng（重量） vs chóng（重复）
    '重' => [
        ['type' => 'post', 'char' => '量', 'pinyin' => 'zhòng'], // 重量
        ['type' => 'post', 'char' => '要', 'pinyin' => 'zhòng'], // 重要
        ['type' => 'post', 'char' => '复', 'pinyin' => 'chóng'], // 重复
        ['type' => 'post', 'char' => '新', 'pinyin' => 'chóng'], // 重新
        ['type' => 'word', 'word' => '重构', 'pinyin' => 'chóng'], // 重构（技术：代码重构）
        ['type' => 'word', 'word' => '重点', 'pinyin' => 'zhòng'], // 重点
    ],

    // 参：cān（参与） vs shēn（人参） vs cēn（参差）
    '参' => [
        ['type' => 'post', 'char' => '与', 'pinyin' => 'cān'],   // 参与
        ['type' => 'post', 'char' => '数', 'pinyin' => 'cān'],   // 参数（技术：API参数）
        ['type' => 'pre', 'char' => '人', 'pinyin' => 'shēn'],   // 人参
        ['type' => 'post', 'char' => '差', 'pinyin' => 'cēn'],   // 参差
        ['type' => 'word', 'word' => '参考', 'pinyin' => 'cān'], // 参考
    ],

    // 量：liàng（数量） vs liáng（测量）
    '量' => [
        ['type' => 'post', 'char' => '力', 'pinyin' => 'liàng'], // 力量
        ['type' => 'post', 'char' => '化', 'pinyin' => 'liàng'], // 量化（技术：量化指标）
        ['type' => 'pre', 'char' => '测', 'pinyin' => 'liáng'],  // 测量
        ['type' => 'post', 'char' => '杯', 'pinyin' => 'liáng'], // 量杯
        ['type' => 'word', 'word' => '流量', 'pinyin' => 'liàng'], // 流量（网络流量）
    ],

    // 度：dù（温度） vs duó（揣度）
    '度' => [
        ['type' => 'post', 'char' => '数', 'pinyin' => 'dù'],    // 度数
        ['type' => 'post', 'char' => '量', 'pinyin' => 'dù'],    // 度量
        ['type' => 'pre', 'char' => '揣', 'pinyin' => 'duó'],    // 揣度
        ['type' => 'word', 'word' => '进度', 'pinyin' => 'dù'],  // 进度（项目进度）
        ['type' => 'word', 'word' => '度娘', 'pinyin' => 'dù'],  // 度娘（网络用语）
    ],

    // 数：shù（数字） vs shǔ（数数） vs shuò（数见不鲜）
    '数' => [
        ['type' => 'post', 'char' => '字', 'pinyin' => 'shù'],   // 数字
        ['type' => 'post', 'char' => '据', 'pinyin' => 'shù'],   // 数据（数据库）
        ['type' => 'post', 'char' => '量', 'pinyin' => 'shù'],   // 数量
        ['type' => 'post', 'char' => '数', 'pinyin' => 'shǔ'],   // 数数（动作）
        ['type' => 'word', 'word' => '数模', 'pinyin' => 'shù'], // 数模（数学模型）
    ],

    // 中：zhōng（中间） vs zhòng（中奖）
    '中' => [
        ['type' => 'post', 'char' => '间', 'pinyin' => 'zhōng'], // 中间
        ['type' => 'post', 'char' => '心', 'pinyin' => 'zhōng'], // 中心（数据中台）
        ['type' => 'post', 'char' => '奖', 'pinyin' => 'zhòng'], // 中奖
        ['type' => 'post', 'char' => '靶', 'pinyin' => 'zhòng'], // 中靶
        ['type' => 'word', 'word' => '中台', 'pinyin' => 'zhōng'], // 中台（数据中台）
    ],

    // 盛：shèng（盛开） vs chéng（盛饭）
    '盛' => [
        ['type' => 'post', 'char' => '开', 'pinyin' => 'shèng'], // 盛开
        ['type' => 'post', 'char' => '行', 'pinyin' => 'shèng'], // 盛行
        ['type' => 'post', 'char' => '饭', 'pinyin' => 'chéng'], // 盛饭
        ['type' => 'word', 'word' => '盛世', 'pinyin' => 'shèng'], // 盛世
    ],

    // 奔：bēn（奔跑） vs bèn（投奔）
    '奔' => [
        ['type' => 'post', 'char' => '跑', 'pinyin' => 'bēn'],   // 奔跑
        ['type' => 'post', 'char' => '驰', 'pinyin' => 'bēn'],   // 奔驰
        ['type' => 'post', 'char' => '赴', 'pinyin' => 'bèn'],   // 奔赴
        ['type' => 'pre', 'char' => '投', 'pinyin' => 'bèn'],    // 投奔
        ['type' => 'word', 'word' => '奔腾', 'pinyin' => 'bēn'], // 奔腾（芯片品牌）
    ],

    // 调：tiáo（调节） vs diào（调动）
    '调' => [
        ['type' => 'post', 'char' => '节', 'pinyin' => 'tiáo'],  // 调节
        ['type' => 'post', 'char' => '整', 'pinyin' => 'tiáo'],  // 调整
        ['type' => 'post', 'char' => '动', 'pinyin' => 'diào'],  // 调动
        ['type' => 'post', 'char' => '试', 'pinyin' => 'diào'],  // 调试（技术：代码调试）
        ['type' => 'word', 'word' => '调度', 'pinyin' => 'diào'], // 调度（任务调度）
    ],
];

PHP;
        file_put_contents($this->dictDir . 'polyphone_rules.php', $polyContent);
        
        // 自学习字典模板
        $selfLearnTpl = "<?php\nreturn [];\n";
        file_put_contents($this->dictDir . 'self_learn_with_tone.php', $selfLearnTpl);
        file_put_contents($this->dictDir . 'self_learn_no_tone.php', $selfLearnTpl);
        file_put_contents($this->dictDir . 'char_frequency.php', "<?php\nreturn [];");
        
        echo "\n📋 生成元数据及辅助模板（多音字规则为紧凑友好格式）";
    }

    /**
     * 校验关键汉字
     */
    private function validateCriticalChars($noToneCommon, $noToneRare) {
        $critical = [
            '天' => 'tian', '开' => 'kai', '发' => 'fa', '源' => 'yuan',
            '文' => 'wen', '术' => 'shu', '业' => 'ye', '务' => 'wu'
        ];
        $errors = [];

        foreach ($critical as $char => $expected) {
            $actual = [];
            if (isset($noToneCommon[$char])) {
                $actual = $noToneCommon[$char];
            } elseif (isset($noToneRare[$char])) {
                $actual = $noToneRare[$char];
            } else {
                $errors[] = "缺失汉字：{$char}";
                continue;
            }
            $firstPinyin = $actual[0] ?? '';
            if (strtolower($firstPinyin) !== strtolower($expected)) {
                $actualStr = implode(',', $actual);
                $errors[] = "{$char}：实际读音{$actualStr}，预期默认读音{$expected}";
            }
        }

        if (!empty($errors)) {
            throw new Exception("字典校验失败：" . implode('，', $errors));
        }
        echo "\n✅ 关键汉字校验通过";
    }

    /**
     * 主生成方法
     */
    public function generate() {
        try {
            $valid = $this->validateEntries();
            list($common, $rare) = $this->splitCommonAndRare($valid);
            $this->metadata['generated_at'] = date('Y-m-d H:i:s');
            list($withToneCommon, $noToneCommon) = $this->generateCommonDicts($common);
            list($withToneRare, $noToneRare) = $this->generateRareDicts($rare);
            $this->generateAuxFiles();
            $this->validateCriticalChars($noToneCommon, $noToneRare);
            echo "\n🎉 字典生成完成！输出目录：{$this->dictDir}\n";
            return true;
        } catch (Exception $e) {
            echo "\n❌ 生成失败：" . $e->getMessage() . "\n";
            return false;
        }
    }
}

// 使用示例
try {
    $generator = new PinyinDictGenerator('pinyin_dict_withtone.js', [
        'dictDir' => './data',
        'commonCount' => 3500,
        'autoFix' => true
    ]);
    $generator->generate();
} catch (Exception $e) {
    echo "初始化失败：" . $e->getMessage();
}