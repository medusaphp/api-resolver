<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver\InternalAdminInterface\Controller;

use Medusa\App\ApiResolver\Container;
use Medusa\App\ApiResolver\InternalAdminInterface\DAO\AdminUserDAO;
use Medusa\App\ApiResolver\InternalAdminInterface\View;
use Medusa\App\ApiResolver\InternalApi\ClientException;
use Medusa\Http\Simple\Request;
use Medusa\Http\Simple\Response;
use Throwable;
use function array_filter;
use function is_array;
use function Medusa\arrayPrefix;
use function password_hash;
use const PASSWORD_BCRYPT;

class AdminUser {

    public static function delete(Request $message, Container $container, int $userId) {

        if ($message->getMethod() === 'GET') {
            $result = self::show($message, $container);
            $user = (new AdminUserDAO($container))->get($userId);
            if (!$user) {
                return new Response([
                                        'Location' => '/__admin__/',
                                    ], '', protocolVersion: $message->getProtocolVersion());
            }
            return $result . (new View('Confirm'))->assign(
                    'hint', 'Wollen Sie den Benutzer "' . $user['username'] . '" wirklich löschen?'
                )->render();
        }

        (new AdminUserDAO($container))->delete($userId);

        return new Response(
            [
                'Location' => '/__admin__/AdminUser/Show',
            ], '', protocolVersion: $message->getProtocolVersion());
    }

    public static function show(Request $message, Container $container) {

        $view = new View('AdminUserShow');

        $adminUsers = (new AdminUserDAO($container))->getAll();

        View::navEntries([
                             [
                                 'url'   => '/__admin__/AdminUser/Add',
                                 'label' => 'Admin hinzufügen',
                             ],
                         ], 1);
        $view->assign('adminUsers', $adminUsers);
        $view->assign('headline', 'Administratoren Übersicht');
        return $view->render();
    }

    public static function add(Request $message, Container $container) {
        $view = new View('AdminUserAdd');

        if ($message->getMethod() === 'POST') {
            try {
                $requestedParams = $message->getParsedBody();

                if (!is_array($requestedParams)) {
                    throw new ClientException('Invalid parameter body');
                }

                $requestedParams = array_filter($requestedParams);
                $requestedParams['enabled'] = $requestedParams['enabled'] === 'yes';

                (function(string &$password, string $username, bool $enabled = false) {
                    if (empty($password)) {
                        throw new ClientException('Password must not be empty');
                    }
                    $password = password_hash($password, PASSWORD_BCRYPT);
                })(...$requestedParams);

                $requestedParams = arrayPrefix($requestedParams, 'webadminaccount_');

                (new AdminUserDAO($container))->insert($requestedParams);

                return new Response([
                                        'Location' => '/__admin__/AdminUser/Show',
                                    ], '', protocolVersion: $message->getProtocolVersion());
            } catch (Throwable $e) {
                $view->assign('error', $e->getMessage());
            }
        }

        $view->assign('headline', 'Administrator hinzufügen');
        return $view->render();
    }

    public static function edit(Request $message, Container $container, int $userId) {

        $user = (new AdminUserDAO($container))->get($userId);

        if (!$user) {
            return new Response([
                                    'Location' => '/__admin__/',
                                ], '', protocolVersion: $message->getProtocolVersion());
        }

        if ($message->getMethod() === 'GET') {
            return (new View('AdminUserEdit'))
                ->assign('headline', 'Administratorkonto "' . $user['username'] . '" bearbeiten')
                ->assign('user', $user)
                ->render();
        }

        $requestedParams = $message->getParsedBody();

        if (!is_array($requestedParams)) {
            throw new ClientException('Invalid parameter body');
        }

        $requestedParams = array_filter($requestedParams);
        $requestedParams['enabled'] = $requestedParams['enabled'] === 'yes';

        (function(string &$password = null, bool $enabled = false) {
            if ($password !== null) {
                $password = password_hash($password, PASSWORD_BCRYPT);
            }
        })(...$requestedParams);

        $requestedParams = arrayPrefix($requestedParams, 'webadminaccount_');
        (new AdminUserDAO($container))->update($requestedParams, $userId);

        return new Response([
                                'Location' => '/__admin__/AdminUser/Show',
                            ], '', protocolVersion: $message->getProtocolVersion());
    }
}
