<?php

/**
 * OpenAPI Validation Middleware.
 *
 * @see       https://github.com/hkarlstrom/openapi-validation-middleware
 *
 * @copyright Copyright (c) 2018 Henrik Karlström
 * @license   MIT
 */

namespace HKarlstrom\Middleware;

use Exception;
use HKarlstrom\Middleware\OpenApiValidation\Helpers\Json as JsonHelper;
use HKarlstrom\Middleware\OpenApiValidation\Helpers\Schema as SchemaHelper;
use HKarlstrom\Middleware\OpenApiValidation\Property;
use HKarlstrom\OpenApiReader\OpenApiReader;
use Opis\JsonSchema\FormatContainer;
use Opis\JsonSchema\ValidationError;
use Opis\JsonSchema\Validator;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tuupola\Http\Factory\ResponseFactory;
use Tuupola\Http\Factory\StreamFactory;
use Tuupola\Middleware\DoublePassTrait;

class OpenApiValidation implements MiddlewareInterface
{
    use DoublePassTrait;

    private $openapi;
    private $options = [
        'additionalParameters'   => false,
        'beforeHandler'          => null,
        'errorHandler'           => null,
        'exampleResponse'        => false,
        'missingFormatException' => true,
        'pathNotFoundException'  => true,
        'setDefaultParameters'   => false,
        'stripResponse'          => false,
        'validateError'          => false,
        'validateRequest'        => true,
        'validateResponse'       => true,
    ];
    private $formatContainer;

    public function __construct(string $file, array $options = [])
    {
        if (!file_exists($file)) {
            throw new Exception(sprintf("The file '%s' does not exist", $file));
        }
        $this->openapi = new OpenApiReader($file);
        $allOptions    = array_keys($this->options);
        foreach ($options as $option => $value) {
            if (in_array($option, $allOptions)) {
                $this->options[$option] = $value;
            } else {
                throw new Exception(sprintf('Invalid option: %s', $option));
            }
        }
        $this->formatContainer = new FormatContainer();
        // Password validator only checks that it's a string, as format=password only is a hint to the UI
        $this->formatContainer->add('string', 'password', new OpenApiValidation\Formats\PasswordValidator());
    }

    public function addFormat(string $type, string $name, \Opis\JsonSchema\IFormat $format)
    {
        $this->formatContainer->add($type, $name, $format);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $method         = mb_strtolower($request->getMethod());
        $pathParameters = [];
        $path           = $this->openapi->getPathFromUri($request->getRequestTarget(), $method, $pathParameters);
        // Id cors preflight request, dont do anything
        if ('options' == $method && $request->hasHeader('HTTP_ACCESS_CONTROL_REQUEST_METHOD')) {
            return $handler->handle($request);
        }

        if (null === $path && $this->options['pathNotFoundException']) {
            throw new Exception(sprintf('%s %s not defined in OpenAPI document.', mb_strtoupper($method), $request->getRequestTarget()));
        }
        if (null === $path) {
            return $handler->handle($request);
        }

        if ($this->options['validateRequest']
            && $errors = $this->validateRequest($request, $path, $method, $pathParameters)) {
            if (is_callable($this->options['beforeHandler'])) {
                $beforeRequest = $this->options['beforeHandler']($request, $errors);
                if ($beforeRequest instanceof ServerRequestInterface) {
                    $response = $handler->handle($beforeRequest);
                } else {
                    throw new Exception("Invalid return value from 'beforeHandler' handler");
                }
            } else {
                $response = $this->error(400, 'Request validation failed', $errors);
                if (!$this->options['validateError']) {
                    return $response;
                }
            }
        } else {
            $response = $handler->handle($request);
        }

        if (204 == $response->getStatusCode()) {
            return $response;
        }

        if ($this->options['exampleResponse']
            && '' === $response->getBody()->__toString()) {
            $response = $this->setExampleResponse($request, $response, $path, $method);
        }

        if ($this->options['validateResponse']
            && $errors = $this->validateResponse($response, $path, $method)) {
            return $this->error(500, 'Response validation failed', $errors);
        }

        return $response;
    }

    public function validateResponse(ResponseInterface &$response, string $path, string $method) : ?array
    {
        $responseBodyData = json_decode($response->getBody()->__toString(), true);
        $code             = $response->getStatusCode();
        $mediaType        = $this->getMediaType($response);
        $responseObject   = $this->openapi->getOperationResponse($path, $method, $code);
        if (null === $responseObject) { // Not in file
            return [];
        }
        $responseSchema = $responseObject->getContent($mediaType)->schema;
        if (null === $responseSchema) {
            $mediaType      = $responseObject->getDefaultMediaType();
            $responseSchema = $responseObject->getContent($mediaType)->schema;
        }
        if (null === $responseBodyData && $responseSchema) {
            return [['name' => 'responseBody', 'code' => 'error_required']];
        }
        $errors = $this->validateObject($responseSchema, $responseBodyData);
        if ($this->options['stripResponse']) {
            $notAdditionalErrors = [];
            foreach ($errors as $error) {
                if ('error_additional' == $error['code']) {
                    $responseBodyData = JsonHelper::remove($responseBodyData, explode('.', $error['name']));
                } else {
                    $notAdditionalErrors[] = $error;
                }
            }
            $errors   = $notAdditionalErrors;
            $response = $response->withBody((new StreamFactory())->createStream(json_encode($responseBodyData)));
        }
        return $errors;
    }

