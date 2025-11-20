# Unihan数据库自动更新文档

## 概述

本项目配置了GitHub Actions工作流，用于自动更新Unicode Unihan数据库。Unihan数据库包含了汉字的读音、部首、笔画等重要信息，是拼音转换功能的核心数据源。

## 更新机制

### 定时更新

- **频率**: 每周一早上8:00（UTC时间）自动运行
- **检查逻辑**: 检查当前数据库文件的时间戳，如果超过7天未更新则执行更新
- **备份策略**: 更新前自动备份当前数据库到 `unicode/backup/` 目录

### 手动触发

- **方式**: 在GitHub Actions页面手动触发工作流
- **强制更新选项**: 可选择强制更新，忽略时间检查
- **标签创建**: 强制更新时会创建版本标签

## 工作流详情

### 触发条件

```yaml
on:
  schedule:
    - cron: '0 8 * * 1'  # 每周一8:00 UTC
  workflow_dispatch:     # 手动触发
    inputs:
      force_update:
        description: '强制更新数据库'
        required: false
        default: 'false'
        type: boolean
```

### 主要步骤

1. **环境准备**
   - 检出代码
   - 设置PHP 8.2环境
   - 安装依赖

2. **更新检查**
   - 检查数据库文件最后更新时间
   - 计算距离上次更新的天数
   - 决定是否需要更新

3. **数据更新**
   - 使用 `php unicode/extract_unihan.php --update` 更新数据
   - 使用 `php unicode/extract_unihan.php --extract` 提取拼音字典
   - 清理临时文件和目录

4. **测试验证**
   - 运行单元测试
   - 基本功能测试
   - 验证数据完整性

5. **提交更改**
   - 自动提交更新到仓库
   - 只提交 `data/unihan/` 目录的生成文件

## 数据提取工具

### 使用现有工具

工作流使用项目现有的 `unicode/extract_unihan.php` 工具：

```bash
# 更新Unihan数据
php unicode/extract_unihan.php --update [--force]

# 提取拼音字典
php unicode/extract_unihan.php --extract
```

### 工具功能

- **自动下载**: 从Unicode官网下载最新Unihan数据
- **智能缓存**: 支持缓存机制，避免重复下载
- **数据验证**: 验证下载数据的完整性
- **分类提取**: 按字符显示特性分类生成PHP文件
- **错误处理**: 完善的错误处理和重试机制

## 临时文件管理

### 临时文件和目录

- **下载压缩包**: `unicode/Unihan.zip` (用完删除)
- **解压目录**: `unicode/temp_unihan/` (用完删除)
- **说明**: 这些文件是数据提取过程中的临时文件，不纳入git版本控制

### 清理策略

工作流会自动清理临时文件：
```bash
rm -f unicode/Unihan.zip      # 删除下载的压缩包
rm -rf unicode/temp_unihan    # 删除临时解压目录
```

## 版本控制

### Git管理

- **生成文件**: `data/unihan/` 目录已纳入git版本控制
- **临时文件**: `unicode/temp_unihan/` 和 `unicode/Unihan.zip` 不纳入版本控制
- **恢复方式**: 使用git历史记录恢复数据文件

### 数据恢复

如需恢复到之前的版本：

```bash
# 查看提交历史
git log --oneline -- data/unihan/

# 恢复到指定提交
git checkout <commit-hash> -- data/unihan/
```

## 目录结构

```
unicode/
├── Unihan.zip                    # 下载的Unihan压缩包（临时）
├── temp_unihan/                  # 解压后的临时目录（用完即删）
│   ├── Unihan_Readings.txt       # 拼音读音数据
│   ├── Unihan_DictionaryLikeData.txt
│   └── ...                       # 其他Unihan数据文件
├── extract_unihan.php            # 数据提取脚本
└── update_log.md                 # 更新日志（可选）

data/unihan/                      # 生成的PHP数据文件（git版本控制）
├── all_unihan_pinyin.php         # 所有Unihan拼音数据
├── cjk_basic.php                 # CJK基本汉字
├── cjk_ext_a.php                 # CJK扩展A
└── ...                           # 其他分类文件
```

