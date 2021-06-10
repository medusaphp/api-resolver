<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver;

use Medusa\Http\Simple\ServerRequest;
use function preg_match;

/**
 * Class ApiRequestWrapper
 * @package Medusa\App\ApiResolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class ApiRequestWrapper extends ServerRequest {

    protected string $project;
    protected string $controllerNamespace;
    protected string $controllerName;
    protected string $controllerVersion;
    protected string $servicesRoot;

    public static function createFromGlobals(): ?self {

        if (!preg_match('#^/([a-z0-9_-]+)/([a-z0-9_-]+)/([a-z0-9_-]+)(.*)/(\d+.\d+.\d+)/#i', $_SERVER['REQUEST_URI'], $matches)) {
            return null;
        }

        $servicesRoot = $_SERVER['API_SERVICES_LOCATION'];

        /** @var ApiRequestWrapper $request */
        $request = parent::createFromGlobals();

        [, $project, $controllerNamespace, $controllerName, $controllerVersion] = $matches;

        $request->project = $project;
        $request->controllerNamespace = $controllerNamespace;
        $request->controllerName = $controllerName;
        $request->servicesRoot = $servicesRoot;

        return $request;
    }

    /**
     * @return string
     */
    public function getServicesRoot(): string {
        return $this->servicesRoot;
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