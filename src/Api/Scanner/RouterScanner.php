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

namespace PSX\ApiBundle\Api\Scanner;

use PSX\Api\ApiManagerInterface;
use PSX\Api\Exception\ApiException;
use PSX\Api\Scanner\FilterInterface;
use PSX\Api\ScannerInterface;
use PSX\Api\Specification;
use PSX\Api\SpecificationInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * RouterScanner
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://phpsx.org
 */
readonly class RouterScanner implements ScannerInterface
{
    public function __construct(private RouterInterface $router, private ApiManagerInterface $apiManager)
    {
    }

    public function generate(?FilterInterface $filter = null): SpecificationInterface
    {
        $specification = new Specification();

        $classes = $this->getAllControllerClasses();
        foreach ($classes as $class) {
            try {
                $spec = $this->apiManager->getApi($class);
                $specification->merge($spec);
            } catch (ApiException) {
            }
        }

        return $specification;
    }

    private function getAllControllerClasses(): array
    {
        $classes = [];
        $collection = $this->router->getRouteCollection();
        foreach ($collection->all() as $route) {
            $controllerClass = $this->getControllerClass($route->getDefault('_controller'));
            if (empty($controllerClass)) {
                continue;
            }

            $classes[] = $controllerClass;
        }

        return array_unique($classes);
    }

    private function getControllerClass(mixed $controller): ?string
    {
        if (is_object($controller)) {
            return $controller::class;
        }

        if (str_contains($controller, '::')) {
            $parts = explode('::', $controller, 2);
            $class = $parts[0] ?? '';

            if (class_exists($class)) {
                return $class;
            }
        } elseif (class_exists($controller)) {
            return $controller;
        }

        return null;
    }
}
