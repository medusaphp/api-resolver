<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver\Installer;

use Medusa\App\ApiResolver\Container;
use Medusa\App\ApiResolver\Database\MedusaPDO;
use Medusa\App\ApiResolver\InternalAdminInterface\Controller\Login;
use Medusa\App\ApiResolver\ResolverConfig;
use Medusa\FileSystem\DirectoryResource\Directory;
use Medusa\FileSystem\DirectoryResource\Filter\DirectoryFileFilter;
use Medusa\FileSystem\FileResource\JsonFile;
use Medusa\Http\Simple\MessageInterface;
use Medusa\Http\Simple\Response;
use PDO;
use Throwable;
use function array_merge;
use function array_search;
use function array_values;
use function basename;
use function call_user_func;
use function current;
use function dd;
use function dirname;
use function explode;
use function hash;
use function in_array;
use function is_array;
use function is_string;
use function is_writable;
use function json_encode;
use function mb_substr;
use function Medusa\Http\getRemoteAddress;
use function method_exists;
use function preg_match;
use function sprintf;
use function str_replace;
use function var_dump;

/**
 * Class InternalAdminInterface
 * @package Medusa\App\ApiResolver\InternalAdminInterface
 * @author  Pascale Schnell <pascale.schnell@check24.de>
 */
class WebInstaller {

    private array $routes    = [

    ];
    private array $checks    = [];
    private array $questions = [];
    private array $steps     = [
        'Init',
        'Db',
        'CreateTables',
        'FinalizeConfig',
    ];

    public function __construct(private Installer $installer) {

    }

    public function handleRequest(MessageInterface $request): Response {

        $currentStep = $_GET['currentStep'] ?? null;

        if (!is_string($currentStep) || !in_array($currentStep, $this->steps)) {
            $currentStep = current($this->steps);
        } else {
            do {
            } while ($currentStep !== next($this->steps));

            $currentStep = current($this->steps);
        }

        $step = 'handleStep' . $currentStep;
        $view = new View('Main');

        $error = '';
        try {
            $result = call_user_func([$this, $step], $request);
        } catch (\Throwable $exception) {
            $result = '';
            $error = $exception->getMessage();
        }

        if ($result instanceof Response) {
            return $result;
        } elseif ($result === true) {
            $result = '';
        }

        $nextStep = next($this->steps);

        if ($nextStep === false) {
            $request->getUri()->setPath('/__admin__/');
        } else {
            $requestUri = $request->getUri()->setQuery([
                                                           'currentStep' => $nextStep,
                                                       ]);
        }

        $view->assign('nextStep', (string)$requestUri);
        $result = $view
            ->assign('error', $error)
            ->assign('content', $result)
            ->assign('checks', $this->checks)
            ->assign('questions', $this->questions)
            ->render();
        return new Response([
                                'Content-Type: text/html',
                            ], $result, 200, null, $request->getProtocolVersion());
    }

    public function handleStepDb(MessageInterface $request): string|bool {
        $this->ask('username', 'DB Username', fn() => $this->getTempConfig()->getData()['database']['user'] ?? '', 'text'
        )->ask('password', 'DB password', fn() => $this->getTempConfig()->getData()['database']['pass'] ?? '', 'text'
        )->ask('host', 'DB host', fn() => $this->getTempConfig()->getData()['database']['host'] ?? '', 'text'
        )->ask('database', 'DB database', fn() => $this->getTempConfig()->getData()['database']['db'] ?? '', 'text'
        )->ask('port', 'DB port', fn() => $this->getTempConfig()->getData()['database']['port'] ?? '', 'text'
        )->ask('tablePrefix', 'DB tableprefix', fn() => $this->getTempConfig()->getData()['database']['tablePrefix'] ?? '', 'text'
        );
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            //            dd($_POST);

            (function(string $username, string $password, string $database, string &$port, string $host, string $tablePrefix) {

                if ((string)(int)$port !== $port) {
                    throw new \Exception('Invalid port');
                }

                $port = (int)$port;

                $dsn = sprintf(
                    'mysql:port=%d;host=%s;dbname=%s;charset=utf8mb4',
                    $port,
                    $host,
                    $database,
                );
                $pdo = new PDO($dsn, $username, $password);
            })(...$data);

            $this->addToTempConfig([
                                       'database' => [
                                           'tablePrefix' => $data['tablePrefix'],
                                           'user'        => $data['username'],
                                           'pass'        => $data['password'],
                                           'db'          => $data['database'],
                                           'host'        => $data['host'],
                                           'port'        => $data['port'],
                                           'charset'     => 'utf8mb4',
                                       ],
                                   ]);

            return '<h3>DATEN GESPEICHERT UND VERIFIZIERT - BITTE WEITER DRÜCKEN</h3>';
        }

