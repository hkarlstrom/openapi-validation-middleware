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
use HKarlstrom\Middleware\OpenApiValidation\Exception\BeforeHandlerException;
use HKarlstrom\Middleware\OpenApiValidation\Exception\FileNotFoundException;
use HKarlstrom\Middleware\OpenApiValidation\Exception\InvalidOptionException;
use HKarlstrom\Middleware\OpenApiValidation\Exception\MissingFormatException;
use HKarlstrom\Middleware\OpenApiValidation\Exception\PathNotFoundException;
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
        'additionalParameters'    => false,
        'beforeHandler'           => null,
        'errorHandler'            => null,
        'exampleResponse'         => false,
        'missingFormatException'  => true,
        'pathNotFoundException'   => true,
        'setDefaultParameters'    => false,
        'stripResponse'           => false,
        'stripResponseHeaders'    => false,
        'validateError'           => false,
        'validateRequest'         => true,
        'validateResponse'        => true,
        'validateResponseHeaders' => false,
    ];
    private $formatContainer;

    public function __construct(string $filename, array $options = [])
    {
        if (!file_exists($filename)) {
            throw new FileNotFoundException($filename);
        }
        $this->openapi = new OpenApiReader($filename);
        $allOptions    = array_keys($this->options);
        foreach ($options as $option => $value) {
            if (in_array($option, $allOptions)) {
                $this->options[$option] = $value;
            } else {
                throw new InvalidOptionException($option);
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
            throw new PathNotFoundException($method, $request->getRequestTarget());
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
                    throw new BeforeHandlerException(gettype($beforeRequest));
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
            && $errors = $this->validateResponseBody($response, $path, $method)) {
            return $this->error(500, 'Response validation failed', $errors);
        }

        if ($this->options['validateResponseHeaders']
            && $errors = $this->validateResponseHeaders($response, $path, $method)) {
            return $this->error(500, 'Response validation failed', $errors);
        }

        return $response;
    }

    public function validateResponseHeaders(ResponseInterface $response, string $path, string $method) : array
    {
        $code                  = $response->getStatusCode();
        $responseObject        = $this->openapi->getOperationResponse($path, $method, $code);
        $headersSpecifications = $responseObject->getHeaders();
        $responseHeaders       = $response->getHeaders();

        // https://swagger.io/specification/#responseObject
        // - If a response header is defined with the name "Content-Type", it SHALL be ignored.
        $normalizedHeaderNamesInSpecification = [];
        foreach ($headersSpecifications as $headerName => $header) {
            $normalizedHeaderNamesInSpecification[] = mb_strtolower($headerName);
        }

        // If stripResponseHeaders is true, remove additional headers
        if ($this->options['stripResponseHeaders']) {
            foreach ($responseHeaders as $headerName => $headerValue) {
                $normalizedHeaderName = mb_strtolower($headerName);
                if ('content-type' != $normalizedHeaderName && !in_array($normalizedHeaderName, $normalizedHeaderNamesInSpecification)) {
                    $response = $response->withoutHeader($headerName);
                }
            }
        }

        // No specification for headers means any header allowed, skip the check
        if (empty($headersSpecifications)) {
            return [];
        }

        $normalizedResponseHeaders = [];
        foreach ($responseHeaders as $headerName => $values) {
            $normalizedHeaderName = mb_strtolower($headerName);
            // TODO Fix support if $values has many elements
            if ('content-type' != $normalizedHeaderName) {
                $normalizedResponseHeaders[$normalizedHeaderName] = $values[0];
            }
        }
        $properties = [];
        foreach ($headersSpecifications as $headerName => $header) {
            $properties[] = Property::fromHeader($headerName, $header, $normalizedResponseHeaders[mb_strtolower($headerName)] ?? null);
        }
        return $this->validateProperties($properties);
    }

    public function validateResponseBody(ResponseInterface &$response, string $path, string $method) : array
    {
        $responseBodyData = json_decode($response->getBody()->__toString(), true);
        $code             = $response->getStatusCode();
        $mediaType        = $this->getMediaType($response);
        $responseObject   = $this->openapi->getOperationResponse($path, $method, $code);
        if (null === $responseObject) { // Not in file
            return [];
        }
        $responseSchema = $responseObject->getContent($mediaType)->schema ?? null;
        if (null === $responseSchema) {
            $mediaType      = $responseObject->getDefaultMediaType();
            $responseSchema = $responseObject->getContent($mediaType)->schema;
        }
        if (null === $responseBodyData && $responseSchema) {
            return [['name' => 'responseBody', 'code' => 'error_required']];
        }
        $errors = $this->validateObject($responseSchema, $responseBodyData);
        if ($this->options['stripResponse']) {
            $notAdditionalOrNullErrors = [];
            foreach ($errors as $error) {
                if ('error_additional' == $error['code']) {
                    $responseBodyData = JsonHelper::remove($responseBodyData, explode('.', $error['name']));
                } elseif ('error_type' == $error['code'] && 'null' == $error['used'] && null === $error['value']) {
                    $responseBodyData = JsonHelper::remove($responseBodyData, explode('.', $error['name']));
                } else {
                    $notAdditionalOrNullErrors[] = $error;
                }
            }
            $errors   = $notAdditionalOrNullErrors;
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
                    throw new MissingFormatException($type, $format);
                }
            }
        }
    }

    private function validateRequest(ServerRequestInterface &$request, string $path, string $method, array $pathValues) : array
    {
        $errors          = [];
        $parameters      = $this->openapi->getOperationParameters($path, $method);
        $request         = $this->deserialize($request, $pathValues, $parameters);
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
            if (empty($requestBodyData) && $requestBody->required) {
                $errors[] = ['name' => 'requestBody', 'code' => 'error_required'];
            } else {
                switch ($mediaType) {
                    case 'application/json':
                        if (!empty($requestBodyData)) {
                            $errors = $this->validateObject($requestMediaType->schema, $requestBodyData);
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

    private function deserialize(ServerRequestInterface $request, array &$pathValues, array $parameters) : ServerRequestInterface
    {
        foreach ($parameters as $parameter) {
            $schema = $parameter->schema;
            if (!in_array($schema->type, ['array', 'object'])) {
                continue;
            }
            $name = $parameter->name;
            switch ($parameter->in) {
                case 'query':
                    $queryParams = $request->getQueryParams();
                    if (!isset($queryParams[$name])) {
                        continue;
                    }
                    $queryParams[$name] = $this->styleValue(
                        $parameter->in,
                        $parameter->style,
                        $parameter->explode,
                        $queryParams[$name]);
                    $request = $request->withQueryParams($queryParams);
                    break;
            }
        }
        return $request;
    }

    private function styleValue(string $in, string $style, bool $explode, string $value)
    {
        switch ($in) {
            case 'query':
                switch ($style) {
                    case 'form':
                        return !$explode ? explode(',', $value) : $value;
                    case 'spaceDelimited':
                        return explode(' ', $value);
                    case 'pipeDelimited':
                        return explode('|', $value);
                }
        }
        return $value;
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
                    $err = [
                        'name' => $property->name,
                        'code' => 'error_required',
                        'in'   => $property->in,
                    ];
                    $errors[] = $err;
                }
                continue;
            }
            try {
                $result = $validator->dataValidation($property->value, $property->schema, 99);
            } catch (Exception $e) {
            }
            if (!$result->isValid()) {
                foreach ($result->getErrors() as $error) {
                    $errors = array_merge($errors, $this->parseErrors($error, $property->name, $property->in));
                }
            }
        }
        return $errors;
    }

    private function validateObject(array $schema, array $value) : array
    {
        $errors = [];
        foreach (SchemaHelper::getFormats($schema) as $f) {
            $this->checkFormat($f['type'], $f['format']);
        }
        $validator = new Validator();
        $validator->setFormats($this->formatContainer);
        $schema = SchemaHelper::openApiToJsonSchema($schema);
        try {
            $value  = json_decode(json_encode($value));
            $schema = json_decode(json_encode($schema));
            $result = $validator->dataValidation($value, $schema, 99);
        } catch (Exception $e) {
            return [[
                'name'    => 'server',
                'code'    => 'error_server',
                'message' => $e->getMessage(),
            ]];
            return [$e->getMessage()];
        }
        if (!$result->isValid()) {
            foreach ($result->getErrors() as $error) {
                $errors = array_merge($errors, $this->parseErrors($error, null, 'body'));
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
                    'form-data',
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

    private function parseErrors(ValidationError $error, $name = null, $in = null) : array
    {
        $errors = [];
        if ($error->subErrorsCount()) {
            foreach ($error->subErrors() as $subError) {
                $errors = array_merge($errors, $this->parseErrors($subError, $name, $in));
            }
        } else {
            $err = [
                'name'  => $name ?? implode('.', $error->dataPointer()),
                'code'  => 'error_'.$error->keyword(),
                'value' => $error->data(),
            ];
            if ($in) {
                $err['in'] = $in;
            }
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
