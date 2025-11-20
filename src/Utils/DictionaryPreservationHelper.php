<?php

namespace tekintian\pinyin\Utils;

/**
 * 字典文件保护助手
 * 用于在保存字典时保留注释和格式
 */
class DictionaryPreservationHelper
{
    /**
     * 安全保存多音字规则文件，保留注释
     * @param string $filePath 文件路径
     * @param array $newData 新的规则数据
     * @return bool
     */
    public static function preservePolyphoneRules($filePath, $newData)
    {
        // 读取原文件内容
        $originalContent = '';
        if (file_exists($filePath)) {
            $originalContent = file_get_contents($filePath);
        }

        // 提取注释
        $comments = self::extractComments($originalContent);

        // 生成新的数据内容
        $newContent = "<?php\n";

        // 添加保留的注释
        if (!empty($comments)) {
            $newContent .= $comments . "\n";
        }

        // 添加新的数据
        $newContent .= "return " . self::exportPolyphoneRules($newData) . ";\n";

        // 写入文件
        return file_put_contents($filePath, $newContent) !== false;
    }

    /**
     * 提取PHP文件中的注释
     * @param string $content 文件内容
     * @return string 提取的注释
     */
    private static function extractComments($content)
    {
        if (empty($content)) {
            return '';
        }

        // 移除PHP开始标签
        $content = preg_replace('/^<\?php\s*/', '', $content);

        // 移除return语句及之后的内容
        $content = preg_replace('/\s*return\s*\[.*$/s', '', $content);

        // 清理多余的空白行
        $content = trim($content);

        return $content;
    }

    /**
     * 导出多音字规则，保持可读性
     * @param array $rules 规则数组
     * @return string 格式化的PHP代码
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
     * 检查多音字规则中的weight配置是否有效
     * @param array $rules 规则数组
     * @return array 分析结果
     */
    public static function analyzeWeightUsage($rules)
    {
        $analysis = [
            'total_rules' => 0,
            'rules_with_weight' => 0,
            'weight_values' => [],
            'invalid_weights' => [],
            'weight_effectiveness' => []
        ];

        foreach ($rules as $char => $charRules) {
            if (!is_array($charRules)) {
                continue;
            }

            foreach ($charRules as $rule) {
                if (!is_array($rule)) {
                    continue;
                }

                $analysis['total_rules']++;

                if (isset($rule['weight'])) {
                    $analysis['rules_with_weight']++;
                    $weight = $rule['weight'];

                    // 记录权重值
                    $analysis['weight_values'][] = $weight;

                    // 检查权重值是否有效
                    if (!is_numeric($weight) || $weight < 0) {
                        $analysis['invalid_weights'][] = [
                            'char' => $char,
                            'rule' => $rule,
                            'weight' => $weight
                        ];
                    }

                    // 分析权重的实际效果
                    $ruleType = $rule['type'] ?? '';
                    $baseScore = self::getBaseScore($ruleType);
                    $adjustedScore = $baseScore * floatval($weight);

                    $analysis['weight_effectiveness'][] = [
                        'char' => $char,
                        'rule_type' => $ruleType,
                        'weight' => $weight,
                        'base_score' => $baseScore,
                        'adjusted_score' => $adjustedScore,
                        'impact' => $adjustedScore !== $baseScore
                    ];
                }
            }
        }

        // 计算统计信息
        if (!empty($analysis['weight_values'])) {
            $analysis['weight_stats'] = [
                'min' => min($analysis['weight_values']),
                'max' => max($analysis['weight_values']),
                'avg' => array_sum($analysis['weight_values']) / count($analysis['weight_values'])
            ];
        }

        return $analysis;
    }

    /**
     * 获取规则类型的基础分数
     * @param string $ruleType 规则类型
     * @return float 基础分数
     */
    private static function getBaseScore($ruleType)
    {
        $baseScores = [
            'word' => 1.0,
            'post' => 0.8,
            'pre' => 0.8,
            'sentence_start' => 0.7,
            'sentence_end' => 0.7
        ];

        return $baseScores[$ruleType] ?? 0.5;
    }

    /**
     * 生成权重使用报告
     * @param array $analysis 分析结果
     * @return string 报告内容
     */
    public static function generateWeightReport($analysis)
    {
        $report = "# 多音字规则权重分析报告\n\n";

        $report .= "## 基本统计\n";
        $report .= "- 总规则数: {$analysis['total_rules']}\n";
        $report .= "- 包含权重的规则数: {$analysis['rules_with_weight']}\n";
        $report .= "- 权重使用率: " . round($analysis['rules_with_weight'] / max($analysis['total_rules'], 1) * 100, 2) . "%\n\n";

        if (!empty($analysis['weight_stats'])) {
            $report .= "## 权重值统计\n";
            $report .= "- 最小值: {$analysis['weight_stats']['min']}\n";
            $report .= "- 最大值: {$analysis['weight_stats']['max']}\n";
            $report .= "- 平均值: " . round($analysis['weight_stats']['avg'], 3) . "\n\n";
        }

        if (!empty($analysis['invalid_weights'])) {
            $report .= "## 无效权重\n";
            foreach ($analysis['invalid_weights'] as $invalid) {
                $report .= "- 字符 '{$invalid['char']}' 的权重值 '{$invalid['weight']}' 无效\n";
            }
            $report .= "\n";
        }

        $effectiveWeights = array_filter($analysis['weight_effectiveness'], function ($item) {
            return $item['impact'];
        });

        if (!empty($effectiveWeights)) {
            $report .= "## 有效权重规则\n";
            foreach ($effectiveWeights as $effective) {
                $report .= "- 字符 '{$effective['char']}' ({$effective['rule_type']}): " .
                           "{$effective['weight']} ({$effective['base_score']} -> {$effective['adjusted_score']})\n";
            }
        } else {
            $report .= "## 权重效果分析\n";
            $report .= "当前所有权重配置都没有实际效果，建议:\n";
            $report .= "1. 移除所有 weight='1' 的配置\n";
            $report .= "2. 为需要调整优先级的规则设置有效的权重值\n";
        }

        return $report;
    }
}
