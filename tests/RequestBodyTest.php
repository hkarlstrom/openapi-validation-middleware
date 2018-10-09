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

use Opis\JsonSchema\IFormat;

class CustomFormat2 implements IFormat
{
    public function validate($data) : bool
    {
        return 'OK' === $data;
    }
}

class RequestBodyTest extends BaseTest
{
    public function testRequestBody()
    {
        $response = $this->response('post', '/request/body', [
            'formats' => [
                ['string', 'customFormat', new CustomFormat2()],
            ],
            'body' => [
                'foo'    => 'test',
                'bar'    => 123,
                'person' => [
                    'name'  => 'Donald',
                    'email' => 'aaa@aaa.com',
                ],
                'custom' => 'OK',
            ],
        ]);
        $json = $this->json($response);
        $this->assertTrue($json['ok']);
        $this->assertSame(200, $response->getStatusCode());

        $response = $this->response('post', '/request/body', [
            'formats' => [
                ['string', 'customFormat', new CustomFormat2()],
            ],
            'body' => [
                'foo'    => 123,
                'bar'    => 'test',
                'person' => [
                    'email' => 'aaaaa.com',
                    'extra' => 'hmm',
                ],
                'custom' => 'NOT',
            ],
        ]);
        $json = $this->json($response);
        $this->assertSame(400, $response->getStatusCode());
        $json = $this->json($response);

        $errors = $json['errors'];
        $this->assertSame('error_type', $errors[0]['code']);
        $this->assertSame('error_type', $errors[1]['code']);
        $this->assertSame('error_required', $errors[2]['code']);
        $this->assertSame('error_format', $errors[3]['code']);
        $this->assertSame('error_additional', $errors[4]['code']);
        $this->assertSame('error_format', $errors[5]['code']);
        $this->assertSame('customFormat', $errors[5]['format']);
    }

    public function testEmptyRequestBody()
    {
        $response = $this->response('post', '/request/body/empty');
        $json     = $this->json($response);
        $this->assertTrue($json['ok']);
        $this->assertSame(200, $response->getStatusCode());
    }
}
