<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver\Exception;

use mysqli_stmt;

/**
 * Class StatementException
 * @package medusa/api-resolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class StatementException extends DatabaseException {

    public function __construct(mysqli_stmt $stmt) {
        parent::__construct('(' . $stmt->errno . ') ' . $stmt->error, $stmt->errno);
    }
}
