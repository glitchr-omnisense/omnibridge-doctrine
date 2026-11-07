<?php

namespace Omnibridge\Doctrine\Omnibase\Database\Mapping;

/*
 * What ClassMetadataManipulator extends: on omnisense/omnibase 5.x the core's Base\Database\Mapping\MetadataManipulator
 * (plan "omnibase agnostic", step E3: the mapping read from the entities' shapes, the Description, on no ORM - the
 * attribute reader typed on it, Doctrine's manipulator one of them); on 4.x, which this bridge serves too, the local cache
 * it extended before.
 */
if (class_exists(\Base\Database\Mapping\MetadataManipulator::class)) {
    class_alias(\Base\Database\Mapping\MetadataManipulator::class, MetadataManipulatorBase::class);
} else {
    abstract class MetadataManipulatorBase extends \Base\Cache\Abstract\AbstractLocalCache
    {
    }
}
