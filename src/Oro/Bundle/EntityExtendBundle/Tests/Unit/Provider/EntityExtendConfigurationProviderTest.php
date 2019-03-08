<?php

namespace Oro\Bundle\EntityExtendBundle\Tests\Unit\Provider;

use Oro\Bundle\EntityExtendBundle\Provider\EntityExtendConfigurationProvider;
use Oro\Bundle\EntityExtendBundle\Tests\Unit\Fixtures\Bundles\TestBundle1\TestBundle1;
use Oro\Bundle\EntityExtendBundle\Tests\Unit\Fixtures\Bundles\TestBundle2\TestBundle2;
use Oro\Component\Config\CumulativeResourceManager;
use Oro\Component\Testing\TempDirExtension;

class EntityExtendConfigurationProviderTest extends \PHPUnit\Framework\TestCase
{
    use TempDirExtension;

    /** @var EntityExtendConfigurationProvider */
    private $configurationProvider;

    /** @var string */
    private $cacheFile;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->cacheFile = $this->getTempFile('EntityExtendConfigurationProvider');

        $this->configurationProvider = new EntityExtendConfigurationProvider($this->cacheFile, false);
    }

    public function testGetUnderlyingTypes()
    {
        $bundle1 = new TestBundle1();
        $bundle2 = new TestBundle2();
        CumulativeResourceManager::getInstance()
            ->clear()
            ->setBundles([
                $bundle1->getName() => get_class($bundle1),
                $bundle2->getName() => get_class($bundle2)
            ]);

        $this->assertEquals(
            [
                'enum'      => 'manyToOne',
                'multiEnum' => 'manyToMany'
            ],
            $this->configurationProvider->getUnderlyingTypes()
        );
    }
}