        return true;
    }

    private function ask(string $var, string $desc, callable $postCallback, string $type) {

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $prefill = $_POST[$var];
        } else {
            $prefill = call_user_func($postCallback);
        }

        $this->questions[] = [
            'name'  => $var,
            'label' => $desc,
            'type'  => $type,
            'value' => $prefill,
        ];
        return $this;
    }

    private function getTempConfig() {
        static $tempConf;

        if (!$tempConf) {
            $confTemp = $this->installer->getPathToConfigFile() . '.tmp';
            /** @var JsonFile $tempConf */
            $tempConf = JsonFile::create($confTemp);
        }

        return $tempConf;
    }

    private function addToTempConfig(array $data) {

        $tempConf = $this->getTempConfig();
        $data = array_merge($tempConf->getData(), $data);
        $tempConf->setData($data)->save();
    }

    public function handleStepCreateTables(MessageInterface $request): Response|string|array|bool {

        $config = $this->getTempConfig()->getData()['database'];
        $dsn = sprintf(
            'mysql:port=%d;host=%s;dbname=%s;charset=%s',
            $config['port'],
            $config['host'],
            $config['db'],
            $config['charset'],
        );

        $pdo = new PDO($dsn, $config['user'], $config['pass']);

        $sqlFiles = (new Directory(__DIR__ . '/Sql'))->getResources(
            (new DirectoryFileFilter())->setPattern('/\.sql$/')
        );

        $prefix = $config['tablePrefix'] ?? '';

        foreach ($sqlFiles as $file) {
            /** @var \Medusa\FileSystem\FileResource\File $file */
            $file->load();
            $fileBasename = basename($file->getLocation(), '.sql');
            $sql = str_replace('{{TABLE_PREFIX}}', $prefix, $file->getContent());
            $this->check('Create table ' . $fileBasename, fn() => $pdo->query($sql) !== false, null, false);
        }

        return true;
    }

    protected function check(string $desc, callable $checkFn, ?callable $onTrue = null, callable|null|bool $onFalse = null) {

        $res = false;
        $this->checks[] = [
            'desc' => $desc,
            'ok'   => &$res,
        ];

        try {
            $res = call_user_func($checkFn);
        } catch (\Throwable $exception) {
            $res = false;
        }

        if ($res && $onTrue) {
            call_user_func($onTrue);
        } elseif (!$res && $onFalse) {
            call_user_func($onFalse);
        } elseif (!$res && $onFalse === false) {
            throw new Exception('Installation failed');
        }
    }

    public function handleStepFinalizeConfig(MessageInterface $request): Response|string|array|bool {

        $config = $this->getTempConfig()->getData();

        $config['internalNetworks'] = [];
        $config['debugMode'] = true;
        $config['apiServer'] = [
            'accessSecret'       => hash('sha256', microtime(true) . '#' . getmypid() . '#' . mt_rand(0, 1000)),
            'ipAddressWhitelist' => [
                getRemoteAddress(),
            ],
        ];
        $config['adminInterface'] = [
            'enabled'       => true,
            'cryptPassword' => password_hash(hash('sha256', microtime(true) . '#' . getmypid() . '#' . mt_rand(2000, 3000)), PASSWORD_BCRYPT),
        ];
        $config['forwardingEnvVars'] = [];

        $this->check('Create env.json', function() use ($config) {
            /** @var JsonFile $envJson */
            $envJson = JsonFile::create($this->installer->getPathToConfigFile());
            $envJson->setData($config)->save();
            return true;
        }, function() {
            $this->check('Remove temp conf', function() {
                $this->getTempConfig()->unlink();
                return !$this->getTempConfig()->exists();
            });
        }, false);

        return true;
    }

    public function handleStepInit(MessageInterface $request): bool {

        $view = new \Medusa\App\ApiResolver\Installer\View('init');

        $dir = new Directory(dirname($this->installer->getPathToConfigFile()));
        $this->check('Config directory exists?',
            fn() => $dir->exists(),
            function() use ($dir) {
                $this->check('Config directory is writeable', fn() => is_writable($dir->getLocation()), null, false);
            },
            function() use ($dir) {
                $this->check('Try to create config directory', function() use ($dir) {
                    $dir->ensureExists();
                    return $dir->exists();
                }, null, function() {
                    throw new Exception('Installation failed');
                });
            }
        );

        return true;
    }
}