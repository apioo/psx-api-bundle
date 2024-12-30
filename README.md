
# PSX API Bundle

The PSX API bundle integrates the [PSX API components](https://phpsx.org/) into Symfony which help
to build fully type-safe REST APIs. Basically the bundle provides additional attributes which you
can use at your [controller](#controller) to map HTTP parameters to arguments of your controller
and commands to generate based on those attributes and type-hints different artifacts:

* Generate Client SDKs for different languages i.e. TypeScript and PHP
  * `php bin/console generate:sdk client-typescript`
* Generate OpenAPI specification without additional attributes
  * `php bin/console generate:sdk spec-openapi`
* Generate DTO classes using [TypeSchema](https://typeschema.org/)
  * `php bin/console generate:model`

As you note this bundle is about REST APIs and not related to any PlayStation content, the name PSX
was invented way back and is simply an acronym which stands for "**P**HP, **S**QL, **X**ML"

## Installation

To install the bundle simply require the composer package at your Symfony project.

```
composer require psx/api-bundle
```

Make sure, that the bundle is registered at the `config/bundles.php` file:

```php
return [
    PSX\ApiBundle\PSXApiBundle::class => ['all' => true],
];
```

## Controller

The following is a simple controller which shows how to use the PSX specific attributes to describe
different HTTP parameters:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\PostCollection;
use App\Model\Post;
use App\Model\Message;
use PSX\Api\Attribute\Body;
use PSX\Api\Attribute\Param;
use PSX\Api\Attribute\Query;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

final class PostController extends AbstractController
{
    public function __construct(private PostService $service, private PostRepository $repository)
    {
    }

    #[Route('/post', methods: ['GET'])]
    public function getAll(#[Query] ?string $filter): PostCollection
    {
        return $this->repository->findAll();
    }

    #[Route('/post/{id}', methods: ['GET'])]
    public function get(#[Param] int $id): Post
    {
        return $this->repository->find($id);
    }

    #[Route('/post', methods: ['POST'])]
    public function create(#[Body] Post $payload): Message
    {
        return $this->service->create($payload);
    }

    #[Route('/post/{id}', methods: ['PUT'])]
    public function update(#[Param] int $id, #[Body] Post $payload): Message
    {
        return $this->service->update($id, $payload);
    }

    #[Route('/post/{id}', methods: ['DELETE'])]
    public function delete(#[Param] int $id): Message
    {
        return $this->service->delete($id);
    }
}
```

In the example we use the `#[Query]`, `#[Param]` and `#[Body]` attribute to map different parts of
the incoming HTTP request. In the controller we use a fictional `PostService` and `PostRepository`
but you are complete free to design the controller how you like, for PSX it is only important to map
the incoming HTTP request parameters to arguments and to provide a return type.

### Raw payload

We always recommend to generate concrete DTOs to describe the request and response payloads.
If you need a raw payload we provide the following type-hints to receive a raw value.

* `Psr\Http\Message\StreamInterface`
  * Receive the raw request as stream `application/octet-stream`
* `PSX\Data\Body\Json`
  * Receive the raw request as JSON `application/json`
* `PSX\Data\Body\Form`
  * Receive the raw request as form `application/x-www-form-urlencoded`
* `string`
  * Receive the raw request as string `text/plain`

For example to write a simple proxy method which returns the provided JSON payload s.

```php
#[Route('/post', methods: ['POST'])]
public function update(#[Body] Json $body): Json
{
    return $body;
}
```

## Generator

### SDK

To generate an SDK you can simply run the following command:

```
php bin/console generate:sdk
```

This reads alls the attributes from your controller and writes the SDK to the `output` folder.
At first argument you can also provide a type, by default this is `client-typescript` but you can also
select a different type.

* `client-php`
* `client-typescript`
* `spec-openapi`

#### SDKgen

Through the SDKgen project you have the option to generate also client SDKs for
different programming languages, therefor you only need to register at the [SDKgen](https://sdkgen.app/)
website to obtain a client id and secret which you need to set as `psx_api.sdkgen_client_id` and `psx_api.sdkgen_client_secret`
at your config. After this you can use one of the following types:

* `client-csharp`
* `client-go`
* `client-java`
* `client-python`

#### TypeHub

If you want to share your API specification it is possible to push your specification to the [TypeHub](https://typehub.cloud/)
platform with the following command:

```
php bin/console api:push my_document_name
```

Then you also need to provide a client id and secret for your account. The TypeHub platform basically tracks all changes of
the API specification and it is possible to download different SDKs. 

### Model

This bundle also provides a model generator which helps to generate DTOs to describe the
incoming and outgoing payload s.

```
php bin/console generate:model
```

This commands reads the [TypeSchema](https://typeschema.org/) specification located at `config/typeschema.json`
and writes all model classes to `src/Model`. In general TypeSchema is a JSON specification to describe data models.
The following is an example specification to generate a simple Student model.

```json
{
  "definitions": {
    "Student": {
      "description": "A simple student struct",
      "type": "struct",
      "properties": {
        "firstName": {
          "type": "string"
        },
        "lastName": {
          "type": "string"
        },
        "age": {
          "type": "integer"
        }
      }
    }
  }
}
```

## Configuration

The bundle needs the following `psx_api.yaml` configuration:

```yaml
psx_api:
  base_url: 'https://api.acme.com'
  sdkgen_client_id: ''
  sdkgen_client_secret: ''
```

The `base_url` is the absolute url to your API so that you don't need to provide the
base url at your client SDK.

The `sdkgen_client_id` and `sdkgen_client_secret` are credentials to the [SDKgen](https://sdkgen.app/) app.

## Technical

This bundle tries to not change any Symfony behaviour, for example we use the existing `#[Route]` attribute instead
of the `#[Path]` attribute. This has some small tradeoffs, at first you are required to use the
`#[Route]` attribute, `YAML`, `XML` or `PHP` routing is not supported, since otherwise the generate command will not
be able to parse the routes, and second your route has to specify a concrete HTTP method filter, since the SDK generator
needs a concrete HTTP method for every endpoint.

Basically the bundle only registers the following classes:

* `PSX\ApiBundle\ArgumentResolver\ValueResolver`
  * To parse the attributes of each argument and transform the incoming value
* `PSX\ApiBundle\EventListener\SerializeResponseListener`
  * To transform the response of the controller
* `PSX\ApiBundle\EventListener\ExceptionResponseListener`
  * To transform the exception response

## Community

Feel free to create an issue or PR in case you want to improve this bundle. We also like to give a
shout-out to [praswicaksono](https://github.com/praswicaksono/typeapi-bundle) for implementing a
first version of this bundle.
