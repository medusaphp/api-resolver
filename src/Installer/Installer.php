<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver\Installer;

use Medusa\Http\Simple\MessageInterface;
use Medusa\Http\Simple\Request;
use Medusa\Http\Simple\Response;
use Throwable;
use function dd;
use function file_exists;
use function str_starts_with;

/**
 * Class Installer
 * @author Pascale Schnell <pascale.schnell@check24.de>
 */
class Installer {

    private string $pathToConfigFile;

    /**
     * @return string
     */
    public function getPathToConfigFile(): string {
        return $this->pathToConfigFile;
    }

    public function __construct(string $pathToConfigFile) {

        if (!str_starts_with($pathToConfigFile, '/')) {
            throw new Exception('Config file must be absolute');
        }

        if (file_exists($pathToConfigFile)) {
            throw new Exception('Config file already exists');
        }

        $this->pathToConfigFile = $pathToConfigFile;
    }

    public function start(?MessageInterface $request = null): Response {

        try {
            $request ??= Request::createFromGlobals();

            $webInstaller = new WebInstaller($this);
            $response = $webInstaller->handleRequest($request);


            return $response;

            //            $protocol = $request->getProtocolVersion();
        } catch (Throwable $exception) {

            dd($exception);
        }
    }
}
//'../conf.d/env.json'