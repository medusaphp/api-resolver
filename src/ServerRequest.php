<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver;

use function file_get_contents;
use function Medusa\Http\getRemoteAddress;
use function preg_match;
use function strpos;
use function strtolower;
use function substr;

/**
 * Class ServerRequest
 * @package medusa/app-apiresolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class ServerRequest {

    private string $remoteAddress;

    public function __construct(
        private string $project,
        private string $controllerNamespace,
        private string $controllerName,
        private string $controllerVersion,
        private string $servicesRoot,
        private string $method,
        private null|string|array $body
    ) {
    }

    public static function createFromGlobals(): ?self {

        if (!preg_match('#^/([a-z0-9_-]+)/([a-z0-9_-]+)/([a-z0-9_-]+)(.*)/(\d+.\d+.\d+)/#i', $_SERVER['REQUEST_URI'], $matches)) {
            return null;
        }

        [, $project, $controllerNamespace, $controllerName, $controllerVersion] = $matches;
        $servicesRoot = $_SERVER['API_SERVICES_LOCATION'];
        $body = null;

        switch ($_SERVER['REQUEST_METHOD']) {
            case 'DELETE':
            case 'PATCH':
            case 'POST':
            case 'PUT':
                $contentType = strtolower($_SERVER['HTTP_CONTENT_TYPE']);
                $usePostArray = strpos($contentType, 'multipart/form-data') !== false
                    || strpos($contentType, 'application/x-www-form-urlencoded') !== false;
                if ($usePostArray) {
                    $body = $_POST;
                } else {
                    $body = file_get_contents('php://input');
                }
        }

        return new self(
            $project,
            $controllerNamespace,
            $controllerName,
            $controllerVersion,
            $servicesRoot,
            $_SERVER['REQUEST_METHOD'],
            $body
        );
    }

    /**
     * @return array|string|null
     */
    public function getBody(): array|string|null {
        return $this->body;
    }

    /**
     * @return bool
     */
    public function hasBody(): bool {
        return $this->body !== null;
    }

    /**
     * @return string
     */
    public function getMethod(): string {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getServicesRoot(): string {
        return $this->servicesRoot;
    }

    public function getRemoteAddress(): string {

        $remoteAddress = (string)getRemoteAddress();
        if (substr($remoteAddress, 0, 5) === 'unix:') {
            $remoteAddress = '127.0.0.1';
        }

        return $remoteAddress;
    }

    /**
     * @return string
     */
    public function getProject(): string {
        return $this->project;
    }

    /**
     * @return string
     */
    public function getControllerNamespace(): string {
        return $this->controllerNamespace;
    }

    /**
     * @return string
     */
    public function getControllerName(): string {
        return $this->controllerName;
    }

    /**
     * @return string
     */
    public function getControllerVersion(): string {
        return $this->controllerVersion;
    }
}
