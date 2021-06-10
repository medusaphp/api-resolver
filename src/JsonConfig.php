<?php declare(strict_types = 1);

namespace Medusa\App\ApiResolver;

use JsonException;
use function file_get_contents;
use function json_decode;
use const JSON_THROW_ON_ERROR;

/**
 * Class JsonConfig
 * @package medusa/api-resolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class JsonConfig {

    public function __construct(protected array $data) {
    }

    /**
     * @param string $path
     * @return JsonConfig
     * @throws JsonException
     */
    public static function load(string $path): static {
        $data = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        return new static($data);
    }

    /**
     * @return array
     */
    public function getData(): array {
        return $this->data;
    }
}
