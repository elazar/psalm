<?php

declare(strict_types=1);

namespace Psalm\Tests\Cache;

use Psalm\Config;
use Psalm\Internal\Analyzer\IssueData;
use Psalm\Internal\Analyzer\ProjectAnalyzer;
use Psalm\Internal\IncludeCollector;
use Psalm\Internal\Provider\FakeFileProvider;
use Psalm\Internal\Provider\Providers;
use Psalm\Internal\RuntimeCaches;
use Psalm\IssueBuffer;
use Psalm\Tests\Internal\Provider\ClassLikeStorageInstanceCacheProvider;
use Psalm\Tests\Internal\Provider\FakeFileReferenceCacheProvider;
use Psalm\Tests\Internal\Provider\FileStorageInstanceCacheProvider;
use Psalm\Tests\Internal\Provider\ParserInstanceCacheProvider;
use Psalm\Tests\Internal\Provider\ProjectCacheProvider;
use Psalm\Tests\TestCase;

use function str_replace;

use const DIRECTORY_SEPARATOR;

class CacheTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        RuntimeCaches::clearAll();
    }

    public function tearDown(): void
    {
        RuntimeCaches::clearAll();

        parent::tearDown();
    }

    /**
     * @param array<string, list<IssueData>> $issue_data
     * @return array<string, list<string>>
     */
    private static function normalizeIssueData(array $issue_data): array
    {
        $return = [];
        foreach ($issue_data as $issue_data_per_file) {
            foreach ($issue_data_per_file as $one_issue_data) {
                $file_name = str_replace(DIRECTORY_SEPARATOR, '/', $one_issue_data->file_name);
                $return[$file_name][] = $one_issue_data->type . ': ' . $one_issue_data->message;
            }
        }

        return $return;
    }

    /**
     * @param list<array{
     *     files: array<string, string|null>,
     *     issues?: array<string, list<string>>,
     * }> $interactions
     * @dataProvider provideCacheInteractions
     */
    public function testCacheInteractions(
        array $interactions
    ): void {
        $config = Config::loadFromXML(
            __DIR__ . DIRECTORY_SEPARATOR . 'test_base_dir',
            <<<'XML'
                <?xml version="1.0"?>
                <psalm>
                    <projectFiles>
                        <directory name="src" />
                    </projectFiles>
                </psalm>
                XML,
        );
        $config->setIncludeCollector(new IncludeCollector());

        $file_provider = new FakeFileProvider();
        $providers = new Providers(
            $file_provider,
            new ParserInstanceCacheProvider(),
            new FileStorageInstanceCacheProvider(),
            new ClassLikeStorageInstanceCacheProvider(),
            new FakeFileReferenceCacheProvider(),
            new ProjectCacheProvider(),
        );

        foreach ($interactions as $interaction) {
            foreach ($interaction['files'] as $file_path => $file_contents) {
                $file_path = $config->base_dir . str_replace('/', DIRECTORY_SEPARATOR, $file_path);
                if ($file_contents === null) {
                    $file_provider->deleteFile($file_path);
                } else {
                    $file_provider->registerFile($file_path, $file_contents);
                }
            }

            RuntimeCaches::clearAll();

            $project_analyzer = new ProjectAnalyzer($config, $providers);
            $project_analyzer->check($config->base_dir, true);

            $issues = self::normalizeIssueData(IssueBuffer::getIssuesData());
            self::assertSame($interaction['issues'] ?? [], $issues);
        }
    }

    /**
     * @return iterable<string, list{
     *     list<array{
     *         files: array<string, string|null>,
     *         issues?: array<string, list<string>>,
     *     }>,
     * }>
     */
    public static function provideCacheInteractions(): iterable
    {
    }
}
