<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver\InternalAdminInterface\DAO;

use Medusa\App\ApiResolver\Container;
use Medusa\App\ApiResolver\Database\MedusaPDO;
use Medusa\App\ApiResolver\InternalApi\Utils;
use function array_filter;
use function sprintf;

/**
 * Class UserDAO
 * @package medusa/api-resolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class UserDAO {

    public function __construct(private Container $container) {

    }

    /**
     * @param int $userId
     * @return array
     */
    public function delete(int $userId): array {
        /** @var MedusaPDO $db */
        $db = $this->container->get(MedusaPDO::class);

        $sql = sprintf(
            'DELETE FROM %1$saccount WHERE account_id = :account_id',
            $this->container->get('tablePrefix')
        );

        $db->query($sql, array_filter(
            [
                'account_id' => $userId,
            ]));

        (new AccountIpDAO($this->container))->delete($userId);
        (new PermissionDAO($this->container))->delete($userId);
        return $this->getAll($userId);
    }

    /**
     * @return array
     */
    public function getAll(): array {
        /** @var MedusaPDO $db */
        $db = $this->container->get(MedusaPDO::class);
        $sql = sprintf(
            'SELECT * FROM %1$saccount',
            $this->container->get('tablePrefix')
        );

        $rows = $db->getAllAsAssocArray(
            $sql
        );

        foreach ($rows as &$row) {
            $row = Utils::arrayExtract($row, 'account_', true);
            $row['enabled'] = $row['enabled'] === 1;
        }

        return $rows;
    }

    /**
     * @param array $fields
     * @param       $userId
     * @return array
     */
    public function update(array $fields, $userId): array {
        /** @var MedusaPDO $db */
        $db = $this->container->get(MedusaPDO::class);
        $db->update($this->container->get('tablePrefix') . 'account', $fields, ['account_id' => $userId]);
        return $this->get($userId);
    }

    /**
     * @param int $userId
     * @return array
     */
    public function get(int $userId): array {

        /** @var MedusaPDO $db */
        $db = $this->container->get(MedusaPDO::class);

        $sql = sprintf(
            'SELECT * FROM %1$saccount WHERE account_id = :userId',
            $this->container->get('tablePrefix')
        );

        $row = $db->getRowAsAssocArray($sql,
                                       [
                                           'userId' => $userId,
                                       ]);

        if ($row) {
            $row = Utils::arrayExtract($row, 'account_', true);
            $row['enabled'] = $row['enabled'] === 1;
        }

        return $row;
    }

    /**
     * @param array $fields
     * @return array
     */
    public function insert(array $fields): array {
        /** @var MedusaPDO $db */
        $db = $this->container->get(MedusaPDO::class);
        $userId = $db->insert($this->container->get('tablePrefix') . 'account', $fields);
        return $this->get($userId);
    }
}
