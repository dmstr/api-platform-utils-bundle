<?php
// file generated with AI assistance: Claude Code - 2026-05-27 00:56:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Command;

use Dmstr\OpenApiJsonSchema\Service\OperationInputSchemaResolver;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator as JsonSchemaValidator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base class for CLI commands that mirror REST custom operations with a JSON
 * Schema body. Auto-expands the schema's top-level properties to
 * `--c-<property>` options so users can pass body fields directly instead of
 * inline JSON.
 *
 * Resolution order (highest priority last):
 *  1. `--file=path.json`  → file contents loaded as base input.
 *  2. `--config '{"k":"v"}'` → JSON-merged on top.
 *  3. `--c-<prop>=value`  → individual property overrides.
 *
 * The final assembled input is validated against the operation's JSON Schema
 * via `Dmstr\OpenApiJsonSchema\Service\OperationInputSchemaResolver`. Subclasses receive the
 * validated input via {@see executeWithInput()}.
 *
 * The associated operation name (e.g. `survey_publish`) is provided by
 * subclasses via {@see getOperationName()}.
 */
abstract class AbstractJsonSchemaInputCommand extends Command
{
    /** @var array<string, mixed>|null */
    private ?array $cachedSchema = null;

    public function __construct(
        private readonly OperationInputSchemaResolver $schemaResolver,
    ) {
        parent::__construct();
    }

    /**
     * Operation name used to look up the JSON Schema file
     * (e.g. `survey_publish` → `survey-publish-input.json`).
     */
    abstract protected function getOperationName(): string;

    /**
     * Subclass hook executed after the input has been assembled and validated.
     *
     * @param array<string, mixed> $input Validated input array (matches REST body).
     */
    abstract protected function executeWithInput(
        array $input,
        InputInterface $input_,
        OutputInterface $output,
    ): int;

    protected function configure(): void
    {
        parent::configure();

        $this->addOption(
            'file',
            null,
            InputOption::VALUE_REQUIRED,
            'Path to a JSON file containing the request body. Use for deeply nested inputs.',
        );
        $this->addOption(
            'config',
            null,
            InputOption::VALUE_REQUIRED,
            'Inline JSON body (merged on top of --file).',
        );

        $schema = $this->loadSchema();
        if ($schema === null) {
            return;
        }

        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];
        if (!is_array($properties)) {
            return;
        }

        foreach ($properties as $name => $definition) {
            if (!is_string($name) || !is_array($definition)) {
                continue;
            }
            $optionName = 'c-' . self::toKebab($name);
            $description = $this->describeProperty($name, $definition, in_array($name, (array)$required, true));
            $this->addOption(
                $optionName,
                null,
                InputOption::VALUE_REQUIRED,
                $description,
            );
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $assembled = $this->assembleInput($input);

        $schema = $this->loadSchema();
        if ($schema !== null) {
            $errors = $this->validateAgainst($assembled, $schema);
            if ($errors !== []) {
                $output->writeln('<error>Input validation failed:</error>');
                foreach ($errors as $err) {
                    $output->writeln('  - ' . $err);
                }
                return Command::FAILURE;
            }
        }

        return $this->executeWithInput($assembled, $input, $output);
    }

    /**
     * @return array<string, mixed>
     */
    private function assembleInput(InputInterface $input): array
    {
        $assembled = [];

        $filePath = $input->getOption('file');
        if (is_string($filePath) && $filePath !== '') {
            if (!is_file($filePath) || !is_readable($filePath)) {
                throw new InvalidOptionException(sprintf('--file: cannot read "%s".', $filePath));
            }
            $decoded = json_decode((string)file_get_contents($filePath), true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                throw new InvalidOptionException(sprintf('--file: invalid JSON in "%s".', $filePath));
            }
            $assembled = $decoded;
        }

        $configJson = $input->getOption('config');
        if (is_string($configJson) && $configJson !== '') {
            $decoded = json_decode($configJson, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                throw new InvalidOptionException('--config: invalid JSON.');
            }
            $assembled = array_replace_recursive($assembled, $decoded);
        }

        $schema = $this->loadSchema();
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        foreach ($properties as $name => $definition) {
            if (!is_string($name) || !is_array($definition)) {
                continue;
            }
            $optionName = 'c-' . self::toKebab($name);
            $raw = $input->getOption($optionName);
            if ($raw === null) {
                continue;
            }
            $assembled[$name] = $this->coerce($raw, $definition);
        }

        return $assembled;
    }

