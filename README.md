# phpcli-micro Doc

## 一、框架概述与功能特点
PHP CLI 命令行应用开发微型框架，支持数据库操作、缓存计数和任务调度
- 支持composer管理包
- 基于Symfony Console组件构建
- 支持MySQL和Redis数据存储
- 使用.env文件管理环境变量
- 支持crontab定时调度


## 二、快速入门指南
### 1. 环境要求
- PHP 8.0+
- Redis 6.0+
- MySQL 5.7+

### 2. 项目初始化
```bash
git clone https://github.com/trangfoo/phpcli-micro.git
composer install
cp .env.example .env
```

### 3. env配置
```bash
APP_ENV=production
TIMEZONE=Asia/Shanghai

# Mysql
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=root
DB_PASSWORD=
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

# Redis
REDIS_HOST=127.0.0.1
REDIS_AUTH=
REDIS_PORT=6379
REDIS_DB=1
```

### 4. DemoCommand 里用到的表结构
```bash
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5. crontab调用示例
```bash
# 每5分钟执行一次
*/5 * * * * /usr/local/bin/php /var/www/html/project/code/index.php DemoCommand
```

## 三、功能模块详解
### 1. Mysql调用
```php
// 获取应用实例
$app = Application::getInstance();
     
// 获取DB实例
$db = $app->getDB();

// 1. 插入单条用户记录
$userId = $db->insert('users', [
   'username' => 'john_doe',
   'email' => 'john@example.com',
   'created_at' => time()
]);
echo "插入的用户ID: $userId\n";

// 2. 批量插入用户
$users = [
   ['username' => 'alice', 'email' => 'alice@example.com', 'created_at' => time()],
   ['username' => 'bob', 'email' => 'bob@example.com', 'created_at' => time()],
   ['username' => 'charlie', 'email' => 'charlie@example.com', 'created_at' => time()]
];
$insertedRows = $db->batchInsert('users', $users);
echo "批量插入了 $insertedRows 条记录\n";

// 3. 查询用户(条件数组形式)
$user = $db->find('users', ['username' => 'john_doe']);
print_r($user);

// 4. 查询多个用户(条件字符串形式)
$users = $db->select('users', 'created_at > "2023-01-01"', '*', 'username ASC', 10);
print_r($users);

// 5. 更新用户
$affected = $db->update('users', ['id' => $userId], ['email' => 'new_email@example.com']);
echo "更新了 $affected 条记录\n";

// 6. 删除用户
$deleted = $db->delete('users', ['username' => 'test_user']);
echo "删除了 $deleted 条记录\n";

// 7. 事务处理示例
try {
   $db->beginTransaction();

   $db->insert('users', [
       'username' => 'transaction_user',
       'email' => 'tx@example.com',
       'created_at' => time()
   ]);

   $db->update('users', ['username' => 'john_doe'], ['email' => 'updated_in_tx@example.com']);

   $db->commit();
   echo "事务执行成功\n";
} catch (Exception $e) {
   $db->rollback();
   echo "事务回滚: " . $e->getMessage() . "\n";
}

```

### 2. Redis集成
```php
// 获取应用实例
$app = Application::getInstance();
$redis = $app->getRedis();
$redis->incr('daily_visits');
$redis->expire('daily_visits', 86400);
```

### 3. 命令任务
后期的开发主要是围绕在 code/src/Command 目录下
```php
class DemoCommand extends Command
{
    /**
     * 配置命令
     *
     * 通过反射自动获取类名作为命令名称
     * 例如：执行php bin/console时会显示为"DemoCommand"
     */
    protected function configure()
    {
        $this->setName((new \ReflectionClass(__CLASS__))->getShortName())->setDescription('执行数据库CRUD操作示例')
            ->setHelp('演示数据库基本操作命令');
    }

    /**
     * 执行操作
     *
     * @param InputInterface $input 输入参数
     * @param OutputInterface $output 输出流
     * @return int 执行状态码
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // 获取应用实例
        $app = Application::getInstance();

        // 记录执行日志
        $output->writeln('['.date('Y-m-d H:i:s').'] '.self::getName());


        //调用Redis实例
        $redis = $app->getRedis();
        // 更新Redis计数器，记录执行次数
        $redis->incr('step1_counter');



        // 获取DB实例
        $db = $app->getDB();

        // 1. 插入单条用户记录
        $userId = $db->insert('users', [
            'username' => 'john_doe',
            'email' => 'john@example.com',
            'created_at' => time()
        ]);
        echo "插入的用户ID: $userId\n";



        // 返回成功状态码
        return Command::SUCCESS;
    }
}
```

## 四、性能优化策略
### 1. 数据库层面
- 使用batchInsert替换循环插入
- 建立合适索引
- 启用查询缓存

### 2. Redis层面
- Pipeline批量操作
- 连接池复用

### 3. Cli层面
- 使用Symfony ProgressBar显示进度
- 实现分段任务处理
- 异常重试机制
