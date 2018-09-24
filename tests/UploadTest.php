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

class UploadTest extends TestCase
{
    protected $openapiFile = __DIR__.'/testapi.json';

    public function testUpload()
    {
        $response = $this->response('post', '/upload', [
            'formData' => [
                'id' => 100,
            ],
            'upload' => [
                'paramName' => 'file',
            ],
        ]);
        $json = $this->json($response);
        $this->assertTrue($json['ok']);
    }

    public function testUploadErrors()
    {
        $response = $this->response('post', '/upload', [
            'formData' => [
                'id' => 100,
            ],
            'upload' => [
                'paramName' => 'file',
                'type'      => 'image/png',
            ],
        ]);
        $json = $this->json($response);
        $this->assertSame('error_content_type', $json['errors'][0]['code']);
    }

    protected function json(ResponseInterface $response) : array
    {
        $response->getBody()->rewind();
        return json_decode($response->getBody()->getContents(), true);
    }

    protected function response($method, $path, array $args = []) : ResponseInterface
    {
        unset($_FILES);
        if (isset($args['upload'])) {
            $_FILES = [$args['upload']['paramName'] ?? 'fileName' => [
                'name'     => $args['upload']['name'] ?? 'numbers.txt',
                'type'     => $args['upload']['type'] ?? 'text/plain',
                'tmp_name' => $args['upload']['tmp_name'] ?? __DIR__.'/numbers.txt',
                'error'    => $args['upload']['error'] ?? 0,
                'size'     => $args['upload']['size'] ?? 10,
            ]];
        }
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
        if (isset($args['body'])) {
            $request->getBody()->write(json_encode($args['body']));
            $request->getBody()->rewind();
            $request = $request->withHeader('Content-Type', 'application/json');
        } elseif (isset($args['formData'])) {
            $request->getBody()->write(http_build_query($args['formData'] ?? []));
            $request->getBody()->rewind();
            $request = $request->withHeader('Content-Type', 'multipart/form-data');
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
