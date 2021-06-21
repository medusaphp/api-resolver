<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver\InternalAdminInterface\DAO;

use Medusa\App\ApiResolver\Container;
use Medusa\App\ApiResolver\Database\MedusaPDO;
use Medusa\App\ApiResolver\InternalApi\Utils;
use function array_filter;
use function dd;
use function sprintf;

/**
 * @package medusa/api-resolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class AdminUserDAO {

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
            'DELETE FROM %1$swebadminaccount WHERE webadminaccount_id = :webadminaccount_id',
            $this->container->get('tablePrefix')
        );

        $db->query($sql, array_filter(
            [
                'webadminaccount_id' => $userId,
            ]));

        return $this->get($userId);
    }

    /**
     * @return array
     */
    public function getAll(): array {
        /** @var MedusaPDO $db */
        $db = $this->container->get(MedusaPDO::class);
        $sql = sprintf(
            'SELECT * FROM %1$swebadminaccount',
            $this->container->get('tablePrefix')
        );

        $rows = $db->getAllAsAssocArray(
            $sql
        );

        foreach ($rows as &$row) {
            $row = Utils::arrayExtract($row, 'webadminaccount_', true);
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
        $db->update($this->container->get('tablePrefix') . 'webadminaccount', $fields, ['webadminaccount_id' => $userId]);
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
            'SELECT * FROM %1$swebadminaccount WHERE webadminaccount_id = :userId',
            $this->container->get('tablePrefix')
        );

        $row = $db->getRowAsAssocArray($sql,
                                       [
                                           'userId' => $userId,
                                       ]);

        if ($row) {
            $row = Utils::arrayExtract($row, 'webadminaccount_', true);
            $row['enabled'] = $row['enabled'] === 1;
        }

        return $row;
    }

    public function getEnabledByName(string $username): array {

        /** @var MedusaPDO $db */
        $db = $this->container->get(MedusaPDO::class);

        $sql = sprintf(
            'SELECT * FROM %1$swebadminaccount WHERE webadminaccount_enabled = true AND webadminaccount_username = :userName',
            $this->container->get('tablePrefix')
        );

        $row = $db->getRowAsAssocArray($sql,
                                       [
                                           'userName' => $username,
                                       ]);

        if ($row) {
            $row = Utils::arrayExtract($row, 'webadminaccount_', true);
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
        $userId = $db->insert($this->container->get('tablePrefix') . 'webadminaccount', $fields);
        return $this->get($userId);
    }
}
