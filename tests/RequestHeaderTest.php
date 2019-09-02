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

class RequestHeaderTest extends BaseTest
{
    public function testHeaderRequiredMissing()
    {
        $response = $this->response('get', '/headers', []);
        $json = $this->json($response);
        $error = $json['errors'][0];
        $this->assertSame('X-Required', $error['name']);
        $this->assertSame('error_required', $error['code']);
        $this->assertSame('header', $error['in']);
    }

    public function testHeaderRequiredInvalid()
    {
        $options = [
            'headers' => [
                'X-Required' => "999999"
            ]
        ];

        $response = $this->response('get', '/headers', $options);
        $json = $this->json($response);

        $error = $json['errors'][0];
        $this->assertSame('X-Required', $error['name']);
        $this->assertSame('error_pattern', $error['code']);
        $this->assertSame('header', $error['in']);
    }

    public function testHeaderRequiredValid()
    {
        $options = [
            'headers' => [
                'X-Required' => "TST"
            ]
        ];

        $response = $this->response('get', '/headers', $options);
        $json = $this->json($response);
        $this->assertTrue($json['ok']);
        $this->assertSame(200, $response->getStatusCode());
    }
}
