<?php

declare(strict_types=1);

namespace Systemsdk\Bundle\EasyLogBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Systemsdk\Bundle\EasyLogBundle\DependencyInjection\Compiler\ValidateConfigurationPass;

/**
 * Class EasyLogBundle
 */
class EasyLogBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new ValidateConfigurationPass());
    }
}
