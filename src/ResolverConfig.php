<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver;

/**
 * Class Config
 * @package medusa/api-resolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class ResolverConfig extends JsonConfig {

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

    /**
     * @return array
     */
    public function getForwardingEnvVars(): array {
        return $this->data['forwardingEnvVars'] ?? [];
    }

    /**
     * @return bool
     */
    public function isDebugModeEnabled(): bool {
        return ($this->data['debugMode'] ?? false) === true;
    }
}
