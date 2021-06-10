<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver\Exception;

use mysqli;

/**
 * Class DatabaseHandlerException
 * @package medusa/api-resolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class DatabaseHandlerException extends DatabaseException {

    public function __construct(mysqli $mysqli) {
        parent::__construct('(' . $mysqli->errno . ') ' . $mysqli->error, $mysqli->errno);
    }
}
