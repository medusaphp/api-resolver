<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver\InternalApi;

use Medusa\App\ApiResolver\Container;
use Medusa\App\ApiResolver\Database\MedusaPDO;
use Medusa\App\ApiResolver\InternalApi\Controller\AccountIp;
use Medusa\App\ApiResolver\InternalApi\Controller\User;
use Medusa\App\ApiResolver\InternalApi\Controller\Userpermission;
use Medusa\App\ApiResolver\ResolverConfig;
use Medusa\Http\Simple\MessageInterface;
use Medusa\Http\Simple\Response;
use Throwable;
use function array_merge;
use function array_values;
use function call_user_func;
use function is_array;
use function json_encode;
use function preg_match;
use function sprintf;

/**
 * Class InternalApi
 * @package medusa/api-resolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class InternalApiServer {

    private array $routes = [
        'GET'    => [
            '/User' => [
                ''       => [User::class, 'getAll'],
                '/(\d+)' => [
                    ''            => [User::class, 'get'],
                    '/Ip'         => [
                        ''       => [AccountIp::class, 'getAll'],
                        '/(\d+)' => [
                            '' => [AccountIp::class, 'get'],
                        ],
                    ],
                    '/Permission' => [
                        ''       => [Userpermission::class, 'getAll'],
                        '/(\d+)' => [
                            '' => [Userpermission::class, 'get'],
                        ],
                    ],
                ],
            ],
        ],
        'PUT'    => [
            '/User' => [
                '/(\d+)' => [
                    ''            => [User::class, 'edit'],
                    '/Ip'         => [
                        '/(\d+)' => [
                            '' => [AccountIp::class, 'edit'],
                        ],
                    ],
                    '/Permission' => [
                        '/(\d+)' => [
                            '' => [Userpermission::class, 'edit'],
                        ],
                    ],
                ],
            ],
        ],
        'POST'   => [
            '/User' => [
                ''       => [User::class, 'create'],
                '/(\d+)' => [
                    '/Ip'         => [
                        ''       => [AccountIp::class, 'create'],
                        '/(\d+)' => [
                        ],
                    ],
                    '/Permission' => [
                        ''       => [Userpermission::class, 'create'],
                        '/(\d+)' => [
                        ],
                    ],
                ],
            ],
        ],
        'DELETE' => [
            '/User' => [
                '/(\d+)' => [
                    ''            => [User::class, 'delete'],
                    '/Ip'         => [
                        ''       => [AccountIp::class, 'delete'],
                        '/(\d+)' => [
                            '' => [AccountIp::class, 'delete'],
                        ],
                    ],
                    '/Permission' => [
                        ''       => [Userpermission::class, 'delete'],
                        '/(\d+)' => [
                            '' => [Userpermission::class, 'delete'],
                        ],
                    ],
                ],
            ],
        ],

    ];

    public function __construct(private ResolverConfig $config) {

    }

    public function handleRequest(MessageInterface $request): Response {

        $routes = $this->buildRoute($this->routes[$request->getMethod()]);
        $requestUri = mb_substr($request->getUri(), 8);
        $result = null;
        $code = 200;
        $phrase = 'OK';
        $protocol = $request->getProtocolVersion();

        $container = new Container(
            [
                'tablePrefix'    => function() {
                    return $this->config->getDb()['tablePrefix'] ?? '';
                },
                MedusaPDO::class => function() {

                    $config = $this->config->getDb();
                    $dsn = sprintf(
                        'mysql:port=%d;host=%s;dbname=%s;charset=utf8mb4',
                        $config['port'],
                        $config['host'],
                        $config['db'],
                    );
                    return new MedusaPDO($dsn, $config['user'], $config['pass']);
                },
            ]);

        foreach ($routes as $route => $fn) {
            $route = '#^' . $route . '$#';
            if (preg_match($route, $requestUri, $matches)) {

                unset($matches[0]);
                $matches = array_values($matches);
                try {
                    $result = call_user_func($fn, $request, $container, ...$matches);
                    return new Response([
                                            'Content-Type: application/json',
                                        ], json_encode($result), $code, $phrase, $protocol);
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
