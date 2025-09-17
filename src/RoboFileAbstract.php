<?php
declare(strict_types=1);

namespace Szemul\Robo;

use Exception;
use InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;
use josegonzalez\Dotenv\Loader;
use Robo\Result;
use Robo\Tasks;
use Szemul\Robo\OpenApi\DocumentationGenerator;
use Szemul\Robo\OpenApi\OpenApiV3Generator;
use Szemul\Robo\OpenApi\SwaggerGenerator;

abstract class RoboFileAbstract extends Tasks
{
    protected const OPEN_API_VERSION_V2 = 'v2';
    protected const OPEN_API_VERSION_V3 = 'v3';

    /**
     * @return ApplicationConfig[]
     */
    abstract protected function getOpenApiConfigs(): array;

    abstract protected function getOpenApiVersion(): string;

    /**
     * Returns the full path to the .env file
     */
    abstract protected function getEnvFilePath(): string;

    /**
     * Returns the full path to the .env.example file
     */
    abstract protected function getEnvExamplePath(): string;

    /**
     * Prepares the execution environment
     *
     * @param mixed[] $opts
     *
     * @option $environment Name of the environment to use
     */
    public function buildEnvironment(array $opts = ['environment|e' => null]): void
    {
        $envPath = $this->getEnvFilePath();

        if (file_exists($envPath)) {
            return;
        }

        if (!empty($opts['environment'])) {
            $environment = $opts['environment'];
        } else {
            do {
                $environment = $this->askDefault(
                    'Please select the environment (eg. dev/test/staging/production)',
                    'dev',
                );
            } while (empty($environment));
        }

        $envFileContent = '';

        foreach (file($this->getEnvExamplePath()) as $line) {
            if (strstr($line, 'ENVIRONMENT_NAME')) {
                $envFileContent .= 'ENVIRONMENT_NAME=' . $environment . "\n";
            } else {
                $envFileContent .= $line;
            }
        }

        file_put_contents($this->getEnvFilePath(), $envFileContent);

        $this->say('Set current environment to ' . $environment . ' and created .env file.');
        $this->say('Do not forget to fix the credentials in it!');
    }

    /**
     * Install composer dependencies
     *
     * @param mixed[] $opts
     *
     * @option $no-dev Do not install development dependencies
     */
    public function buildComposer(array $opts = ['no-dev' => false]): Result
    {
        $composer = $this->taskComposerInstall();

        if (!$this->isRequiredPhpVersion()) {
            $composer->option('ignore-platform-reqs');
        }

        if ($opts['no-dev']) {
            $composer->noDev();
        }

        return $composer->run();
    }

    /**
     * Run the migrations
     */
    public function migrate(): Result
    {
        $this->requireMinimumRequirements();

        return $this->taskExec('vendor/bin/phinx')->option('ansi')->arg('migrate')->run();
    }

    /**
     * Run the behat tests
     *
     * @param mixed[] $opts
     *
     * @option $silent Produces less output
     *
     * @throws Exception
     */
    public function testBehat(array $opts = ['silent|s' => false]): Result
    {
        $this->requireMinimumRequirements();

        return $this->taskBehat()->format($opts['silent'] ? 'progress' : 'pretty')->colors()->run();
    }

    /**
     * Generates the API documentation
     */
    public function generateApiDoc(): void
    {
        switch ($this->getOpenApiVersion()) {
            case self::OPEN_API_VERSION_V2:
                $generator     = new SwaggerGenerator();
                $generatorPath = 'vendor/bin/swagger';
                break;

            case self::OPEN_API_VERSION_V3:
                $generator     = new OpenApiV3Generator();
                $generatorPath = 'vendor/bin/openapi';
                break;

            default:
                throw new InvalidArgumentException('Unknown version: ' . $this->getOpenApiVersion());
        }

        $configs = $this->getOpenApiConfigs();
        $tasks   = [];

        for ($i = 0; $i < count($configs); $i++) {
            $tasks[] = $this->taskExec($generatorPath);
        }

        (new DocumentationGenerator($generator))->generate($tasks, ...$configs);
    }

    /**
     * Updates the .env file from the .env.example file.
     *
     * @param string[] $updatedEnvironments
     */
    protected function updateEnvFile(
        bool $doUpdate = false,
        string $environmentKeyName = 'ENVIRONMENT_NAME',
        array $updatedEnvironments = ['dev', 'development'],
        bool $includeEmptyValues = false,
    ): void {
        $envPath     = $this->getEnvFilePath();
        $examplePath = $this->getEnvExamplePath();
        $env         = $this->loadEnvFile($envPath);
        $example     = $this->loadEnvFile($examplePath);
        $additions   = [];

        foreach ($example as $key => $value) {
            if (!empty($value) && empty($env[$key])) {
                $additions[$key] = $value;
            } elseif (empty($value) && $includeEmptyValues && !array_key_exists($key, $env)) {
                $additions[$key] = '';
            }
        }

        if (!empty($additions)) {
            $content = file_get_contents($envPath);

            foreach ($additions as $key => $value) {
                $content = preg_replace('/^\s*#*\s*(' . preg_quote($key, '/') . '\s*=[^\n]*$)/m', '#$1', $content);
                $content .= "\n$key=$value\n";
            }

            if ($doUpdate && in_array($env[$environmentKeyName] ?? null, $updatedEnvironments, true)) {
                file_put_contents($envPath, $content);
            } else {
                $this->say('Your .env file is outdated, please update it with the following content:');
                $this->io()->block($content);
            }
        }
    }

