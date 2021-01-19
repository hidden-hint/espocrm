<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2020 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: https://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace tests\unit\Espo\Core\Select\Order;

use Espo\Core\{
    Select\Order\ItemConverterFactory,
    Select\Order\ItemConverter,
    Select\Order\ItemConverters\EnumType,
    Utils\Metadata,
    InjectableFactory,
};

class ItemConverterFactoryTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp() : void
    {
        $this->injectableFactory = $this->createMock(InjectableFactory::class);
        $this->metadata = $this->createMock(Metadata::class);

        $this->factory = new ItemConverterFactory(
            $this->injectableFactory,
            $this->metadata
        );
    }

    public function testCreate1()
    {
        $this->prepareFactoryTest(null, EnumType::class);
        $this->prepareFactoryTest(null, EnumType::class, true);
    }

    public function testCreate2()
    {
        $this->prepareFactoryTest(EnumType::class, null);
        $this->prepareFactoryTest(EnumType::class, null, true);
    }

    protected function prepareFactoryTest(?string $className1, ?string $className2, bool $testHas = false)
    {
        $defaultClassName = ItemConverter::class;

        $entityType = 'Test';

        $field = 'name';

        $type = 'varchar';

        $object = $this->createMock($defaultClassName);

        $className = $className1 ?? $className2 ?? null;

        $this->metadata
            ->expects($this->at(0))
            ->method('get')
            ->with([
                'selectDefs', $entityType, 'orderItemConverterClassNameMap', $field
            ])
            ->willReturn($className1);

        if (!$className1) {
            $this->metadata
                ->expects($this->at(1))
                ->method('get')
                ->with([
                    'entityDefs', $entityType, 'fields', $field, 'type'
                ])
                ->willReturn($type);

            $this->metadata
                ->expects($this->at(2))
                ->method('get')
                ->with([
                    'app', 'select', 'orderItemConverterClassNameMap', $type
                ])
                ->willReturn($className2);
        }

        if ($testHas) {
            $this->assertTrue(
                $this->factory->has($entityType, $field)
            );

            return;
        }

        $object = $this->createMock($className);

        $this->injectableFactory
            ->expects($this->at(0))
            ->method('createWith')
            ->with(
                $className,
                [
                    'entityType' => $entityType,
                ]
            )
            ->willReturn($object);

        $resultObject = $this->factory->create(
            $entityType,
            $field
        );

        $this->assertEquals($object, $resultObject);
    }

    public function testHasFalse()
    {
        $entityType = 'Test';

        $field = 'name';

        $type = 'varchar';

        $this->metadata
            ->expects($this->at(0))
            ->method('get')
            ->with([
                'selectDefs', $entityType, 'orderItemConverterClassNameMap', 'badName'
            ])
            ->willReturn(null);

        $this->metadata
            ->expects($this->at(1))
            ->method('get')
            ->with([
                'entityDefs', $entityType, 'fields', 'badName', 'type'
            ])
            ->willReturn($type);

        $this->metadata
            ->expects($this->at(2))
            ->method('get')
            ->with([
                'app', 'select', 'orderItemConverterClassNameMap', $type
            ])
            ->willReturn(null);

        $this->assertFalse(
            $this->factory->has($entityType, 'badName')
        );
    }
}
