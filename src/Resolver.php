<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver;

use JsonException;
use Medusa\Http\Simple\Response;
use Medusa\Http\Simple\ServerRequest;
use Throwable;
use function array_filter;
use function array_merge;
use function array_values;
use function curl_exec;
use function curl_init;
use function curl_setopt;
use function explode;
use function file_exists;
use function hash;
use function implode;
use function is_array;
use function json_encode;
use function microtime;
use function stripos;
use function strtolower;
use const CURLOPT_COOKIESESSION;
use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_HEADER;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_UNIX_SOCKET_PATH;
use const CURLOPT_URL;

/**
 * Class Resolver
 * @package medusa/api-resolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class Resolver {

    private string $debugChallenge;

    public function __construct(private ResolverConfig $config) {

    }

    public function start(?ServerRequest $request = null): Response {

        try {
            $request ??= ApiRequestWrapper::createFromGlobals();

            if (!$request) {
                return new Response([
                                        'Content-Type: application/json',
                                        'HTTP/1.1 404 Malformed URL',
                                    ], '', 400);
            }

            $serviceConfig = $this->determineServiceConfig($request);

            if (!$serviceConfig) {
                return new Response([
                                        'Content-Type: application/json',
                                        'HTTP/1.1 404 Not Found',
                                    ], '', 404);
            }

            if ($serviceConfig->getAccessType() !== 'int' && (empty($_SERVER['PHP_AUTH_USER']) || empty($_SERVER['PHP_AUTH_PW']))) {
                return new Response([
                                        'Content-Type: application/json',
                                        'WWW-Authenticate: Basic realm="auth"',
                                        'HTTP/1.1 401 Unauthorized',
                                    ], '', 401);
            }

            if (!Doorman::accessAllowed($request, $this->config, $serviceConfig)) {
                return new Response([
                                        'Content-Type: application/json',
                                        'HTTP/1.1 401 Unauthorized ' . $request->getRemoteAddress(),
                                    ], '', 401);
            }

            return $this->forward($request, $serviceConfig);
        } catch (Throwable $exception) {
            if ($this->config->isDebugModeEnabled()) {
                $errorBody = json_encode([
                                             '_hint'         => 'Hi my friend, you wondering why you see this message? Your debug mode is enabled :-)',
                                             '_errorMessage' => $exception->getMessage(),
                                             '_errorTrace'   => $exception->getTraceAsString(),
                                             '_errorCode'    => $exception->getCode(),
                                         ]);
            }
        }

        return new Response([
                                'Content-Type: application/json',
                                'HTTP/1.1 500 Internal Server Error',
                            ], $errorBody ?? '', 500);
    }

    /**
     * @param ServerRequest $request
     * @return ServiceConfig|null
     * @throws JsonException
     */
    public function determineServiceConfig(ServerRequest $request): ?ServiceConfig {
        $controllerDirectoryBasename = strtolower($request->getProject()) . '/' . strtolower($request->getControllerNamespace() . '_' . $request->getControllerName());
        $servicesRoot = $request->getServicesRoot();
        $configFile = $servicesRoot . '/services/' . $controllerDirectoryBasename . '/conf.d/env.json';

        if (!file_exists($configFile)) {
            return null;
        }

        $availableEndpoints = [];
        $availableEndpointsConfigFile = $servicesRoot . '/' . $controllerDirectoryBasename . '/conf.d/availableEndpoints.json';
        if (file_exists($availableEndpointsConfigFile)) {
            $availableEndpoints = JsonConfig::load($availableEndpointsConfigFile)->getData();
        }

        return ServiceConfig::load($configFile, [
            'controllerDirectoryBasename' => $controllerDirectoryBasename,
            'availableEndpoints'          => $availableEndpoints,
        ]);
    }

    public function forward(ServerRequest $request, ServiceConfig $conf): Response {

        $controllerDirectoryBasename = $conf->getControllerDirectoryBasename();
        $resolver = $conf->getResolver();
        $headers = [
            'X-Service-Resolver'       => ($resolver === 'self' ? ('services/' . $controllerDirectoryBasename) : ('secondaryResolver/' . $resolver)),
            'X-Service'                => $controllerDirectoryBasename,
            'X-Forwarded-For'          => $request->getRemoteAddress(),
            'X-Medusa-Debug-Challenge' => $this->getDebugChallenge(),
        ];

        $headers = array_merge($request->getHeaders(), $headers);
        unset($headers['Accept-Encoding']);

        foreach ($headers as $key => &$header) {
            $header = $key . ':' . $header;
        }

        $headers = array_values($headers);
        $resolverSocket = $_SERVER['API_RESOLVER'];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_UNIX_SOCKET_PATH, $resolverSocket);
        curl_setopt($ch, CURLOPT_URL, 'service.resolver/' . $conf->getInterpreter() . $_SERVER['REQUEST_URI']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_COOKIESESSION, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request->getMethod());

        if ($request->hasBody()) {
            $body = $request->getBody();
            if (is_array($body)) {
                unset($headers['content-length']);
                // Remove boundary
                $headers['content-type'] = implode(';',
                                                   array_filter(
                                                       explode(';', $headers['content-type']),
                                                       fn(string $header) => stripos($header, 'boundary=') === false
                                                   ));
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if (false === $response) {
            return new Response([
                                    'HTTP/1.1 500 Internal Server Error',
                                ], '', 500);
        }

        return Response::createFromRawResponse($response);
    }

    /**
     * @return string
     */
    public function getDebugChallenge(): string {
        return $this->debugChallenge ??=
            $this->config->isDebugModeEnabled() ?
                hash('sha256', __FILE__ . '#' . microtime(true) . '#' . __LINE__)
                : '';
    }
}
