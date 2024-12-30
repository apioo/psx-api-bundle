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

namespace PSX\ApiBundle\EventListener;

use PSX\Api\ApiManagerInterface;
use PSX\Api\OperationInterface;
use PSX\Api\Parser\Attribute\BuilderInterface;
use PSX\ApiBundle\Http\RequestReader;
use PSX\Data\Body;
use PSX\Data\Reader;
use PSX\DateTime\Exception\InvalidFormatException;
use PSX\DateTime\LocalDate;
use PSX\DateTime\LocalDateTime;
use PSX\DateTime\LocalTime;
use PSX\Http\Exception\UnsupportedMediaTypeException;
use PSX\Http\Stream\Stream;
use PSX\Schema\ContentType;
use PSX\Schema\DefinitionsInterface;
use PSX\Schema\Format;
use PSX\Schema\Schema;
use PSX\Schema\Type\AnyPropertyType;
use PSX\Schema\Type\BooleanPropertyType;
use PSX\Schema\Type\IntegerPropertyType;
use PSX\Schema\Type\NumberPropertyType;
use PSX\Schema\Type\PropertyTypeAbstract;
use PSX\Schema\Type\ReferencePropertyType;
use PSX\Schema\Type\StringPropertyType;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ErrorController;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * ControllerArgumentsListener
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://phpsx.org
 */
#[AsEventListener(event: KernelEvents::CONTROLLER_ARGUMENTS, method: 'onKernelControllerArguments')]
final readonly class ControllerArgumentsListener
{
    public function __construct(private RequestReader $requestReader, private ApiManagerInterface $apiManager, private BuilderInterface $builder)
    {
    }

    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        $controller = $event->getController();
        if ($controller instanceof ErrorController) {
            return;
        }

        [$controllerClass, $methodName] = $this->getControllerAndMethod($controller);

        $specification = $this->apiManager->getApi($controllerClass);

        $operationId = $this->builder->buildOperationId($controllerClass, $methodName);
        $operation = $specification->getOperations()->get($operationId);

        $event->setArguments($this->buildArguments($controllerClass, $methodName, $operation, $event->getRequest(), $specification->getDefinitions()));
    }

    private function buildArguments(string $controller, string $methodName, OperationInterface $operation, Request $request, DefinitionsInterface $definitions): array
    {
        $result = [];
        $arguments = $this->builder->buildArguments($controller, $methodName);
        foreach ($arguments as $parameterName => $realName) {
            $argument = $operation->getArguments()->get($realName);
            if ($argument->getIn() === 'path') {
                $value = $request->attributes->get($realName);
                $result[$parameterName] = $this->castToType($argument->getSchema(), $value);
            } elseif ($argument->getIn() === 'header') {
                $value = $request->headers->get($realName);
                $result[$parameterName] = $this->castToType($argument->getSchema(), $value);
            } elseif ($argument->getIn() === 'query') {
                $value = $request->query->get($realName);
                $result[$parameterName] = $this->castToType($argument->getSchema(), $value);
            } elseif ($argument->getIn() === 'body') {
                $result[$parameterName] = $this->parseRequest($argument->getSchema(), $request, $definitions);
            }
        }

        return $result;
    }

    /**
     * @throws InvalidFormatException
     */
    private function castToType(PropertyTypeAbstract|ContentType $type, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($type instanceof ContentType) {
            return $value;
        }

        if ($type instanceof StringPropertyType) {
            return match ($type->getFormat()) {
                Format::DATE => LocalDate::parse($value),
                Format::DATETIME => LocalDateTime::parse($value),
                Format::TIME => LocalTime::parse($value),
                default => (string) $value,
            };
        } elseif ($type instanceof IntegerPropertyType) {
            return (int) $value;
        } elseif ($type instanceof NumberPropertyType) {
            return (float) $value;
        } elseif ($type instanceof BooleanPropertyType) {
            return (bool) $value;
        } elseif ($type instanceof AnyPropertyType) {
            return $value;
        }

        return $value;
    }

    private function parseRequest(PropertyTypeAbstract|ContentType $type, Request $request, DefinitionsInterface $definitions): mixed
    {
        if ($type instanceof ContentType) {
            return match ($type->toString()) {
                ContentType::BINARY => new Stream($request->getContent(true)),
                ContentType::FORM => Body\Form::from($this->requestReader->getBody($request, Reader\Form::class)),
                ContentType::JSON => Body\Json::from($this->requestReader->getBody($request, Reader\Json::class)),
                ContentType::MULTIPART => $this->getMultipart($this->requestReader->getBody($request, Reader\Multipart::class)),
                ContentType::TEXT => (string) $request->getContent(),
            };
        }

        if (!$type instanceof ReferencePropertyType) {
            return null;
        }

        if ($type->getTarget() === 'Passthru') {
            $data = $this->requestReader->getBody($request);
        } else {
            $data = $this->requestReader->getBodyAs($request, new Schema($definitions, $type->getTarget()));
        }

        return $data;
    }

    private function getMultipart(mixed $return): Body\Multipart
    {
        if (!$return instanceof Body\Multipart) {
            throw new UnsupportedMediaTypeException('Provided an invalid content type, must be multipart/form-data');
        }

        return $return;
    }

    private function getControllerAndMethod(callable $controller): array
    {
        $callableName = null;
        if (!is_callable($controller, callable_name: $callableName)) {
            throw new \RuntimeException('Provided an invalid callable, must be in the format class::method');
        }

        if (empty($callableName) || !str_contains($callableName, '::')) {
            throw new \RuntimeException('Provided an invalid callable, must be in the format class::method');
        }

        return explode('::', $callableName, 2);
    }
}