**说明：**
- `unicode/temp_unihan/` 是数据提取的临时目录，非git管理，用完即可删除
- `unicode/Unihan.zip` 是下载的压缩包，处理完成后也会删除
- `data/unihan/` 是最终生成的PHP数据文件，已纳入git版本控制

## 监控和通知

### 成功指标

- ✅ 文件下载成功
- ✅ 数据处理完成
- ✅ 测试验证通过
- ✅ 代码提交成功

### 失败处理

- 网络下载失败时自动尝试备用源
- 数据验证失败时保留原数据库
- 测试失败时记录错误但不阻止提交

### 通知方式

- GitHub Actions状态页面显示执行结果
- 工作流日志记录详细过程
- 提交信息包含更新摘要

## 手动管理

### 强制更新

1. 访问GitHub Actions页面
2. 选择"Update Unihan Database"工作流
3. 点击"Run workflow"
4. 勾选"force_update"选项
5. 点击"Run workflow"按钮

### 查看更新历史

```bash
# 查看Git提交历史
git log --oneline -- data/unihan/

# 查看文件变更
git log --stat -- data/unihan/

# 查看特定提交的变更
git show <commit-hash> -- data/unihan/
```

### 自定义配置

如需修改更新频率或行为，编辑 `.github/workflows/update-unihan.yml`：

```yaml
# 修改定时更新频率（例如改为每两周）
schedule:
  - cron: '0 8 * * 1'  # 每周一8:00 UTC
  # - cron: '0 8 1,15 * *'  # 每月1日和15日
```

## 故障排除

### 常见问题

1. **下载失败**
   - 检查网络连接
   - 验证Unicode官网可访问性
   - 查看备用源是否可用

2. **文件验证失败**
   - 检查下载文件大小
   - 验证文件格式正确性
   - 查看是否有权限问题

3. **测试失败**
   - 检查PHP环境配置
   - 验证依赖包完整性
   - 查看错误日志详情

### 调试方法

1. **查看工作流日志**
   - 在GitHub Actions页面查看详细执行日志
   - 关注错误信息和警告

2. **本地测试**
   ```bash
   # 手动运行更新脚本
   php unicode/extract_unihan.php --update --force
   php unicode/extract_unihan.php --extract
   
   # 验证数据库功能
   php -r "require 'vendor/autoload.php'; \$p = new \tekintian\pinyin\PinyinConverter(); print_r(\$p->convert('测试'));"
   ```

3. **检查文件状态**
   ```bash
   # 检查生成的PHP文件（主要关注）
   ls -la data/unihan/
   
   # 检查临时文件（应该为空或不存在）
   ls -la unicode/temp_unihan/
   
   # 检查是否有未清理的下载文件
   ls -la unicode/Unihan.zip
   ```

### 本地生成数据
```bash
# 更新Unihan数据库
php unicode/extract_unihan.php --update --force

# 提取和生成PHP数据文件
php unicode/extract_unihan.php --extract

# 执行完整流程（更新+提取）
php unicode/extract_unihan.php

# 验证数据完整性
php unicode/extract_unihan.php --validate

# 生成数据报告
php unicode/extract_unihan.php --report
```

## 最佳实践

1. **定期监控**: 定期检查更新日志和执行状态
2. **备份管理**: 重要更新前手动创建额外备份
3. **测试验证**: 更新后及时运行完整测试套件
4. **版本控制**: 重要的数据库更新创建标签标记
5. **文档更新**: 重大变更后更新相关文档

## 相关链接

- [Unicode Unihan数据库](https://unicode.org/reports/tr38/)
- [GitHub Actions文档](https://docs.github.com/en/actions)
- [项目测试文档](./测试指南.md)
- [项目压力测试文档](./压力测试文档.md)