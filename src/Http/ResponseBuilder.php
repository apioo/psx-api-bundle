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

namespace PSX\ApiBundle\Http;

use Psr\Http\Message\StreamInterface;
use PSX\Data\Body;
use PSX\Data\Exception\WriteException;
use PSX\Data\Exception\WriterNotFoundException;
use PSX\Data\GraphTraverser;
use PSX\Data\Payload;
use PSX\Data\Processor;
use PSX\Data\Writer;
use PSX\Data\WriterInterface;
use PSX\Http\Environment\HttpResponseInterface;
use PSX\Http\Exception\InternalServerErrorException;
use PSX\Http\Exception\NotAcceptableException;
use PSX\Schema\ContentType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ResponseBuilder
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://phpsx.org
 */
class ResponseBuilder
{
    private Processor $processor;
    private array $supportedWriter;

    public function __construct(Processor $processor, array $supportedWriter)
    {
        $this->processor = $processor;
        $this->supportedWriter = $supportedWriter;
    }

    public function build(mixed $data, mixed $writerType = null): Response
    {
        if ($data instanceof Response) {
            return $data;
        }

        $statusCode = 200;
        $headers = [];
        if ($data instanceof HttpResponseInterface) {
            $statusCode = $data->getStatusCode();
            $headers = $data->getHeaders();
            $body = $data->getBody();
        } else {
            $body = $data;
        }

        if (!GraphTraverser::isEmpty($body)) {
            if ($writerType instanceof Request) {
                $options = $this->getWriterOptions($writerType);
            } elseif (is_string($writerType)) {
                $options = new WriterOptions();
                $options->setWriterType($writerType);
            } else {
                $options = new WriterOptions();
                $options->setWriterType(WriterInterface::JSON);
            }

            if (is_string($body)) {
                if (!isset($headers['Content-Type'])) {
                    $headers['Content-Type'] = ContentType::TEXT;
                }

                return new Response($body, $statusCode, $headers);
            } elseif ($body instanceof StreamInterface) {
                if (!isset($headers['Content-Type'])) {
                    $headers['Content-Type'] = ContentType::BINARY;
                }

                return new StreamedResponse(function () use ($body) {
                    echo $body->getContents();
                }, $statusCode, $headers);
            } elseif ($body instanceof Body\Json) {
                return new JsonResponse($body, $statusCode, $headers);
            } elseif ($body instanceof Body\Form) {
                if (!isset($headers['Content-Type'])) {
                    $headers['Content-Type'] = ContentType::FORM;
                }

                return new Response(http_build_query($body->getAll(), '', '&'), $statusCode, $headers);
            }

            return $this->buildWithProcessor($statusCode, $headers, $body, $options);
        } else {
            return new Response(null, 204);
        }
    }

    private function buildWithProcessor(?int $statusCode, ?array $headers, mixed $data, WriterOptions $options): Response
    {
        $format = $options->getFormat();
        $writerType = $options->getWriterType();

        if (!empty($format) && $writerType === null) {
            $writerType = $this->processor->getConfiguration()->getWriterFactory()->getWriterClassNameByFormat($format);
        }

        $supported = $options->getSupportedWriter();

        try {
            $writer = $this->processor->getWriter($options->getContentType(), $writerType, $supported);
        } catch (WriterNotFoundException $e) {
            throw new NotAcceptableException($e->getMessage(), previous: $e);
        }

        // set writer specific settings
        $callback = $options->getWriterCallback();
        if ($callback instanceof \Closure) {
            $callback($writer);
        }

        // write the response
        $payload = Payload::create($data, $options->getContentType());
        if ($writerType !== null) {
            $payload->setRwType($writerType);
        }

        if (!empty($supported)) {
            $payload->setRwSupported($supported);
        }

        try {
            $result = $this->processor->write($payload);
        } catch (WriteException $e) {
            throw new InternalServerErrorException($e->getMessage(), previous: $e);
        } catch (WriterNotFoundException $e) {
            throw new NotAcceptableException($e->getMessage(), previous: $e);
        }

        // the response may have multiple presentations based on the Accept
        // header field but only in case we have no fix writer type
        if ($writerType === null) {
            $headers['Vary'] = 'Accept';
        }

        // set content type header if not available
        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = $writer->getContentType();
        }

        return new Response($result, $statusCode ?? 200, $headers);
    }

    protected function getWriterOptions(Request $request): WriterOptions
    {
        $options = new WriterOptions();
        $options->setContentType('' . $request->headers->get('Accept'));
        $options->setFormat('' . $request->query->get('format'));
        $options->setSupportedWriter($this->supportedWriter);
        $options->setWriterCallback(function(WriterInterface $writer) use ($request){
            if ($writer instanceof Writer\Jsonp) {
                if (!$writer->getCallbackName()) {
                    $writer->setCallbackName('' . $request->query->get('callback'));
                }
            }
        });

        return $options;
    }
}
