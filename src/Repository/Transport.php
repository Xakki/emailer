<?php

declare(strict_types=1);

namespace Xakki\Emailer\Repository;

class Transport extends AbstractRepository
{
    /**
     * @param int $projectId
     * @param int $domainId
     * @param int|null $id
     * @return array<string, mixed>
     * @throws \Doctrine\DBAL\Exception
     * @throws \Xakki\Emailer\Exception\Exception
     */
    public static function find(int $projectId, int $domainId, ?int $id): array
    {
        $query = static::createQueryBuilder();
        $query->select('*', 'IF(domain_id=:domain_id, 2, IF(id=:id, 1, 0)) as ord');
        $query->where('project_id = :pid');
        $query->setParameters(['pid' => $projectId, 'domain_id' => $domainId, 'id' => $id]);
        $query->orderBy('ord', 'DESC');
        return static::getRowByQuery($query);
    }

    protected static function tableName(): string
    {
        return 'transport';
    }

    /**
     * @return array<string, int>
     */
    protected static function getRules(): array
    {
        return [
            static::pkName() => static::TYPE_INT,
            'limit_day' => static::TYPE_INT,
            'cnt_day' => static::TYPE_INT,
            'domain_id' => static::TYPE_INT,
            'project_id' => static::TYPE_INT,
        ];
    }
}
