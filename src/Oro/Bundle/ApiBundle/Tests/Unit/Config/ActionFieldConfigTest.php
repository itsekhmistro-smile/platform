<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Config;

use Oro\Bundle\ApiBundle\Config\ActionFieldConfig;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

class ActionFieldConfigTest extends \PHPUnit_Framework_TestCase
{
    public function testClone()
    {
        $config = new ActionFieldConfig();
        $this->assertEmpty($config->toArray());

        $config->set('test', 'value');
        $objValue = new \stdClass();
        $objValue->someProp = 123;
        $config->set('test_object', $objValue);

        $configClone = clone $config;

        $this->assertEquals($config, $configClone);
        $this->assertNotSame($objValue, $configClone->get('test_object'));
    }

    public function testCustomAttribute()
    {
        $attrName = 'test';

        $config = new ActionFieldConfig();
        $this->assertFalse($config->has($attrName));
        $this->assertNull($config->get($attrName));
        $this->assertSame([], $config->keys());

        $config->set($attrName, null);
        $this->assertFalse($config->has($attrName));
        $this->assertNull($config->get($attrName));
        $this->assertEquals([], $config->toArray());
        $this->assertSame([], $config->keys());

        $config->set($attrName, false);
        $this->assertTrue($config->has($attrName));
        $this->assertFalse($config->get($attrName));
        $this->assertEquals([$attrName => false], $config->toArray());
        $this->assertEquals([$attrName], $config->keys());

        $config->remove($attrName);
        $this->assertFalse($config->has($attrName));
        $this->assertNull($config->get($attrName));
        $this->assertSame([], $config->toArray());
        $this->assertSame([], $config->keys());
    }

    public function testExcluded()
    {
        $config = new ActionFieldConfig();
        $this->assertFalse($config->hasExcluded());
        $this->assertFalse($config->isExcluded());

        $config->setExcluded();
        $this->assertTrue($config->hasExcluded());
        $this->assertTrue($config->isExcluded());
        $this->assertEquals(['exclude' => true], $config->toArray());

        $config->setExcluded(false);
        $this->assertTrue($config->hasExcluded());
        $this->assertFalse($config->isExcluded());
        $this->assertEquals([], $config->toArray());
    }

    public function testPropertyPath()
    {
        $config = new ActionFieldConfig();
        $this->assertFalse($config->hasPropertyPath());
        $this->assertNull($config->getPropertyPath());
        $this->assertEquals('default', $config->getPropertyPath('default'));

        $config->setPropertyPath('path');
        $this->assertTrue($config->hasPropertyPath());
        $this->assertEquals('path', $config->getPropertyPath());
        $this->assertEquals('path', $config->getPropertyPath('default'));
        $this->assertEquals(['property_path' => 'path'], $config->toArray());

        $config->setPropertyPath(null);
        $this->assertFalse($config->hasPropertyPath());
        $this->assertNull($config->getPropertyPath());
        $this->assertEquals([], $config->toArray());

        $config->setPropertyPath('path');
        $config->setPropertyPath('');
        $this->assertFalse($config->hasPropertyPath());
        $this->assertNull($config->getPropertyPath());
        $this->assertEquals('default', $config->getPropertyPath('default'));
        $this->assertEquals([], $config->toArray());
    }

    public function testDirection()
    {
        $config = new ActionFieldConfig();
        self::assertFalse($config->hasDirection());
        self::assertTrue($config->isInput());
        self::assertTrue($config->isOutput());

        $config->setDirection('input-only');
        self::assertTrue($config->hasDirection());
        self::assertTrue($config->isInput());
        self::assertFalse($config->isOutput());
        self::assertEquals(['direction' => 'input-only'], $config->toArray());

        $config->setDirection('output-only');
        self::assertTrue($config->hasDirection());
        self::assertFalse($config->isInput());
        self::assertTrue($config->isOutput());
        self::assertEquals(['direction' => 'output-only'], $config->toArray());

        $config->setDirection('bidirectional');
        self::assertTrue($config->hasDirection());
        self::assertTrue($config->isInput());
        self::assertTrue($config->isOutput());
        self::assertEquals(['direction' => 'bidirectional'], $config->toArray());

        $config->setDirection(null);
        self::assertFalse($config->hasDirection());
        self::assertTrue($config->isInput());
        self::assertTrue($config->isOutput());
        self::assertEquals([], $config->toArray());
    }

    // @codingStandardsIgnoreStart
    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The possible values for the direction are "input-only", "output-only" or "bidirectional".
     */
    // @codingStandardsIgnoreEnd
    public function testSetInvalidDirection()
    {
        $config = new ActionFieldConfig();

        $config->setDirection('another');
    }

    public function testFormType()
    {
        $config = new ActionFieldConfig();
        $this->assertNull($config->getFormType());

        $config->setFormType('test');
        $this->assertEquals('test', $config->getFormType());
        $this->assertEquals(['form_type' => 'test'], $config->toArray());

        $config->setFormType(null);
        $this->assertNull($config->getFormType());
        $this->assertEquals([], $config->toArray());
    }

    public function testFormOptions()
    {
        $config = new ActionFieldConfig();
        $this->assertNull($config->getFormOptions());

        $config->setFormOptions(['key' => 'val']);
        $this->assertEquals(['key' => 'val'], $config->getFormOptions());
        $this->assertEquals(['form_options' => ['key' => 'val']], $config->toArray());

        $config->setFormOptions(null);
        $this->assertNull($config->getFormOptions());
        $this->assertEquals([], $config->toArray());
    }

    public function testSetFormOption()
    {
        $config = new ActionFieldConfig();

        $config->setFormOption('option1', 'value1');
        $config->setFormOption('option2', 'value2');
        $this->assertEquals(
            ['option1' => 'value1', 'option2' => 'value2'],
            $config->getFormOptions()
        );

        $config->setFormOption('option1', 'newValue');
        $this->assertEquals(
            ['option1' => 'newValue', 'option2' => 'value2'],
            $config->getFormOptions()
        );
    }

    public function testFormConstraints()
    {
        $config = new ActionFieldConfig();

        $this->assertNull($config->getFormOptions());
        $this->assertNull($config->getFormConstraints());

        $config->addFormConstraint(new NotNull());
        $this->assertEquals(['constraints' => [new NotNull()]], $config->getFormOptions());
        $this->assertEquals([new NotNull()], $config->getFormConstraints());

        $config->addFormConstraint(new NotBlank());
        $this->assertEquals(['constraints' => [new NotNull(), new NotBlank()]], $config->getFormOptions());
        $this->assertEquals([new NotNull(), new NotBlank()], $config->getFormConstraints());
    }
}
