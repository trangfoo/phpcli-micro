<?php
declare(strict_types=1);

namespace App;

use PDO;
use PDOStatement;
use PDOException;

/**
 * 数据库操作类
 * 提供完整的CRUD操作、事务处理和批量插入功能
 * 支持参数绑定防止SQL注入，严格类型检查保证代码健壮性
 */
class DB
{
    /**
     * @var PDO PDO数据库连接实例
     */
    private PDO $pdo;

    /**
     * @var int 事务嵌套层级计数器
     */
    private int $transactionLevel = 0;

    /**
     * 构造函数
     * @param PDO $pdo 已初始化的PDO实例
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /**
     * 执行SQL语句(带参数绑定)
     * @param string $sql 要执行的SQL语句
     * @param array $params 绑定参数数组
     * @return PDOStatement 返回执行后的statement对象
     * @throws PDOException SQL执行失败时抛出
     */
    public function execute(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(
                is_int($key) ? $key + 1 : $key,
                $value,
                is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }
        $stmt->execute();
        return $stmt;
    }

    /**
     * 查询SQL语句(execute方法的别名)
     * @param string $sql 查询SQL语句
     * @param array $params 绑定参数
     * @return PDOStatement 返回statement对象
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        return $this->execute($sql, $params);
    }

    /**
     * 查询多条记录
     * @param string $table 表名
     * @param string|array $conditions 查询条件(字符串或数组)
     * @param string $columns 查询字段
     * @param string $orderBy 排序条件
     * @param int|null $limit 限制条数
     * @return array 查询结果数组
     */
    public function select(
        string $table,
        $conditions = [],
        string $columns = '*',
        string $orderBy = '',
        ?int $limit = null
    ): array {
        $where = is_string($conditions) ? $conditions : $this->buildWhere($conditions);
        $sql = "SELECT $columns FROM $table";

        if ($where) {
            $sql .= " WHERE $where";
        }

        if ($orderBy) {
            $sql .= " ORDER BY $orderBy";
        }

        if ($limit !== null) {
            $sql .= " LIMIT $limit";
        }

        $params = is_array($conditions) ? $conditions : [];
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * 查询单条记录
     * @param string $table 表名
     * @param string|array $conditions 查询条件
     * @param string $columns 查询字段
     * @return array|null 单条记录或null
     */
    public function find(string $table, $conditions = [], string $columns = '*'): ?array
    {
        $result = $this->select($table, $conditions, $columns, '', 1);
        return $result[0] ?? null;
    }

    /**
     * 插入单条记录
     * @param string $table 表名
     * @param array $data 插入数据
     * @return int 最后插入ID
     */
    public function insert(string $table, array $data): int
    {
        $columns = implode(',', array_keys($data));
        $placeholders = implode(',', array_map(fn($k) => ":$k", array_keys($data)));

        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        $this->execute($sql, $data);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * 批量插入记录
     * @param string $table 表名
     * @param array $dataList 要插入的数据列表(二维数组)
     * @param int $batchSize 每批插入数量(默认100)
     * @return int 总共插入的行数
     * @throws PDOException 插入失败时抛出异常
     */
    public function batchInsert(string $table, array $dataList, int $batchSize = 100): int
    {
        if (empty($dataList)) {
            return 0;
        }

        $totalRows = 0;
        $columns = array_keys($dataList[0]);
        $columnStr = implode(',', $columns);
        $placeholders = implode(',', array_map(fn($col) => ":$col", $columns));

        $sql = "INSERT INTO $table ($columnStr) VALUES ($placeholders)";
        $stmt = $this->pdo->prepare($sql);

        try {
            $this->beginTransaction();

            foreach (array_chunk($dataList, $batchSize) as $batch) {
                foreach ($batch as $data) {
                    foreach ($columns as $col) {
                        $stmt->bindValue(":$col", $data[$col] ?? null);
                    }
                    $stmt->execute();
                    $totalRows += $stmt->rowCount();
                }
            }

            $this->commit();
            return $totalRows;
        } catch (PDOException $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * 更新记录
     * @param string $table 表名
     * @param string|array $conditions 更新条件
     * @param array $data 更新数据
     * @return int 受影响行数
     */
    public function update(string $table, $conditions, array $data): int
    {
        $set = implode(',', array_map(fn($k) => "$k=:$k", array_keys($data)));
        $where = is_string($conditions) ? $conditions : $this->buildWhere($conditions);

        $sql = "UPDATE $table SET $set WHERE $where";
        $params = array_merge($data, is_array($conditions) ? $conditions : []);

        return $this->execute($sql, $params)->rowCount();
    }

    /**
     * 删除记录
     * @param string $table 表名
     * @param string|array $conditions 删除条件
     * @return int 受影响行数
     */
    public function delete(string $table, $conditions): int
    {
        $where = is_string($conditions) ? $conditions : $this->buildWhere($conditions);
        $sql = "DELETE FROM $table WHERE $where";

        $params = is_array($conditions) ? $conditions : [];
        return $this->execute($sql, $params)->rowCount();
    }

    /**
     * 执行SQL并返回受影响行数
     * @param string $sql SQL语句
     * @param array $params 绑定参数
     * @return int 受影响行数
     */
    public function exec(string $sql, array $params = []): int
    {
        return $this->execute($sql, $params)->rowCount();
    }

    /**
     * 开始事务
     */
    public function beginTransaction(): void
    {
        if ($this->transactionLevel === 0) {
            $this->pdo->beginTransaction();
        }
        $this->transactionLevel++;
    }

    /**
     * 提交事务
     */
    public function commit(): void
    {
        if ($this->transactionLevel === 1) {
            $this->pdo->commit();
        }
        $this->transactionLevel = max(0, $this->transactionLevel - 1);
    }

    /**
     * 回滚事务
     */
    public function rollback(): void
    {
        if ($this->transactionLevel === 1) {
            $this->pdo->rollBack();
        }
        $this->transactionLevel = max(0, $this->transactionLevel - 1);
    }

    /**
     * 构建WHERE条件(数组形式)
     * @param array $conditions 条件数组
     * @return string WHERE条件字符串
     */
    private function buildWhere(array $conditions): string
    {
        if (empty($conditions)) {
            return '';
        }

        $parts = [];
        foreach ($conditions as $key => $value) {
            if (is_int($key)) {
                // 直接使用条件片段
                $parts[] = $value;
            } else {
                // 键值对条件
                $parts[] = "$key = :$key";
            }
        }

        return implode(' AND ', $parts);
    }
}
