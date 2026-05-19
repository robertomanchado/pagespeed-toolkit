<?php

namespace Inforob\PageSpeedToolkit;

use Inforob\PageSpeedToolkit\DependencyInjection\PageSpeedExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PageSpeedBundle extends Bundle
{
    public function getContainerExtension(): ExtensionInterface
    {
        return new PageSpeedExtension();
    }
}
