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

use Opis\JsonSchema\IFormat;

class PasswordValidator implements IFormat
{
    public function validate($data) : bool
    {
        return is_string($data);
    }
}
