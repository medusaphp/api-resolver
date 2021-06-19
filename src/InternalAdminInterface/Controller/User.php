<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver\InternalAdminInterface\Controller;

use Generator;
use Medusa\App\ApiResolver\Container;
use Medusa\App\ApiResolver\InternalAdminInterface\View;
use Medusa\App\ApiResolver\InternalApi\Controller as ApiController;
use Medusa\Http\Simple\Request;
use Medusa\Http\Simple\Response;
use Throwable;
use function array_filter;
use function array_flip;
use function array_map;
use function explode;
use function intval;
use function ip2long;
use function long2ip;
use function pow;
use function sprintf;
use function substr;
use function trim;
use function usort;
use const PHP_EOL;

/**
 * Class User
 * @package Medusa\App\ApiResolver\InternalAdminInterface\Controller
 * @author  Pascale Schnell <pascale.schnell@check24.de>
 */
class User {

    public static function permissionAdd(Request $message, Container $container, int $userId) {

        $permission = ApiController\Userpermission::getAll($message, $container, $userId);
        $services = ApiController\Service::getAll($message, $container)['services'];
        $services = array_map(fn() => false, array_flip($services));
        $available = [];
        foreach ($permission as $row) {
            $available[$row['service']] = $row;
            $services[$row['service']] = $row['enabled'];
        }

        if ($message->getMethod() === 'POST') {

            $newPermissions = $message->getParsedBody();

            foreach ($newPermissions as $newPermission => $val) {

                $enabled = $val === 'yes';
                if ($available[$newPermission] ?? false) {

                    if ($enabled === $available[$newPermission]['enabled']) {
                        continue;
                    }

                    ApiController\Userpermission::edit($message->withBody([
                                                                              'enabled' => $enabled,
                                                                          ]), $container, $userId, $available[$newPermission]['id']);
                    continue;
                }

                if (!$enabled) {
                    continue;
                }

                ApiController\Userpermission::create($message->withBody([
                                                                            'service' => $newPermission,
                                                                            'enabled' => $enabled,
                                                                        ]), $container, $userId);
            }

            return new Response(
                [
                    'Location' => '/__admin__/User/' . $userId . '/Permission',
                ], '', protocolVersion: $message->getProtocolVersion());
        }

        $user = ApiController\User::get($message, $container, $userId);

        $view = new View('PermissionAdd');
        $view->assign('headline', 'API Berechtigungen für "' . $user['username'] . '" bearbeiten')
            ->assign('user', $user)
            ->assign('permission', $permission)
            ->assign('services', $services);

        return $view->render();
    }

    public static function permission(Request $message, Container $container, int $userId) {

        $user = ApiController\User::get($message, $container, $userId);
        $permission = ApiController\Userpermission::getAll($message, $container, $userId);

        View::navEntries([[
                              'url'   => '/__admin__/User/' . $user['id'] . '/Permission/Add',
                              'label' => 'Berechtigung bearbeiten',
                          ]]);

        $view = new View('Permission');
        $view->assign('headline', 'API Berechtigungen für "' . $user['username'] . '" bearbeiten')
            ->assign('user', $user)
            ->assign('permissions', $permission);

        return $view->render();
    }

    public static function deletePermission(Request $message, Container $container, int $userId, int $permissionId) {
        if ($message->getMethod() === 'GET') {
            $result = self::get($message, $container, $userId);
            $user = ApiController\User::get($message, $container, $userId);
            $permission = ApiController\Userpermission::get($message, $container, $userId, $permissionId);
            if (!$user || !$permission) {
                return new Response([
                                        'Location' => '/__admin__/',
                                    ], '', protocolVersion: $message->getProtocolVersion());
            }
            return $result . (new View('Confirm'))->assign(
                    'hint', 'Wollen Sie den die Berechtigung "' . $permission['service'] . '" für den Benutzer "' . $user['username'] . '" wirklich löschen?'
                )->render();
        }

        ApiController\Userpermission::delete($message, $container, $userId, $permissionId);

        return new Response(
            [
                'Location' => '/__admin__/User/' . $userId,
            ], '', protocolVersion: $message->getProtocolVersion());
    }

    public static function get(Request $message, Container $container, int $userId) {

        $permissionList = ApiController\Userpermission::getAll($message, $container, $userId);
        $user = ApiController\User::get($message, $container, $userId);

        $view = (new View('Permission'))
            ->assign('permissionList', $permissionList)
            ->assign('user', $user)
            ->assign('headline', 'Service-Berechtigungen ' . $user['username'])
            ->render();

        return $view;
    }

    public static function delete(Request $message, Container $container, int $userId) {
        if ($message->getMethod() === 'GET') {
            $result = self::index($message, $container);
            $user = ApiController\User::get($message, $container, $userId);
            if (!$user) {
                return new Response([
                                        'Location' => '/__admin__/',
                                    ], '', protocolVersion: $message->getProtocolVersion());
            }
            return $result . (new View('Confirm'))->assign(
                    'hint', 'Wollen Sie den Benutzer "' . $user['username'] . '" wirklich löschen?'
                )->render();
        }

        if ($message->getParsedBody()['confirm'] === 'Ja') {
            ApiController\User::delete($message, $container, $userId);
        }

        return new Response(
            [
                'Location' => '/__admin__/',
            ], '', protocolVersion: $message->getProtocolVersion());
    }

