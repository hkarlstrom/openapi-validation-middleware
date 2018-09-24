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

use HKarlstrom\Middleware\OpenApiValidation;

class ExceptionsTest extends BaseTest
{
    public function testFileNotExist()
    {
        $this->expectException('Exception');
        $mw = new OpenApiValidation('not_a_file.json');
    }

    public function testInvalidOption()
    {
        $this->expectException('Exception');
        $mw = new OpenApiValidation($this->openapiFile, ['invalidOption' => true]);
    }

    public function testPathNotFound()
    {
        $this->expectException('Exception');
        $response = $this->response('get', '/not/defined');
    }

    public function testFormatMissingException()
    {
        $this->expectException('Exception');
        $response = $this->response('get', '/missing/format', ['query' => ['test' => 'foo']]);
    }

    public function testPathNotFoundNoException()
    {
        $response = $this->response('get', '/not/defined', [
            'options' => ['pathNotFoundException' => false],
        ]);
        $json = $this->json($response);
        $this->assertTrue($json['ok']);
    }

    public function testFormatMissingExceptionNoException()
    {
        $response = $this->response('get', '/missing/format', [
            'options' => ['missingFormatException' => false],
            'query'   => ['test' => 'foo'],
        ]);
        $json = $this->json($response);
        $this->assertTrue($json['ok']);
    }
}
