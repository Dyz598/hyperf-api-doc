<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace HyperfApiDoc\Command;

use Hyperf\Command\Command;
use HyperfApiDoc\Contract\Writer;
use HyperfApiDoc\Exception\InvalidConfigurationException;
use HyperfApiDoc\Filter\GroupFilter;
use HyperfApiDoc\Filter\TagFilter;
use HyperfApiDoc\Generator\DocumentationGenerator;
use HyperfApiDoc\Generator\SchemaResolver;
use HyperfApiDoc\Renderer\JsonWriter;
use HyperfApiDoc\Renderer\OpenApiRenderer;
use HyperfApiDoc\Renderer\YamlWriter;
use HyperfApiDoc\Scanner\RouteScanner;
use HyperfApiDoc\Support\ConfigInstances;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

use function Hyperf\Config\config;

class ApiDocGenerateCommand extends Command
{
    protected ?string $name = 'api-doc:generate';

    protected string $description = 'Generate API documentation from the application.';

    protected bool $coroutine = false;

    public function handle(): int
    {
        try {
            $files = $this->generate();
        } catch (InvalidConfigurationException $exception) {
            $this->error(sprintf('[Configuration error] %s', $exception->getMessage()));

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error(sprintf('[%s] %s', $exception::class, $exception->getMessage()));

            return self::FAILURE;
        }

        foreach ($files as $file) {
            $this->info(sprintf('API document generated: %s', $file));
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, string> written file paths
     */
    public function generate(): array
    {
        $config = (array) config('api_doc', []);

        $group = $this->input?->getOption('group');
        $groups = is_string($group) && $group !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $group))))
            : [];
        $tags = (array) ($this->input?->getOption('tag') ?? []);
        $file = $this->input?->getOption('file');

        $targets = self::buildTargets($config, $groups, $tags);

        if (is_string($file) && $file !== '') {
            if (! preg_match('/^[A-Za-z0-9._-]+$/', $file)) {
                throw new InvalidConfigurationException(sprintf(
                    'Invalid file name [%s]; use letters, digits, dots, underscores, and hyphens.',
                    $file
                ));
            }

            if (count($targets) !== 1) {
                throw new InvalidConfigurationException(
                    'The --file option requires a single document; combine it with --group/--tag or drop the documents config.'
                );
            }

            $targets[0]['file'] = $file;
        }

        $format = (string) ($this->input?->getOption('format') ?? '');
        $format = $format !== '' ? strtolower($format) : (string) ($config['output']['format'] ?? 'json');

        $writers = $this->writers($config);

        if ($format === 'both') {
            $chosen = array_values($writers);
        } else {
            $chosen = array_values(array_filter(
                $writers,
                static fn (Writer $writer) => $writer->format() === $format
            ));
        }

        if ($chosen === []) {
            throw new InvalidConfigurationException(sprintf(
                'No writer supports format [%s]; available: %s.',
                $format,
                implode(', ', array_map(static fn (Writer $writer) => $writer->format(), $writers))
            ));
        }

        $fallback = defined('BASE_PATH')
            ? BASE_PATH . '/storage/openapi'
            : sys_get_temp_dir() . '/openapi';
        $directory = (string) ($this->input?->getOption('output') ?? ($config['output']['path'] ?? $fallback));

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new InvalidConfigurationException(sprintf('Cannot create output directory [%s].', $directory));
        }

        $pretty = (bool) ($config['output']['pretty'] ?? true);
        $written = [];

        foreach ($targets as $target) {
            foreach ($chosen as $writer) {
                $path = sprintf('%s/%s.%s', rtrim($directory, '/'), $target['file'], $writer->extension());
                file_put_contents($path, $writer->encode($target['spec'], $pretty) . PHP_EOL);
                $written[] = $path;
            }
        }

        return $written;
    }

    /**
     * Assemble and render every output target: one per configured document,
     * or a single api target. CLI --group/--tag filters collapse the run to
     * a single target.
     *
     * @param array<int, string> $groups
     * @param array<int, string> $tags
     * @return array<int, array{name: string, file: string, spec: array}>
     */
    public static function buildTargets(array $config, array $groups = [], array $tags = []): array
    {
        $scanner = new RouteScanner($config);
        $resolver = new SchemaResolver($config);
        $generator = new DocumentationGenerator($scanner, $resolver, $config);

        $document = $generator->generate();

        $definitions = [];

        if ($groups !== [] || $tags !== []) {
            $definitions['api'] = [];
        } else {
            $configured = (array) ($config['documents'] ?? []);

            if ($configured === []) {
                $definitions['api'] = [];
            } else {
                foreach ($configured as $name => $definition) {
                    $definitions[(string) $name] = (array) $definition;
                }
            }
        }

        $targets = [];

        foreach ($definitions as $name => $definition) {
            $filters = [];

            if (array_key_exists('group', $definition) && $definition['group'] !== null) {
                $filters[] = new GroupFilter((array) $definition['group']);
            }

            if (! empty($definition['tags'])) {
                $filters[] = new TagFilter((array) $definition['tags']);
            }

            if ($name === 'api') {
                if ($groups !== []) {
                    $filters[] = new GroupFilter($groups);
                }

                if ($tags !== []) {
                    $filters[] = new TagFilter($tags);
                }
            }

            // Fresh renderer per target: components are scoped to the $refs
            // actually used by this document.
            $renderer = new OpenApiRenderer($generator->registry(), $config, $resolver);
            $spec = $renderer->render($document->filtered($filters));

            $targets[] = [
                'name' => (string) $name,
                'file' => (string) ($definition['file'] ?? $name),
                'spec' => $spec,
            ];
        }

        return $targets;
    }

    protected function configure(): void
    {
        $this->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: a registered writer format (json, yaml) or both', null)
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output directory', null)
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Base file name of the single generated document', null)
            ->addOption('group', null, InputOption::VALUE_REQUIRED, 'Only include operations of these groups (comma-separated)', null)
            ->addOption('tag', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only include operations with this tag', []);
    }

    /**
     * @return array<string, Writer>
     */
    private function writers(array $config): array
    {
        $configured = (array) (($config['output'] ?? [])['writers'] ?? []);

        if ($configured === []) {
            $configured = [JsonWriter::class, YamlWriter::class];
        }

        $writers = [];

        foreach (ConfigInstances::resolve($configured, Writer::class, 'Output writer') as $writer) {
            $writers[$writer->format()] = $writer;
        }

        return $writers;
    }
}
