<?php declare(strict_types = 1);

namespace Medusa\App\ApiResolver;

use function array_merge;

/**
 * Class ServiceConfig
 * @package medusa/api-resolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class ServiceConfig extends JsonConfig {

    public static function load(string $path, array $additionalConfig = []): static {
        $config = parent::load($path);
        $config->data = array_merge($config->data, $additionalConfig);
        return $config;
    }

    public function getResolver(): string {
        return $this->data['secondaryResolver'] ?? 'self';
    }

    public function getAvailableEndpoints(): array {
        return $this->data['availableEndpoints'] ?? [];
    }

    public function getAccessType(): string {
        return $this->data['access'] ?? 'int';
    }

    public function getControllerDirectoryBasename(): string {
        return $this->data['controllerDirectoryBasename'];
    }

    public function getInterpreter(): string {
        return $this->data['interpreter'];
    }
}