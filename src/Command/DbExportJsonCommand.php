<?php
// file generated with AI assistance: Claude Code - 2026-05-30 09:05:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:db:export-json',
    description: 'Export a database table as JSON (by entity name or table name)'
)]
class DbExportJsonCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('entity', InputArgument::REQUIRED, 'Entity FQCN, short name (e.g. ApiConfiguration) or table name')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file path (default: stdout)')
            ->addOption('pretty', null, InputOption::VALUE_NONE, 'Pretty-print JSON output')
            ->setHelp(<<<'HELP'
Export all rows of an entity's table as JSON.

Usage:
  # Print to stdout
  bin/console app:db:export-json ApiConfiguration

  # Write to file (pretty)
  bin/console app:db:export-json ApiConfiguration -o /tmp/api-config.json --pretty

  # Use FQCN
  bin/console app:db:export-json "Dmstr\ApiConfiguration\Entity\ApiConfiguration"

  # Use table name
  bin/console app:db:export-json api_configuration

The output JSON has the shape:
  {
    "_meta": { "entity": "...", "table": "...", "exportedAt": "...",
               "count": N, "fields": { "<field>": { "type": "..." } } },
    "records": [ { "<field>": <value>, ... } ]
  }

Types are converted to JSON-friendly representations:
  - uuid              -> RFC 4122 string
  - datetime[_imm.]   -> ISO 8601 string
  - json              -> object/array (inline)
  - bool/int/string   -> native JSON
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $entityArg = $input->getArgument('entity');

        try {
            $metadata = $this->resolveMetadata($entityArg);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $tableName = $metadata->getTableName();
        $entityClass = $metadata->getName();

        // Hydrate all rows as scalar arrays (Doctrine returns Uuid/DateTime objects)
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('e')->from($entityClass, 'e');
        $rows = $qb->getQuery()->getArrayResult();

        // Doctrine's array hydration drops *-To-One association columns.
        // Fetch them separately via DBAL so the export stays self-contained
        // (RefCustomer.customer, RefProject.apiConfiguration, …).
        $associationFks = $this->fetchAssociationFkColumns($metadata);

        $fieldsMeta = [];
        foreach ($metadata->getFieldNames() as $field) {
            $fieldsMeta[$field] = ['type' => $metadata->getTypeOfField($field)];
        }
        foreach ($metadata->getAssociationNames() as $assocName) {
            if (!$metadata->isSingleValuedAssociation($assocName)) {
                continue;
            }
            $fieldsMeta[$assocName] = [
                'type' => 'uuid',
                'target_entity' => $metadata->getAssociationTargetClass($assocName),
            ];
        }

        $records = [];
        foreach ($rows as $row) {
            $record = $this->normalizeRow($row, $metadata);
            $idStr = $record['id'] ?? null;
            if ($idStr !== null && isset($associationFks[$idStr])) {
                foreach ($associationFks[$idStr] as $assoc => $fkUuid) {
                    $record[$assoc] = $fkUuid;
                }
            }
            $records[] = $record;
        }

        $payload = [
            '_meta' => [
                'entity' => $entityClass,
                'table' => $tableName,
                'exportedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'count' => count($records),
                'fields' => $fieldsMeta,
            ],
            'records' => $records,
        ];

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($input->getOption('pretty')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($payload, $flags);
        if ($json === false) {
            $io->error(sprintf('Failed to encode JSON: %s', json_last_error_msg()));
            return Command::FAILURE;
        }

        $outputPath = $input->getOption('output');
        if ($outputPath !== null) {
            if (file_put_contents($outputPath, $json) === false) {
                $io->error(sprintf('Failed to write to %s', $outputPath));
                return Command::FAILURE;
            }
            $io->success(sprintf('Exported %d record(s) from %s to %s', count($records), $tableName, $outputPath));
            return Command::SUCCESS;
        }

        $output->writeln($json);
        return Command::SUCCESS;
    }

    private function resolveMetadata(string $name): ClassMetadata
    {
        $factory = $this->entityManager->getMetadataFactory();

        // 1) FQCN
        if (class_exists($name) && $factory->hasMetadataFor($name)) {
            return $factory->getMetadataFor($name);
        }

        // 2) Short name or table name across all known metadata
        foreach ($factory->getAllMetadata() as $meta) {
            $shortName = substr($meta->getName(), strrpos($meta->getName(), '\\') + 1);
            if ($shortName === $name || $meta->getTableName() === $name || $meta->getName() === $name) {
                return $meta;
            }
        }

        throw new \RuntimeException(sprintf(
            'Could not resolve entity "%s" — provide a FQCN, short class name or table name.',
            $name
        ));
    }

    /**
     * Fetch single-valued association FK columns for every row via DBAL.
     *
     * @return array<string, array<string, ?string>>  Keyed by row UUID (RFC 4122); value maps association name → fk-uuid or null.
     */
    private function fetchAssociationFkColumns(ClassMetadata $metadata): array
    {
        $assocColumns = [];
        foreach ($metadata->getAssociationNames() as $assocName) {
            if (!$metadata->isSingleValuedAssociation($assocName)) {
                continue;
            }
            $assocColumns[$assocName] = $metadata->getSingleAssociationJoinColumnName($assocName);
        }

        if ($assocColumns === []) {
            return [];
        }

        $idColumn = $metadata->getSingleIdentifierColumnName();
        $tableName = $metadata->getTableName();
        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();

        $selectColumns = array_merge([$idColumn], array_values($assocColumns));
        $quotedSelect = implode(', ', array_map(
            fn ($c) => $platform->quoteSingleIdentifier($c),
            $selectColumns
        ));
        $sql = sprintf('SELECT %s FROM %s', $quotedSelect, $platform->quoteSingleIdentifier($tableName));

        $rawRows = $connection->fetchAllAssociative($sql);

        $out = [];
        foreach ($rawRows as $row) {
            $rowIdRaw = $row[$idColumn];
            if ($rowIdRaw === null) {
                continue;
            }
            $rowId = $this->binaryUuidToString($rowIdRaw);
            $entry = [];
            foreach ($assocColumns as $assocName => $colName) {
                $fk = $row[$colName] ?? null;
                $entry[$assocName] = $fk === null ? null : $this->binaryUuidToString($fk);
            }
            $out[$rowId] = $entry;
        }

        return $out;
    }

    private function binaryUuidToString(string $value): string
    {
        // BINARY(16) -> RFC 4122. If the column is already a 36-char string
        // UUID (legacy guid type), pass it through.
        if (strlen($value) === 16) {
            return Uuid::fromBinary($value)->toRfc4122();
        }
        return $value;
    }

    private function normalizeRow(array $row, ClassMetadata $metadata): array
    {
        $out = [];
        foreach ($metadata->getFieldNames() as $field) {
            if (!array_key_exists($field, $row)) {
                continue;
            }
            $value = $row[$field];
            $type = $metadata->getTypeOfField($field);

            if ($value === null) {
                $out[$field] = null;
                continue;
            }

            $out[$field] = match (true) {
                $value instanceof Uuid => $value->toRfc4122(),
                $value instanceof \Ramsey\Uuid\UuidInterface => $value->toString(),
                $value instanceof \DateTimeInterface => $value->format(\DateTimeInterface::ATOM),
                $type === 'json' && is_string($value) => json_decode($value, true),
                default => $value,
            };
        }
        return $out;
    }
}
