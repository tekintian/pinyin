# BackgroundTaskManager 部署指南

## 🚀 部署方案选择

### 方案一：定时任务（推荐用于生产环境）
**适用场景**：大多数生产环境，任务量适中
**优点**：资源消耗可控，易于监控，稳定性高
**缺点**：实时性稍差

### 方案二：守护进程（推荐用于高实时性场景）
**适用场景**：需要实时处理任务的场景
**优点**：实时响应，处理延迟低
**缺点**：资源消耗较高，需要进程管理

### 方案三：混合模式（推荐）
**适用场景**：大多数实际应用
**策略**：定时任务为主 + 关键任务守护进程

## 📋 详细部署指南

### 1. 定时任务部署

#### Linux/Unix 系统 (crontab)

```bash
# 编辑crontab
crontab -e

# 每5分钟执行一次批量处理
*/5 * * * * /usr/bin/php /path/to/pinyin/bin/task_runner.php --mode batch --batch-size 50 >> /var/log/pinyin_tasks.log 2>&1

# 每天凌晨2点执行完整处理
0 2 * * * /usr/bin/php /path/to/pinyin/bin/task_runner.php --mode batch --batch-size 200 >> /var/log/pinyin_tasks_daily.log 2>&1

# 每小时检查一次守护进程状态
0 * * * * /usr/bin/php /path/to/pinyin/bin/task_runner.php --mode once >> /var/log/pinyin_check.log 2>&1
```

#### Windows 系统 (任务计划程序)

```batch
@echo off
REM pinyin_task.bat
cd /d C:\path\to\pinyin
php bin\task_runner.php --mode batch --batch-size 50 >> C:\logs\pinyin_tasks.log 2>&1

REM 在任务计划程序中配置：
REM - 触发器：每5分钟
REM - 操作：启动程序 pinyin_task.bat
```

### 2. 守护进程部署

#### 启动守护进程
```bash
# 启动守护进程（检查间隔30秒）
php bin/task_runner.php --mode daemon --interval 30

# 后台运行
nohup php bin/task_runner.php --mode daemon --interval 30 > /dev/null 2>&1 &
```

#### 使用 systemd 管理（推荐用于生产环境）

创建服务文件 `/etc/systemd/system/pinyin-tasks.service`：

```ini
[Unit]
Description=Pinyin Background Task Manager
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/path/to/pinyin
ExecStart=/usr/bin/php bin/task_runner.php --mode daemon --interval 30
ExecStop=/bin/kill -TERM $MAINPID
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

启用并启动服务：
```bash
sudo systemctl daemon-reload
sudo systemctl enable pinyin-tasks
sudo systemctl start pinyin-tasks
sudo systemctl status pinyin-tasks
```

### 3. 混合模式部署

#### 配置示例
```bash
# crontab 配置
# 每5分钟处理普通任务
*/5 * * * * /usr/bin/php /path/to/pinyin/bin/task_runner.php --mode batch --batch-size 50 >> /var/log/pinyin_tasks.log 2>&1

# 守护进程处理高优先级任务
sudo systemctl start pinyin-high-priority
```

## 🔧 配置优化

### 性能调优参数

```php
// 在 PinyinConverter 配置中优化
'background_tasks' => [
    'enable' => true,
    'task_dir' => __DIR__.'/../data/backup/tasks/',
    'max_concurrent' => 5, // 根据服务器配置调整
    'task_types' => [
        'not_found_resolve' => [
            'priority' => 1,
            'batch_size' => 100, // 增大批量大小
            'auto_execute' => true
        ],
        'self_learn_merge' => [
            'priority' => 2,
            'batch_size' => 200,
            'auto_execute' => true
        ]
    ]
]
```



推荐的生产环境配置
~~~php

'background_tasks' => [
    'enable' => true,
    'task_dir' => __DIR__.'/../data/backup/tasks/',
    'max_concurrent' => 3,
    'retry_delay' => 60, // 失败重试延迟（秒）
    'max_retries' => 3   // 最大重试次数
]

~~~



### 内存和性能优化

```bash
# PHP 内存限制调整
php -d memory_limit=256M bin/task_runner.php --mode batch

# 使用 OPcache 优化
php -d opcache.enable=1 -d opcache.memory_consumption=256 bin/task_runner.php
```

## 📊 监控和日志

### 日志配置

```php
// 在任务运行器中添加详细日志
error_log("[PinyinTasks] " . date('Y-m-d H:i:s') . " 开始处理任务");

