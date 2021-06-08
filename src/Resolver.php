<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver;

use Throwable;
use function array_filter;
use function curl_exec;
use function curl_init;
use function curl_setopt;
use function explode;
use function file_exists;
use function file_get_contents;
use function getallheaders;
use function is_array;
use function json_decode;
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
 * @package medusa/app-apiresolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class Resolver {

    public function __construct(private ResolverConfig $config) {

    }

    public function start(?ServerRequest $request = null) {

        try {
            $request ??= ServerRequest::createFromGlobals();

            if (!$request) {
                return new Response([
                                        'HTTP/1.1 404 Malformed URL',
                                    ], '');
            }

            $serviceConfig = $this->determineServiceConfig($request);

            if (!$serviceConfig) {
                return new Response([
                                        'HTTP/1.1 404 Not Found',
                                    ], '');
            }

            if ($serviceConfig->getAccessType() !== 'int' && (empty($_SERVER['PHP_AUTH_USER']) || empty($_SERVER['PHP_AUTH_PW']))) {
                return new Response([
                                        'WWW-Authenticate: Basic realm="auth"',
                                        'HTTP/1.1 401 Unauthorized',
                                    ], '');
            }

            if (!Doorman::accessAllowed($request, $this->config, $serviceConfig)) {
                return new Response([
                                        'HTTP/1.1 401 Unauthorized ' . $request->getRemoteAddress(),
                                    ], '');
            }

            return $this->forward($request, $serviceConfig);
        } catch (Throwable $exception) {
        }

        return new Response([
                                'HTTP/1.1 500 Internal Server Error',
                            ], '');
    }

    /**
     * @param ServerRequest $request
     * @return ServiceConfig|null
     */
    public function determineServiceConfig(ServerRequest $request): ?ServiceConfig {
        $controllerDirectoryBasename = strtolower($request->getProject()) . '/' . strtolower($request->getControllerNamespace() . '_' . $request->getControllerName());
        $servicesRoot = $request->getServicesRoot();
        $configFile = $servicesRoot . '/services/' . $controllerDirectoryBasename . '/conf.d/.env.conf';

        if (!file_exists($configFile)) {
            return null;
        }

        $availableEndpoints = [];
        $availableEndpointsConfigFile = $servicesRoot . '/' . $controllerDirectoryBasename . '/conf.d/available_endpoints.json';
        if (file_exists($availableEndpointsConfigFile)) {
            $availableEndpoints = json_decode(file_get_contents($availableEndpointsConfigFile), true);
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
            'x-service-resolver' => 'x-service-resolver: ' . ($resolver === 'self' ? ('services/' . $controllerDirectoryBasename) : ('secondary_resolver/' . $resolver)),
            'x-service'          => 'x-service: ' . $controllerDirectoryBasename,
            'x-forwarded-for' => 'x-forwarded-for: ' . $request->getRemoteAddress(),
        ];

        foreach (getallheaders() as $name => $value) {
            if ($name === 'Accept-Encoding') {
                continue;
            }
            $headers[strtolower($name)] = $name . ': ' . $value;
        }

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
                                ], '');
        }

        return Response::createFromRawResponse($response);
    }
}
