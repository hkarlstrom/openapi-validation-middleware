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

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Http\Environment;
use Slim\Http\Request;

class BaseFormTest extends TestCase
{
    protected $openapiFile = __DIR__.'/testapi.json';

    public function testFormData()
    {
        $response = $this->response('post', '/form-data', [
            'formData' => [
                'id' => 100,
                'text' => 'somestring',
                'array' => [
                    ['name' => 'test', 'value' => 'test2'],
                    ['name' => 'test', 'value' => 'test2'],
                ],
                'object' => [
                    'name' => 'test',
                    'value' => 'test2',
                ]
            ],
        ]);
        $json = $this->json($response);
        $this->assertTrue($json['ok']);
    }

    public function testFormDataErrors()
    {
        $response = $this->response('post', '/form-data', [
            'formData' => [
                'id' => 'invalid-type',
                'text' => 'somestring',
                'arr' => [
                    ['name' => 'test', 'value' => 'test2'],
                ],
                'object' => [
                    'name' => 'test',
                    'value' => 'test2',
                ]
            ],
        ]);
        // id: invalid type (expected: integer)
        // text: missing
        // object: missing value property
        // array: not matching minItems validation

        $json = $this->json($response);

        // TODO: Detailed response validation
        $this->assertSame(400, $response->getStatusCode());
    }

    protected function json(ResponseInterface $response) : array
    {
        $response->getBody()->rewind();
        return json_decode($response->getBody()->getContents(), true);
    }

    protected function response($method, $path, array $args = []) : ResponseInterface
    {
        $uri = $path;
        foreach ($args['path'] ?? [] as $var => $val) {
            $uri = str_replace('{'.$var.'}', $val, $uri);
        }
        $env = Environment::mock([
            'REQUEST_METHOD' => $method,
            'REQUEST_URI'    => $uri,
            'QUERY_STRING'   => http_build_query($args['query'] ?? []),
            'SERVER_NAME'    => 'test.com',
            'CONTENT_TYPE'   => 'application/json;charset=utf8',
        ]);
        $request = Request::createFromEnvironment($env);
        if (isset($args['formData'])) {
            $request->getBody()->write(http_build_query($args['formData'] ?? []));
            $request->getBody()->rewind();
            $request = $request->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        }
        $app = new App(['request' => $request]);

        $callback = function ($req, $res) {
            return $res->withJson(['ok' => true]);
        };

        $app->map([$method], $path, $callback);

        $app->map([$method], '[/{params:.*}]', $callback);
        $mw = new \HKarlstrom\Middleware\OpenApiValidation($this->openapiFile, $args['options'] ?? []);
        $app->add($mw);

        return $app->run(true);
    }
}
