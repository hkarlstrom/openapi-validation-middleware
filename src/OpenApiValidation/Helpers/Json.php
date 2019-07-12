<?php

/**
 * OpenAPI Validation Middleware.
 *
 * @see       https://github.com/hkarlstrom/openapi-validation-middleware
 *
 * @copyright Copyright (c) 2018 Henrik Karlström
 * @license   MIT
 */

namespace HKarlstrom\Middleware\OpenApiValidation\Helpers;

class Json
{
    public static function remove(array $json, array $path) : array
    {
        if (1 == count($path)) {
            $attribute = $path[0];
            unset($json[$attribute]);
            return $json;
        }
        array_shift($path);
        foreach ($json as $attr => $val) {
            if (is_array($val)) {
                $json[$attr] = self::remove($val, $path);
            } else {
                $json[$attr] = $val;
            }
        }
        return $json;
    }
    public static function additionalProperties(array $json, bool $allowAdditionalProperties) {
        if (isset($json['properties'])) {
            $json['additionalProperties'] = $allowAdditionalProperties;
        }
        foreach ($json as $key => &$value) {
            if (is_array($value)) {
                $value = self::additionalProperties($value,$allowAdditionalProperties);
            }
        }
        return $json;
    }
}
