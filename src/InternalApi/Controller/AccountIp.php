<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver\InternalApi\Controller;

use Medusa\App\ApiResolver\Container;
use Medusa\App\ApiResolver\InternalApi\ClientException;
use Medusa\App\ApiResolver\InternalApi\DAO\AccountIpDAO;
use Medusa\App\ApiResolver\InternalApi\Utils;
use Medusa\Http\Simple\MessageInterface;
use Medusa\Http\Simple\Request;
use function is_array;
use function preg_match;

/**
 * Class AccountIp
 * @package medusa/api-resolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class AccountIp {

    /**
     * @param Request   $message
     * @param Container $container
     * @param int       $userId
     * @param int|null  $ipId
     * @return array
     */
    public static function delete(Request $message, Container $container, int $userId, ?int $ipId = null): array {
        return (new AccountIpDAO($container))->delete($userId, $ipId);
    }

    /**
     * @param Request   $message
     * @param Container $container
     * @param int       $userId
     * @return array
     * @throws ClientException
     * @throws \JsonException
     */
    public static function create(Request $message, Container $container, int $userId): array {
        $requestedParams = $message->getParsedBody();
        if (!is_array($requestedParams)) {
            throw new ClientException('Invalid parameter body');
        }
        (function(string $ip, bool $enabled = false) {
            if (!preg_match('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/', $ip)) {
                throw new ClientException('Invalid IP');
            }
        })(...$requestedParams);
        $insert = Utils::arrayPrefix($requestedParams, 'accountip_');
        return (new AccountIpDAO($container))->insert($insert, $userId);
    }

    /**
     * @param MessageInterface $message
     * @param Container        $container
     * @param int              $userId
     * @param int              $ipId
     * @return array
     */
    public static function get(MessageInterface $message, Container $container, int $userId, int $ipId): array {
        return (new AccountIpDAO($container))->get($userId, $ipId);
    }

    /**
     * @param MessageInterface $message
     * @param Container        $container
     * @param int              $userId
     * @param int              $ipId
     * @return array
     * @throws ClientException
     */
    public static function edit(MessageInterface $message, Container $container, int $userId, int $ipId): array {
        $requestedParams = $message->getParsedBody();
        if (!is_array($requestedParams)) {
            throw new ClientException('Invalid parameter body');
        }
        $params = [];
        (function(?string $ip = null, bool $enabled = false) {
            if ($ip !== null && !preg_match('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/', $ip)) {
                throw new ClientException('Invalid IP');
            }
        })(...$requestedParams);

        $update = Utils::arrayPrefix($requestedParams, 'accountip_');
        return (new AccountIpDAO($container))->update($update, $userId, $ipId);
    }

    /**
     * @param MessageInterface $message
     * @param Container        $container
     * @param int              $userId
     * @return array
     */
    public static function getAll(MessageInterface $message, Container $container, int $userId): array {
        return (new AccountIpDAO($container))->getAll($userId);
    }
}
