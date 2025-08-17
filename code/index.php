<?php
declare(strict_types=1);

// 加载Composer自动加载文件，确保所有依赖类都能被正确加载
require __DIR__ . '/vendor/autoload.php';

// 引入框架核心类和审计命令类
use App\Application;
use App\Command\DemoCommand;

/**
 * 应用程序入口文件
 *
 * 主要功能：
 * 1. 获取框架单例实例
 * 2. 添加审计命令到命令行应用
 * 3. 启动命令行应用
 */
// 获取框架单例实例
$app = Application::getInstance();

// 添加审计命令到命令行应用
// 此处添加了一个命令，可根据实际需求扩展
$app->addCommand(new DemoCommand());

// 启动命令行应用，执行已添加的命令
$app->run();