    /**
     * Loads the .env file and returns its content as an array
     *
     * @return array<string,string>
     */
    protected function loadEnvFile(string $envPath): array
    {
        return (new Loader([$envPath]))->parse()->toArray();
    }

    /**
     * Checks if we can run in the current execution environment
     *
     * @throws Exception
     */
    protected function requireMinimumRequirements(): void
    {
        if (!$this->isRequiredPhpVersion() || !$this->isCurlInstalled() || !$this->isMemcachedInstalled()) {
            throw new Exception(
                'Your host does not match the minimum requirements, please run Robo inside the container'
                . ' via "docker-compose exec php-web vendor/bin/robo"',
            );
        }
    }

    /*
     * Checks whether the minimum PHP version is installed
     *
     * return bool
     */
    protected function isRequiredPhpVersion(): bool
    {
        return PHP_MAJOR_VERSION > 7; // @phpstan-ignore-line
    }

    /**
     * Checks whether the curl extension is installed
     */
    protected function isCurlInstalled(): bool
    {
        return function_exists('curl_exec');
    }

    /**
     * Checks whether the memcached extension is installed
     */
    protected function isMemcachedInstalled(): bool
    {
        return class_exists('\Memcached');
    }

    /**
     * @throws Exception
     */
    protected function requireEntryInAuthJson(string $baseDir, string $type, string $host, string $helpText = ''): void
    {
        $authJsonContent = [];
        $authJsonPath    = $baseDir . '/auth.json';
        if (file_exists($authJsonPath)) {
            $authJsonContent = json_decode(file_get_contents($authJsonPath), true);
            if (is_array($authJsonContent)) {
                if (!empty($authJsonContent[$type][$host])) {
                    // The auth.json file contains the required host and type, so no need to create it.
                    return;
                }
            } else {
                $authJsonContent = [];
            }
        }

        $authJsonContent[$type][$host] = match ($type) {
            'http-basic'      => $this->getHttpBasicAuthBlock($host, $helpText),
            'bitbucket-oauth' => $this->getBitbucketOauthBlock($host, $helpText),
            'github-oauth'    => $this->getGithubOauthBlock($host, $helpText),
            default           => throw new Exception('Unsupported auth type: ' . $type),
        };

        file_put_contents($authJsonPath, json_encode($authJsonContent, JSON_PRETTY_PRINT));
    }

    /** @return array<string,string> */
    #[ArrayShape(['username' => 'string', 'password' => 'string'])]
    private function getHttpBasicAuthBlock(string $host, string $helpText = ''): array
    {
        $this->say(
            'No authentication information for ' . $host
            . '. Authentication needs to be set up via http-basic auth. There is no verification of the information'
            . ' entered below. If you entered the wrong credentials, please edit the auth.json file manually to correct'
            . ' the problem.',
        );

        if (!empty($helpText)) {
            $this->io()->block($helpText);
        }

        $username = $this->ask('Please enter your username');
        $password = $this->ask('Please enter your password', true);

        return [
            'username' => $username,
            'password' => $password,
        ];
    }

    /** @return array<string,string> */
    #[ArrayShape(['consumer-key' => 'string', 'consumer-secret' => 'string'])]
    private function getBitbucketOauthBlock(string $host, string $helpText = '')
    {
        $this->say(
            'No authentication information for ' . $host
            . '. Authentication needs to be set up via bitbucket oauth. If you have not done so previously, create an'
            . ' oauth consumer in your bitbucket settings. Give it a name and a callback URL (say http://example.com).'
            . ' Ensure the consumer has Repositories read permission. Then enter your consumer key and secret below for'
            . ' the consumer. The entered information is not checked now, but only during composer install. If you made'
            . ' a mistake edit the auth.json file manually.',
        );

        if (!empty($helpText)) {
            $this->io()->block($helpText);
        }

        $key    = $this->ask('Please enter your consumer key');
        $secret = $this->ask('Please enter your consumer secret');

        return [
            'consumer-key'    => $key,
            'consumer-secret' => $secret,
        ];
    }

    private function getGithubOauthBlock(string $host, string $helpText = ''): string
    {
        $this->say(
            'No authentication information for ' . $host
            . '. Authentication needs to be set up via github personal access tokens. If you have not done so previously, create a'
            . ' personal access token at https://' . $host . '/settings/tokens/new .'
            . ' Ensure the token has the `repo` permission. Then enter your token below.'
            . ' The entered information is not checked now, but only during composer install. If you made'
            . ' a mistake edit the auth.json file manually.',
        );

        if (!empty($helpText)) {
            $this->io()->block($helpText);
        }

        $token  = $this->ask('Please enter your personal access token');

        return $token;
    }
}
