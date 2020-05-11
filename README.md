# OpenAPI Validation Middleware

PSR-7 and PSR-15 [OpenAPI](https://www.openapis.org/) Validation Middleware

The middleware parses an OpenAPI definition document (openapi.json or openapi.yaml) and validates:
* Request parameters (path, query)
* Request body
* Response body

The middleware can be used with any framework using [PSR-7](https://www.php-fig.org/psr/psr-7/) and [PSR-15](https://www.php-fig.org/psr/psr-15/) style middlewares.

All testing has been done using [Slim Framework](https://github.com/slimphp/Slim). The tests are done with a openapi.json file that is valid according to [Swagger/OpenAPI CLI](https://www.npmjs.com/package/swagger-cli)


## Installation

It's recommended that you use [Composer](https://getcomposer.org/download) to install.
```shell
composer require hkarlstrom/openapi-validation-middleware
```

Use [Swagger/OpenAPI CLI](https://www.npmjs.com/package/swagger-cli) to validate openapi.json/openapi.yaml file, as the middleware assumes it to be valid.


## Usage

Basic usage with Slim Framework.
```php
$config = [
    'settings' => [
        'determineRouteBeforeAppMiddleware' => true,
    ],
];
$app = new \Slim\App($config);
$app->add(new HKarlstrom\Middleware\OpenApiValidation('/path/to/openapi.json'));
```

Basic usage with Zend Expressive.
```php
$app = $container->get(\Zend\Expressive\Application::class);
$app->pipe(new HKarlstrom\Middleware\OpenApiValidation('/path/to/openapi.json'));
```

### Options

The options array is passed to the middleware when it's constructed.
```php
$app = new Slim\App;
$app->add(new HKarlstrom\Middleware\OpenApiValidation('/path/to/openapi.json'),[
    'additionalParameters' => true,
    'stripResponse' => true
]);
```


| type                       | format    | default | description |
| -------------------------- | --------- | ------- | --- |
| additionalParameters       | bool      | false   | Allow additional parameters in query |
| beforeHandler              | callable  | null    | Instructions [below](README.md#beforehandler) |
| errorHandler               | callable  | null    | Instructions [below](README.md#errorhandler) |
| exampleResponse            | bool      | false   | Return example response from openapi.json/openapi.yaml if route implementation is empty |
| missingFormatException     | bool      | true    | Throw an exception if a format validator is missing |
| pathNotFoundException      | bool      | true    | Throw an exception if the path is not found in openapi.json/openapi.yaml |
| setDefaultParameters       | bool      | false   | Set the default parameter values for missing parameters and alter the request object |
| strictEmptyArrayValidation | bool      | false   | Consider empty array when object is expected as validation error |
| stripResponse              | bool      | false   | Strip additional attributes from response to prevent response validation error |
| stripResponseHeaders       | bool      | false   | Strip additional headers from response to prevent response validation error |
| validateError              | bool      | false   | Should the error response be validated |
| validateRequest            | bool      | true    | Should the request be validated |
| validateResponse           | bool      | true    | Should the response's body be validated |
| validateResponseHeaders    | bool      | false   | Should the response's headers be validated |


#### beforeHandler
If defined, the function is called when the request validation fails before the next incoming middleware is called. You can use this to alter the request before passing it to the next incoming middleware in the stack. If it returns anything else than \Psr\Http\Message\ServerRequestInterface an exception will be thrown. The `array $errors` is an array containing all the validation errors.
```php
$options = [
    'beforeHandler' => function (\Psr\Http\Message\ServerRequestInterface $request, array $errors) : \Psr\Http\Message\ServerRequestInterface {
        // Alter request
        return $request
    }
];
```

#### errorHandler
If defined, the function is called instead of the default error handler. If it returns anything else than Psr\Http\Message\ResponseInterface it will fallback to the default error handler.
```php
$options = [
    'errorHandler' => function (int $code, string $message, array $errors) : \Psr\Http\Message\ResponseInterface {
        // Alter request
        return $request
    }
];
```

## Formats

There are two ways to validate formats not defined in the [OAS](https://swagger.io/specification/#dataTypes) specification. You can implement a custom format validator and add it to the middleware, or use the build in support for the [Respect Validation](http://respect.github.io/Validation/) libray.

#### Custom validator
```php
class MyOwnFormat implements Opis\JsonSchema\IFormat {
    public function validate($data) : bool
    {
        // Validate data
        // $isValid = ...
        return $isValid;
    }
}

$mw = new HKarlstrom\Middleware\OpenApiValidation('/path/to/openapi.json');
$mw->addFormat('string','my-own-format',new MyOwnFormat());
$app->add($mw);
```

#### Respect Validation

You can use [all the validators](http://respect.github.io/Validation/docs/validators.html) just by setting the `format` property in your openapi.json/openapi.yaml file.
```json
"schema":{
    "type" : "string",
    "format": "country-code"
}
```
The `country-code` value will resolve to the `v::countryCode()` validator.

You can also pass arguments to the validator defined in the format attribute:

```json
"schema": {
    "type": "string",
    "format":"ends-with('@gmail.com')"
}
```
or
```json
"schema": {
    "type": "integer",
    "format":"between(10, 20)"
}
```

## License

The OpenAPI Validation Middleware is licensed under the MIT license. See [License File](LICENSE) for more information.
