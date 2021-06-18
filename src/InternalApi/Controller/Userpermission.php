<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver\InternalApi\Controller;

use Medusa\App\ApiResolver\Container;
use Medusa\App\ApiResolver\InternalApi\ClientException;
use Medusa\App\ApiResolver\InternalApi\DAO\PermissionDAO;
use Medusa\App\ApiResolver\InternalApi\Utils;
use Medusa\Http\Simple\MessageInterface;
use Medusa\Http\Simple\Request;
use function is_array;

/**
 * Class Userpermission
 * @package medusa/api-resolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class Userpermission {

    /**
     * @param Request   $message
     * @param Container $container
     * @param int       $userId
     * @param int|null  $permissionId
     * @return array
     */
    public static function delete(Request $message, Container $container, int $userId, ?int $permissionId = null): array {
        return (new PermissionDAO($container))->delete($userId, $permissionId);
    }

    /**
     * @param MessageInterface $message
     * @param Container        $container
     * @param int              $userId
     * @return array
     * @throws ClientException
     */
    public static function create(MessageInterface $message, Container $container, int $userId): array {
        $requestedParams = $message->getParsedBody();
        if (!is_array($requestedParams)) {
            throw new ClientException('Invalid parameter body');
        }
        (function(string $service, bool $enabled = false) {
        })(...$requestedParams);

        $insert = Utils::arrayPrefix($requestedParams, 'userpermission_');
        return (new PermissionDAO($container))->insert($insert, $userId);
    }

    /**
     * @param MessageInterface $message
     * @param Container        $container
     * @param int              $userId
     * @param int              $permissionId
     * @return array
     * @throws ClientException
     */
    public static function edit(MessageInterface $message, Container $container, int $userId, int $permissionId): array {
        $requestedParams = $message->getParsedBody();
        if (!is_array($requestedParams)) {
            throw new ClientException('Invalid parameter body');
        }
        (function(?string $service = null, ?bool $enabled = null) {
        })(...$requestedParams);

        $update = Utils::arrayPrefix($requestedParams, 'userpermission_');
        return (new PermissionDAO($container))->update($update, $userId, $permissionId);
    }

    /**
     * @param MessageInterface $message
     * @param Container        $container
     * @param int              $userId
     * @param int              $permissionId
     * @return array
     */
    public static function get(MessageInterface $message, Container $container, int $userId, int $permissionId): array {
        return (new PermissionDAO($container))->get($userId, $permissionId);
    }

    /**
     * @param MessageInterface $message
     * @param Container        $container
     * @param int              $userId
     * @return array
     */
    public static function getAll(MessageInterface $message, Container $container, int $userId): array {
        return (new PermissionDAO($container))->getAll($userId);
    }
}
