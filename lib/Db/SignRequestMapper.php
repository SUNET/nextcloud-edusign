<?php

declare(strict_types=1);
// SPDX-FileCopyrightText: Mikael Nordin <kano@sunet.se>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Edusign\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<SignRequest>
 */
class SignRequestMapper extends QBMapper
{
    public const TABLE_NAME = 'edusign_sign_requests';

    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, self::TABLE_NAME, SignRequest::class);
    }

    /**
     * @throws DoesNotExistException if no row matches
     * @throws MultipleObjectsReturnedException
     */
    public function findByUuid(string $uuid): SignRequest
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('uuid', $qb->createNamedParameter($uuid, IQueryBuilder::PARAM_STR)));
        return $this->findEntity($qb);
    }

    /**
     * @throws DoesNotExistException if no row matches
     * @throws MultipleObjectsReturnedException
     */
    public function findByRelayState(string $relayState): SignRequest
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('relay_state', $qb->createNamedParameter($relayState, IQueryBuilder::PARAM_STR)));
        return $this->findEntity($qb);
    }

    /**
     * Delete every request created strictly before the given unix timestamp.
     *
     * @return int number of rows removed
     */
    public function deleteOlderThan(int $timestamp): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->lt('created_at', $qb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT)));
        return $qb->executeStatement();
    }
}
