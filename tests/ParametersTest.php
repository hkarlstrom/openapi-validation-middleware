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

use Opis\JsonSchema\Format;

class CustomFormat implements Format
{
    public function validate($data) : bool
    {
        return 'OK' === $data;
    }
}

class ParametersTest extends BaseTest
{
    public function testRequired()
    {
        $response = $this->response('get', '/parameters', []);
        $json     = $this->json($response);
        $error    = $json['errors'][0];
        $this->assertSame('foo', $error['name']);
        $this->assertSame('error_required', $error['code']);

        $response = $this->response('get', '/parameters', ['query' => ['foo' => 'aaa']]);
        $json     = $this->json($response);
        $this->assertTrue($json['ok']);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testEnum()
    {
        $response = $this->response('get', '/parameters', ['query' => ['foo' => 'ccc']]);
        $json     = $this->json($response);
        $this->assertSame(400, $response->getStatusCode());
        $error = $json['errors'][0];
        $this->assertSame('foo', $error['name']);
        $this->assertSame('query', $error['in']);
        $this->assertSame('error_enum', $error['code']);
        $this->assertSame(['aaa', 'bbb'], $error['expected']);
    }

    public function testQueryBoolean()
    {
        $response = $this->response('get', '/parameters', ['query' => ['boolean' => 'true', 'foo' => 'aaa']]);
        $json     = $this->json($response);
        $this->assertSame(200, $response->getStatusCode());

        $response = $this->response('get', '/parameters', ['query' => ['boolean' => 'TrUe', 'foo' => 'aaa']]);
        $json     = $this->json($response);
        $this->assertSame(200, $response->getStatusCode());

        $response = $this->response('get', '/parameters', ['query' => ['boolean' => 'false', 'foo' => 'aaa']]);
        $json     = $this->json($response);
        $this->assertSame(200, $response->getStatusCode());

        $response = $this->response('get', '/parameters', ['query' => ['boolean' => 0, 'foo' => 'aaa']]);
        $json     = $this->json($response);
        $this->assertSame(200, $response->getStatusCode());

        $response = $this->response('get', '/parameters', ['query' => ['boolean' => 1, 'foo' => 'aaa']]);
        $json     = $this->json($response);
        $this->assertSame(200, $response->getStatusCode());

        $response = $this->response('get', '/parameters', ['query' => ['boolean' => 3, 'foo' => 'aaa']]);
        $json     = $this->json($response);
        $this->assertSame(400, $response->getStatusCode());
        $error = $json['errors'][0];
        $this->assertSame('boolean', $error['expected']);
        $this->assertSame('3', $error['value']);

        $response = $this->response('get', '/parameters', ['query' => ['boolean' => 'hello', 'foo' => 'aaa']]);
        $json     = $this->json($response);
        $error = $json['errors'][0];
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('boolean', $error['expected']);
        $this->assertSame('hello', $error['value']);
    }

    public function testAdditional()
    {
        $response = $this->response('get', '/parameters', ['query' => ['foo' => 'aaa', 'bar' => 'aaa']]);
        $this->assertSame(400, $response->getStatusCode());
        $json  = $this->json($response);
        $error = $json['errors'][0];
        $this->assertSame('bar', $error['name']);
        $this->assertSame('error_additional', $error['code']);
        $response = $this->response('get', '/parameters', [
            'options' => ['additionalParameters' => true],
            'query'   => ['foo' => 'aaa', 'bar' => 'aaa'],
        ]);
        $json = $this->json($response);
        $this->assertTrue($json['ok']);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testFormats()
    {
        $response = $this->response('get', '/formats', [
            'formats' => [
                ['string', 'customFormat', new CustomFormat()],
            ],
            'query' => [
                'string'       => 'test',
                'integer'      => 10,
                'phone'        => '+358501234567',
                'email'        => 'foo@bar.com',
                'between'      => 15,
                'country-code' => 'FI',
                'customFormat' => 'OK',
            ],
        ]);
        $json = $this->json($response);
        $this->assertTrue($json['ok']);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testDate()
    {
        $response = $this->response('get', '/formats', [
            'formats' => [
                ['string', 'customFormat', new CustomFormat()],
            ],
            'query' => [
                'date' => '2014-12-23',
            ],
        ]);
        $json = $this->json($response);
        $this->assertTrue($json['ok']);
        $this->assertSame(200, $response->getStatusCode());

        $response = $this->response('get', '/formats', [
            'formats' => [
                ['string', 'customFormat', new CustomFormat()],
            ],
            'query' => [
                'date' => '2014-02-31',
            ],
        ]);
        $json = $this->json($response);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testSetDefault()
    {
        $args = [
            'query' => [
                'foo' => 'aaa',
            ],
            'options' => [
                'setDefaultParameters' => false,
            ],
            'customHandler' => function ($request, $response) {
                $query = $request->getQueryParams();
                return $response->withJson(['ok' => 50 === ($query['default'] ?? null)]);
            },
        ];
        $response = $this->response('get', '/parameters', $args);
        $res      = $this->json($response);
        $this->assertFalse($res['ok']);

        $args['options']['setDefaultParameters'] = true;
        $response                                = $this->response('get', '/parameters', $args);
        $res                                     = $this->json($response);
        $this->assertTrue($res['ok']);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPath()
    {
        $response = $this->response('get', '/path/100/path/200');
        $json     = $this->json($response);
        $this->assertSame(200, $response->getStatusCode());

        $response = $this->response('get', '/path/100/path/string');
        $json     = $this->json($response);
        $this->assertSame(400, $response->getStatusCode());
        $json  = $this->json($response);
        $error = $json['errors'][0];
        $this->assertSame('path', $error['in']);
    }

    public function testStyle()
    {
        $response = $this->response('get', '/parameters', ['query' => ['foo' => 'aaa', 'list' => 'item1,item3']]);
        $json     = $this->json($response);
        $this->assertSame('item3', $json['errors'][0]['value']);

        $response = $this->response('get', '/parameters', ['query' => ['foo' => 'aaa', 'listPipe' => 'item1|item2']]);
        $json     = $this->json($response);
        $this->assertTrue($json['ok']);
    }

    public function testStyleDeepObject() : void
    {
        $response = $this->response('get', '/parameters', ['query' => ['foo' => 'aaa', 'filter' => ['ids' => [1, 'aaa', 2]]]]);
        $json     = $this->json($response);
        $error    = $json['errors'][0];
        $this->assertSame('filter.ids.1', $error['name']);
        $this->assertSame('integer', $error['expected']);
        $this->assertSame('string', $error['used']);
        $this->assertSame('aaa', $error['value']);
    }
}
