<?php declare(strict_types=1);

namespace Rector\YamlRector\Tests\Rector\ReplaceStringYamlRector;

use Iterator;
use Rector\YamlRector\Tests\AbstractYamlRectorTest;

/**
 * @covers \Rector\YamlRector\Rector\ReplaceStringYamlRector
 */
final class ReplaceStringYamlRectorTest extends AbstractYamlRectorTest
{
    /**
     * @dataProvider provideWrongToFixedFiles()
     */
    public function test(string $wrong, string $fixed): void
    {
        $this->doTestFileMatchesExpectedContent($wrong, $fixed);
    }

    public function provideWrongToFixedFiles(): Iterator
    {
        yield [__DIR__ . '/wrong/wrong.yml', __DIR__ . '/correct/correct.yml'];
        yield [__DIR__ . '/wrong/wrong2.yml', __DIR__ . '/correct/correct2.yml'];
    }

    protected function provideConfig(): string
    {
        return __DIR__ . '/config.yml';
    }
}
