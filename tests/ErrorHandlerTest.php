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
use Slim\Http\Response as SlimResponse;

class ErrorHandlerTest extends BaseTest
{
    public function testErrorHandler()
    {
        $response = $this->response('get', '/parameters', ['options' => [
            'errorHandler' => function (int $code, string $message, array $errors) : ResponseInterface {
                $response = new SlimResponse($code);
                $response = $response->withJson([
                    'message' => 'custom error',
                    'errors'  => $errors,
                ]);
                return $response;
            },
        ]]);
        $json = $this->json($response);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('custom error', $json['message']);
        $error = $json['errors'][0];
        $this->assertSame('foo', $error['name']);
        $this->assertSame('error_required', $error['code']);
    }
}
