<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver\InternalApi\Controller;

use Medusa\App\ApiResolver\Container;
use Medusa\FileSystem\DirectoryResource\Directory;
use Medusa\FileSystem\DirectoryResource\Filter\DirectoryFilter;
use Medusa\FileSystem\FileResource\JsonFile;
use Medusa\Http\Simple\MessageInterface;
use function basename;
use function realpath;

/**
 * Class Service
 * @package medusa/api-resolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class Service {

    /**
     * @param MessageInterface $message
     * @param Container        $container
     * @return array
     */
    public static function getAll(MessageInterface $message, Container $container): array {

        $repoPath = $_SERVER['MEDUSA_API_SERVICE_REPOSITORY_PATH'];
        $servicesDirectory = $repoPath . '/services';
        $repoName = $_SERVER['MEDUSA_API_SERVICE_REPOSITORY'];
        $dir = new Directory($servicesDirectory);
        $endpoints = [];

        foreach ($dir->getResources((new DirectoryFilter())->setMaxDepth(1)) as $possibleServiceDirectory) {

            $conf = new JsonFile($possibleServiceDirectory->getLocation() . '/conf.d/env.json');

            if (!$conf->exists()) {
                continue;
            }

            $parent = basename(realpath($possibleServiceDirectory->getLocation() . '/../'));
            $endpoints[] = $parent . '/' . basename($possibleServiceDirectory->getLocation());
        }

        return [
            'repository' => $repoName,
            'services'   => $endpoints,
        ];
    }
}
