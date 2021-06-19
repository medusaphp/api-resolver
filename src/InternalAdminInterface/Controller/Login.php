<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver\InternalAdminInterface\Controller;

use Medusa\App\ApiResolver\Container;
use Medusa\App\ApiResolver\Crypt;
use Medusa\App\ApiResolver\InternalAdminInterface\View;
use Medusa\App\ApiResolver\InternalApi\ClientException;
use Medusa\App\ApiResolver\ResolverConfig;
use Medusa\Http\Simple\MessageInterface;
use Medusa\Http\Simple\Request;
use Medusa\Http\Simple\Response;
use Throwable;
use function in_array;
use function is_string;
use function Medusa\Http\isSsl;
use function password_verify;
use function setcookie;
use function time;

/**
 * Class User
 * @package Medusa\App\ApiResolver\InternalAdminInterface\Controller
 * @author  Pascale Schnell <pascale.schnell@check24.de>
 */
class Login {

    public static function handleLogout(Request $message, Container $container) {
        $cookieOptions = [
            'expires'  => time() - 1,
            'path'     => '/',
            'domain'   => $_SERVER['SERVER_NAME'],
            'httponly' => true,
            'samesite' => 'Strict',
            'secure'   => isSsl($message),
        ];
        setcookie('auth', '', $cookieOptions);
        return new Response(
            [
                'Location' => '/__admin__/',
            ], '', protocolVersion: $message->getProtocolVersion());
    }

    public static function handleLogin(Request $message, Container $container) {
        $view = new View('Login');

        if ($message->getMethod() === 'POST') {

            try {
                /** @var ResolverConfig $config */
                $config = $container->get(ResolverConfig::class);
                $body = $message->getParsedBody();
                $res = (function(ResolverConfig $config, Request $message, string $username, string $password) {

                    foreach ($config->getAdminInterfaceUsers() as $user) {

                        if ($user['name'] !== $username) {
                            continue;
                        }

                        if (!password_verify($password, $user['password'])) {
                            throw new ClientException('Invalid credentials');
                        }

                        if (!in_array($message->getRemoteAddress(), $user['ips'])) {
                            throw new ClientException('Invalid credentials / ip block');
                        }

                        self::doLoginAuth($message, $config);
                        return new Response(
                            [
                                'Location' => '/__admin__/',
                            ], '', protocolVersion: $message->getProtocolVersion());
                    }
                })($config, $message, ...$body);

                if ($res instanceof Response) {
                    return $res;
                }

                throw new ClientException('Invalid credentials');
            } catch (Throwable $exception) {
                $view->assign('error', $exception->getMessage());
            }
        }

        $view
            ->assign('showNavi', false)
            ->assign('headline', 'Login');

        return $view->render();
    }

    /**
     * @param MessageInterface $message
     * @param ResolverConfig   $config
     */
    private static function doLoginAuth(MessageInterface $message, ResolverConfig $config): void {
        $currentTime = time();
        $token = Crypt::encryptData($config->getAdminInterfaceCryptPassword(), [
            'expire' => $currentTime + 300,
        ]);

        $cookieOptions = [
            'expires'  => $currentTime + 60 * 60 * 24 + 30,
            'path'     => '/',
            'domain'   => $_SERVER['SERVER_NAME'],
            'httponly' => true,
            'samesite' => 'Strict',
            'secure'   => isSsl($message),
        ];
        setcookie('auth', $token, $cookieOptions);
        $_COOKIE['auth'] = $token;
    }

    public static function checkAuth(MessageInterface $message, ResolverConfig $config) {

        $authToken = $_COOKIE['auth'] ?? null;

        if (!is_string($authToken)) {
            return false;
        }

        $decrypt = Crypt::decryptToken($config->getAdminInterfaceCryptPassword(), $authToken);
        $currentTime = time();

        if (!$decrypt || $decrypt['expire'] < $currentTime) {
            return false;
        }

        self::doLoginAuth($message, $config);
        return true;
    }
}
