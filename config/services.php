<?php

declare(strict_types=1);

use PSX\Api\ApiManager;
use PSX\Api\ApiManagerInterface;
use PSX\Api\ConfiguratorInterface;
use PSX\Api\Console\PushCommand;
use PSX\Api\GeneratorFactory;
use PSX\Api\Parser\Attribute\Builder;
use PSX\Api\Parser\Attribute\BuilderInterface;
use PSX\Api\Repository;
use PSX\Api\Repository\RepositoryInterface;
use PSX\Api\Scanner\FilterFactory;
use PSX\Api\Scanner\FilterFactoryInterface;
use PSX\Api\ScannerInterface;
use PSX\ApiBundle\Api\Parser\SymfonyAttribute;
use PSX\ApiBundle\Api\Repository\SDKgen\Config;
use PSX\ApiBundle\Api\Scanner\RouterScanner;
use PSX\ApiBundle\Command\ModelCommand;
use PSX\ApiBundle\Command\SdkCommand;
use PSX\ApiBundle\Command\TableCommand;
use PSX\ApiBundle\Data\ProcessorFactory;
use PSX\ApiBundle\EventListener\ControllerArgumentsListener;
use PSX\ApiBundle\EventListener\ExceptionResponseListener;
use PSX\ApiBundle\EventListener\SerializeResponseListener;
use PSX\ApiBundle\Http\RequestReader;
use PSX\ApiBundle\Http\ResponseBuilder;
use PSX\Data\Processor;
use PSX\Data\Writer;
use PSX\Schema\SchemaManager;
use PSX\Schema\SchemaManagerInterface;
use PSX\Sql\TableManager;
use PSX\Sql\TableManagerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $parameters = $container->parameters();
    $parameters->set('psx.supported_writer', [
        Writer\Json::class,
        Writer\Jsonp::class,
        Writer\Jsonx::class,
    ]);

    $services = $container->services();
    $services->defaults()->autowire()->autoconfigure();

    $services->set(ProcessorFactory::class);
    $services->set(Processor::class)
        ->factory([service(ProcessorFactory::class), 'factory']);

    $services->set(SchemaManager::class)
        ->arg('$debug', param('kernel.debug'));
    $services->alias(SchemaManagerInterface::class, SchemaManager::class);

    $services->set(TableManager::class);
    $services->alias(TableManagerInterface::class, TableManager::class);

    $services->set(Builder::class)
        ->arg('$debug', param('kernel.debug'));
    $services->alias(BuilderInterface::class, Builder::class);

    $services->set(RouterScanner::class);
    $services->alias(ScannerInterface::class, RouterScanner::class);

    $services->set(FilterFactory::class);
    $services->alias(FilterFactoryInterface::class, FilterFactory::class);

    $services
        ->instanceof(RepositoryInterface::class)
        ->tag('psx.api_repository');

    $services
        ->instanceof(ConfiguratorInterface::class)
        ->tag('psx.api_configurator');

    $services->set(Repository\LocalRepository::class);
    $services->set(Repository\SchemaRepository::class);
    $services->set(Repository\SDKgenRepository::class);
    $services->set(Config::class);
    $services->alias(Repository\SDKgen\ConfigInterface::class, Config::class);
    $services->set(GeneratorFactory::class)
        ->args([
            tagged_iterator('psx.api_repository'),
            tagged_iterator('psx.api_configurator'),
        ]);

    $services->set(SymfonyAttribute::class);
    $services->set(ApiManager::class)
        ->arg('$debug', param('kernel.debug'))
        ->call('register', ['php', service(SymfonyAttribute::class)]);
    $services->alias(ApiManagerInterface::class, ApiManager::class);

    $services->set(RequestReader::class);
    $services->set(ResponseBuilder::class)
        ->arg('$supportedWriter', param('psx.supported_writer'));

    $services->set(ModelCommand::class);
    $services->set(SdkCommand::class);
    $services->set(TableCommand::class);
    $services->set(PushCommand::class);

    $services->set(ControllerArgumentsListener::class);
    $services->set(ExceptionResponseListener::class);
    $services->set(SerializeResponseListener::class);

};
