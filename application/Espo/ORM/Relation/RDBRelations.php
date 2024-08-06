<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM – Open Source CRM application.
 * Copyright (C) 2014-2024 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
 * Website: https://www.espocrm.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU Affero General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\ORM\Relation;

use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Part\Order;
use Espo\ORM\Type\RelationType;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * @internal
 */
class RDBRelations implements Relations
{
    /** @var array<string, Entity|EntityCollection<Entity>|null> */
    private array $data = [];
    /** @var array<string, Entity|EntityCollection<Entity>|null> */
    private array $setData = [];
    private ?Entity $entity = null;

    /** @var string[] */
    private array $manyTypeList = [
        RelationType::MANY_MANY,
        RelationType::HAS_MANY,
        RelationType::HAS_CHILDREN,
    ];

    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function setEntity(Entity $entity): void
    {
        if ($this->entity) {
            throw new LogicException("Entity is already set.");
        }

        $this->entity = $entity;
    }

    public function reset(string $relation): void
    {
        unset($this->data[$relation]);
        unset($this->setData[$relation]);
    }

    public function resetAll(): void
    {
        $this->data = [];
        $this->setData = [];
    }

    public function isSet(string $relation): bool
    {
        return array_key_exists($relation, $this->setData);
    }

    /**
     * @return Entity|EntityCollection<Entity>|null
     */
    public function getSet(string $relation): Entity|EntityCollection|null
    {
        if (!array_key_exists($relation, $this->setData)) {
            throw new RuntimeException("Relation '$relation' is not set.");
        }

        return $this->setData[$relation];
    }

    /**
     * @param Entity|EntityCollection<Entity>|null $related
     */
    public function set(string $relation, Entity|EntityCollection|null $related): void
    {
        $type = $this->getRelationType($relation);

        if (!$type) {
            throw new LogicException("Relation '$relation' does not exist.");
        }

        if (in_array($type, $this->manyTypeList) && !$related instanceof EntityCollection) {
            throw new InvalidArgumentException("Non-collection passed for relation '$relation'.");
        }

        if (
            !in_array($type, [
                RelationType::BELONGS_TO,
                RelationType::BELONGS_TO_PARENT,
                RelationType::HAS_ONE,
            ])
        ) {
            throw new LogicException("Relation type '$type' is not supported for setting.");
        }

        $this->setData[$relation] = $related;
    }

    public function getOne(string $relation): ?Entity
    {
        $entity = $this->get($relation);

        if ($entity instanceof EntityCollection) {
            throw new LogicException("Not an entity. Use `getMany` instead.");
        }

        return $entity;
    }

    /**
     * @return EntityCollection<Entity>
     */
    public function getMany(string $relation): EntityCollection
    {
        $collection = $this->get($relation);

        if (!$collection instanceof EntityCollection) {
            throw new LogicException("Not a collection. Use `getOne` instead.");
        }

        /** @var EntityCollection<Entity> */
        return $collection;
    }

    /**
     * @param string $relation
     * @return Entity|EntityCollection<Entity>|null
     */
    private function get(string $relation): Entity|EntityCollection|null
    {
        if (array_key_exists($relation, $this->setData)) {
            return $this->setData[$relation];
        }

        if (!array_key_exists($relation, $this->data)) {
            if (!$this->entity) {
                throw new LogicException("No entity set.");
            }

            $isMany = in_array($this->getRelationType($relation), $this->manyTypeList);

            $this->data[$relation] = $isMany ?
                $this->findMany($relation) :
                $this->findOne($relation);
        }

        $object = $this->data[$relation];

        if ($object instanceof EntityCollection) {
            /** @var EntityCollection<Entity> $object */
            $object = new EntityCollection(iterator_to_array($object));
        }

        return $object;
    }

    private function findOne(string $relation): ?Entity
    {
        if (!$this->entity) {
            throw new LogicException();
        }

        if (!$this->entity->hasId()) {
            return null;
        }

        return $this->entityManager
            ->getRelation($this->entity, $relation)
            ->findOne();
    }

    /**
     * @return EntityCollection<Entity>
     */
    private function findMany(string $relation): EntityCollection
    {
        if (!$this->entity) {
            throw new LogicException();
        }

        if (!$this->entity->hasId()) {
            /** @var EntityCollection<Entity> */
            return new EntityCollection();
        }

        $relationDefs = $this->entityManager
            ->getDefs()
            ->getEntity($this->entity->getEntityType())
            ->getRelation($relation);

        $orderBy = null;
        $order = null;

        if ($relationDefs->getParam('orderBy')) {
            $orderBy = $relationDefs->getParam('orderBy');

            if ($relationDefs->getParam('order')) {
                $order = strtoupper($relationDefs->getParam('order')) === Order::DESC ? Order::DESC : Order::ASC;
            }
        }

        $builder = $this->entityManager->getRelation($this->entity, $relation);

        if ($orderBy) {
            $builder->order($orderBy, $order);
        }

        $collection = $builder->find();

        if (!$collection instanceof EntityCollection) {
            $collection = new EntityCollection(iterator_to_array($collection));
        }

        /** @var EntityCollection<Entity> */
        return $collection;
    }

    private function getRelationType(string $relation): ?string
    {
        if (!$this->entity) {
            throw new LogicException();
        }

        return $this->entityManager
            ->getDefs()
            ->getEntity($this->entity->getEntityType())
            ->tryGetRelation($relation)
            ?->getType();
    }
}
