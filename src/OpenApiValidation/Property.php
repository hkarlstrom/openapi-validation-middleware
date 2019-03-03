<?php

/**
 * OpenAPI Validation Middleware.
 *
 * @see       https://github.com/hkarlstrom/openapi-validation-middleware
 *
 * @copyright Copyright (c) 2018 Henrik Karlström
 * @license   MIT
 */

namespace HKarlstrom\Middleware\OpenApiValidation;

use HKarlstrom\OpenApiReader\Objects\Header;
use HKarlstrom\OpenApiReader\Objects\Parameter;

class Property
{
    public $name;
    public $in;
    public $required;
    public $schema;
    public $value;

    public function __construct(string $name, string $in, bool $required, $schema, $value = null)
    {
        $this->name     = $name;
        $this->in       = $in;
        $this->required = $required;
        $this->schema   = json_decode(json_encode($schema, JSON_PRESERVE_ZERO_FRACTION));
        $this->value    = $value ?? null;
        if (null == $this->value) {
            return;
        }
        if ('integer' == $this->schema->type && is_numeric($this->value)) {
            $this->value = intval($this->value);
        } elseif ('number' == $this->schema->type && is_numeric($this->value)) {
            $this->value = floatval($this->value);
        } elseif ('string' == $this->schema->type && !is_string($this->value)) {
            $this->value = (string) $this->value;
        }
    }

    public static function fromParameter(Parameter $parameter, $value)
    {
        return new self(
            $parameter->name,
            $parameter->in,
            $parameter->required ?? false,
            $parameter->schema,
            $value
        );
    }

    public static function fromHeader(string $name, Header $header, $value)
    {
        return new self(
            $name,
            'header',
            $header->required ?? false,
            $header->schema,
            $value
        );
    }
}
