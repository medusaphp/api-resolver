<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver\Database;

use PDO;
use function array_keys;
use function array_map;
use function implode;
use function sprintf;

/**
 * Class MedusaPDO
 * @package medusa/api-resolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class MedusaPDO {

    private ?PDO $dbHandle = null;

    public function __construct(private string $dsn, private string $user, private string $pass) {
    }

    public function getRowAsAssocArray(string $sql, array $params = []): array {
        $result = $this->getAllAsAssocArray($sql, $params);
        if ($result[1] ?? false) {
            throw new \LogicException('Logic error, query yields more than one row');
        }
        return $result[0] ?? [];
    }

    public function getAllAsAssocArray(string $sql, array $params = []): array {
        $result = $this->prepare($sql)->bindParams($params)->execute()->fetchAllAssoc();
        return $result;
    }

    /**
     * @param string $query
     * @param array  $options
     * @return MedusaPDOStatement
     */
    public function prepare(string $query, array $options = []): MedusaPDOStatement {
        $this->checkConnection();
        return new MedusaPDOStatement($this->dbHandle->prepare($query, $options));
    }

    private function checkConnection(): void {

        if (empty($this->dbHandle)) {
            $this->connect();
        }
    }

    private function connect(): void {
        $this->dbHandle = new \PDO($this->dsn, $this->user, $this->pass, [
            \PDO::ATTR_PERSISTENT         => true,
            \PDO::ATTR_EMULATE_PREPARES   => false,
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_general_ci',
        ]);
    }

    public function query(string $sql, array $params = []): MedusaPDOStatement {
        return $this->prepare($sql)->bindParams($params)->execute();
    }

    public function update(string $tablename, array $paramsRaw, array $where): MedusaPDOStatement {

        $whereSql = array_map(fn($name) => $name . ' = :' . $name, array_keys($where));
        $updates = array_map(fn($name) => $name . ' = :' . $name, array_keys($paramsRaw));
        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $tablename,
            implode(', ', $updates),
            implode(' AND ', $whereSql),
        );

        $paramsRaw += $where;
        return $this->prepare($sql)->bindParams($paramsRaw)->execute();
    }

    public function insert(string $tablename, array $paramsRaw): int {

        $inserts = array_map(fn(string $name) => ':' . $name, array_keys($paramsRaw));
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $tablename,
            implode(', ', array_keys($paramsRaw)),
            implode(', ', $inserts),
        );

        $this->prepare($sql)->bindParams($paramsRaw)->execute();
        return $this->getInsertId();
    }

    public function getInsertId(): int {
        return (int)$this->dbHandle->lastInsertId();
    }

    public function getHandle(): PDO {
        return $this->dbHandle;
    }

    public function __destruct() {
        $this->dbHandle = null;
    }

    private function disconnect(): void {
        $this->dbHandle = null;
    }
}
