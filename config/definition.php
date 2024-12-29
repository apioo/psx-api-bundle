<?php

declare(strict_types=1);

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;

return static function (DefinitionConfigurator $definition): void {
    $definition->rootNode()
        ->children()
            ->stringNode('base_url')->end()
            ->stringNode('sdkgen_client_id')->end()
            ->stringNode('sdkgen_client_secret')->end()
        ->end();
};
