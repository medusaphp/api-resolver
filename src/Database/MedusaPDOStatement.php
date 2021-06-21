<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver\Database;

use PDOStatement;
use function is_bool;
use function is_int;

/**
 * Class MedusaPDOStatement
 * @package medusa/api-resolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class MedusaPDOStatement {

    private PDOStatement $statement;

    public function __construct(PDOStatement $statement) {
        $this->statement = $statement;
    }

    public function bindParams(array $parameters): MedusaPDOStatement {
        foreach ($parameters as $key => $value) {
            if ($value === null) {
                $type = \PDO::PARAM_NULL;
            } elseif (is_int($value)) {
                $type = \PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $type = \PDO::PARAM_BOOL;
            } else {
                $type = \PDO::PARAM_STR;
            }

            $this->statement->bindValue(':' . $key, $value, $type);
        }

        return $this;
    }

    public function fetchAllAssoc(): array {
        return $this->statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function execute(): MedusaPDOStatement {
        $this->statement->execute();
        return $this;
    }
}
