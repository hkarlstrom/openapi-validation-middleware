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
        $excluded = [
            'date',
            'date-time',
            'email',
            'idn-email',
            'hostname',
            'idn-hostname',
            'ipv4',
            'ipv6',
            'json-pointer',
            'regex',
            'relative-json-pointer',
            'time',
            'uri',
            'uri-reference',
            'uri-template',
            'iri',
            'iri-reference',
        ];
        $formats  = [];
        $callback = function (array $object, array &$formats) use (&$callback, $excluded) {
            $type   = $object['type']   ?? null;
            $format = $object['format'] ?? null;
            if (null !== $format && ('string' != $type || !in_array($format, $excluded))) {
                $found = false;
                foreach ($formats as $f) {
                    if ($f['type'] == $type && $f['format'] == $format) {
                        $found = true;
                    }
                }
                if (!$found) {
                    $formats[] = [
                        'type'   => $object['type'],
                        'format' => $object['format'],
                    ];
                }
            }
            foreach ($object as $attr => $val) {
                if (is_array($val)) {
                    $callback($val, $formats);
                }
            }
        };
        $callback($schema, $formats);
        return $formats;
    }

    public static function isNullable(array $schema, array $path) : bool
    {
        return true;
    }
}
