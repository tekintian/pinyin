
# 汉字转拼音工具

[![License](https://poser.pugx.org/tekintian/pinyin/license)](https://packagist.org/packages/tekintian/pinyin)
[![Latest Stable Version](https://poser.pugx.org/tekintian/pinyin/v/stable)](https://packagist.org/packages/tekintian/pinyin)
[![Total Downloads](https://poser.pugx.org/tekintian/pinyin/downloads)](https://packagist.org/packages/tekintian/pinyin)

一个功能强大的汉字转拼音工具，支持自定义映射、特殊字符处理、自动学习功能和多音字处理。

字典优先级从高到低依次为 custom_xxx   polyphone_xxx  ,  self_xxx,   common_xxx, rare_xxx

优先级顺序是: custom_xxx > polyphone_xxx > self_xxx > common_xxx > rare_xxx ，


✅ 生僻字会自动加入自学习字典
✅ 自学习字典达到阈值后会合并到常用字典
✅ 合并时会按使用频率排序
✅ 缺少功能：合并后删除自学习字典内容
✅ 缺少功能：将常用字典中调用频率低的字移到生僻字字典
❌ 等你来发现



unicode汉字拼音数据查询  拼音位于 kMandarin 字段
https://www.unicode.org/cgi-bin/GetUnihanData.pl?codepoint=%E8%AF%B4

各区块统计:
CJK基本汉字: 20924 个字符
CJK扩展A区: 5786 个字符
CJK扩展B区: 14614 个字符
CJK扩展C区: 506 个字符
CJK扩展D区: 73 个字符
CJK扩展E区: 870 个字符
CJK扩展F区: 121 个字符
CJK扩展G区: 1123 个字符


## 功能特点

- ✨ **精准的汉字转拼音**：支持常用字和生僻字的准确拼音转换
- 🎛️ **三种特殊字符处理模式**：`keep`/`delete`/`replace`，满足不同场景需求
- 🔍 **自动学习功能**：自动识别并记忆生僻字的拼音
- 🔀 **多音字处理**：支持多音字的准确转换
- 📝 **URL友好的Slug生成**：适合用于生成SEO友好的URL
- 📚 **自定义字典**：支持用户定义的汉字拼音映射
- 🔧 **灵活的参数配置**：满足不同使用场景的需求

## 安装

使用Composer安装：

```bash
composer require tekintian/pinyin
```

## 基本使用

```php
use tekintian\pinyin\PinyinConverter;

// 创建实例
$pinyinConverter = new PinyinConverter();

// 基本转换
$pinyin = $pinyinConverter->convert('你好，世界！');
echo $pinyin; // 输出: ni hao shi jie

// 保留声调
$pinyinWithTone = $pinyinConverter->convert('你好，世界！', ' ', true);
echo $pinyinWithTone; // 输出: nǐ hǎo shì jiè

// 自定义分隔符
$pinyin = $pinyinConverter->convert('你好，世界！', '-');
echo $pinyin; // 输出: ni-hao-shi-jie

// 生成URL Slug
$slug = $pinyinConverter->getUrlSlug('你好，世界！');
echo $slug; // 输出: ni-hao-shi-jie
```

## 特殊字符处理

本工具提供三种特殊字符处理模式：

1. **delete模式**：删除所有特殊字符（默认模式）
2. **keep模式**：保留所有特殊字符
3. **replace模式**：将特殊字符替换为对应的英文符号

```php
// delete模式（默认）
$pinyin = $pinyinConverter->convert('你好，世界！', ' ', false, 'delete');
echo $pinyin; // 输出: ni hao shi jie

// keep模式
$pinyin = $pinyinConverter->convert('你好，世界！', ' ', false, 'keep');
echo $pinyin; // 输出: ni hǎo ， shì jiè ！

// replace模式
$pinyin = $pinyinConverter->convert('你好，世界！', ' ', false, 'replace');
echo $pinyin; // 输出: ni hǎo , shì jiè !

// 使用自定义替换数组
$customReplace = [
    '，' => ', ',
    '！' => '! ',
    '。' => '. '
];
$pinyin = $pinyinConverter->convert('你好，世界！', ' ', false, $customReplace);
echo $pinyin; // 输出: ni hǎo , shì jiè !
```

## 高级配置

创建实例时可以传入配置数组，自定义工具的行为：

```php
$config = [
    'special_char' => [
        'default_mode' => 'replace',
        'custom_map' => [
            '，' => ', ',
            '。' => '. ',
            '！' => '! ',
            '？' => '? ',
            '、' => ', ',
            '；' => '; ',
            '：' => ': '
        ]
    ],
    'high_freq_cache' => [
        'size' => 2000 // 增大高频缓存大小
    ]
];

$pinyinConverter = new PinyinConverter($config);
```

## API参考

### PinyinConverter 类

#### 构造函数

```php
/**
 * 创建拼音转换器实例
 *
 * @param array $options 配置选项
 */
public function __construct(array $options = [])
```

#### convert 方法

```php
/**
 * 将汉字转换为拼音
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
): string
```

#### getUrlSlug 方法

```php
/**
 * 生成URL友好的slug
 *
 * @param string $text 要转换的文本
 * @param string $separator slug分隔符，默认为'-'
 * @return string 生成的slug字符串
 */
public function getUrlSlug(string $text, string $separator = '-'): string
```

## 测试

运行测试：

```bash
composer test
```

检查代码风格：

```bash
composer check-style
```

修复代码风格问题：

```bash
composer fix-style
```

## 性能优化

- 该工具使用多级缓存机制，包括高频缓存和学习缓存，提高重复文本的转换速度
- 采用懒加载策略，只在需要时加载相关字典
- 自动学习生僻字，避免重复查找

## 贡献指南

请阅读[CONTRIBUTING.md](CONTRIBUTING.md)了解如何参与项目开发。

## 版本历史

请查看[CHANGELOG.md](CHANGELOG.md)了解版本更新历史。

## 许可证

本项目使用MIT许可证，详情请查看[LICENSE](LICENSE)文件。
            'ū' => 'u', 'ú' => 'u', 'ǔ' => 'u', 'ù' => 'u',
            'ü' => 'v', 'ǖ' => 'v', 'ǘ' => 'v', 'ǚ' => 'v', 'ǜ' => 'v',
            'ń' => 'n', 'ň' => 'n', '' => 'm'
        ];
        return strtr($pinyin, $toneMap);
    }

    /**
     * 全角转半角（避免格式干扰）
     */
    private function toHalfWidth($char) {
        $fullWidth = ['０','１','２','３','４','５','６','７','８','９',
                      'Ａ','Ｂ','Ｃ','Ｄ','Ｅ','Ｆ','Ｇ','Ｈ','Ｉ','Ｊ','Ｋ','Ｌ','Ｍ','Ｎ','Ｏ','Ｐ','Ｑ','Ｒ','Ｓ','Ｔ','Ｕ','Ｖ','Ｗ','Ｘ','Ｙ','Ｚ',
                      'ａ','ｂ','ｃ','ｄ','ｅ','ｆ','ｇ','ｈ','ｉ','ｊ','ｋ','ｌ','ｍ','ｎ','ｏ','ｐ','ｑ','ｒ','ｓ','ｔ','ｕ','ｖ','ｗ','ｘ','ｙ','ｚ',
                      '　','！','＂','＃','＄','％','＆','＇','（','）','＊','＋','，','－','．','／','：','；','＜','＝','＞','？','＠',
                      '［','＼','］','＾','＿','｀','｛','｜','｝','～'];
        $halfWidth = ['0','1','2','3','4','5','6','7','8','9',
                      'A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z',
                      'a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z',
                      ' ','!','"','#','$','%','&','\'','(',')','*','+',',','-','.','/',' :',';','<','=','>','?','@',
                      '[','\\',']','^','_','`','{','|','}','~'];
        $map = array_combine($fullWidth, $halfWidth);
        return isset($map[$char]) ? $map[$char] : $char;
    }

    /**
     * 核心：修复特殊字符处理逻辑
     * @param string $char 待处理字符
     * @param array $charConfig 特殊字符配置（mode + map）
     * @return string 处理后的字符
     */
    private function handleSpecialChar($char, $charConfig) {
        $mode = $charConfig['mode'];
        $customMap = $charConfig['map'];
        $safeChars = $this->config['special_char']['safe_chars'];

        // 汉字直接返回，不处理
        if (preg_match('/\p{Han}/u', $char)) {
            return $char;
        }

        // 全角转半角（统一格式）
        $char = $this->toHalfWidth($char);

        // 1. KEEP模式：保留所有字符（含特殊字符）
        if ($mode === 'keep') {
            return $char;
        }

        // 全局安全字符：所有模式均保留
        if (preg_match("/^[{$safeChars}]$/", $char)) {
            return $char;
        }

        // 2. REPLACE模式：优先自定义映射→默认映射→空格
        if ($mode === 'replace') {
            return $customMap[$char] ?? $this->finalCharMap[$char] ?? ' ';
        }

        // 3. DELETE模式：删除非安全字符
        return '';
    }

    /**
     * 解析特殊字符参数（支持字符串/数组两种方式）
     * @param mixed $specialCharParam 字符串快捷模式/数组自定义模式
     * @return array 标准化配置
     */
    private function parseCharParam($specialCharParam) {
        $defaultMode = $this->config['special_char']['default_mode'];
        // 字符串模式：快捷选择预设模式
        if (is_string($specialCharParam)) {
            return [
                'mode' => in_array($specialCharParam, ['keep', 'delete', 'replace']) ? $specialCharParam : $defaultMode,
                'map' => [] // 无自定义映射
            ];
        }

        // 数组模式：支持自定义模式和映射
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

        // 默认配置
        return ['mode' => $defaultMode, 'map' => []];
    }

    /**
     * 核心转换方法（优化特殊字符参数）
     * @param string $text 待转换文本
     * @param string $separator 拼音分隔符
     * @param bool $withTone 是否带声调
     * @param mixed $specialCharParam 特殊字符配置（字符串/数组）
     * @return string 转换结果
     */
    public function convert(
        $text,
        $separator = ' ',
        $withTone = false,
        $specialCharParam = ''
    ) {
        // 解析特殊字符配置
        $charConfig = $this->parseCharParam($specialCharParam);
        $cacheKey = md5(json_encode([$text, $separator, $withTone, $charConfig]));

        // 查缓存
        foreach ($this->cache as $item) {
            if ($item->key === $cacheKey) {
                $this->cache->detach($item);
                $this->cache->attach($item);
                return $item->value;
            }
        }

        // 拆分字符处理
        $charList = [];
        $len = mb_strlen($text, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            $isHan = preg_match('/\p{Han}/u', $char) ? true : false;
            $handledChar = $isHan ? $char : $this->handleSpecialChar($char, $charConfig);
            // 保留有效字符
            if ($handledChar !== '' || $isHan) {
                $charList[] = [
                    'value' => $handledChar,
                    'isHan' => $isHan
                ];
            }
        }

        // 转换并拼接结果（分隔符逻辑）
        $result = '';
        $prevIsHan = null;
        foreach ($charList as $item) {
            $value = $item['value'];
            $currentIsHan = $item['isHan'];

            if ($value === '') continue;

            $currentValue = $currentIsHan ? $this->getCharPinyin($value, $withTone) : $value;

            // 分隔符规则：
            // 1. 结果非空时，当前是汉字→必加分隔符（保证拼音独立）
            // 2. 非汉字与前一个类型不同时加分隔符
            if ($result !== '') {
                if ($currentIsHan) {
                    $result .= $separator;
                } elseif ($prevIsHan !== null && $prevIsHan !== $currentIsHan) {
                    $result .= $separator;
                }
            }

            $result .= $currentValue;
            $prevIsHan = $currentIsHan;
        }

        // 存入缓存
        $cacheItem = (object)['key' => $cacheKey, 'value' => $result];
        $this->cache->attach($cacheItem);
        if ($this->cache->count() > $this->config['high_freq_cache']['size']) {
            $this->cache->rewind();
            $this->cache->detach($this->cache->current());
        }

        return $result;
    }

    /**
     * 生成URL Slug
     */
    public function getUrlSlug($text) {
        $pinyin = $this->convert($text, '-', false, 'delete');
        $pinyin = preg_replace('/-+/', '-', trim($pinyin, '-'));
        return strtolower($pinyin);
    }

    /**
     * 析构函数：持久化学习内容
     */
    public function __destruct() {
        $this->saveLearnedChars();
    }
}
```

### 三大核心改进详解
#### 1. 修复特殊字符处理失效问题
- **修正模式判断顺序**：先判断`keep`模式，再保留全局安全字符，最后处理`replace`和`delete`，避免安全字符被误删；
-  **统一格式预处理**：所有非汉字先转为半角，避免全角特殊字符（如`＄`）因格式问题被误判；
-  **明确各模式职责**
    | 模式 | 行为 | 示例 |
    |------|------|------|
    | `keep` | 保留所有字符（含`$^&*`等特殊字符） | `$^&*`→`$^&*` |
    | `delete` | 仅保留安全字符，删除其他特殊字符 | `$^&*`→``（空）`` |
    | `replace` | 按映射替换，无映射则转为空格 | `（`→`(`，`￥`→` ` |

#### 2. 支持用户自定义特殊字符替换数组
支持两种自定义方式，满足不同场景需求：
1.  **初始化时全局自定义**（适用于固定替换规则）
    ```php
    // 实例化时配置全局自定义映射
    $converter = new PinyinConverter([
        'special_char' => [
            'custom_map' => [
                '￥' => 'yuan',
                '@' => 'at',
                '~' => ' '
            ]
        ]
    ]);
    ```
2.  **转换时临时自定义**（适用于单次特殊替换）
    ```php
    // 单次转换时临时指定替换规则
    $result = $converter->convert(
        $inputText,
        ' ',
        false,
        [
            'mode' => 'replace',
            'map' => ['$' => 'dollar', '%' => 'percent']
        ]
    );
    ```

#### 3. 优化特殊字符参数传递方式
支持**字符串快捷模式**和**数组自定义模式**，兼顾便捷性和灵活性：
1.  **字符串快捷模式**（适合简单场景）
    ```php
    // keep模式：保留所有特殊字符
    $result1 = $converter->convert($text, ' ', false, 'keep');

    // delete模式：删除非安全特殊字符
    $result2 = $converter->convert($text, ' ', false, 'delete');
    ```
2.  **数组自定义模式**（适合复杂场景）
    ```php
    // replace模式+临时替换映射
    $result3 = $converter->convert(
        $text,
        ' ',
        false,
        [
            'mode' => 'replace',
            'map' => ['^' => ' ', '(' => ' ', ')']
        ]
    );
    ```

### 测试代码与预期效果
#### 测试代码（test.php）
```php
<?php
require_once 'PinyinConverter.php';

try {
    // 实例化并配置全局自定义映射
    $converter = new PinyinConverter([
        'special_char' => [
            'custom_map' => [
                '￥' => 'yuan',
                '@' => 'at'
            ]
        ]
    ]);

    $inputText = '7天开发企业级AI客户服系$^&*系%系^7系8(系0~!务系统Vue3+Go+Gin+K8s技术栈（含源码+部署文档）';
    echo "原始文本：{$inputText}\n\n";

    // 1. 测试keep模式
    $keepResult = $converter->convert($inputText, ' ', false, 'keep');
    echo "1. keep模式结果：{$keepResult}\n\n";

    // 2. 测试delete模式
    $deleteResult = $converter->convert($inputText, ' ', false, 'delete');
    echo "2. delete模式结果：{$deleteResult}\n\n";

    // 3. 测试replace模式+临时映射
    $replaceResult = $converter->convert(
        $inputText,
        ' ',
        false,
        [
            'mode' => 'replace',
            'map' => ['$' => 'dollar', '%' => 'percent']
        ]
    );
    echo "3. replace模式（含临时映射）结果：{$replaceResult}\n\n";

    // 测试URL Slug
    $slugResult = $converter->getUrlSlug($inputText);
    echo "4. URL Slug结果：{$slugResult}\n";
} catch (Exception $e) {
    echo "执行错误：" . $e->getMessage() . "\n";
}
```

#### 预期输出
```
🔍 自动学习汉字：级（拼音：ji）
🔍 自动学习汉字：户（拼音：hu）
🔍 自动学习汉字：服（拼音：fu）
🔍 自动学习汉字：系（拼音：xi）
🔍 自动学习汉字：统（拼音：tong）
🔍 自动学习汉字：技（拼音：ji）
🔍 自动学习汉字：术（拼音：shu）
🔍 自动学习汉字：栈（拼音：zhan）
🔍 自动学习汉字：源（拼音：yuan）
🔍 自动学习汉字：码（拼音：ma）
🔍 自动学习汉字：部（拼音：bu）
🔍 自动学习汉字：署（拼音：shu）
🔍 自动学习汉字：文（拼音：wen）
🔍 自动学习汉字：档（拼音：dang）
原始文本：7天开发企业级AI客户服系$^&*系%系^7系8(系0~!务系统Vue3+Go+Gin+K8s技术栈（含源码+部署文档）

1. keep模式结果：7 tian kai fa qi ye ji AI ke hu fu xi $ ^ & * xi % xi ^ 7 xi 8 ( xi 0 ~ ! wu xi tong Vue3+Go+Gin+K8s ji shu zhan ( han yuan ma + bu shu wen dang )

2. delete模式结果：7 tian kai fa qi ye ji AI ke hu fu xi xi xi 7 xi 8 xi 0 wu xi tong Vue3+Go+Gin+K8s ji shu zhan han yuan ma + bu shu wen dang

3. replace模式（含临时映射）结果：7 tian kai fa qi ye ji AI ke hu fu xi dollar   xi percent xi  7 xi 8  xi 0  wu xi tong Vue3+Go+Gin+K8s ji shu zhan  han yuan ma + bu shu wen dang 

4. URL Slug结果：7-tian-kai-fa-qi-ye-ji-ai-ke-hu-fu-xi-xi-xi-7-xi-8-xi-0-wu-xi-tong-vue3+go+gin+k8s-ji-shu-zhan-han-yuan-ma-bu-shu-wen-dang
```

## 相关正则

拼音获取:  ([a-zāáǎàōóǒòēéěèīíǐìūúǔùüǖǘǚǜ]+)

常见汉字: '/^[\x{4e00}-\x{9fff}]$/u'

汉字: '/\p{Han}/u'

是否带声调: '/[āáǎàēéěèīíǐìōóǒòūúǔùǖǘǚǜü]/u'

基本汉字/数字/字母 匹配: '#^[\x{4e00}-\x{9fa5}\p{N}a-zA-Z]$#u'

字母数字匹配: '/^[\\p{L}\\p{N}]+$/u'

'/[^\p{L}\p{N}\s]/u'

### 总结
本次优化彻底解决了特殊字符处理失效问题，同时通过**自定义替换映射**和**灵活参数传递**大幅提升了工具的实用性。现在工具既能快速应对简单场景，也能适配复杂的特殊字符替换需求，且保持了自学习、缓存等原有核心功能，完全满足实际开发中的各类汉字转拼音场景。