// 监控关键指标
$stats = $taskManager->getStats();
if ($stats['failed'] > 10) {
    error_log("[PinyinTasks] 警告：失败任务过多: " . $stats['failed']);
}
```

### 健康检查脚本

```php
<?php
// health_check.php
require_once __DIR__ . '/vendor/autoload.php';

use tekintian\pinyin\BackgroundTaskManager;

$taskManager = new BackgroundTaskManager();
$stats = $taskManager->getStats();

// 检查任务积压
if ($stats['pending'] > 100) {
    echo "CRITICAL: 任务积压严重: " . $stats['pending'] . " 个待处理任务\n";
    exit(2);
}

// 检查失败任务
if ($stats['failed'] > 20) {
    echo "WARNING: 失败任务较多: " . $stats['failed'] . " 个失败任务\n";
    exit(1);
}

echo "OK: 系统运行正常\n";
exit(0);
?>
```

## 🛠️ 故障排除

### 常见问题

#### 问题1：任务创建失败
**症状**：`createBackgroundTask` 返回 false
**解决方案**：
```bash
# 检查任务目录权限
chmod 755 /path/to/pinyin/data/backup/tasks/
chown www-data:www-data /path/to/pinyin/data/backup/tasks/

# 检查磁盘空间
df -h /path/to/pinyin/
```

#### 问题2：外部API调用失败
**症状**：任务状态为 failed，错误信息包含网络错误
**解决方案**：
```bash
# 检查网络连接
ping www.zdic.net

# 调整超时时间
php -d default_socket_timeout=30 bin/task_runner.php
```

#### 问题3：内存不足
**症状**：PHP 内存耗尽错误
**解决方案**：
```bash
# 增加内存限制
php -d memory_limit=512M bin/task_runner.php

# 减少批量大小
php bin/task_runner.php --batch-size 20
```

### 调试模式

```bash
# 启用详细调试
php bin/task_runner.php --mode batch --batch-size 10 -v

# 查看详细日志
tail -f /var/log/pinyin_tasks.log
```

## 🔄 自动化部署脚本

### Docker 部署

```dockerfile
FROM php:8.1-cli

WORKDIR /app
COPY . .

# 安装依赖
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# 安装 Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader

# 创建启动脚本
RUN echo '#!/bin/bash\nphp bin/task_runner.php --mode daemon --interval 30' > /app/start.sh
RUN chmod +x /app/start.sh

CMD ["/app/start.sh"]
```

### Ansible 部署脚本

```yaml
# deploy_pinyin_tasks.yml
- name: Deploy Pinyin Background Tasks
  hosts: pinyin_servers
  vars:
    pinyin_path: /opt/pinyin
    
  tasks:
    - name: Create directory
      file:
        path: "{{ pinyin_path }}"
        state: directory
        owner: www-data
        group: www-data
        
    - name: Deploy application
      copy:
        src: ../pinyin/
        dest: "{{ pinyin_path }}"
        
    - name: Install dependencies
      command: composer install --no-dev
      args:
        chdir: "{{ pinyin_path }}"
        
    - name: Configure systemd service
      template:
        src: pinyin-tasks.service.j2
        dest: /etc/systemd/system/pinyin-tasks.service
      notify: reload systemd
      
    - name: Start service
      systemd:
        name: pinyin-tasks
        state: started
        enabled: yes
        
  handlers:
    - name: reload systemd
      systemd:
        daemon_reload: yes
```

## 📈 性能监控

### Prometheus 监控指标

```php
<?php
// metrics.php - Prometheus 指标导出
$taskManager = new BackgroundTaskManager();
$stats = $taskManager->getStats();

header('Content-Type: text/plain');
echo "# HELP pinyin_tasks_total Total number of tasks\n";
echo "# TYPE pinyin_tasks_total gauge\n";
echo 'pinyin_tasks_total{status="total"} ' . $stats['total'] . "\n";
echo 'pinyin_tasks_total{status="pending"} ' . $stats['pending'] . "\n";
echo 'pinyin_tasks_total{status="completed"} ' . $stats['completed'] . "\n";
echo 'pinyin_tasks_total{status="failed"} ' . $stats['failed'] . "\n";
?>
```

## 🎯 最佳实践

1. **生产环境推荐使用定时任务**，资源消耗可控
2. **开发环境可以使用守护进程**，便于调试
3. **定期监控任务积压情况**，及时调整处理策略
4. **设置合理的批量大小**，平衡性能和内存使用
5. **配置日志轮转**，避免日志文件过大
6. **定期备份任务数据**，防止数据丢失

---

**最后更新**：2025年11月12日17:15:10

**维护者**：tekintian