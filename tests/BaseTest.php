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
use Slim\Http\Response;

abstract class BaseTest extends TestCase
{
    protected $openapiFile = __DIR__.'/testapi.json';

    protected function json(ResponseInterface $response) : array
    {
        $response->getBody()->rewind();
        return json_decode($response->getBody()->getContents(), true);
    }

    protected function response(string $method, string $uri, array $args = []) : ResponseInterface
    {
        $env = Environment::mock([
            'REQUEST_METHOD' => $method,
            'REQUEST_URI'    => $uri,
            'QUERY_STRING'   => http_build_query($args['query'] ?? []),
            'SERVER_NAME'    => 'test.com',
            'CONTENT_TYPE'   => 'application/json;charset=utf8',
        ]);
        $request = Request::createFromEnvironment($env);
        if (isset($args['body'])) {
            if (is_array($args['body'])) {
                $request->getBody()->write(json_encode($args['body']));
            } else {
                $request->getBody()->write($args['body']);
            }
            $request->getBody()->rewind();
            $request = $request->withHeader('Content-Type', $args['bodyContentType'] ?? 'application/json');
        }
        if (isset($args['cors'])) {
            $request = $request->withHeader('Access-Control-Request-Method', 'GET');
        }

        if (isset($args['headers'])) {
            foreach ($args['headers'] as $header => $value) {
                $request = $request->withHeader($header, $value);
            }
        }
        $app               = new App(['request' => $request, 'settings' => ['determineRouteBeforeAppMiddleware' => true]]);
        $c                 = $app->getContainer();
        $c['errorHandler'] = function ($c) {
            return function ($request, $response, $exception) use ($c) {
                throw $exception;
            };
        };
        if ($args['emptyHandler'] ?? false) {
            $callback = function ($request, $response) {
            };
        } elseif ($args['customHandler'] ?? false) {
            $callback = $args['customHandler'];
        } else {
            $callback = function ($request, $response) : ResponseInterface {
                return $response->withJson(['ok' => true]);
            };
        }
        $app->map([$method], '[/{params:.*}]', $callback);
        $mw = new \HKarlstrom\Middleware\OpenApiValidation($this->openapiFile, $args['options'] ?? []);
        foreach ($args['formats'] ?? [] as $f) {
            $mw->addFormat($f[0], $f[1], $f[2]);
        }
        $app->add($mw);

        return $app->run(true);
    }
}
