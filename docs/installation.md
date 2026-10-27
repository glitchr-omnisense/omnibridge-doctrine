---
title: Installation
order: 10
---

# Installation

## Requirements

| Package | Version |
|---|---|
| PHP | 8.2 or later (8.4 for native lazy objects, used when available) |
| `omnisense/omnibridge` | 1.x-dev (the Schema) |
| `doctrine/orm` | ^3.4 |
| `doctrine/dbal` | ^4.2 |
| `symfony/cache` | ^7.0 or ^8.0 (`ArrayAdapter`, the metadata cache by default) |

```sh
composer require omnibridge/doctrine
```

## An entity manager in plain PHP

Two entities in `src/Entity` (the same as the core's [schema example](https://github.com/glitchr-omnisense/omnibridge/blob/1.x/docs/schema.md)),
an in-memory SQLite database:

```php
<?php
// src/Entity/Thread.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Thread
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(unique: true)]
    public string $slug = '';

    #[ORM\Column(type: 'utc_datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $publishedAt = null;

    #[ORM\ManyToMany(targetEntity: Thread\Tag::class)]
    public iterable $tags = [];
}
```

```php
<?php
// src/Entity/Thread/Tag.php
namespace App\Entity\Thread;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Tag
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 64)]
    public string $name = '';
}
```

```php
<?php

require 'vendor/autoload.php';

use App\Entity\Thread;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Tools\SchemaTool;
use Omnibridge\Doctrine\DeclarationDriver;
use Omnibridge\Doctrine\EntityManagers;
use Omnibridge\Doctrine\MetadataDescription;
use Omnibridge\Schema\Builder;

$connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
$entityManager = EntityManagers::create($connection, [__DIR__.'/src/Entity'], configure: function ($configuration) {
    $configuration->setMetadataDriverImpl(new DeclarationDriver($configuration->getMetadataDriverImpl()));
});

$metadata = $entityManager->getClassMetadata(Thread::class);
echo get_class($entityManager->getConfiguration()->getMetadataDriverImpl()), "\n";
echo $metadata->getTableName(), ' ', $metadata->associationMappings['tags']->joinTable->name, "\n";

$doctrine = MetadataDescription::of($entityManager);
$builder = (new Builder())->build([Thread::class, Thread\Tag::class]);
echo $doctrine->toJson() === $builder->toJson() ? "the same description\n" : "different descriptions\n";
echo implode(', ', array_keys($doctrine->tables)), "\n";

(new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
$thread = new Thread();
$thread->slug = 'le-fuji';
$thread->publishedAt = new DateTimeImmutable('2026-10-27 09:00', new DateTimeZone('Asia/Tokyo'));
$entityManager->persist($thread);
$entityManager->flush();
echo $connection->fetchOne('SELECT publishedAt FROM thread'), "\n";
$entityManager->clear();
$read = $entityManager->find(Thread::class, $thread->id);
echo $read->publishedAt->format(DATE_ATOM), "\n";
```

Its output (PHP 8.4.27, doctrine/orm 3.7.5, dbal 4.5.0, in the image `lesringostars-laravel-app`,
2026-10-27):

```text
Omnibridge\Doctrine\DeclarationDriver
thread thread_tags
the same description
thread, threadTag, thread_tags
2026-10-27 00:00:00
2026-10-27T00:00:00+00:00
```

- The metadata come from the declarations (`DeclarationDriver` around ORMSetup's attribute driver),
  named by the family's naming (`threadTag`, `thread_tags`).
- The description of Doctrine's metadata and tables is the one the builder makes without Doctrine,
  byte for byte as JSON.
- 09:00 in Tokyo is stored and read back as 00:00 UTC (`utc_datetime_immutable`).
