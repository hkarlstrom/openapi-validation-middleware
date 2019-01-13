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

class ResponsesTest extends BaseTest
{
    public function testExampleResponse()
    {
        $response = $this->response('get', '/response/example', [
            'emptyHandler' => true,
            'options'      => [
                'exampleResponse' => true,
            ],
        ]);
        $this->assertSame(500, $response->getStatusCode());
        $error = $this->json($response)['errors'][0];
        $this->assertSame('extra', $error['name']);
        $this->assertSame('error_additional', $error['code']);
    }

    public function testExampleResponsePost()
    {
        $response = $this->response('post', '/response/example', [
            'emptyHandler' => true,
            'body'         => ['foo' => 'bar'],
            'options'      => [
                'exampleResponse' => true,
            ],
        ]);
        $this->assertSame(201, $response->getStatusCode());
        $json = $this->json($response);
        $this->assertSame('bar', $json['foo']);
        $this->assertSame(100, $json['bar']);
    }

    public function testExampleResponseList()
    {
        $response = $this->response('get', '/response/example/list', [
            'emptyHandler' => true,
            'options'      => [
                'exampleResponse' => true,
                'stripResponse'   => true,
            ],
        ]);
        $json = $this->json($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('test', $json[0]['foo']);
        $this->assertSame(100, $json[0]['bar']);
    }

    public function testStrip()
    {
        $response = $this->response('get', '/response/example', [
            'emptyHandler' => true,
            'options'      => [
                'exampleResponse' => true,
                'stripResponse'   => true,
            ],
        ]);
        $json = $this->json($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('test', $json['foo']);
        $this->assertSame(100, $json['bar']);
    }

    public function testResponseEmpty()
    {
        $response = $this->response('get', '/response/example', [
            'emptyHandler' => true,
        ]);
        $this->assertSame(500, $response->getStatusCode());
        $error = $this->json($response)['errors'][0];
        $this->assertSame('responseBody', $error['name']);
        $this->assertSame('error_required', $error['code']);
    }

    public function testResponseMissedHeader()
    {
        $response = $this->response('get', '/missing/header', [
            'options' => [
                'validateResponseHeaders' => true,
            ],
        ]);
        $this->assertSame(500, $response->getStatusCode());
        $error = $this->json($response)['errors'][0];
        $this->assertSame('responseHeader', $error['name']);
        $this->assertSame('error_required', $error['code']);
        $this->assertSame('X-Response-Id', $error['message']);
    }
}
