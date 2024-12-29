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

use PSX\Http\Exception\StatusCodeException;
use PSX\Model\Error;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * ExceptionResponseListener
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://phpsx.org
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, method: 'onKernelException')]
final class ExceptionResponseListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if ($exception instanceof StatusCodeException) {
            $title   = get_class($exception);
            $message = $exception->getMessage() . ' in ' . $exception->getFile() . ' on line ' . $exception->getLine();
            $trace   = $exception->getTraceAsString();

            $error = new Error();
            $error->setSuccess(false);
            $error->setTitle($title);
            $error->setMessage($message);
            $error->setTrace($trace);

            $event->setResponse(new JsonResponse($error, $exception->getStatusCode()));
        }
    }
}