    public static function index(Request $message, Container $container) {

        $userList = ApiController\User::getAll($message, $container);

        $view = (new View('UserIndex'))
            ->assign('userList', $userList)
            ->assign('headline', 'API User Übersicht')
            ->render();

        return $view;
    }

    public static function edit(Request $message, Container $container, int $userId) {

        if ($message->getMethod() === 'GET') {

            $ips = ApiController\AccountIp::getAll($message, $container, $userId);
            usort($ips, function($a, $b) {
                return $a['ip'] <=> $b['ip'];
            });

            $user = ApiController\User::get($message, $container, $userId);
            if (!$user) {
                return new Response([
                                        'Location' => '/__admin__/',
                                    ], '', protocolVersion: $message->getProtocolVersion());
            }

            return (new View('UserEdit'))
                ->assign('headline', 'API User bearbeiten')
                ->assign('ips', $ips)
                ->assign('user', $user)
                ->render();
        }

        $body = array_filter($message->getParsedBody());
        $isIpForm = 'ipform' === $body['form'];
        $isAddIpForm = 'addipform' === $body['form'];
        unset($body['form']);

        if ($isIpForm) {
            foreach ($body as $name => $item) {
                $id = (int)substr($name, 3);
                $delete = $item === 'delete';
                $item = $item === 'yes';

                if ($delete) {
                    ApiController\AccountIp::delete($message, $container, $userId, $id);
                } else {
                    ApiController\AccountIp::edit($message->withBody([
                                                                         'enabled' => $item,
                                                                     ]), $container, $userId, $id);
                }
            }
        } elseif ($isAddIpForm) {
            $address = $body['ipaddress'];
            $address = explode(PHP_EOL, $address);
            $address = array_map(function($address) {

                $address = trim($address);
                $tmp = explode('/', $address);
                $netIp = $tmp[0];
                $netMask = intval($tmp[1] ?? 32);

                if ($netMask === 0) {
                    $netMask = 32;
                }

                if ($netMask < 24) {
                    throw new \Exception('netmask to low');
                }
                $ipAsLong = ip2long($netIp);

                if (false === $ipAsLong) {
                    return null;
                }

                return [$netIp, $netMask];
            }, $address);

            foreach ($address as $row) {

                foreach (self::getAllIPAddresses(...$row) as $ip) {

                    try {

                        ApiController\AccountIp::create($message->withBody(
                            [
                                'ip'      => $ip,
                                'enabled' => true,

                            ]), $container, $userId);
                    } catch (\Throwable $exception) {
                        $code = (int)$exception->getCode();
                        //ignore duplicate failure
                        if ($code !== 23000) {
                            throw $exception;
                        }
                    }
                }
            }

            return new Response([
                                    'Location' => '/__admin__/User/' . $userId . '/Edit',
                                ], '', protocolVersion: $message->getProtocolVersion());
        } else {
            $body['enabled'] = $body['enabled'] === 'yes';
            ApiController\User::edit($message->withBody($body), $container, $userId);
        }
        return new Response([
                                'Location' => '/__admin__/',
                            ], '', protocolVersion: $message->getProtocolVersion());
    }

    /**
     * @param string $ipAddress
     * @param int    $prefixLength
     * @return \Generator
     */
    private static function getAllIPAddresses(string $ipAddress, int $prefixLength): Generator {
        $octet = explode('.', $ipAddress);
        $countIpAddresses = pow(2, (32 - $prefixLength));

        $subnetMask = 0xFFFFFFFF << (32 - $prefixLength);
        $startIp = sprintf('%d.%d.%d.%d',
                           $octet[0] & ($subnetMask >> 24),
                           $octet[1] & ($subnetMask >> 16),
                           $octet[2] & ($subnetMask >> 8),
                           $octet[3] & ($subnetMask >> 0),
        );

        $endIp = sprintf('%d.%d.%d.%d',
                         ($octet[0] & ($subnetMask >> 24)) + ((($countIpAddresses - 1) >> 24) & 0xFF),
                         ($octet[1] & ($subnetMask >> 16)) + ((($countIpAddresses - 1) >> 16) & 0xFF),
                         ($octet[2] & ($subnetMask >> 8)) + ((($countIpAddresses - 1) >> 8) & 0xFF),
                         ($octet[3] & ($subnetMask >> 0)) + ((($countIpAddresses - 1) >> 0) & 0xFF),
        );

        //https://www.calculator.net/ip-subnet-calculator.html
        $startIp = ip2long($startIp);
        $endIp = ip2long($endIp);

        if ($prefixLength < 31) {
            $startIp += 1;
            $endIp -= 1;
        }

        for ($ip = $startIp; $ip <= $endIp; $ip++) {
            yield long2ip($ip);
        }
    }

    public static function add(Request $message, Container $container) {
        $view = new View('UserAdd');

        if ($message->getMethod() === 'POST') {
            $body = $message->getParsedBody();
            $body['enabled'] = $body['enabled'] === 'yes';
            $message = $message->withBody($body);

            try {
                ApiController\User::create($message, $container);
                return new Response([
                                        'Location' => '/__admin__/',
                                    ], '', protocolVersion: $message->getProtocolVersion());
            } catch (Throwable $e) {
                $view->assign('error', $e->getMessage());
            }
        }

        $view->assign('headline', 'API User hinzufügen');
        return $view->render();
    }
}