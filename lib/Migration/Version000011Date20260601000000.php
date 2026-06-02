<?php

declare(strict_types=1);
// SPDX-FileCopyrightText: Mikael Nordin <kano@sunet.se>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Edusign\Migration;

use Closure;
use OCA\Edusign\AppInfo\Application;
use OCA\Edusign\BackgroundJob\CleanupJob;
use OCA\Edusign\Db\SignRequestMapper;
use OCP\BackgroundJob\IJobList;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Introduces the edusign_sign_requests table and moves the transient signing
 * request state that used to live in appconfig (the eduid-path-*,
 * eduid-redirect-uri-* and eduid-uid-* keys) into it.
 */
class Version000011Date20260601000000 extends SimpleMigrationStep
{
    public function __construct(
        private IDBConnection $db,
        private IAppConfig $appConfig,
        private IJobList $jobList,
    ) {
    }

    /**
     * @param Closure(): ISchemaWrapper $schemaClosure
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable(SignRequestMapper::TABLE_NAME)) {
            return null;
        }

        $table = $schema->createTable(SignRequestMapper::TABLE_NAME);
        $table->addColumn('id', Types::BIGINT, [
            'autoincrement' => true,
            'notnull' => true,
            'length' => 20,
        ]);
        $table->addColumn('uuid', Types::STRING, [
            'notnull' => false,
            'length' => 64,
        ]);
        $table->addColumn('relay_state', Types::STRING, [
            'notnull' => false,
            'length' => 255,
        ]);
        $table->addColumn('uid', Types::STRING, [
            'notnull' => false,
            'length' => 64,
        ]);
        $table->addColumn('path', Types::TEXT, [
            'notnull' => false,
        ]);
        $table->addColumn('redirect_uri', Types::TEXT, [
            'notnull' => false,
        ]);
        $table->addColumn('created_at', Types::BIGINT, [
            'notnull' => true,
            'default' => 0,
            'length' => 20,
        ]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['uuid'], 'edusign_sr_uuid_idx');
        $table->addIndex(['relay_state'], 'edusign_sr_relay_idx');
        $table->addIndex(['created_at'], 'edusign_sr_created_idx');

        return $schema;
    }

    /**
     * Copy the existing eduid-* appconfig values into the new table and then
     * remove them from appconfig.
     *
     * The original code stored three independent key spaces:
     *   eduid-path-<uuid>          -> file path
     *   eduid-redirect-uri-<uuid>  -> redirect uri
     *   eduid-uid-<relay_state>    -> user id
     *
     * The uuid and relay_state were only ever joined in memory, so the link is
     * not recoverable from appconfig. We therefore migrate values faithfully but
     * best-effort: one row per uuid (path + redirect_uri) and one row per
     * relay_state (uid). Anything left from never-completed flows is stale and
     * will be removed by `occ edusign:cleanup`.
     *
     * @param Closure(): ISchemaWrapper $schemaClosure
     */
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
    {
        $appId = Application::APP_ID;
        $keys = $this->appConfig->getKeys($appId);

        // Group by uuid so path + redirect_uri land on the same row.
        $byUuid = [];
        $byRelayState = [];

        foreach ($keys as $key) {
            if (str_starts_with($key, 'eduid-path-')) {
                $uuid = substr($key, strlen('eduid-path-'));
                $byUuid[$uuid]['path'] = $this->appConfig->getValueString($appId, $key);
            } elseif (str_starts_with($key, 'eduid-redirect-uri-')) {
                $uuid = substr($key, strlen('eduid-redirect-uri-'));
                $byUuid[$uuid]['redirect_uri'] = $this->appConfig->getValueString($appId, $key);
            } elseif (str_starts_with($key, 'eduid-uid-')) {
                $relayState = substr($key, strlen('eduid-uid-'));
                $byRelayState[$relayState] = $this->appConfig->getValueString($appId, $key);
            }
        }

        $now = time();
        $migrated = 0;

        foreach ($byUuid as $uuid => $row) {
            $this->insertRow(
                uuid: $uuid,
                relayState: null,
                uid: null,
                path: $row['path'] ?? null,
                redirectUri: $row['redirect_uri'] ?? null,
                createdAt: $now,
            );
            $migrated++;
        }

        foreach ($byRelayState as $relayState => $uid) {
            $this->insertRow(
                uuid: null,
                relayState: $relayState,
                uid: $uid,
                path: null,
                redirectUri: null,
                createdAt: $now,
            );
            $migrated++;
        }

        // Remove the migrated keys from appconfig.
        foreach ($keys as $key) {
            if (
                str_starts_with($key, 'eduid-path-')
                || str_starts_with($key, 'eduid-redirect-uri-')
                || str_starts_with($key, 'eduid-uid-')
            ) {
                $this->appConfig->deleteKey($appId, $key);
            }
        }

        $output->info(sprintf('Migrated %d eduSign signing-request entries out of appconfig.', $migrated));

        // Schedule the recurring cleanup of incomplete/abandoned requests.
        // IJobList::add() is a no-op when the job is already registered.
        $this->jobList->add(CleanupJob::class);
    }

    private function insertRow(
        ?string $uuid,
        ?string $relayState,
        ?string $uid,
        ?string $path,
        ?string $redirectUri,
        int $createdAt,
    ): void {
        $qb = $this->db->getQueryBuilder();
        $qb->insert(SignRequestMapper::TABLE_NAME)
            ->values([
                'uuid' => $qb->createNamedParameter($uuid),
                'relay_state' => $qb->createNamedParameter($relayState),
                'uid' => $qb->createNamedParameter($uid),
                'path' => $qb->createNamedParameter($path),
                'redirect_uri' => $qb->createNamedParameter($redirectUri),
                'created_at' => $qb->createNamedParameter($createdAt, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
            ]);
        $qb->executeStatement();
    }
}
