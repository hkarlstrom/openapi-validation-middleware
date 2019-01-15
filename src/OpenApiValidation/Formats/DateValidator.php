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

class DateValidator implements IFormat
{
    public function validate($data) : bool
    {
        if (preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/', $data)) {
            $ymd = explode('-', $data);
            return checkdate($ymd[1], $ymd[2], $ymd[0]);
        }
        return false;
    }
}
