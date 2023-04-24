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

use Psr\Http\Message\ResponseInterface;

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
        $this->assertSame(200, $response->getStatusCode());
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
        $this->assertFalse(isset($json['extra']));
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
        $this->assertSame('X-Response-Id', $error['name']);
        $this->assertSame('error_required', $error['code']);
        $this->assertSame('header', $error['in']);
    }

    public function testResponseInvalidHeaderFormat()
    {
        $response = $this->response('get', '/missing/header', [
            'options' => [
                'validateResponseHeaders' => true,
            ],
            'customHandler' => function ($request, ResponseInterface $response) {
                $response->getBody()->write(json_encode(['ok' => true]));
                return $response
                    ->withHeader('X-Response-Id', 'foo')
                    ->withHeader('Content-type', 'application/json');
            },
        ]);
        $this->assertSame(500, $response->getStatusCode());
        $error = $this->json($response)['errors'][0];
        $this->assertSame('X-Response-Id', $error['name']);
        $this->assertSame('error_type', $error['code']);
        $this->assertSame('integer', $error['expected']);
        $this->assertSame('string', $error['used']);
        $this->assertSame('header', $error['in']);
    }

    // public function testResponseWithNullableBodyAttributes()
    // {
    //     $response = $this->response('get', '/response/nullable', [
    //         'customHandler' => function ($request, ResponseInterface $response) {
    //             $response->getBody()->write(json_encode(['ok' => true]));
    //             return $response->withHeader('Content-type', 'application/json');
    //         },
    //     ]);
    //     $json = $this->json($response);
    //     $this->assertSame(200, $response->getStatusCode());
    //     $this->assertSame(null, $json['ok']);
    // }

    public function testResponsesWithAnyOfBodyAttribute()
    {
        $response = $this->response('get', '/response/any-of', [
            'customHandler' => function ($request, ResponseInterface $response) {
                $response->getBody()->write(json_encode(['value' => 15]));
                return $response->withHeader('Content-type', 'application/json');
            },
        ]);
        $json = $this->json($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(15, $json['value']);
    }
}
