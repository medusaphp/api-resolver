<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver\InternalApi;

use function is_string;
use function strlen;
use function substr;

/**
 * Class Utils
 * @package medusa/api-resolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class Utils {

    /**
     * Array extract
     * @param array  $array
     * @param string $prefix
     * @param bool   $removePrefix
     * @return array
     */
    public static function arrayExtract(array $array, string $prefix, bool $removePrefix = false): array {

        $result = [];
        $prefixLength = strlen($prefix);

        foreach ($array as $key => $value) {
            if (substr($key, 0, $prefixLength) === $prefix) {
                if ($removePrefix) {
                    $key = substr($key, $prefixLength);
                }

                $result[$key] = $value;
            }
        }

        return $result;
    }

    public static function arrayPrefix(array $array, string $prefix) {
        $result = [];
        foreach ($array as $key => $value) {
            if (is_string($key)) {
                $key = $prefix . $key;
            }
            $result[$key] = $value;
        }
        return $result;
    }
}
