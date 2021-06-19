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
     * @return string
     */
    public function getApiServerAccessSecret(): string {
        return $this->data['apiServer']['accessSecret'] ?? '';
    }

    /**
     * @return array
     */
    public function getApiServerIpAddressWhitelist(): array {
        return $this->data['apiServer']['ipAddressWhitelist'] ?? [];
    }

    /**
     * @return array
     */
    public function getAdminInterfaceUsers(): array {
        return $this->data['adminInterface']['users'] ?? [];
    }

    /**
     * @return string
     */
    public function getAdminInterfaceCryptPassword(): string {
        return $this->data['adminInterface']['cryptPassword'] ?? '';
    }

    /**
     * @return bool
     */
    public function isAdminInterfaceEnabled(): bool {
        return $this->data['adminInterface']['enabled'] ?? false;
    }

    /**
     * @return bool
     */
    public function isDebugModeEnabled(): bool {
        return ($this->data['debugMode'] ?? false) === true;
    }
}
