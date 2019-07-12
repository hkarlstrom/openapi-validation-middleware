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

class JsonHelperTest extends TestCase
{
    public function testAdditionalProperties()
    {
        $json = [
            'type'=>'object',
            'properties' => [
                'foo' => [
                    'type' => 'object',
                    'properties' => [
                        'bar' => [
                            'type' => 'number'
                        ]
                    ]
                ]
            ]
                        ];
        $json = Helpers\Json::additionalProperties($json,false);
        $this->assertArrayHasKey('additionalProperties',$json);
        $this->assertFalse($json['additionalProperties']);
        $this->assertArrayHasKey('additionalProperties',$json['properties']['foo']);
        $this->assertFalse($json['properties']['foo']['additionalProperties']);
    }
}
