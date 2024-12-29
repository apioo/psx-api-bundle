<?php
/*
 * PSX is an open source PHP framework to develop RESTful APIs.
 * For the current version and information visit <https://phpsx.org>
 *
 * Copyright (c) Christoph Kappestein <christoph.kappestein@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace PSX\ApiBundle\Api\Parser;

use PSX\Api\Attribute as Attr;
use PSX\Api\Exception\ParserException;
use PSX\Api\Parser\Attribute;
use PSX\Api\Parser\Attribute\Meta;
use PSX\Api\Util\Inflection;
use ReflectionMethod;
use Symfony\Component\Routing\Attribute\Route;

/**
 * This parser builds transparently PSX attributes based on the Symfony {@see Route} attribute. Note this requires
 * that every route has a concrete HTTP method filter which is by default not required at Symfony, in the API context
 * this is needed since every endpoint needs a concrete HTTP method
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://phpsx.org
 */
class SymfonyAttribute extends Attribute
{
    /**
     * @throws ParserException
     */
    protected function parseMethodAttributes(ReflectionMethod $method): Meta
    {
        $result = [];
        $attributes = $method->getAttributes();
        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            if ($instance instanceof Route) {
                $path = $instance->getPath();
                if (empty($path)) {
                    continue;
                }

                $result[] = new Attr\Path(Inflection::convertPlaceholderToColon($path));
                $result = array_merge($result, $this->getMethods($instance));
            }
        }

        return new Meta($result);
    }

    /**
     * @return array<Attr\MethodAbstract>
     * @throws ParserException
     */
    private function getMethods(Route $route): array
    {
        $methods = $route->getMethods();
        if (empty($methods)) {
            throw new ParserException('No HTTP methods configured at route ' . $route->getName() . ' (' . $route->getPath() . ') you need to configure a concrete HTTP method for every route');
        }

        $result = [];
        foreach ($methods as $httpMethod) {
            $method = match ($httpMethod) {
                'GET' => new Attr\Get(),
                'POST' => new Attr\Post(),
                'PUT' => new Attr\Put(),
                'PATCH' => new Attr\Patch(),
                'DELETE' => new Attr\Delete(),
                default => null,
            };

            if (!$method instanceof Attr\MethodAbstract) {
                continue;
            }

            $result[] = $method;
        }

        if (count($result) > 1) {
            throw new ParserException('Multiple HTTP methods configured at route ' . $route->getName() . ' (' . $route->getPath() . ') you need to configure exactly one HTTP method at a route');
        }

        return $result;
    }
}
