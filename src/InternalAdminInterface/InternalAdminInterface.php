<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver\InternalAdminInterface;

use Medusa\App\ApiResolver\Container;
use Medusa\App\ApiResolver\Database\MedusaPDO;
use Medusa\App\ApiResolver\InternalAdminInterface\Controller\Login;
use Medusa\App\ApiResolver\ResolverConfig;
use Medusa\Http\Simple\MessageInterface;
use Medusa\Http\Simple\Response;
use Throwable;
use function array_merge;
use function array_values;
use function call_user_func;
use function explode;
use function is_array;
use function json_encode;
use function mb_substr;
use function preg_match;
use function sprintf;

/**
 * Class InternalAdminInterface
 * @package Medusa\App\ApiResolver\InternalAdminInterface
 * @author  Pascale Schnell <pascale.schnell@check24.de>
 */
class InternalAdminInterface {

    private array $routes = [
        'GET'  => [
            '/logout'    => [Controller\Login::class, 'handleLogout'],
            '/login'     => [Controller\Login::class, 'handleLogin'],
            '/'          => [Controller\User::class, 'index'],
            '/User'      => [
                '/Add'   => [Controller\User::class, 'add'],
                '/(\d+)' => [
                    '/Permission' => [
                        '/Add'   => [Controller\User::class, 'permissionAdd'],
                        ''       => [Controller\User::class, 'permission'],
                        '/(\d+)' => [
                            '/Delete' => [Controller\User::class, 'deletePermission'],
                        ],
                    ],
                    '/Delete'     => [Controller\User::class, 'delete'],
                    '/Edit'       => [Controller\User::class, 'edit'],
                    ''            => [Controller\User::class, 'get'],

                ],
            ],
            '/AdminUser' => [
                '/Add'   => [Controller\AdminUser::class, 'add'],
                '/Show'  => [Controller\AdminUser::class, 'show'],
                '/(\d+)' => [
                    '/Edit'       => [Controller\AdminUser::class, 'edit'],
                    '/Delete'     => [Controller\AdminUser::class, 'delete'],
                ],
            ],

        ],
        'POST' => [
            '/login' => [Controller\Login::class, 'handleLogin'],
            '/User'  => [
                '/Add'   => [Controller\User::class, 'add'],
                '/(\d+)' => [
                    '/Permission' => [
                        '/Add'   => [Controller\User::class, 'permissionAdd'],
                        '/(\d+)' => [
                            '/Delete' => [Controller\User::class, 'deletePermission'],
                        ],
                    ],
                    '/Delete'     => [Controller\User::class, 'delete'],
                    '/Edit'       => [Controller\User::class, 'edit'],
                ],
            ],
            '/AdminUser' => [
                '/Add'   => [Controller\AdminUser::class, 'add'],
                '/(\d+)' => [
                    '/Edit'       => [Controller\AdminUser::class, 'edit'],
                    '/Delete'     => [Controller\AdminUser::class, 'delete'],
                ],
            ],
        ],

    ];

    public function __construct(private ResolverConfig $config) {

    }

    public function handleRequest(MessageInterface $request): Response {

        $routes = $this->buildRoute($this->routes[$request->getMethod()]);
        $requestUri = mb_substr($request->getUri()->getPath(), 10);
        $result = null;
        $code = 200;
        $phrase = 'OK';
        $protocol = $request->getProtocolVersion();

        $container = new Container(
            [
                'tablePrefix'         => function() {
                    return $this->config->getDb()['tablePrefix'] ?? '';
                },
                MedusaPDO::class      => function() {
                    $config = $this->config->getDb();
                    $dsn = sprintf(
                        'mysql:port=%d;host=%s;dbname=%s;charset=utf8mb4',
                        $config['port'],
                        $config['host'],
                        $config['db'],
                    );
                    return new MedusaPDO($dsn, $config['user'], $config['pass']);
                },
                ResolverConfig::class => fn() => $this->config,
            ]);

        foreach ($routes as $route => $fn) {
            $route = '#^' . $route . '$#';

            $requestUri = explode('?', $requestUri, 2)[0];

            if (preg_match($route, $requestUri, $matches)) {

                if ($requestUri !== '/login' && !($user = Login::checkAuth($request, $this->config, $container))) {
                    return new Response([
                                            'location: /__admin__/login',
                                        ], '', 200, 'Unauthorized', $protocol);
                }

                unset($matches[0]);
                $matches = array_values($matches);
                try {
                    $result = call_user_func($fn, $request, $container, ...$matches);
                    if ($result instanceof Response) {
                        return $result;
                    }
                    $view = new View('Main');
                    $result = $view->assign('content', $result)->assign('adminUser', $user)->render();
                    return new Response([
                                            'Content-Type: text/html',
                                        ], $result, $code, $phrase, $protocol);
                } catch (Throwable $exception) {
                    $result = [
                        '_error' => $exception->getMessage(),
                    ];

                    $code = 400;
                    $phrase = 'Bad Request';
                    return new Response([
                                            'Content-Type: application/json',
                                        ], json_encode($result), $code, $phrase, $protocol);
                }
            }
        }

        return new Response([
                                'Content-Type: application/json',
                            ], '[]', 404, 'Controller not found', $protocol);
    }

    private function buildRoute($routes, $prefix = ''): array {

        $results = [];
        foreach ($routes as $routePart => $next) {

            if (is_array($next) && empty($next[0])) {
                $results = array_merge($results, $this->buildRoute($next, $prefix . $routePart));
            } else {
                $results[$prefix . $routePart] = $next;
            }
        }

        return $results;
    }
}