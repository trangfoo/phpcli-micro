<?php
declare(strict_types=1);

namespace App;

use PDO;
use Redis;
use Dotenv\Dotenv;
use Symfony\Component\Console\Application as ConsoleApplication;

class Application
{
    private static $instance;
    private $pdo;
    private $redis;
    private $console;
    private $db;

    private function __construct()
    {
        $this->loadEnv();
        $this->loadUtils();
        $this->initPDO();
        $this->initRedis();
        $this->console = new ConsoleApplication('PhpCLI Micro Framework', '1.0.0');
    }

    private function loadEnv(): void
    {
        Dotenv::createImmutable(__DIR__.'/..')->load();
    }

    private function loadUtils(): void
    {
        require_once __DIR__.'/Utils/functions.php';
    }

    private function initPDO(): void
    {
        $this->pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s',
                env('DB_HOST'),
                env('DB_PORT'),
                env('DB_DATABASE')),
            env('DB_USERNAME'),
            env('DB_PASSWORD'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    }

    private function initRedis(): void
    {
        $this->redis = new Redis();
        $this->redis->connect(
            env('REDIS_HOST'),
            (int)env('REDIS_PORT'),
            2.5
        );

        if (!empty(env('REDIS_AUTH'))) {
            $this->redis->auth(env('REDIS_AUTH'));
        }

        $this->redis->select((int)(env('REDIS_DB') ?? 0));
    }

    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPDO(): PDO
    {
        return $this->pdo;
    }

    public function getRedis(): Redis
    {
        return $this->redis;
    }

    public function getDB(): DB
    {
        if (!$this->db) {
            if (!class_exists('App\DB')) {
                throw new \RuntimeException('DB class not found');
            }
            $this->db = new DB($this->getPDO());
        }
        return $this->db;
    }

    public function run(): void
    {
        $this->console->run();
    }

    public function addCommand($command): void
    {
        $this->console->add($command);
    }
}
