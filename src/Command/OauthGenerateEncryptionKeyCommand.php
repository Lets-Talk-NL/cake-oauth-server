<?php

namespace OAuthServer\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * OAuth 2.0 command
 *
 * Helper
 *
 * bin/cake oauth_generate_encryption_key
 */
class OauthGenerateEncryptionKeyCommand extends Command
{
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription('Generates a suitable OAuth 2.0 server encryption key');
        return $parser;
    }

    /**
     * Generates encryption key
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $io->out(base64_encode(random_bytes(32)));
        return Command::CODE_SUCCESS;
    }
}
