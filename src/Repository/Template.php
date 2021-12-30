<?php

declare(strict_types=1);

namespace Xakki\Emailer\Repository;

class Template extends AbstractRevision
{
//    protected static bool $revisionFieldChange = [
//        'html'
//    ];
    /**
     * @param int $projectId
     * @param string $type
     * @return string[]
     */
    public static function getTplNameBlocks(int $projectId, string $type): array
    {
        $query = static::createQueryBuilder();
        $query->select('name');
        $query->where('project_id = :project_id AND type = :type');
        $query->setParameters(['project_id' => $projectId, 'type' => $type]);
        $result = $query->executeQuery();
        return $result->fetchFirstColumn();
    }

    protected static function tableNameMain(): string
    {
        return 'tpl';
    }

    /**
     * @return array<string, int>
     */
    protected static function getRules(): array
    {
        return [
            static::pkName() => static::TYPE_INT,
            'base_id' => static::TYPE_INT,
            'project_id' => static::TYPE_INT,
        ];
    }
}
