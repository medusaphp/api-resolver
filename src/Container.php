<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver;

use function call_user_func;

/**
 * Class Container
 * @package medusa/api-resolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class Container {

    private array $instances = [];

    public function __construct(private array $config) {
    }

    /**
     * @param string $ident
     * @return mixed
     */
    public function get(string $ident): mixed {
        return $this->instances[$ident] ??= call_user_func($this->config[$ident], $this);
    }
}
