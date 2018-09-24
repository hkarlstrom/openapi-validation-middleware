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

class RequestBodyTest extends BaseTest
{
    public function testRequestBody()
    {
        $response = $this->response('post', '/request/body', [
            'body' => [
                'foo'    => 'test',
                'bar'    => 123,
                'person' => [
                    'name'  => 'Donald',
                    'email' => 'aaa@aaa.com',
                ],
            ],
        ]);
        $json = $this->json($response);
        $this->assertTrue($json['ok']);
        $this->assertSame(200, $response->getStatusCode());

        $response = $this->response('post', '/request/body', [
            'body' => [
                'foo'    => 123,
                'bar'    => 'test',
                'person' => [
                    'email' => 'aaaaa.com',
                    'extra' => 'hmm',
                ],
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
    }
}
