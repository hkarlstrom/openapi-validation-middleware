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

// class BaseFormTest extends TestCase
// {
//     protected $openapiFile = __DIR__.'/testapi.json';

//     public function testFormData()
//     {
//         $response = $this->response('post', '/form-data', [
//             'formData' => [
//                 'id' => 100,
//                 'text' => 'somestring',
//                 'array' => [
//                     ['name' => 'test', 'value' => 'test2'],
//                     ['name' => 'test', 'value' => 'test2'],
//                 ],
//                 'object' => [
//                     'name' => 'test',
//                     'value' => 'test2',
//                 ]
//             ],
//         ]);
//         $json = $this->json($response);
//         $this->assertTrue($json['ok']);
//     }

//     public function providerFormDataErrors()
//     {
//         return [
//             // id: invalid type (expected: integer)
//             'invalid_type' => [
//                 [
//                     'id' => 'invalid-type',
//                     'text' => 'somestring',
//                     'array' => [
//                         ['name' => 'test', 'value' => 'test2'],
//                         ['name' => 'test', 'value' => 'test2'],
//                     ],
//                     'object' => [
//                         'name' => 'test',
//                         'value' => 'test2',
//                     ]
//                 ],
//                 [
//                     'message' => 'Request validation failed',
//                     'errors' => [
//                         [
//                             'name' => 'id',
//                             'code' => 'error_type',
//                             'value' => 'invalid-type',
//                             'in' => 'form-data',
//                             'expected' => 'integer',
//                             'used' => 'string',
//                         ],
//                     ],
//                 ],
//             ],
//             // text: missing
//             'text_missing' => [
//                 [
//                     'id' => 100,
//                     'array' => [
//                         ['name' => 'test', 'value' => 'test2'],
//                         ['name' => 'test', 'value' => 'test2'],
//                     ],
//                     'object' => [
//                         'name' => 'test',
//                         'value' => 'test2',
//                     ]
//                 ],
//                 [
//                     'message' => 'Request validation failed',
//                     'errors' => [
//                         [
//                             'name' => 'text',
//                             'code' => 'error_required',
//                             'in' => 'form-data',
//                         ],
//                     ],
//                 ],
//             ],
//             // object: missing value property
//             'object_no_property' => [
//                 [
//                     'id' => 100,
//                     'text' => 'somestring',
//                     'array' => [
//                         ['name' => 'test', 'value' => 'test2'],
//                         ['name' => 'test', 'value' => 'test2'],
//                     ],
//                     'object' => [
//                         'name' => 'test',
//                     ]
//                 ],
//                 [
//                     'message' => 'Request validation failed',
//                     'errors' => [
//                         [
//                             'name' => 'object.value',
//                             'code' => 'error_required',
//                             'in' => 'form-data',
//                         ],
//                     ],
//                 ],
//             ],
//             // array: not matching minItems validation
//             'array_to_few_items' => [
//                 [
//                     'id' => 100,
//                     'text' => 'somestring',
//                     'array' => [
//                         ['name' => 'test', 'value' => 'test2'],
//                     ],
//                     'object' => [
//                         'name' => 'test',
//                         'value' => 'test2',
//                     ]
//                 ],
//                 [
//                     'message' => 'Request validation failed',
//                     'errors' => [
//                         [
//                             'name' => 'array',
//                             'code' => 'error_minItems',
//                             'value' => [
//                                 ['name' => 'test', 'value' => 'test2'],
//                             ],
//                             'in' => 'form-data',
//                             'min' => 2,
//                             'count' => 1,
//                         ],
//                     ],
//                 ],
//             ],
//         ];
//     }

//     /**
//      * @dataProvider providerFormDataErrors
//      */
//     public function testFormDataErrors(array $formData, array $expectedResponse)
//     {
//         $response = $this->response('post', '/form-data', [
//             'formData' => $formData,
//         ]);

//         $json = $this->json($response);

//         $this->assertSame(400, $response->getStatusCode());
//         $this->assertSame($expectedResponse, $json);
//     }

//     protected function json(ResponseInterface $response) : array
//     {
//         $response->getBody()->rewind();
//         return json_decode($response->getBody()->getContents(), true);
//     }

//     protected function response($method, $path, array $args = []) : ResponseInterface
//     {
//         $uri = $path;
//         foreach ($args['path'] ?? [] as $var => $val) {
//             $uri = str_replace('{'.$var.'}', $val, $uri);
//         }
//         $env = Environment::mock([
//             'REQUEST_METHOD' => $method,
//             'REQUEST_URI'    => $uri,
//             'QUERY_STRING'   => http_build_query($args['query'] ?? []),
//             'SERVER_NAME'    => 'test.com',
//             'CONTENT_TYPE'   => 'application/json;charset=utf8',
//         ]);
//         $request = Request::createFromEnvironment($env);
//         if (isset($args['formData'])) {
//             $request->getBody()->write(http_build_query($args['formData'] ?? []));
//             $request->getBody()->rewind();
//             $request = $request->withHeader('Content-Type', 'application/x-www-form-urlencoded');
//         }
//         $app = new App(['request' => $request]);

//         $callback = function ($req, $res) {
//             return $res->withJson(['ok' => true]);
//         };

//         $app->map([$method], $path, $callback);

//         $app->map([$method], '[/{params:.*}]', $callback);
//         $mw = new \HKarlstrom\Middleware\OpenApiValidation($this->openapiFile, $args['options'] ?? []);
//         $app->add($mw);

//         return $app->run(true);
//     }
// }
