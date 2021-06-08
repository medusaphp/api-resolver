<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver;

use function explode;

/**
 * Class Response
 * @package medusa/app-apiresolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class Response {

    /**
     * Response constructor.
     * @param array  $headers
     * @param string $body
     */
    public function __construct(private array $headers, private string $body) {
    }

    /**
     * @param string $rawResponse
     * @return static
     */
    public static function createFromRawResponse(string $rawResponse): self {
        [$headers, $body] = explode("\r\n\r\n", $rawResponse, 2);
        return new self(explode("\r\n", $headers), $body);
    }

    /**
     * @return string
     */
    public function getBody(): string {
        return $this->body;
    }

    /**
     * @return array
     */
    public function getHeaders(): array {
        return $this->headers;
    }
}
