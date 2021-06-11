<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver;

use function preg_match;
use function strtolower;

/**
 * Class RequestedPathTranslator
 * @package medusa/api-resolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class RequestedPathTranslator {

    private string $controllerDirectoryBasename;

    public function __construct(
        protected string $project,
        protected string $controllerNamespace,
        protected string $controllerName,
        protected string $controllerVersion,
        protected string $servicesRoot
    ) {

    }

    public static function createFromGlobals(): ?static {

        if (!preg_match('#^/([a-z0-9_-]+)/([a-z0-9_-]+)/([a-z0-9_-]+)(.*)/(\d+.\d+.\d+)/#i', $_SERVER['REQUEST_URI'], $matches)) {
            return null;
        }

        $servicesRoot = $_SERVER['MEDUSA_API_SERVICE_REPOSITORY_PATH'];

        [, $project, $controllerNamespace, $controllerName, $controllerVersion] = $matches;

        $translator = new static(
            $project,
            $controllerNamespace,
            $controllerName,
            $controllerVersion,
            $servicesRoot
        );

        return $translator;
    }

    public function getConfigDirectory() {
        $servicesRoot = $this->getServicesRoot();
        $configFile = $servicesRoot . '/services/' . $this->getControllerDirectoryBasename() . '/conf.d';
        return $configFile;
    }

    /**
     * @return string
     */
    public function getServicesRoot(): string {
        return $this->servicesRoot;
    }

    public function getControllerDirectoryBasename(): string {
        return $this->controllerDirectoryBasename ??= strtolower($this->getProject())
            . '/' . strtolower(
                $this->getControllerNamespace()
                . '_' . $this->getControllerName()
            );
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