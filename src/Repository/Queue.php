<?php

declare(strict_types=1);

namespace Xakki\Emailer\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Query\QueryBuilder;

class Queue extends AbstractRepository
{
    protected static function tableName(): string
    {
        return 'queue';
    }

    /**
     * Rows due for a geometric-backoff retry: status is $status AND retry_at has
     * arrived. Every pre-backoff row (legacy backlog, any retry value) has
     * retry_at = NULL from the additive migration, so historical stuck mail is
     * never swept up here — `retry_at <= :now` alone already excludes it under
     * standard SQL three-valued NULL comparison (NULL <= x is NULL, never true,
     * confirmed identically on SQLite and MySQL). `retry_at IS NOT NULL` is kept
     * anyway to make that exclusion an explicit, readable part of the query
     * rather than an implicit consequence a future edit could lose.
     *
     * @param list<int> $skipIds
     * @param list<int> $skipTransportIds
     * @return array<string, mixed>
     * @throws \Xakki\Emailer\Exception\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public static function findOneForRepeat(
        int $status,
        \DateTimeInterface $now,
        bool $selectForUpdate = false,
        array $skipIds = [],
        array $skipTransportIds = [],
    ): array {
        $query = static::createQueryBuilder();
        $query->andWhere('status = :status')
            ->setParameter('status', $status)
            ->andWhere('retry_at IS NOT NULL')
            ->andWhere('retry_at <= :now')
            ->setParameter('now', $now->format('Y-m-d H:i:s'));
        static::skip($query, $skipIds, $skipTransportIds);

        return static::getRowByQuery($query, $selectForUpdate);
    }

    /**
     * Next row with $status, minus the rows / transports the current run has
     * set aside (see Cqrs\Queue\TransportPause).
     *
     * @param list<int> $skipIds
     * @param list<int> $skipTransportIds
     * @return array<string, mixed>
     * @throws \Xakki\Emailer\Exception\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public static function findOneByStatus(
        int $status,
        bool $selectForUpdate = false,
        array $skipIds = [],
        array $skipTransportIds = [],
    ): array {
        $query = static::createQueryBuilder();
        $query->andWhere('status = :status')
            ->setParameter('status', $status);
        static::skip($query, $skipIds, $skipTransportIds);

        return static::getRowByQuery($query, $selectForUpdate);
    }

    /**
     * Excludes the given rows, and every row routed to one of the given
     * transports — in the selection itself, so a paused transport's backlog
     * costs no query per row (0: no transport; such a row is never excluded).
     *
     * @param list<int> $skipIds
     * @param list<int> $skipTransportIds
     */
    protected static function skip(QueryBuilder $query, array $skipIds, array $skipTransportIds): void
    {
        if ($skipIds) {
            $query->andWhere('id NOT IN (:skip_ids)')
                ->setParameter('skip_ids', $skipIds, ArrayParameterType::INTEGER);
        }
        if ($skipTransportIds) {
            $query->andWhere('COALESCE(' . Transport::routedIdSubquery(static::tableName()) . ', 0) NOT IN (:skip_transport_ids)')
                ->setParameter('skip_transport_ids', $skipTransportIds, ArrayParameterType::INTEGER);
        }
    }

    /**
     * @return array<string, int>
     */
    protected static function getRules(): array
    {
        return [
            static::pkName() => static::TYPE_INT,
            'status' => static::TYPE_INT,
            'retry' => static::TYPE_INT,
            'project_id' => static::TYPE_INT,
            'campaign_id' => static::TYPE_INT,
            'notify_id' => static::TYPE_INT,
            'email_id' => static::TYPE_INT,
        ];
    }
}