    private function checkFormat(string $type, string $format)
    {
        if (null === $this->formatContainer->get($type, $format)) {
            try {
                $this->formatContainer->add($type, $format, new OpenApiValidation\Formats\RespectValidator($format));
            } catch (Exception $e) {
                if ($this->options['missingFormatException']) {
                    throw new Exception(sprintf('Missing validator for type=%s, format=%s', $type, $format));
                }
            }
        }
    }

    private function validateRequest(ServerRequestInterface &$request, string $path, string $method, array $pathValues) : array
    {
        $errors          = [];
        $parameters      = $this->openapi->getOperationParameters($path, $method);
        $values          = ['path' => $pathValues];
        $values['query'] = $request->getQueryParams();

        if (!$this->options['additionalParameters']) {
            $errors = array_merge($errors, $this->checkAdditionalParamters($parameters, $values));
        }
        if ($this->options['setDefaultParameters']) {
            $request         = $this->setDefaultParamterValues($parameters, $request, $values);
            $values['query'] = $request->getQueryParams();
        }

        $properties = [];
        foreach ($parameters as $p) {
            $properties[] = Property::fromParameter($p, $values[$p->in][$p->name] ?? null);
        }
        $errors          = array_merge($errors, $this->validateProperties($properties));
        $requestBody     = $this->openapi->getOperationRequestBody($path, $method);
        $requestBodyData = $request->getParsedBody();
        $mediaType       = $this->getMediaType($request);

        if ($requestBody && $requestMediaType = $requestBody->getContent($mediaType)) {
            if ($requestBodyData === [] && $requestBody->required) {
                $errors[] = ['name' => 'requestBody', 'code' => 'error_required'];
            } else {
                switch ($mediaType) {
                    case 'application/json':
                        $objectErrors = $this->validateObject($requestMediaType->schema, $requestBodyData);
                        foreach ($objectErrors as $error) {
                            if (!('error_type' == $error['code']
                                && 'null' == $error['used']
                                && SchemaHelper::isNullable($requestMediaType->schema, explode('.', $error['name'])))) {
                                $errors[] = $error;
                            }
                        }
                        break;
                    case 'multipart/form-data':
                        $errors = array_merge($errors, $this->validateFormData($requestMediaType->schema, $requestMediaType->encoding, $request));
                        break;
                }
            }
        }
        return $errors;
    }

    private function validateProperties(array $properties) : ?array
    {
        $errors = [];
        foreach ($properties as $property) {
            if (isset($property->schema->format)) {
                $this->checkFormat($property->schema->type, $property->schema->format);
            }
        }
        $validator = new Validator();
        $validator->setFormats($this->formatContainer);
        foreach ($properties as $property) {
            if (null === $property->value) {
                if ($property->required) {
                    $errors[] = [
                        'name' => $property->name,
                        'code' => 'error_required',
                    ];
                }
                continue;
            }
            try {
                $result = $validator->dataValidation($property->value, $property->schema, 99);
            } catch (Exception $e) {
            }
            if (!$result->isValid()) {
                foreach ($result->getErrors() as $error) {
                    $errors = array_merge($errors, $this->parseErrors($error, $property->name));
                }
            }
        }
        return $errors;
    }

    private function validateObject(array $schema, array $value) : array
    {
        $schema = SchemaHelper::addRecursive($schema, 'additionalProperties', false);
        $errors = [];
        foreach (SchemaHelper::getFormats($schema) as $f) {
            $this->checkFormat($f['type'], $f['format']);
        }
        $validator = new Validator();
        $validator->setFormats($this->formatContainer);
        try {
            $value  = json_decode(json_encode($value));
            $schema = json_decode(json_encode($schema));
            $result = $validator->dataValidation($value, $schema, 99);
        } catch (Exception $e) {
        }
        if (!$result->isValid()) {
            foreach ($result->getErrors() as $error) {
                $errors = array_merge($errors, $this->parseErrors($error));
            }
        }
        return $errors;
    }

