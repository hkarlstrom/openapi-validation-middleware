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
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

class TextResponseException extends \Exception
{
}

abstract class BaseTest extends TestCase
{
    protected $openapiFile = __DIR__.'/testapi.json';

    protected function json(Response $response) : array
    {
        $response->getBody()->rewind();
        return json_decode($response->getBody()->getContents(), true);
    }

    protected function response(string $method, string $uri, array $args = []) : Response
    {
        $app = AppFactory::create();

        // $request = new TestRequest([
        //     'REQUEST_METHOD' => $method,
        //     'CONTENT_TYPE'   => 'application/json;charset=utf8',
        // ]);

        $uri = new \Slim\Psr7\Uri('http', 'test.com', '80', $uri, http_build_query($args['query'] ?? []));

        $serverRequestFactory = new \Slim\Psr7\Factory\ServerRequestFactory();

        $headerArgs = $args['headers'] ?? [];
        if (!isset($headerArgs['Content-type'])) {
            $headerArgs['Content-type'] = 'application/json;charset=utf8';
        }

        $headers = new \Slim\Psr7\Headers($headerArgs);

        $cookies = [];

        if (isset($args['body'])) {
            $body = (new \Slim\Psr7\Factory\StreamFactory())->createStream(is_array($args['body']) ? json_encode($args['body']) : $args['body']);
        } else {
            $cacheResource = fopen('php://temp', 'wb+');
            $cache         = $cacheResource ? new \Slim\Psr7\Stream($cacheResource) : null;
            $body          = (new \Slim\Psr7\Factory\StreamFactory())->createStreamFromFile('php://input', 'w+', $cache);
        }
        $uploadedFiles = [];

        $request = new \Slim\Psr7\Request($method, $uri, $headers, $cookies, $_SERVER, $body, $uploadedFiles);

        // $method,
        // UriInterface $uri,
        // HeadersInterface $headers,
        // array $cookies,
        // array $serverParams,
        // StreamInterface $body,
        // array $uploadedFiles = []

        // $contentTypes = $request->getHeader('Content-Type');

        // $parsedContentType = '';
        // foreach ($contentTypes as $contentType) {
        //     $fragments = explode(';', $contentType);
        //     $parsedContentType = current($fragments);
        // }

        // $contentTypesWithParsedBodies = ['application/x-www-form-urlencoded', 'multipart/form-data'];
        // if ($method === 'POST' && in_array($parsedContentType, $contentTypesWithParsedBodies)) {
        //     return $request->withParsedBody($_POST);
        // }

        // $c                 = $app->getContainer();
        // $c['errorHandler'] = function ($c) {
        //     return function ($request, $response, $exception) use ($c) {
        //         throw $exception;
        //     };
        // };

        if ($args['emptyHandler'] ?? false) {
            $callback = function ($request, $response) {
                return $response;
            };
        } elseif ($args['customHandler'] ?? false) {
            $callback = $args['customHandler'];
        } else {
            $callback = function ($request, $response) : Response {
                $response->getBody()->write(json_encode(['ok' => true]));
                return $response->withHeader('Content-type', 'application/json');
            };
        }
        $app->map([$method], '[/{params:.*}]', $callback);
        $mw = new \HKarlstrom\Middleware\OpenApiValidation($this->openapiFile, $args['options'] ?? []);
        foreach ($args['formats'] ?? [] as $f) {
            $mw->addFormat($f[0], $f[1], $f[2]);
        }
        $app->add($mw);

        $app->addBodyParsingMiddleware();
        $response = null;
        $app->add(function (\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Server\RequestHandlerInterface $handler) use (&$response) {
            $response = $handler->handle($request);
            throw new TextResponseException('stop');
            return $response;
        });

        if (isset($args['cors'])) {
            $request = $request->withHeader('Access-Control-Request-Method', 'GET');
        } try {
            $app->run($request);
        } catch (TextResponseException $e) {
            return $response;
        }
    }
}
