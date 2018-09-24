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

class Schema
{
    public static function addRecursive(array $schema, string $attribute, $value) : array
    {
        if (($schema['type'] ?? null) === 'object') {
            $schema[$attribute] = $value;
        }
        foreach ($schema as $attr => $val) {
            if (is_array($val)) {
                $schema[$attr] = self::addRecursive($schema[$attr], $attribute, $value);
            }
        }
        return $schema;
    }

    public static function getFormats(array $schema) : array
    {
        $formats  = [];
        $callback = function (array $schema, array &$formats) use (&$callback) : array {
            if (isset($object['format']) && is_string($object['format'])) {
                $formats[] = [
                    'type'   => $object['type'],
                    'format' => $object['format'],
                ];
            }
            foreach ($object as $attr => $val) {
                if (is_array($val)) {
                    $callback($val, $formats);
                }
            }
        };
        return $formats;
    }

    public static function isNullable(array $schema, array $path) : bool
    {
        return true;
    }
}
