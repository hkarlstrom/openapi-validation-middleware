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
        $this->assertSame('error_format', $errors[4]['code']);
        $this->assertSame('customFormat', $errors[4]['format']);
    }

    public function testEmptyRequestBody()
    {
        $response = $this->response('post', '/request/body/empty');
        $json     = $this->json($response);
        $this->assertTrue($json['ok']);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testAllOfComposition()
    {
        $response = $this->response('put', '/all/of', [
            'body' => [
                'id'          => 'a',
                'first_name'  => 'Jane',
                'last_name'   => 'Doe',
                'phone'       => '3333-11111111',
                'nationality' => 'IE',
            ],
        ]);
        $json = $this->json($response);
        $this->assertSame('e_mail', $json['errors'][0]['name']);
        $this->assertSame('error_required', $json['errors'][0]['code']);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('id', $json['errors'][1]['name']);
        $this->assertSame('string', $json['errors'][1]['used']);
        $this->assertSame('integer', $json['errors'][1]['expected']);
        $this->assertSame('error_type', $json['errors'][1]['code']);
    }
}
