<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver;

use Medusa\App\ApiResolver\Database\MedusaPDO;
use Medusa\Http\Simple\Exception\ServerException;
use Medusa\Http\Simple\MessageInterface;
use function explode;
use function ip2long;
use function is_string;
use function password_verify;
use function sprintf;

/**
 * Class Doorman
 * @package medusa/api-resolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class Doorman {

    private MedusaPDO $db;

    /**
     * @param MessageInterface $request
     * @param ResolverConfig   $resolverConfig
     * @param ServiceConfig    $serviceConfig
     * @return bool
     * @throws ServerException
     */
    public static function accessAllowed(MessageInterface $request, ResolverConfig $resolverConfig, ServiceConfig $serviceConfig): bool {
        $self = new self();
        $config = $resolverConfig->getDb();
        $dsn = sprintf(
            'mysql:port=%d;host=%s;dbname=%s;charset=utf8mb4',
            $config['port'],
            $config['host'],
            $config['db'],
        );
        $self->db = new MedusaPDO($dsn, $config['user'], $config['pass']);

        return $self->hasAccess($request, $resolverConfig, $serviceConfig);
    }

    /**
     * @param MessageInterface $request
     * @param ResolverConfig   $resolverConfig
     * @param ServiceConfig    $serviceConfig
     * @return bool
     * @throws ServerException
     */
    public function hasAccess(MessageInterface $request, ResolverConfig $resolverConfig, ServiceConfig $serviceConfig): bool {
        if ($serviceConfig->getAccessType() === 'int') {
            return $this->checkInternalAccessAllowed($request, $resolverConfig);
        }
        return $this->checkAccess($request, $resolverConfig, $serviceConfig);
    }

    /**
     * @param MessageInterface $request
     * @param ResolverConfig   $resolverConfig
     * @return bool
     */
    private function checkInternalAccessAllowed(MessageInterface $request, ResolverConfig $resolverConfig): bool {
        $remoteAddressAsLong = ip2long($request->getRemoteAddress());
        foreach ($resolverConfig->getInternalNetworks() as $ipCIDR) {
            [$networkAddress, $CIDR] = explode('/', $ipCIDR);
            $addressAsLong = ip2long($networkAddress);
            $ipMask = ~((1 << (32 - $CIDR)) - 1);

            if ($addressAsLong === ($remoteAddressAsLong & $ipMask)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param MessageInterface $request
     * @param ResolverConfig   $resolverConfig
     * @param ServiceConfig    $config
     * @return bool
     * @throws Exception\DatabaseConnectionException
     * @throws Exception\DatabaseException
     * @throws Exception\DatabaseHandlerException
     * @throws Exception\StatementException
     * @throws ServerException
     */
    private function checkAccess(MessageInterface $request, ResolverConfig $resolverConfig, ServiceConfig $config): bool {

        $user = $_SERVER['PHP_AUTH_USER'];
        $pass = $_SERVER['PHP_AUTH_PW'];

        if (!is_string($user) || !is_string($pass)) {
            throw new ServerException('user and password must be string');
        }

        $controllerDirectoryBasename = $config->getControllerDirectoryBasename();
        $sql = 'SELECT *
FROM %1$saccount
INNER JOIN %1$suserpermission
ON userpermission_account_id = account_id
INNER JOIN %1$saccountip
ON accountip_account_id = account_id
WHERE 
      account_enabled = true
  AND accountip_enabled = true
  AND userpermission_enabled = true
  AND userpermission_service = :service
  AND account_username = :user
  AND accountip_ip = :ip
';
        $sql = sprintf($sql, $resolverConfig->getDb()['tablePrefix'] ?? '');
        $result = $this->db->getRowAsAssocArray($sql, [
            'service' => $controllerDirectoryBasename,
            'user'    => $user,
            'ip'      => $request->getRemoteAddress(),
        ]);

        if (!$result) {
            return false;
        }

        if ($pass === '__NO_USER__') {
            return $result['account_password'] === $pass;
        }

        return password_verify($pass, $result['account_password']);
    }
}
