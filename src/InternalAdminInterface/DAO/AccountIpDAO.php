<?php declare(strict_types = 1);
namespace Medusa\App\ApiResolver\InternalApi\DAO;

use Medusa\App\ApiResolver\Container;
use Medusa\App\ApiResolver\Database\MedusaPDO;
use Medusa\App\ApiResolver\InternalApi\Utils;
use function array_filter;
use function sprintf;

/**
 * Class AccountIpDAO
 * @package medusa/api-resolver
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class AccountIpDAO {

    /**
     * AccountIpDAO constructor.
     * @param Container $container
     */
    public function __construct(private Container $container) {

    }

    /**
     * @param int      $userId
     * @param int|null $ipId
     * @return array
     */
    public function delete(int $userId, ?int $ipId = null): array {
        /** @var MedusaPDO $db */
        $db = $this->container->get(MedusaPDO::class);

        $sql = sprintf(
            'DELETE FROM %1$saccountip WHERE accountip_account_id = :accountip_account_id',
            $this->container->get('tablePrefix')
        );

        if ($ipId !== null) {
            $sql .= ' AND accountip_id = :accountip_id';
        }

        $db->query($sql, array_filter(
            [
                'accountip_id'         => $ipId,
                'accountip_account_id' => $userId,
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
            'SELECT * FROM %1$saccountip WHERE  accountip_account_id = :userId',
            $this->container->get('tablePrefix')
        );

        $rows = $db->getAllAsAssocArray(
                $sql,
                [
                    'userId' => $userId,
                ]
            ) ?? [];

        foreach ($rows as &$row) {
            $row = Utils::arrayExtract($row, 'accountip_', true);
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
        $fields['accountip_account_id'] = $userId;
        $ipId = $db->insert($this->container->get('tablePrefix') . 'accountip', $fields);
        return $this->get($userId, $ipId);
    }

    /**
     * @param int $userId
     * @param int $ipId
     * @return array
     */
    public function get(int $userId, int $ipId): array {

        /** @var MedusaPDO $db */
        $db = $this->container->get(MedusaPDO::class);

        $sql = sprintf(
            'SELECT * FROM %1$saccountip WHERE accountip_account_id = :userId AND accountip_id = :ipId',
            $this->container->get('tablePrefix')
        );

        $row = $db->getRowAsAssocArray($sql,
                                       [
                                           'userId' => $userId,
                                           'ipId'   => $ipId,
                                       ]);

        if ($row) {
            $row = Utils::arrayExtract($row, 'accountip_', true);
            $row['enabled'] = $row['enabled'] === 1;
        }

        return $row;
    }

    /**
     * @param array $fields
     * @param int   $userId
     * @param int   $ipId
     * @return array
     */
    public function update(array $fields, int $userId, int $ipId): array {
        /** @var MedusaPDO $db */
        $db = $this->container->get(MedusaPDO::class);
        $d = $db->update($this->container->get('tablePrefix') . 'accountip', $fields, [
            'accountip_id'         => $ipId,
            'accountip_account_id' => $userId,
        ]);
        return $this->get($userId, $ipId);
    }
}
