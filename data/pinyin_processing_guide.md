# 生僻字拼音处理指南

## 处理流程

1. **查询工具准备**
   - 《汉语大字典》（推荐）
   - 《康熙字典》
   - 汉字叔叔网站（http://zi.tools）
   - 国学大师（http://www.guoxuedashi.com）

2. **查询步骤**
   - 复制汉字到查询工具
   - 记录正确的拼音（带声调）
   - 记录参考来源

3. **填写确认信息**
   - 修改 `confirmed_pinyin` 字段
   - 设置 `confirmed = true`
   - 填写 `confirmed_by`（处理人）
   - 填写 `confirmed_at`（确认时间）

## 字段说明

| 字段名 | 说明 | 示例 |
|--------|------|------|
| `char` | 汉字 | '𠮷' |
| `unicode` | Unicode编码 | 'U+20BB7' |
| `confirmed_pinyin` | 确认的拼音 | 'jí' |
| `confirmed_by` | 处理人 | '张三' |
| `reference_source` | 参考来源 | '《汉语大字典》' |

## 批量导入脚本

确认完成后，可以使用以下脚本批量导入到自定义字典：

```php
// 导入到自定义字典的示例脚本
$pendingData = require 'pinyin_pending_confirmation.php';
$customDict = [];

foreach ($pendingData as $item) {
    if ($item['confirmed']) {
        $customDict[$item['char']] = $item['confirmed_pinyin'];
    }
}

// 保存到自定义字典
file_put_contents('custom_with_tone.php', '<?php\nreturn ' . var_export($customDict, true) . ';\n');
```

## 注意事项

- 确保拼音准确性，特别是声调
- 记录参考来源以便复查
- 定期备份处理进度