    /**
     * @param array<string, mixed> $value
     * @param array<string, mixed> $schema
     * @return list<string>
     */
    private function validateAgainst(array $value, array $schema): array
    {
        $validator = new JsonSchemaValidator();
        $result = $validator->validate(
            json_decode((string)json_encode((object)$value)),
            json_decode((string)json_encode($schema)),
        );
        if ($result->isValid()) {
            return [];
        }
        $formatter = new ErrorFormatter();
        $formatted = $formatter->format($result->error());
        $messages = [];
        foreach ($formatted as $err) {
            $pointer = $err['data']['pointer'] ?? $err['instanceLocation'] ?? '';
            $keyword = $err['keyword'] ?? 'error';
            $msg = $err['error'] ?? $err['message'] ?? '';
            $messages[] = trim(sprintf('%s [%s] %s', $pointer !== '' ? $pointer : '(root)', $keyword, $msg));
        }
        return $messages;
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function loadSchema(): ?array
    {
        if ($this->cachedSchema !== null) {
            return $this->cachedSchema;
        }
        $loaded = $this->schemaResolver->loadSchema($this->getOperationName());
        if ($loaded !== null) {
            $this->cachedSchema = $loaded;
        }
        return $loaded;
    }

    private function coerce(string $raw, array $definition): mixed
    {
        $types = (array)($definition['type'] ?? ['string']);
        $primary = is_string($types[0] ?? null) ? $types[0] : 'string';

        return match ($primary) {
            'integer' => filter_var($raw, FILTER_VALIDATE_INT) !== false ? (int)$raw : $raw,
            'number'  => is_numeric($raw) ? (float)$raw : $raw,
            'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $raw,
            'array'   => $this->splitArray($raw, $definition),
            'object'  => $this->decodeJson($raw),
            'null'    => $raw === '' || strtolower($raw) === 'null' ? null : $raw,
            default   => $raw,
        };
    }

    private function splitArray(string $raw, array $definition): array
    {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
        $items = array_map('trim', explode(',', $raw));
        $itemType = $definition['items']['type'] ?? 'string';
        return array_map(
            fn(string $v) => match ($itemType) {
                'integer' => (int)$v,
                'number' => (float)$v,
                'boolean' => filter_var($v, FILTER_VALIDATE_BOOLEAN),
                default => $v,
            },
            array_filter($items, fn(string $v) => $v !== ''),
        );
    }

    private function decodeJson(string $raw): mixed
    {
        $decoded = json_decode($raw, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
    }

    private function describeProperty(string $name, array $definition, bool $required): string
    {
        $parts = [];
        if (isset($definition['description']) && is_string($definition['description'])) {
            $parts[] = $definition['description'];
        } elseif (isset($definition['title']) && is_string($definition['title'])) {
            $parts[] = $definition['title'];
        }
        $type = $definition['type'] ?? 'string';
        $parts[] = '[type: ' . (is_array($type) ? implode('|', $type) : $type) . ']';
        if ($required) {
            $parts[] = '(required)';
        }
        return $parts === [] ? $name : implode(' ', $parts);
    }

    private static function toKebab(string $camel): string
    {
        $kebab = preg_replace('/([a-z\d])([A-Z])/', '$1-$2', $camel);
        return strtolower((string)$kebab);
    }
}
