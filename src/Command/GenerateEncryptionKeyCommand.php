<?php
// file generated with AI assistance: Claude Code - 2026-06-10 10:30:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Command;

use Dmstr\ApiPlatformUtils\Service\CredentialEncryption;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'dmstr:generate-encryption-key',
    description: 'Generate a new encryption key for API credentials',
    aliases: ['app:generate-encryption-key'],
)]
class GenerateEncryptionKeyCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $key = CredentialEncryption::generateKey();

        $io->success('Encryption key generated successfully!');
        $io->section('Add this to your .env.local file:');
        $io->writeln(sprintf('CREDENTIALS_ENCRYPTION_KEY=%s', $key));
        $io->newLine();
        $io->warning([
            'IMPORTANT: Store this key securely!',
            '- Never commit this key to version control',
            '- Use environment variables in production',
            '- Losing this key means losing access to all encrypted credentials',
        ]);

        return Command::SUCCESS;
    }
}
