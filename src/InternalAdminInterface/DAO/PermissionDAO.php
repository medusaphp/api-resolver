<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver\InternalApi\DAO;

use Medusa\App\ApiResolver\Container;
use Medusa\App\ApiResolver\Database\MedusaPDO;
use Medusa\App\ApiResolver\InternalApi\Utils;
use function array_filter;
use function sprintf;

/**
 * Class PermissionDAO
 * @package medusa/api-resolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class PermissionDAO {

    /**
     * PermissionDAO constructor.
     * @param Container $container
     */
    public function __construct(private Container $container) {

    }

    /**
     * @param int      $userId
     * @param int|null $permissionId
     * @return array
     */
    public function delete(int $userId, ?int $permissionId = null): array {
        /** @var MedusaPDO $db */
        $db = $this->container->get(MedusaPDO::class);

        $sql = sprintf(
            'DELETE FROM %1$suserpermission WHERE userpermission_account_id = :userId',
            $this->container->get('tablePrefix')
        );

        if ($permissionId !== null) {
            $sql .= ' AND userpermission_id = :permissionId';
        }

        $db->query($sql, array_filter(
            [
                'permissionId' => $permissionId,
                'userId'       => $userId,
            ]));

        return $this->getAll($userId);
    }

    /**
     * @param int $userId
     * @return array
     */
    public function getAll(int $userId): array {

        /** @var MedusaPDO $db */
        $db = $this->container->get(MedusaPDO::class);
        $sql = sprintf(
            'SELECT * FROM %1$suserpermission WHERE  userpermission_account_id = :userId',
            $this->container->get('tablePrefix')
        );

        $rows = $db->getAllAsAssocArray(
                $sql,
                [
                    'userId' => $userId,
                ]
            ) ?? [];

        foreach ($rows as &$row) {
            $row = Utils::arrayExtract($row, 'userpermission_', true);
            $row['enabled'] = $row['enabled'] === 1;
        }

        return $rows;
    }

    /**
     * @param array $fields
     * @param int   $userId
     * @return array
     */
    public function insert(array $fields, int $userId): array {
        /** @var MedusaPDO $db */
        $db = $this->container->get(MedusaPDO::class);
        $fields['userpermission_account_id'] = $userId;
        $ipId = $db->insert($this->container->get('tablePrefix') . 'userpermission', $fields);
        return $this->get($userId, $ipId);
    }

    /**
     * @param int $userId
     * @param int $permissionId
     * @return array
     */
    public function get(int $userId, int $permissionId): array {

        /** @var MedusaPDO $db */
        $db = $this->container->get(MedusaPDO::class);

        $sql = sprintf(
            'SELECT * FROM %1$suserpermission WHERE userpermission_account_id = :userId AND userpermission_id = :permissionId',
            $this->container->get('tablePrefix')
        );

        $row = $db->getRowAsAssocArray($sql,
                                       [
                                           'userId'       => $userId,
                                           'permissionId' => $permissionId,
                                       ]);

        if ($row) {
            $row = Utils::arrayExtract($row, 'userpermission_', true);
            $row['enabled'] = $row['enabled'] === 1;
        }

        return $row;
    }

    /**
     * @param array $fields
     * @param int   $userId
     * @param int   $permissionId
     * @return array
     */
    public function update(array $fields, int $userId, int $permissionId): array {
        /** @var MedusaPDO $db */
        $db = $this->container->get(MedusaPDO::class);
        $d = $db->update($this->container->get('tablePrefix') . 'userpermission', $fields, [
            'userpermission_id'         => $permissionId,
            'userpermission_account_id' => $userId,
        ]);
        return $this->get($userId, $permissionId);
    }
}
