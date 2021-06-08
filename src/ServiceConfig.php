<?php declare(strict_types = 1);

namespace Medusa\App\ApiResolver;

use function array_merge;
use function parse_ini_file;

/**
 * Class ServiceConfig
 * @package medusa/app-apiresolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class ServiceConfig {

    public function __construct(private array $data) {

    }

    public static function load(string $path, array $additionalConfig = []) {
        return new self(array_merge(parse_ini_file($path), $additionalConfig));
    }

    public function getResolver(): string {
        return $this->data['secondary_resolver'] ?? 'self';
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