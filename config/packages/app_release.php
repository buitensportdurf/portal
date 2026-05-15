<?php

declare(strict_types=1);

use App\Service\LastRelease;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $release = new LastRelease(dirname(__DIR__, 2) . '/release.json');
    $container->parameters()->set('app.release', $release->getCommitHashShort());
};
