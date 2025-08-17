<?php
declare(strict_types=1);

namespace App\Command;

use App\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * 命令类范例
 *
 * 功能说明：
 *      自动获取类名作为命令名称（DemoCommand）
 */
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
        $this->setName((new \ReflectionClass(__CLASS__))->getShortName())
            ->setDescription('执行数据库CRUD操作示例')
            ->setHelp('演示数据库基本操作命令');
    }

    /**
     * 执行审计第一步操作
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



        // 返回成功状态码
        return Command::SUCCESS;
    }
}