    private function validateFormData(array $schema, array $encoding, ServerRequestInterface $request) : array
    {
        $errors        = [];
        $formData      = $request->getParsedBody();
        $properties    = [];
        $uploadedFiles = $request->getUploadedFiles();
        foreach ($schema['properties'] as $name => $property) {
            if (isset($property['format']) && in_array($property['format'], ['binary', 'base64'])) {
                if (in_array($name, $schema['required'] ?? []) && !isset($uploadedFiles[$name])) {
                    $errors[] = ['name' => $name, 'code' => 'error_required'];
                } elseif ($uploaded = $uploadedFiles[$name]) {
                    if (isset($encoding[$name]) && !$encoding[$name]->hasContentType($uploaded->getClientMediaType())) {
                        $types    = $encoding[$name]->contentTypes;
                        $errors[] = [
                            'name'     => $name,
                            'code'     => 'error_content_type',
                            'expected' => 1 === count($types) ? $types[0] : $types,
                            'used'     => $uploaded->getClientMediaType(),
                        ];
                    }
                }
            } else {
                $properties[] = new Property(
                    $name,
                    in_array($name, $schema['required'] ?? []),
                    $property,
                    $formData[$name]
                );
            }
        }
        if (count($properties)) {
            $errors = array_merge($errors, $this->validateProperties($properties));
        }
        return $errors;
    }

    private function checkAdditionalParamters(array $parameters, array $values) : array
    {
        $errors  = [];
        $defined = ['path' => [], 'query' => [], 'header' => [], 'cookie' => []];
        foreach ($parameters as $p) {
            $defined[$p->in][] = $p->name;
        }
        foreach ($values as $in => $map) {
            foreach ($map as $name => $value) {
                if (!in_array($name, $defined[$in])) {
                    $errors[] = [
                        'name' => $name,
                        'code' => 'error_additional',
                        'in'   => $in,
                    ];
                }
            }
        }
        return $errors;
    }

    private function setDefaultParamterValues(array $parameters, ServerRequestInterface $request, array $values) : ServerRequestInterface
    {
        foreach ($parameters as $parameter) {
            if ('query' == $parameter->in && !isset($values['query'][$parameter->name]) && isset($parameter->schema->default)) {
                $values['query'][$parameter->name] = $parameter->schema->default;
            }
        }
        return $request->withQueryParams($values['query']);
    }

    private function setExampleResponse(ServerRequestInterface $request, ResponseInterface $response, string $path, string $method) : ResponseInterface
    {
        if ($responseObject = $this->openapi->getOperationResponse($path, $method)) {
            $requestBodyData = $request->getParsedBody();
            if (null === $mediaType = $this->getMediaType($request)) {
                $mediaType = $responseObject->getDefaultMediaType();
            }
            $exampleResponseBodyData = $responseObject->getContent($mediaType)->getExample() ?? [];
            if (null !== $requestBodyData && !isset($exampleResponseBodyData[0])) {
                // If the request is a post or put, merge the request data to the example if the example is an object,
                // just to make the dummy data a bit more like in real life
                $exampleResponseBodyData = array_merge($exampleResponseBodyData, $requestBodyData);
            }
            $response = $response
                ->withBody((new StreamFactory())->createStream(json_encode($exampleResponseBodyData)))
                ->withHeader('Content-Type', $mediaType.';charset=utf-8');
            if (is_numeric($responseObject->statusCode)) {
                $response = $response->withStatus($responseObject->statusCode);
            }
        }
        return $response;
    }

    private function error(int $code, string $message, array $errors = []) : ResponseInterface
    {
        if (is_callable($this->options['errorHandler'])) {
            $response = $this->options['errorHandler']($code, $message, $errors);
            if ($response instanceof ResponseInterface) {
                return $response;
            }
        }
        $response = (new ResponseFactory())->createResponse($code);
        $json     = ['message' => $message];
        if (count($errors)) {
            $json['errors'] = $errors;
        }
        $response = $response->withHeader('Content-Type', 'application/json;charset=utf-8');
        return $response->withBody((new StreamFactory())->createStream(json_encode($json)));
    }

    private function parseErrors(ValidationError $error, $name = null) : array
    {
        $errors = [];
        if ('additionalProperties' == $error->keyword()) {
            foreach ($error->subErrors() as $se) {
                $errors[] = [
                    'name'  => implode('.', $se->dataPointer()),
                    'code'  => 'error_additional',
                    'value' => $se->data(),
                ];
            }
        } else {
            $err = [
                'name'  => $name ?? implode('.', $error->dataPointer()),
                'code'  => 'error_'.$error->keyword(),
                'value' => $error->data(),
            ];
            foreach ($error->keywordArgs() as $attr => $value) {
                $err[$attr] = $value;
            }
            if ('error_required' == $err['code']) {
                $err['name'] .= mb_strlen($err['name']) ? '.'.$err['missing'] : $err['missing'];
                unset($err['missing'],$err['value']);
            }
            $errors[] = $err;
        }
        return $errors;
    }

    private function getMediaType(MessageInterface $message) : ?string
    {
        $header = $message->getHeader('Content-Type');
        if (!$header) {
            return null;
        }
        $contentTypeParts = preg_split('/\s*[;,]\s*/', $header[0]);
        return mb_strtolower($contentTypeParts[0]);
    }
}
