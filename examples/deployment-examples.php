<?php
/**
 * Composer 自动部署示例
 * 演示不同场景下的词库部署配置
 */

echo "=== 拼音转换库 Composer 自动部署示例 ===\n\n";

// 示例1: 开发环境部署
echo "示例1: 开发环境部署\n";
echo "-------------------\n";
echo "命令: composer install\n";
echo "说明: 首次安装时进入交互模式，配置词库路径\n";
echo "默认路径: 项目根目录/data/\n";
echo "自动创建: .env 文件\n\n";

// 示例2: 生产环境部署
echo "示例2: 生产环境部署\n";
echo "-------------------\n";
echo "命令: export COMPOSER_PROD_INSTALL=1 && composer install --no-dev\n";
echo "说明: 生产环境标识，跳过自动部署\n";
echo "后续: 手动部署到共享位置\n\n";

// 示例3: 自定义路径部署
echo "示例3: 自定义路径部署\n";
echo "---------------------\n";
echo "命令: composer run-script deploy-dicts-to -- --target=/shared/dicts\n";
echo "说明: 部署到指定目录\n";
echo "自动更新: .env 文件中的环境变量\n\n";

// 示例4: 强制覆盖部署
echo "示例4: 强制覆盖部署\n";
echo "-------------------\n";
echo "命令: composer run-script deploy-dicts-force\n";
echo "说明: 备份现有词库后强制覆盖\n";
echo "备份位置: 原目录.backup.时间戳\n\n";

// 示例5: Docker 部署
echo "示例5: Docker 部署\n";
echo "-----------------\n";
echo "Dockerfile:\n";
echo "  RUN composer install && \\\n";
echo "      composer run-script deploy-dicts-silent -- --target=/app/data\n";
echo "  ENV PINYIN_DICT_ROOT_PATH=/app/data\n\n";

// 示例6: CI/CD 部署
echo "示例6: CI/CD 部署\n";
echo "----------------\n";
echo "GitHub Actions:\n";
echo "  - name: Deploy dictionaries\n";
echo "    run: |\n";
echo "      composer run-script deploy-dicts-silent \\\n";
echo "        -- --target=\${{ github.workspace }}/data\n\n";

// 示例7: 环境变量配置
echo "示例7: 环境变量配置\n";
echo "-------------------\n";
echo ".env 文件内容:\n";
echo "  PINYIN_DICT_ROOT_PATH=/shared/dicts\n";
echo "  PINYIN_SKIP_AUTO_DEPLOY=0\n\n";

// 示例8: 验证部署
echo "示例8: 验证部署\n";
echo "-------------\n";
echo "PHP 代码验证:\n";
echo "  \$dictRoot = getenv('PINYIN_DICT_ROOT_PATH') ?: __DIR__ . '/data';\n";
echo "  echo \"词库路径: {\$dictRoot}\\n\";\n";
echo "  echo \"目录存在: \" . (is_dir(\$dictRoot) ? '是' : '否') . \"\\n\";\n\n";

echo "=== 部署策略对比 ===\n\n";

$strategies = [
    'skip' => [
        'name' => '跳过部署',
        'description' => '使用现有词库，仅更新环境变量',
        'use_case' => '已有正确词库的情况',
        'risk' => '低'
    ],
    'overwrite' => [
        'name' => '覆盖部署', 
        'description' => '备份现有词库后强制覆盖',
        'use_case' => '需要更新词库版本',
        'risk' => '中（有备份）'
    ],
    'change_dir' => [
        'name' => '更换目录',
        'description' => '指定新的部署目录',
        'use_case' => '需要重新组织目录结构',
        'risk' => '低'
    ]
];

foreach ($strategies as $key => $strategy) {
    echo sprintf("%-12s %s\n", $strategy['name'], $strategy['description']);
    echo sprintf("%-12s 适用场景: %s\n", '', $strategy['use_case']);
    echo sprintf("%-12s 风险等级: %s\n\n", '', $strategy['risk']);
}

echo "=== 常见问题解决 ===\n\n";

$troubles = [
    [
        'problem' => '权限不足',
        'solution' => 'chmod 755 /path/to/dictionaries',
        'description' => '确保目标目录有写权限'
    ],
    [
        'problem' => '路径不存在',
        'solution' => 'mkdir -p /path/to/dictionaries',
        'description' => '提前创建目标目录'
    ],
    [
        'problem' => '跳过部署',
        'solution' => 'unset PINYIN_SKIP_AUTO_DEPLOY',
        'description' => '检查并清除跳过部署的环境变量'
    ],
    [
        'problem' => '环境变量未生效',
        'solution' => 'source .env 或重启应用',
        'description' => '确保环境变量正确加载'
    ]
];

foreach ($troubles as $trouble) {
    echo "问题: {$trouble['problem']}\n";
    echo "解决: {$trouble['solution']}\n";
    echo "说明: {$trouble['description']}\n\n";
}

echo "=== 最佳实践建议 ===\n\n";

$practices = [
    '开发环境' => [
        '使用交互式部署',
        '将 .env 添加到 .gitignore',
        '记录部署配置'
    ],
    '生产环境' => [
        '使用环境变量配置',
        '设置 PINYIN_SKIP_AUTO_DEPLOY=1',
        '定期备份词库'
    ],
    '团队协作' => [
        '统一词库版本',
        '使用相对路径',
        'CI/CD 自动化部署'
    ],
    '容器化' => [
        '使用卷挂载',
        '设置环境变量',
        '考虑文件大小'
    ]
];

foreach ($practices as $env => $list) {
    echo "{$env}:\n";
    foreach ($list as $item) {
        echo "  • {$item}\n";
    }
    echo "\n";
}

echo "=== 相关文档 ===\n";
echo "• docs/ComposerAutoDeployment.md - 详细部署文档\n";
echo "• .env.example - 环境变量模板\n";
echo "• scripts/deploy-dictionaries.php - 交互式部署脚本\n";
echo "• scripts/deploy-dictionaries-silent.php - 静默部署脚本\n\n";

echo "部署完成后，可以通过以下方式验证:\n";
echo "php -r \"echo getenv('PINYIN_DICT_ROOT_PATH') ?: 'default', PHP_EOL;\"\n";
echo "php -r \"echo is_dir(getenv('PINYIN_DICT_ROOT_PATH') ?: './data') ? 'OK' : 'FAIL', PHP_EOL;\"\n";