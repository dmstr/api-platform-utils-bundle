<?php
// file generated with AI assistance: Claude Code - 2025-11-22

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Service;

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
     * Find entity with binary UUID storage using native SQL
     *
     * Uses HEX(id) to convert binary UUID to string for LIKE matching
     */
    private function findByPartialBinaryUuid(string $entityClass, string $partialId): ?object
    {
        $metadata = $this->entityManager->getClassMetadata($entityClass);
        $tableName = $metadata->getTableName();

        $conn = $this->entityManager->getConnection();
        $sql = "SELECT BIN_TO_UUID(id) as uuid_str FROM {$tableName} WHERE LOWER(HEX(id)) LIKE :partialId LIMIT 2";
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
