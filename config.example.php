<?php

/**
 * PinyinConverter 配置文件示例
 * 复制此文件为 config.php 并根据需要修改配置
 */

return [
    // 应用基础配置
    'app' => [
        'debug' => (bool)getenv('APP_DEBUG'),
        'env' => getenv('APP_ENV') ?: 'production',
        'version' => '2.0.0'
    ],

    // 字典配置
    'dict' => [
        'root_path' => getenv('PINYIN_DICT_ROOT_PATH') ?: __DIR__ . '/data',
        'files' => [
            // 自定义字典
            'custom' => [
                'with_tone' => 'custom_with_tone.php',
                'no_tone' => 'custom_no_tone.php'
            ],
            // 常用字典
            'common' => [
                'with_tone' => 'common_with_tone.php',
                'no_tone' => 'common_no_tone.php'
            ],
            // 生僻字字典
            'rare' => [
                'with_tone' => 'rare_with_tone.php',
                'no_tone' => 'rare_no_tone.php'
            ],
            // Unicode扩展字典
            'unihan' => [
                'with_tone' => 'unihan/cjk_ext_a.php',
                'no_tone' => 'unihan/cjk_ext_a_no_tone.php'
            ],
            // 自学习字典
            'self_learn' => [
                'with_tone' => 'self_learn_with_tone.php',
                'no_tone' => 'self_learn_no_tone.php'
            ],
            // 多音字规则
            'polyphone_rules' => 'polyphone_rules.php',
            // 字频统计
            'frequency' => 'char_frequency.php',
            // 备份目录
            'backup' => 'backup/',
            // 未找到字符记录
            'not_found' => 'diy/not_found_chars.php'
        ]
    ],

    // 字典加载配置
    'loading' => [
        'strategy' => 'both', // 'both'|'with_tone'|'no_tone'
        'lazy_loading' => [
            'enabled' => true,
            'preload_priority' => ['custom', 'common'],
            'lazy_dicts' => ['rare', 'unihan'],
            'batch_size' => 50
        ],
        'performance' => [
            'memory_limit' => '256M',
            'max_load_time' => 30,
            'enable_profiling' => false
        ]
    ],

    // 缓存配置
    'cache' => [
        'high_frequency' => [
            'enabled' => true,
            'size' => (int)(getenv('PINYIN_CACHE_SIZE') ?: 1000),
            'ttl' => 3600 // 1小时
        ],
        'persistence' => [
            'enabled' => true,
            'delayed_write' => true,
            'max_delayed_entries' => 100,
            'auto_save_interval' => 30,
            'save_on_destruct' => true
        ],
        'redis' => [
            'enabled' => (bool)getenv('PINYIN_REDIS_ENABLED'),
            'host' => getenv('PINYIN_REDIS_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('PINYIN_REDIS_PORT') ?: 6379),
            'password' => getenv('PINYIN_REDIS_PASSWORD') ?: null,
            'prefix' => 'pinyin:',
            'ttl' => 86400 // 24小时
        ]
    ],

    // 特殊字符处理
    'special_char' => [
        'default_mode' => 'delete', // 'delete'|'replace'|'keep'
        'custom_map' => [
            // 自定义字符映射
            // '&' => 'and',
            // '@' => 'at'
        ],
        'allowed_chars' => [
            // 允许保留的特殊字符
            // '-', '_', '.'
        ]
    ],

    // 自学习功能
    'self_learn' => [
        'enabled' => (bool)getenv('PINYIN_SELF_LEARN_ENABLED') ?: true,
        'merge' => [
            'threshold' => 1000, // 触发合并的阈值
            'batch_threshold' => 50, // 批量处理阈值
            'incremental' => true, // 增量合并
            'max_per_merge' => 500, // 每次合并的最大条目数
            'frequency_limit' => 86400, // 24小时执行一次
            'backup_before_merge' => true, // 合并前备份
            'sort_by_frequency' => true, // 按频率排序
            'enable_background_task' => true // 启用后台任务
        ]
    ],

    // 后台任务
    'background_tasks' => [
        'enabled' => (bool)getenv('PINYIN_BACKGROUND_TASKS_ENABLED') ?: true,
        'task_dir' => 'tasks/',
        'max_concurrent' => 3,
        'task_types' => [
            'not_found_resolve' => [
                'description' => '处理未找到拼音的字符',
                'priority' => 1,
                'batch_size' => 50,
                'auto_execute' => true
            ],
            'self_learn_merge' => [
                'description' => '自学习字典合并',
                'priority' => 2,
                'batch_size' => 100,
                'auto_execute' => true
            ]
        ]
    ]
];