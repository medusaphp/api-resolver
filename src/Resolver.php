<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver;

use JsonException;
use Medusa\App\ApiResolver\InternalApi\InternalApiServer;
use Medusa\Http\Simple\Curl;
use Medusa\Http\Simple\MessageInterface;
use Medusa\Http\Simple\Request;
use Medusa\Http\Simple\Response;
use Throwable;
use function array_flip;
use function array_intersect_key;
use function file_exists;
use function hash;
use function in_array;
use function is_array;
use function json_encode;
use function microtime;
use function str_starts_with;
use const JSON_UNESCAPED_SLASHES;

/**
 * Class Resolver
 * @package medusa/api-resolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class Resolver {

    private string $debugChallenge;

    public function __construct(private ResolverConfig $config) {

    }

    public function start(?MessageInterface $request = null): Response {

        try {
            $request ??= Request::createFromGlobals();
            $translator = RequestedPathTranslator::createFromGlobals();
            $protocol = $request->getProtocolVersion();

            if (!$translator) {

                if (
                    str_starts_with($request->getUri()->getPath(), '/__admin__/')
                    && $this->config->isAdminInterfaceEnabled()) {
                    return (new InternalAdminInterface\InternalAdminInterface($this->config))->handleRequest($request);
                }

                $secret = $request->getHeader('X-Medusa-Api-Resolver-Access-Secret')[0] ?? null;

                if (
                    !$secret
                    || $secret !== $this->config->getApiServerAccessSecret()
                    || !in_array($request->getRemoteAddress(), $this->config->getApiServerIpAddressWhitelist(), true)
                ) {
                    return new Response([
                                            'Content-Type: application/json',
                                        ], '', 400, 'Malformed URL', $protocol);
                }

                return (new InternalApiServer($this->config))->handleRequest($request);
            }

            $serviceConfig = $this->determineServiceConfig($translator);

            if (!$serviceConfig) {
                $body = '';
                if ($this->config->isDebugModeEnabled()) {
                    $body = json_encode(
                        ['Couldnt find a env.json in following directory \'' . $translator->getConfigDirectory() . '\''],
                        JSON_UNESCAPED_SLASHES
                    );
                }

                return new Response([
                                        'Content-Type: application/json',
                                    ], $body, 404, 'Not Found', $protocol);
            }

            if ($serviceConfig->getAccessType() !== 'int' && (empty($_SERVER['PHP_AUTH_USER']) || empty($_SERVER['PHP_AUTH_PW']))) {
                return new Response([
                                        'Content-Type: application/json',
                                        'WWW-Authenticate: Basic realm="auth"',
                                    ], '', 401, 'Unauthorized', $protocol);
            }

            if (!Doorman::accessAllowed($request, $this->config, $serviceConfig)) {
                return new Response([
                                        'Content-Type: application/json',
                                    ], '', 401, 'Unauthorized' . $request->getRemoteAddress(), $protocol);
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
                            ], $errorBody ?? '', 500, 'Internal Server Error');
    }

    /**
     * @param RequestedPathTranslator $translator
     * @return ServiceConfig|null
     * @throws JsonException
     */
    public function determineServiceConfig(RequestedPathTranslator $translator): ?ServiceConfig {

        $configDirectory = $translator->getConfigDirectory();
        $configFile = $configDirectory . '/env.json';

        if (!file_exists($configFile)) {
            return null;
        }

        $availableEndpoints = [];
        $availableEndpointsConfigFile = $configDirectory . '/availableEndpoints.json';
        if (file_exists($availableEndpointsConfigFile)) {
            $availableEndpoints = JsonConfig::load($availableEndpointsConfigFile)->getData();
        }

        return ServiceConfig::load($configFile, [
            'controllerDirectoryBasename' => $translator->getControllerDirectoryBasename(),
            'availableEndpoints'          => $availableEndpoints,
        ]);
    }

    public function forward(MessageInterface $request, ServiceConfig $conf): Response {

        $forwardedRequest = clone($request);
        $controllerDirectoryBasename = $conf->getControllerDirectoryBasename();
        $resolver = $conf->getResolver();

        $forwardedRequest->addHeaders(
            [
                'Medusa-Service-Resolver: ' . ($resolver === 'self' ? ('services/' . $controllerDirectoryBasename) : ('secondaryResolver/' . $resolver)),
                'Medusa-Service: ' . $controllerDirectoryBasename,
                'X-Medusa-Debug-Challenge: ' . $this->getDebugChallenge(),
            ]
        );
        $forwardedRequest->removeHeader('Accept-Encoding');
        $resolverSocket = $_SERVER['MEDUSA_API_RESOLVER_SOCK'];

        if ($forwardedRequest->hasBody()) {
            $body = $forwardedRequest->getBody();
            if (is_array($body)) {
                $forwardedRequest->removeHeader('Content-Length');
                $forwardedRequest->removeHeaderValue('Content-Type', 'boundary');
            }
        }

        $forwardedRequest->setUri('http://service.resolver/' . $conf->getInterpreter() . $_SERVER['REQUEST_URI']);
        $forwardingVars = $this->config->getForwardingEnvVars();
        $forwardingVars[] = 'MEDUSA_API_SERVICE_REPOSITORY_PATH';
        $forwardingVars = array_flip($forwardingVars);
        $forwardingVars = array_intersect_key($_SERVER, $forwardingVars);
        $forwardingVars['X-Medusa-Api-Service-Repository'] = $_SERVER['MEDUSA_API_SERVICE_REPOSITORY'];
        $forwardedRequest->addHeaders($forwardingVars);

        $curl = Curl::createForRequest($forwardedRequest);
        $curl->setSocketPath(
            $resolverSocket
        );

        return $curl->send();
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
