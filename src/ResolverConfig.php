<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver;

use function file_get_contents;
use function json_decode;

/**
 * Class Config
 * @package medusa/app-apiresolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class ResolverConfig {

    /**
     * ResolverConfig constructor.
     * @param array $data
     */
    public function __construct(private array $data) {

    }

    /**
     * @param string $path
     * @return static
     */
    public static function load(string $path): self {
        return new self(json_decode(file_get_contents($path), true));
    }

    /**
     * @return array
     */
    public function getInternalNetworks(): array {
        return $this->data['internalNetworks'] ?? [];
    }
    /**
     * @return array
     */
    public function getDb(): array {
        return $this->data['database'] ?? [];
    }
}
