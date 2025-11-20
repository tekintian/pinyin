# 字典格式标准化工具

## 概述

提供两个脚本来标准化字典文件格式，将非紧凑格式转换为紧凑友好的格式。

## 脚本说明

### 1. standardize_dictionary_format.php（推荐）

功能完整的标准版本，包含：
- 格式检测和转换
- 自动备份
- 错误处理
- 结果验证
- 详细日志输出

```bash
php tools/standardize_dictionary_format.php
```

### 2. quick_format.php（快速版本）

简化版本，适合快速格式化：
- 核心格式转换功能
- 简化输出
- 基本备份功能

```bash
php tools/quick_format.php
```

## 目标文件

处理以下模式的字典文件：
- `data/custom_*.php`
- `data/self_*.php` 
- `data/rare_*.php`

排除：`polyphone_rules.php`（需要保留注释）

## 格式转换示例

### 转换前（非紧凑格式）
```php
<?php
return array (
  '云南' => 
  array (
    0 => 'yun nan',
  ),
  '北京' => 
  array (
    0 => 'bei jing',
  ),
);
```

### 转换后（紧凑格式）
```php
<?php
return [
    '云南' => ['yun nan'],
    '北京' => ['bei jing'],
];
```

## 安全特性

1. **自动备份**：修改前自动创建备份文件到 `data/backup/` 目录
2. **原始格式保留**：备份文件保持原始格式，不做任何修改
3. **格式检测**：只处理需要格式化的文件
4. **语法验证**：确保转换后的文件语法正确
5. **回滚支持**：可使用备份文件恢复原格式

## 使用场景

- **定期维护**：统一字典文件格式
- **新文件集成**：将新增字典文件标准化
- **CI/CD流程**：自动化代码质量检查
- **开发环境**：确保格式一致性

## 注意事项

1. 脚本会自动跳过已经是紧凑格式的文件
2. 备份文件会保留原始格式，可安全恢复
3. 建议在版本控制下运行，便于追踪变更
4. 大文件处理可能需要较长时间

## 恢复操作

如需恢复某个文件：
```bash
# 查看备份文件
ls data/backup/custom_no_tone.php.backup.*

# 恢复（替换原文件）
cp data/backup/custom_no_tone.php.backup.2025-11-20_12-00-00 data/custom_no_tone.php
```