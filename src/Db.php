<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver;

use Medusa\App\ApiResolver\Exception\DatabaseConnectionException;
use Medusa\App\ApiResolver\Exception\DatabaseException;
use Medusa\App\ApiResolver\Exception\DatabaseHandlerException;
use Medusa\App\ApiResolver\Exception\StatementException;
use mysqli;
use function array_reduce;
use function implode;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;
use const MYSQLI_ASSOC;
use const MYSQLI_BINARY_FLAG;
use const MYSQLI_CLIENT_COMPRESS;

/**
 * Class Db
 * @package medusa/app-apiresolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class Db {

    private mysqli $handle;

    public function __construct(private array $config) {
        $this->handle = new mysqli();
    }

    public function insert(string $tablename, array $paramsRaw) {

        $fields = [];
        $paramTypesAsString = '';
        $params = [];
        foreach ($paramsRaw as $field => $param) {
            $fields[] = $this->escape($field);
            $params[] = $param;

            if (is_string($param)) {
                $paramTypesAsString .= 's';
            } elseif (is_int($param)) {
                $paramTypesAsString .= 'i';
            } elseif (is_float($param)) {
                $paramTypesAsString .= 'd';
            } else {
                throw new DatabaseException('Unsupported type for parameter');
            }
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES(?, ?)',
            $this->escape($tablename),
            implode(',', $fields)
        );

        $stmt = $this->handle->prepare($sql);

        if (!$stmt) {
            throw new DatabaseHandlerException($this->handle);
        }

        if (!$stmt->bind_param($paramTypesAsString, ...$params)) {
            throw new StatementException($stmt);
        }

        if (!$stmt->execute()) {
            throw new StatementException($stmt);
        }
    }

    private function escape(string $val): string {
        return $this->handle->real_escape_string($val);
    }

    public function getAssoc(string $sql, array $params = []): array {

        $stmt = $this->handle->prepare($sql);

        if (!$stmt) {
            throw new DatabaseHandlerException($this->handle);
        }

        $paramTypesAsString = array_reduce($params, function(string $current, $param) {

            if (is_string($param)) {
                return $current . 's';
            } elseif (is_int($param)) {
                return $current . 'i';
            } elseif (is_float($param)) {
                return $current . 'd';
            } else {
                throw new DatabaseException('Unsupported type for parameter');
            }
        }, '');

        if (!$stmt->bind_param($paramTypesAsString, ...$params)) {
            throw new StatementException($stmt);
        }

        if (!$stmt->execute()) {
            throw new StatementException($stmt);
        }

        $result = $stmt->get_result();

        if (!$result) {
            throw new StatementException($stmt);
        }

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function connect() {
        $connectionEstablished = $this->handle->real_connect(
            $this->config['host'],
            $this->config['user'],
            $this->config['pass'],
            $this->config['db'],
            $this->config['port'],
            null,
            MYSQLI_CLIENT_COMPRESS | MYSQLI_BINARY_FLAG
        );

        if (!$connectionEstablished) {
            throw new DatabaseConnectionException($this->handle);
        }

        if (!$this->handle->set_charset($this->config['charset'])) {
            throw new DatabaseConnectionException($this->handle);
        }
    }
}
