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

use Psr\Http\Message\ServerRequestInterface;

class BeforeHandlerTest extends BaseTest
{
    public function testBeforeHandler()
    {
        $response = $this->response('get', '/parameters', [
            'options' => [
                'beforeHandler' => function (ServerRequestInterface $request, array $errors) : ServerRequestInterface {
                    return $request->withAttribute('error', $errors[0]['code']);
                },
            ],
            'customHandler' => function ($request, $response) {
                $response->getBody()->write(json_encode(['ok' => true]));
                $response = $response->withHeader('Content-type', 'application/json');
                $response = $response->withHeader('X-ERROR', $request->getAttribute('error'));
                return $response;
            },
        ]);
        $json = $this->json($response);
        $this->assertTrue($json['ok']);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('error_required', $response->getHeader('X-ERROR')[0]);
    }
}
