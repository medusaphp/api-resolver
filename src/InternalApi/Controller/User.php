<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver\InternalApi\Controller;

use Medusa\App\ApiResolver\Container;
use Medusa\App\ApiResolver\InternalApi\ClientException;
use Medusa\App\ApiResolver\InternalApi\DAO\UserDAO;
use Medusa\App\ApiResolver\InternalApi\Utils;
use Medusa\Http\Simple\MessageInterface;
use Medusa\Http\Simple\Request;
use function is_array;
use function password_hash;
use const PASSWORD_BCRYPT;

/**
 * Class User
 * @package medusa/api-resolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class User {

    /**
     * @param Request   $message
     * @param Container $container
     * @param int       $userId
     * @return array
     */
    public static function delete(Request $message, Container $container, int $userId): array {
        return (new UserDAO($container))->delete($userId, $ipId);
    }

    /**
     * @param Request   $message
     * @param Container $container
     * @return array
     * @throws ClientException
     * @throws \JsonException
     */
    public static function create(Request $message, Container $container): array {
        $requestedParams = $message->getParsedBody();
        if (!is_array($requestedParams)) {
            throw new ClientException('Invalid parameter body');
        }
        (function(string $username, string &$password, bool $enabled = false) {
            $password = password_hash($password, PASSWORD_BCRYPT);
        })(...$requestedParams);

        $insert = Utils::arrayPrefix($requestedParams, 'account_');
        return (new UserDAO($container))->insert($insert);
    }

    /**
     * @param MessageInterface $message
     * @param Container        $container
     * @param int              $userId
     * @return array
     */
    public static function get(MessageInterface $message, Container $container, int $userId): array {
        return (new UserDAO($container))->get($userId);
    }

    /**
     * @param Request   $message
     * @param Container $container
     * @param int       $userId
     * @return array
     * @throws ClientException
     * @throws \JsonException
     */
    public static function edit(Request $message, Container $container, int $userId): array {
        $requestedParams = $message->getParsedBody();
        if (!is_array($requestedParams)) {
            throw new ClientException('Invalid parameter body');
        }
        (function(?string $username = null, ?string &$password = null, ?bool $enabled = null) {

            if ($password !== null) {
                $password = password_hash($password, PASSWORD_BCRYPT);
            }
        })(...$requestedParams);

        $update = Utils::arrayPrefix($requestedParams, 'account_');
        return (new UserDAO($container))->update($update, $userId);
    }

    /**
     * @param MessageInterface $message
     * @param Container        $container
     * @return array
     */
    public static function getAll(MessageInterface $message, Container $container): array {
        return (new UserDAO($container))->getAll();
    }
}
