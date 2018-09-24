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

class CorsTest extends BaseTest
{
    public function testCorsRequest()
    {
        $response = $this->response('options', '/does/not/exist', [
            'cors' => true,
        ]);
        $json = $this->json($response);
        $this->assertTrue($json['ok']);
        $this->assertSame(200, $response->getStatusCode());
    }
}
