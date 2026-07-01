<?php
// file generated with AI assistance: Claude Code - 2025-11-22

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Service;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Service for resolving entities by full or partial UUID
 *
 * Supports both binary UUID storage (Symfony Uid with BINARY(16))
 * and string UUID storage (GUID/CHAR(36)).
 *
 * Usage:
 *   $entity = $uuidResolver->findByPartialUuid(MyEntity::class, '12527a4c');
 */
class UuidResolver
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Find entity by full or partial UUID
     *
     * @param string $entityClass The entity class name (e.g., MyEntity::class)
     * @param string $partialId Full UUID or partial UUID (e.g., first 8 characters)
     * @return object|null The entity if found and unique, null if not found
     * @throws \RuntimeException If partial UUID matches multiple entities (ambiguous)
     */
    public function findByPartialUuid(string $entityClass, string $partialId): ?object
    {
        // Try exact match first
        try {
            $uuid = Uuid::fromString($partialId);
            $entity = $this->entityManager->getRepository($entityClass)->find($uuid);
            if ($entity !== null) {
                return $entity;
            }
        } catch (\InvalidArgumentException $e) {
            // Not a valid full UUID, continue to partial match
        }

        // Determine if entity uses binary UUID or string UUID
        $metadata = $this->entityManager->getClassMetadata($entityClass);
        $idFieldType = $metadata->getTypeOfField('id');

        if ($idFieldType === 'guid') {
            // String UUID (CHAR(36)) - use DQL
            return $this->findByPartialStringUuid($entityClass, $partialId);
        } else {
            // Binary UUID (BINARY(16)) - use native SQL
            return $this->findByPartialBinaryUuid($entityClass, $partialId);
        }
    }

    /**
     * Find entity with UUID storage using native SQL.
     *
     * The Symfony `uuid` type stores the value differently per platform — a
     * BINARY(16) on MySQL/MariaDB, a native `uuid` column on PostgreSQL — so the
     * partial-prefix match must be built per platform (see {@see buildPartialUuidSql}).
     */
    private function findByPartialBinaryUuid(string $entityClass, string $partialId): ?object
    {
        $metadata = $this->entityManager->getClassMetadata($entityClass);
        $tableName = $metadata->getTableName();
        $idColumn = $metadata->getColumnName('id');

        $conn = $this->entityManager->getConnection();
        $sql = self::buildPartialUuidSql($conn->getDatabasePlatform(), $tableName, $idColumn);
        $stmt = $conn->prepare($sql);
        $stmt->bindValue('partialId', strtolower(str_replace('-', '', $partialId)) . '%');
        $resultSet = $stmt->executeQuery();
        $uuids = $resultSet->fetchAllAssociative();

        if (empty($uuids)) {
            return null;
        }

        if (count($uuids) > 1) {
            throw new \RuntimeException(
                sprintf('Ambiguous partial UUID "%s" matches multiple %s entries', $partialId, $entityClass)
            );
        }

        $fullUuid = Uuid::fromString($uuids[0]['uuid_str']);
        return $this->entityManager->getRepository($entityClass)->find($fullUuid);
    }

    /**
     * Build the native partial-UUID lookup SQL for the given platform.
     *
     * Both branches select the canonical hyphenated UUID string as `uuid_str`
     * and match the caller's hyphen-stripped, lower-cased hex prefix:
     *  - PostgreSQL: the column is a native `uuid`; cast to text and strip the
     *    hyphens (`REPLACE(LOWER(id::text), '-', '')`).
     *  - MySQL/MariaDB: the column is BINARY(16); `HEX()` yields the 32-char hex
     *    and `BIN_TO_UUID()` reads it back as a canonical string.
     *
     * Public + static so it can be unit-tested per platform without a database.
     */
    public static function buildPartialUuidSql(AbstractPlatform $platform, string $tableName, string $idColumn): string
    {
        if ($platform instanceof PostgreSQLPlatform) {
            return sprintf(
                "SELECT %2\$s::text AS uuid_str FROM %1\$s WHERE REPLACE(LOWER(%2\$s::text), '-', '') LIKE :partialId LIMIT 2",
                $tableName,
                $idColumn
            );
        }

        return sprintf(
            'SELECT BIN_TO_UUID(%2$s) AS uuid_str FROM %1$s WHERE LOWER(HEX(%2$s)) LIKE :partialId LIMIT 2',
            $tableName,
            $idColumn
        );
    }

    /**
     * Find entity with string UUID storage using DQL
     *
     * Uses LIKE operator on string UUID column
     */
    private function findByPartialStringUuid(string $entityClass, string $partialId): ?object
    {
        $repository = $this->entityManager->getRepository($entityClass);
        $qb = $repository->createQueryBuilder('e');
        $qb->where('e.id LIKE :id')
            ->setParameter('id', $partialId . '%')
            ->setMaxResults(2);

        $results = $qb->getQuery()->getResult();

        if (count($results) === 0) {
            return null;
        }

        if (count($results) > 1) {
            throw new \RuntimeException(
                sprintf('Ambiguous partial UUID "%s" matches multiple %s entries', $partialId, $entityClass)
            );
        }

        return $results[0];
    }
}
