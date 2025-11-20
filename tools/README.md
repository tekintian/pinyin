# 字典管理工具集

本目录包含多个字典格式标准化和管理工具。

## 工具概览

### 1. quick_format.php - 快速格式化
最简单的格式化工具，适合日常使用。

```bash
php tools/quick_format.php
```

**特点：**
- 轻量级，执行快速
- 自动检测需要格式化的文件
- 基本备份功能
- 简洁的输出

### 2. standardize_dictionary_format.php - 标准格式化
功能完整的格式化工具，包含验证和详细日志。

```bash
php tools/standardize_dictionary_format.php
```

**特点：**
- 完整的错误处理
- 格式验证
- 详细的处理日志
- 自动备份和恢复提示

### 3. smart_dictionary_manager.php - 智能管理器
功能最全面的字典管理工具。

```bash
# 显示统计信息
php tools/smart_dictionary_manager.php stats

# 格式化所有文件
php tools/smart_dictionary_manager.php format

# 仅格式化custom文件
php tools/smart_dictionary_manager.php format custom

# 验证文件格式
php tools/smart_dictionary_manager.php validate

# 显示帮助
php tools/smart_dictionary_manager.php help
```

**特点：**
- 批量处理
- 分类操作（custom/self/rare）
- 统计信息显示
- 格式验证
- 智能备份管理

## 使用建议

### 日常维护
使用 `quick_format.php` 进行快速格式化：
```bash
php tools/quick_format.php
```

### 详细检查
使用 `smart_dictionary_manager.php` 进行全面检查：
```bash
php tools/smart_dictionary_manager.php stats
php tools/smart_dictionary_manager.php validate
```

### CI/CD集成
在自动化流程中使用：
```bash
# 检查格式（不修改）
php tools/smart_dictionary_manager.php validate

# 自动修复格式
php tools/standardize_dictionary_format.php
```

## 目标文件

所有工具都处理以下字典文件：
- `data/custom_*.php` - 自定义字典
- `data/self_*.php` - 自学习字典  
- `data/rare_*.php` - 罕用字字典

**排除：** `polyphone_rules.php`（需要保留人工注释）

## 格式标准

统一使用紧凑格式：
```php
<?php
return [
    '字典' => ['zi dian'],
    '格式' => ['ge shi'],
];
```

而非：
```php
<?php
return array (
  '字典' => 
  array (
    0 => 'zi dian',
  ),
);
```

## 安全保障

1. **自动备份** - 修改前创建备份
2. **格式检测** - 只处理需要修改的文件
3. **语法验证** - 确保PHP语法正确
4. **回滚支持** - 可通过备份文件恢复

## 故障排除

### 备份文件位置
- 所有工具：`data/backup/filename.php.backup.timestamp`

### 恢复文件
```bash
# 查看备份
ls data/backup/*.backup.*

# 恢复
cp data/backup/custom_no_tone.php.backup.2025-11-20_12-00-00 data/custom_no_tone.php
```

### 权限问题
确保工具有读写权限：
```bash
chmod +x tools/*.php
chmod -R 755 data/
```