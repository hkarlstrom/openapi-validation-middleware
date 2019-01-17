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
use Psr\Http\Message\ServerRequestInterface;

class ExceptionsTest extends BaseTest
{
    public function testFileNotExist()
    {
        try {
            $mw = new OpenApiValidation('not_a_file.json');
        } catch (\HKarlstrom\Middleware\OpenApiValidation\Exception\FileNotFoundException $e) {
            $this->assertSame('not_a_file.json', $e->filename());
        }
    }

    public function testInvalidOption()
    {
        try {
            $mw = new OpenApiValidation($this->openapiFile, ['invalidOption' => true]);
        } catch (\HKarlstrom\Middleware\OpenApiValidation\Exception\InvalidOptionException $e) {
            $this->assertSame('invalidOption', $e->option());
        }
    }

    public function testPathNotFound()
    {
        try {
            $response = $this->response('get', '/not/defined');
        } catch (\HKarlstrom\Middleware\OpenApiValidation\Exception\PathNotFoundException $e) {
            $this->assertSame('GET', $e->method());
            $this->assertSame('/not/defined', $e->path());
        }
    }

    public function testInvalidBeforeHandlerReturnValue()
    {
        try {
            $response = $this->response('get', '/parameters', [
                'options' => [
                    'beforeHandler' => function (ServerRequestInterface $request, array $errors) {
                        return 'no';
                    },
                ],
            ]);
        } catch (\HKarlstrom\Middleware\OpenApiValidation\Exception\BeforeHandlerException $e) {
            $this->assertSame('string', $e->type());
        }
    }

    public function testFormatMissingException()
    {
        $this->expectException('Exception');
        try {
            $response = $this->response('get', '/missing/format', ['query' => ['test' => 'foo']]);
        } catch (\HKarlstrom\Middleware\OpenApiValidation\Exception\PathNotFoundException $e) {
            $this->assertSame('string', $e->type());
            $this->assertSame('uid', $e->format());
        }
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
