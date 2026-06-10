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
    name: 'app:db:import-json',
    description: 'Import a database table from a JSON file produced by app:db:export-json'
)]
class DbImportJsonCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('input-file', InputArgument::REQUIRED, 'Path to JSON file (or "-" to read stdin)')
            ->addOption('entity', null, InputOption::VALUE_REQUIRED, 'Override entity (FQCN, short name or table); defaults to _meta.entity')
            ->addOption('mode', null, InputOption::VALUE_REQUIRED, 'Import mode: insert (default — fails on duplicate id) or upsert (update existing rows by id)', 'insert')
            ->addOption('truncate', null, InputOption::VALUE_NONE, 'Truncate table before import (DESTRUCTIVE)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate input but do not write')
            ->setHelp(<<<'HELP'
Import an entity's table from a JSON file produced by app:db:export-json.

The import bypasses Doctrine lifecycle callbacks (PrePersist/PreUpdate) and
writes directly via DBAL, so exported timestamps, UUIDs and JSON columns are
restored verbatim.

Usage:
  # Restore (additive insert; fails on duplicate primary key)
  bin/console app:db:import-json /tmp/api-config.json

  # Full replace
  bin/console app:db:import-json /tmp/api-config.json --truncate

  # Dry-run (parse + validate, no writes)
  bin/console app:db:import-json /tmp/api-config.json --dry-run

  # Read from stdin
  cat /tmp/api-config.json | bin/console app:db:import-json -
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $inputFile = $input->getArgument('input-file');
        $json = $this->readInput($inputFile);
        if ($json === null) {
            $io->error(sprintf('Could not read input "%s"', $inputFile));
            return Command::FAILURE;
        }

        $payload = json_decode($json, true);
        if (!is_array($payload) || !isset($payload['records']) || !is_array($payload['records'])) {
            $io->error('Invalid payload: expected an object with a "records" array');
            return Command::FAILURE;
        }

        $mode = $input->getOption('mode');
        if (!in_array($mode, ['insert', 'upsert'], true)) {
            $io->error(sprintf('Invalid --mode "%s"; expected insert or upsert', $mode));
            return Command::FAILURE;
        }

        $entityName = $input->getOption('entity') ?? ($payload['_meta']['entity'] ?? null);
        if (!is_string($entityName) || $entityName === '') {
            $io->error('Entity not specified and missing in _meta.entity');
            return Command::FAILURE;
        }

        try {
            $metadata = $this->resolveMetadata($entityName);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $tableName = $metadata->getTableName();
        $records = $payload['records'];

        $io->title('Import JSON');
        $io->definitionList(
            ['Entity' => $metadata->getName()],
            ['Table' => $tableName],
            ['Records' => (string) count($records)],
            ['Mode' => $mode],
            ['Truncate' => $input->getOption('truncate') ? 'yes' : 'no'],
            ['Dry run' => $input->getOption('dry-run') ? 'yes' : 'no'],
        );

        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();

        // Prepare rows up-front so we can validate before touching the DB
        $prepared = [];
        try {
            foreach ($records as $i => $record) {
                if (!is_array($record)) {
                    throw new \RuntimeException(sprintf('Record #%d is not an object', $i));
                }
                $prepared[] = $this->prepareRow($record, $metadata, $platform);
            }
        } catch (\Throwable $e) {
            $io->error(sprintf('Failed to prepare records: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        if ($input->getOption('dry-run')) {
            $io->success(sprintf('Dry-run OK — %d record(s) would be imported into %s', count($prepared), $tableName));
            return Command::SUCCESS;
        }

        $connection->beginTransaction();
        try {
            if ($input->getOption('truncate')) {
                $this->truncate($connection, $tableName);
                $io->writeln(sprintf('<comment>Truncated table %s</comment>', $tableName));
            }

            $idColumn = $metadata->getSingleIdentifierColumnName();
            $insertedCount = 0;
            $updatedCount = 0;

            foreach ($prepared as $row) {
                if ($mode === 'upsert' && $this->rowExists($connection, $tableName, $idColumn, $row['data'][$idColumn] ?? null, $row['types'][$idColumn] ?? 'uuid')) {
                    $idValue = $row['data'][$idColumn];
                    $idType = $row['types'][$idColumn];
                    $updateData = $row['data'];
                    $updateTypes = $row['types'];
                    unset($updateData[$idColumn], $updateTypes[$idColumn]);
                    $connection->update(
                        $tableName,
                        $updateData,
                        [$idColumn => $idValue],
                        array_merge($updateTypes, [$idColumn => $idType])
                    );
                    $updatedCount++;
                } else {
                    $connection->insert($tableName, $row['data'], $row['types']);
                    $insertedCount++;
                }
            }

            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            $io->error(sprintf('Import failed (transaction rolled back): %s', $e->getMessage()));
            if ($output->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }
            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Imported %d record(s) into %s (inserted: %d, updated: %d)',
            $insertedCount + $updatedCount,
            $tableName,
            $insertedCount,
            $updatedCount
        ));
        return Command::SUCCESS;
    }

    private function rowExists($connection, string $tableName, string $idColumn, mixed $idValue, string $idType): bool
    {
        if ($idValue === null) {
            return false;
        }
        $platform = $connection->getDatabasePlatform();
        $sql = sprintf(
            'SELECT 1 FROM %s WHERE %s = ? LIMIT 1',
            $platform->quoteSingleIdentifier($tableName),
            $platform->quoteSingleIdentifier($idColumn)
        );
        $result = $connection->fetchOne($sql, [$idValue], [$idType]);
        return $result !== false;
    }

    private function readInput(string $path): ?string
    {
        if ($path === '-') {
            $data = stream_get_contents(STDIN);
            return $data === false ? null : $data;
        }
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }
        $data = file_get_contents($path);
        return $data === false ? null : $data;
    }

    private function resolveMetadata(string $name): ClassMetadata
    {
        $factory = $this->entityManager->getMetadataFactory();

        if (class_exists($name) && $factory->hasMetadataFor($name)) {
            return $factory->getMetadataFor($name);
        }

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
     * Convert a JSON record into a DBAL-ready array (column => php-value) plus
     * a parallel types array. $connection->insert() / ->update() apply the
     * matching type converters when binding parameters, so we only need to
     * restore the PHP-level representation (string -> Uuid / DateTimeImmutable).
     *
     * Single-valued associations (e.g. RefCustomer.customer) are handled too:
     * the record carries the target row's id as a plain string under the
     * property name; we resolve it to the join column + target id type.
     */
    private function prepareRow(array $record, ClassMetadata $metadata, $platform): array
    {
        $data = [];
        $types = [];

        foreach ($metadata->getFieldNames() as $field) {
            if (!array_key_exists($field, $record)) {
                continue;
            }
            $type = $metadata->getTypeOfField($field);
            $column = $metadata->getColumnName($field);

            $data[$column] = $this->coercePhpValue($record[$field], $type);
            $types[$column] = $type;
        }

        foreach ($metadata->getAssociationNames() as $assocName) {
            if (!$metadata->isSingleValuedAssociation($assocName)) {
                continue;
            }
            if (!array_key_exists($assocName, $record)) {
                continue;
            }

            $column = $metadata->getSingleAssociationJoinColumnName($assocName);
            $value = $record[$assocName];

            $targetClass = $metadata->getAssociationTargetClass($assocName);
            $targetMeta = $this->entityManager->getClassMetadata($targetClass);
            $targetIdField = $targetMeta->getSingleIdentifierFieldName();
            $targetIdType = $targetMeta->getTypeOfField($targetIdField);

            $data[$column] = $this->coercePhpValue($value, $targetIdType);
            $types[$column] = $targetIdType;
        }

        return ['data' => $data, 'types' => $types];
    }

    private function coercePhpValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match (true) {
            $type === 'uuid' && is_string($value) => Uuid::fromString($value),
            in_array($type, ['datetime', 'datetime_immutable', 'date', 'date_immutable', 'time', 'time_immutable'], true)
                && is_string($value) => new \DateTimeImmutable($value),
            // json: DBAL JsonType expects a PHP array/scalar and json_encodes it
            $type === 'json' => $value,
            default => $value,
        };
    }

    private function truncate($connection, string $tableName): void
    {
        // DELETE (not TRUNCATE) so the operation stays inside the surrounding
        // transaction — MySQL would otherwise implicit-commit on TRUNCATE.
        $quoted = $connection->getDatabasePlatform()->quoteSingleIdentifier($tableName);
        $connection->executeStatement('DELETE FROM ' . $quoted);
    }
}
