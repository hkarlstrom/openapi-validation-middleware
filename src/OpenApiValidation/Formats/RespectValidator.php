<?php

/**
 * OpenAPI Validation Middleware.
 *
 * @see       https://github.com/hkarlstrom/openapi-validation-middleware
 *
 * @copyright Copyright (c) 2018 Henrik Karlström
 * @license   MIT
 */

namespace HKarlstrom\Middleware\OpenApiValidation\Formats;

use Exception;
use Opis\JsonSchema\IFormat;
use Respect\Validation\Validator as v;

class RespectValidator implements IFormat
{
    private $validator;
    private $args;

    public function __construct(string $format)
    {
        $parsed          = $this->parseFormat($format);
        $this->validator = $parsed['validator'];
        $this->args      = $parsed['args'];
        if (!class_exists('\Respect\Validation\Rules\\'.mb_strtoupper($this->validator))) {
            throw new Exception(sprintf(
                "Respect\Validation\Validator '%s' not found.",
                mb_strtoupper($this->validator)
            ));
        }
    }

    public function validate($data) : bool
    {
        $v = v::{$this->validator}(...$this->args);
        return $v->validate($data);
    }

    private function parseFormat(string $format)
    {
        $matches = [];
        $pattern = '/(?<function>[a-zA-Z\-]+)(\((?<arguments>(?>[^()]++|(?2))*)\))*/';
        preg_match_all($pattern, $format, $matches);
        $validator = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $matches['function'][0] ?? ''))));
        $args      = [];

        foreach (explode(',', $matches['arguments'][0]) as $arg) {
            $arg = trim($arg, "'");
            if ('true' == $arg) {
                $arg = true;
            } elseif ('false' == $arg) {
                $arg = false;
            } elseif (is_numeric($arg)) {
                $arg = false !== mb_strpos($arg, '.') ? floatval($arg) : intval($arg);
            }
            $args[] = $arg;
        }
        return ['validator' => $validator, 'args' => $args];
    }
}
