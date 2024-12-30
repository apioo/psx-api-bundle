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

declare(strict_types=1);

namespace PSX\ApiBundle\ArgumentResolver;

use Psr\Http\Message\StreamInterface;
use PSX\Api\Attribute;
use PSX\ApiBundle\Http\RequestReader;
use PSX\Data\Body;
use PSX\Data\Reader;
use PSX\DateTime\LocalDate;
use PSX\DateTime\LocalDateTime;
use PSX\DateTime\LocalTime;
use PSX\Http\Exception\BadRequestException;
use PSX\Http\Stream\Stream;
use PSX\Schema\SchemaSource;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

readonly class ValueResolver implements ValueResolverInterface
{
    public function __construct(private RequestReader $requestReader)
    {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $param = $this->getFirstAttribute($argument, Attribute\Param::class);
        if ($param instanceof Attribute\Param) {
            $name = $param->name ?? $argument->getName();
            return [$this->parseParameter($request->attributes->get($name), $argument, $name, 'path')];
        }

        $query = $this->getFirstAttribute($argument, Attribute\Query::class);
        if ($query instanceof Attribute\Query) {
            $name = $param->name ?? $argument->getName();
            return [$this->parseParameter($request->query->get($name), $argument, $name, 'query')];
        }

        $header = $this->getFirstAttribute($argument, Attribute\Header::class);
        if ($header instanceof Attribute\Header) {
            $name = $param->name ?? $argument->getName();
            return [$this->parseParameter($request->headers->get($name), $argument, $name, 'header')];
        }

        $body = $this->getFirstAttribute($argument, Attribute\Body::class);
        if ($body instanceof Attribute\Body) {
            return [$this->parseBody($argument->getType(), $request)];
        }

        return [];
    }

    private function parseParameter(mixed $value, ArgumentMetadata $argument, string $name, string $type): mixed
    {
        if (!$argument->isNullable() && $value === null) {
            throw new BadRequestException('Missing ' . $type . ' parameter "' . $name . '"');
        }

        $type = $argument->getType();
        if ($type === null || $value === null) {
            return null;
        }

        return match ($type) {
            LocalDate::class => LocalDate::parse($value),
            LocalDateTime::class => LocalDateTime::parse($value),
            LocalTime::class => LocalTime::parse($value),
            'string' => (string) $value,
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            default => $value,
        };
    }

    private function parseBody(?string $type, Request $request): mixed
    {
        if ($type === null) {
            return null;
        }

        return match ($type) {
            StreamInterface::class => new Stream($request->getContent(true)),
            Body\Json::class => Body\Json::from($this->requestReader->getBody($request, Reader\Json::class)),
            Body\Form::class => Body\Form::from($this->requestReader->getBody($request, Reader\Form::class)),
            Body\Multipart::class => $this->requestReader->getBody($request, Reader\Multipart::class),
            'string' => (string) $request->getContent(),
            default => class_exists($type) ? $this->requestReader->getBodyAs($request, SchemaSource::fromClass($type)) : null,
        };
    }

    /**
     * @param class-string $attributeClass
     */
    private function getFirstAttribute(ArgumentMetadata $argument, string $attributeClass): ?object
    {
        foreach ($argument->getAttributesOfType($attributeClass) as $attribute) {
            return $attribute;
        }

        return null;
    }
}
