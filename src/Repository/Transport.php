<?php

declare(strict_types=1);

namespace Xakki\Emailer\Repository;

class Transport extends AbstractRepository
{
    /**
     * The project's transport for a message to $domainId of a campaign bound to
     * transport $id (see routeRank()).
     *
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
        $query->select('*', self::routeRank('domain_id', 'id', ':domain_id', ':id') . ' as ord');
        $query->where('project_id = :pid');
        $query->setParameters(['pid' => $projectId, 'domain_id' => $domainId, 'id' => $id]);
        $query->orderBy('ord', 'DESC')->addOrderBy('id', 'ASC');
        return static::getRowByQuery($query);
    }

    /**
     * Correlated subquery: the id of the transport find() picks for the queue
     * row of the outer query, via the row's email domain and campaign — NULL
     * when the project has no transport or the email / campaign row is missing
     * (PHP cannot route such a row either). Lets the queue selection exclude a
     * paused transport's rows in SQL.
     */
    public static function routedIdSubquery(string $queueTable): string
    {
        return '(SELECT rt.id FROM ' . static::tableName() . ' rt, '
            . Email::tableName() . ' re, ' . Campaign::tableName() . ' rc'
            . ' WHERE rt.project_id = ' . $queueTable . '.project_id'
            . ' AND re.id = ' . $queueTable . '.email_id'
            . ' AND rc.id = ' . $queueTable . '.campaign_id'
            . ' ORDER BY ' . self::routeRank('rt.domain_id', 'rt.id', 're.domain_id', 'rc.transport_id')
            . ' DESC, rt.id ASC LIMIT 1)';
    }

    /**
     * Routing rank, highest wins: the transport bound to the recipient's
     * domain (2), then the campaign's own transport (1), then any other
     * transport of the project (0); ties go to the lowest id. The single
     * definition behind find() and routedIdSubquery(), so PHP-side and
     * SQL-side routing cannot drift apart.
     */
    private static function routeRank(string $domainIdCol, string $idCol, string $domainId, string $transportId): string
    {
        return 'CASE WHEN ' . $domainIdCol . ' = ' . $domainId . ' THEN 2'
            . ' WHEN ' . $idCol . ' = ' . $transportId . ' THEN 1 ELSE 0 END';
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
