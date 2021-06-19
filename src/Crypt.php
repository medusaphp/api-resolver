<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver;

use function count;
use function explode;
use function json_decode;
use function json_encode;
use function Medusa\Http\base64UrlDecode;
use function Medusa\Http\base64UrlEncode;
use function openssl_cipher_iv_length;
use function openssl_decrypt;
use function openssl_encrypt;
use function openssl_random_pseudo_bytes;
use function strlen;

/**
 * Class Crypt
 * @package medusa/api-resolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class Crypt {

    private const CYPHER_METHOD = 'AES-256-CBC';

    /**
     * @param string $password
     * @param array  $input
     * @return string|null
     */
    public static function encryptData(string $password, array $input): ?string {

        $data = json_encode($input);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CYPHER_METHOD));
        $encrypted = openssl_encrypt($data, self::CYPHER_METHOD, $password, 0, $iv);

        return base64UrlEncode($iv) . '.' . base64UrlEncode($encrypted);
    }

    /**
     * @param string $password
     * @param string $input
     * @return array|null
     */
    public static function decryptToken(string $password, string $input): ?array {

        $tmp = explode('.', $input);

        if (count($tmp) !== 2) {
            return null;
        }

        [$iv, $encrypted] = $tmp;
        $encrypted = base64UrlDecode($encrypted);
        $iv = base64UrlDecode($iv);

        if (strlen($iv) !== 16) {
            return null;
        }

        $decrypted = openssl_decrypt($encrypted, self::CYPHER_METHOD, $password, 0, $iv);

        if ($decrypted === false) {
            return null;
        }

        return json_decode($decrypted, true);
    }
}
