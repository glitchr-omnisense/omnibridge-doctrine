<?php

namespace Omnibridge\Doctrine\Omnibase;

use Base\Database\Repository\Entities;
use Base\Database\Repository\EntityRepositories;
use Base\Database\Repository\Finders;
use Base\Database\Repository\Repositories;
use Base\Service\Storages;
use Base\Attributes\AttributeReader;
use Base\Database\Entity\EntityExtension;
use Base\Database\Entity\EntityHydrator;
use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Database\Mapping\NamingStrategy;
use Base\Database\Events as BaseEvents;
use Base\DatabaseSubscriber\AttributeSubscriber;
use Base\DatabaseSubscriber\InverseCollectionCacheSubscriber;
use Base\DatabaseSubscriber\IntlSubscriber;
use Base\DatabaseSubscriber\TrackingPolicySubscriber;
use Base\EntitySubscriber\ExtensionSubscriber;
use Base\EntitySubscriber\TrasheableSubscriber;
use Base\EntitySubscriber\VersionableSubscriber;
use Base\Service\ArrayParameterBag;
use Base\Service\LocalizerInterface;
use Base\Service\Model\ClassAliases;
use Doctrine\DBAL\Types\TypeRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Events;
use Doctrine\ORM\Tools\AttachEntityListenersListener;
use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use Omnibridge\Doctrine\DeclarationDriver;

/**
 * omnibase's Doctrine mapping on an entity manager built by hand - no kernel,
 * no container, no DoctrineBundle (plan "omnibase agnostique", C6):
 *
 *     $entityManager = Omnibridge\Doctrine\EntityManagers::create($connection, $paths, configure: Omnibridge\Doctrine\Omnibase\Mapping::configure(...));
 *     Omnibridge\Doctrine\Omnibase\Mapping::register($entityManager, ['project_dir' => ..., 'environment' => 'prod']);
 *
 * before the first metadata is read. It does what DoctrineBundle and the
 * bundle do under Symfony:
 *
 * - the class convention (Base\Service\Model\ClassAliases: App\Entity\X is
 *   Base\Entity\X where the application has none);
 * - the column types (Omnibridge\Doctrine\Omnibase\Types): the core's, the
 *   enumerations of the core and of the project (src/Enum), those given;
 * - the configuration: omnibase's naming strategy, the filters and the DQL
 *   functions (Omnibridge\Doctrine\Omnibase\Configuration), the mapping driver wrapped
 *   twice (omnibridge/doctrine's DeclarationDriver: the metadata built from the
 *   classes' declarations, Omnibridge\Schema\Declaration, no longer from the
 *   attributes read by Doctrine - audit R14; then Omnibridge\Doctrine\Omnibase\MappingDriver:
 *   the discriminator maps, the `resolveDiscriminator` event);
 * - omnibase's repositories (the core's EntityRepositories over DoctrineFinders:
 *   $repositories, $finders); configure() sets the factory that keeps Doctrine's
 *   own getRepository() on its plain repository;
 * - the services the mapping reads through: the ClassMetadataManipulator
 *   (the completors: aliases, hierarchies), the AttributeReader (#[Alias],
 *   #[DiscriminatorEntry], #[Hierarchify], #[Cache], #[OrderColumn]...) with
 *   the parameters given and none of the framework's services;
 * - the listeners, in the order DoctrineBundle gives them: AttributeSubscriber
 *   and IntlSubscriber (the translations' mapping, their discriminators, the
 *   walker of a translatable's query), the targets resolved, the entity
 *   listeners attached, the tracking
 *   policy, the extensions' index, then the soft deletion, the revisions and
 *   the inverse collections' cache on flush;
 * - the trash and vault filters enabled, the vault for the environment.
 *
 * Options: project_dir (the project's src/Enum and src/Attribute; the current
 * directory by default), environment ("prod"), cache_dir (the reader's and
 * the manipulator's caches; a folder of the system's temporary one by default),
 * parameters (the application's, by full name: base.attributes.use_custom,
 * base.extension.max_revisions...), types (by name, a bundle's enumerations),
 * enumerations (more folders of enumerations), attribute_paths (more folders
 * of attributes: a bundle's src/Attribute), resolve_target_entities
 * (interface => class), entity_listeners (entity class => list of
 * {event, class, method}: what a bundle tags doctrine.orm.entity_listener), localizer (a LocalizerInterface, none by default),
 * flysystem (omnibase's Base\Service\Storages over the host's storages, none by default: the
 * attributes that read a stored file - #[Uploader]'s public address of an upload, an article's audio -
 * read it through the reader's; without it they cannot answer), services (a closure giving the host's service of a
 * type, or null: what a repository takes beside the Finders, a site's cache pool),
 * filters (true: enabled).
 */
final class Mapping
{
    private function __construct(
        public readonly EntityManagerInterface $entityManager,
        public readonly Registry $registry,
        public readonly ClassMetadataManipulator $classMetadataManipulator,
        public readonly AttributeReader $attributeReader,
        public readonly ArrayParameterBag $parameters,
        public readonly Repositories $repositories,
        public readonly Finders $finders,
    ) {
    }

    /** @param array<string, mixed> $options */
    public static function register(EntityManagerInterface $entityManager, array $options = []): self
    {
        $projectDir = rtrim($options['project_dir'] ?? (string) getcwd(), '/');
        $environment = $options['environment'] ?? 'prod';
        $cacheDir = $options['cache_dir'] ?? sys_get_temp_dir().'/omnibase-doctrine-'.md5($projectDir).'/'.$environment;
        @mkdir($cacheDir, 0777, true);

        // App\Entity\X is Base\Entity\X where the application has none of its own: associations name them.
        ClassAliases::register();

        // The column types in the connection's type provider (omnibridge/doctrine 4.0: not in DBAL's static registry),
        // the one EntityManagers::create() gave it or the host's own registry.
        $provider = $entityManager->getConnection()->getConfiguration()->getTypeProvider();
        if (!$provider instanceof TypeRegistry) {
            throw new \LogicException(\sprintf('omnibase\'s column types go in the connection\'s type provider: a %s is given, build it with %s::provider().', get_debug_type($provider), Types::class));
        }
        Types::provider($options['types'] ?? [], Types::enumerations(array_merge(
            [Types::coreEnumerationDirectory(), $projectDir.'/src/Enum'],
            $options['enumerations'] ?? []
        )), $provider);

        $parameters = ['base.attributes.use_custom' => true, 'base.database.use_custom' => true];
        foreach (array_values(array_merge([$projectDir.'/src/Attribute'], $options['attribute_paths'] ?? [])) as $i => $path) {
            $parameters['base.attributes.paths.'.$i] = $path;
        }
        $parameters = new ArrayParameterBag(($options['parameters'] ?? []) + $parameters);

        $configuration = $entityManager->getConfiguration();
        $configuration->setNamingStrategy(new NamingStrategy());
        Configuration::apply($configuration);
        // Doctrine reads the structure described outside of it (omnibridge/doctrine's DeclarationDriver over the
        // application's driver, audit R14), and around it what omnibase's factory did (MappingDriver).
        $driver = $configuration->getMetadataDriverImpl();
        if (!$driver instanceof MappingDriver) {
            $configuration->setMetadataDriverImpl(new MappingDriver($driver instanceof DeclarationDriver ? $driver : new DeclarationDriver($driver), $entityManager));
        }

        // omnibase's repositories on this entity manager: the core's registry over Doctrine's finders, a repository's
        // own services asked from the host (the option `services`).
        $registry = new Registry($entityManager);
        $finders = new DoctrineFinders($registry);
        $repositories = new EntityRepositories($finders, ($services = $options['services'] ?? null) instanceof \Closure ? new ServiceLocator($services) : null);

        $manipulator = new ClassMetadataManipulator($registry, $entityManager, $cacheDir);
        $hydrator = new EntityHydrator(new DoctrineUnitOfWork($entityManager), $manipulator->getShapes());
        $flysystem = $options['flysystem'] ?? null;
        $reader = new AttributeReader(null, null, $entityManager, $parameters, $flysystem instanceof Storages ? $flysystem : null, null, null, $hydrator, $manipulator, $projectDir, $environment, $cacheDir);

        $attributes = new AttributeSubscriber($entityManager, $manipulator, $reader);
        $localizer = $options['localizer'] ?? null;
        $intl = new IntlSubscriber($entityManager, $localizer instanceof LocalizerInterface ? $localizer : null);

        $events = $entityManager->getEventManager();
        $events->addEventListener([
            Events::loadClassMetadata, BaseEvents::resolveDiscriminator, BaseEvents::preQuery, BaseEvents::onQuery, BaseEvents::postQuery,
            Events::postLoad, Events::preFlush, Events::onFlush, Events::postFlush, Events::prePersist, Events::preUpdate,
            Events::preRemove, Events::postPersist, Events::postUpdate, Events::postRemove,
        ], $attributes);
        $events->addEventListener([
            Events::loadClassMetadata, BaseEvents::resolveDiscriminator, BaseEvents::onQuery, Events::postLoad,
            Events::prePersist, Events::preFlush, Events::onFlush,
        ], $intl);

        if ($targets = $options['resolve_target_entities'] ?? []) {
            $resolver = new ResolveTargetEntityListener();
            foreach ($targets as $interface => $className) {
                $resolver->addResolveTargetEntity($interface, $className, []);
            }
            $events->addEventSubscriber($resolver);
        }

        if ($entityListeners = $options['entity_listeners'] ?? []) {
            $attach = new AttachEntityListenersListener();
            foreach ($entityListeners as $entityClass => $listeners) {
                foreach ($listeners as $listener) {
                    $attach->addEntityListener($entityClass, $listener['class'], $listener['event'] ?? null, $listener['method'] ?? null);
                }
            }
            $events->addEventListener([Events::loadClassMetadata], $attach);
        }

        $events->addEventListener([Events::loadClassMetadata], new TrackingPolicySubscriber($manipulator));

        $extensions = new ExtensionSubscriber($entityManager, new EntityExtension());
        $events->addEventListener([Events::loadClassMetadata], $extensions);

        $events->addEventListener([Events::onFlush, Events::postFlush], new TrasheableSubscriber($parameters->get('base.extension.empty_trash') ?? '+7 days'));
        $events->addEventListener([Events::onFlush, Events::postFlush], new VersionableSubscriber($entityManager, (int) ($parameters->get('base.extension.max_revisions') ?? 5)));
        $events->addEventListener([Events::onFlush, Events::postPersist], $extensions);
        $events->addEventListener([Events::onFlush], new InverseCollectionCacheSubscriber());

        if ($options['filters'] ?? true) {
            Configuration::enableFilters($entityManager, $environment);
        }

        return new self($entityManager, $registry, $manipulator, $reader, $parameters, $repositories, $finders);
    }

    /**
     * What an entity manager built by hand needs set before it exists: the repository factory (Doctrine keeps the
     * one its configuration had at `new EntityManager`), so that Doctrine's own getRepository() hands out its plain
     * repository and never tries to build one of omnibase's. Given to omnibridge/doctrine's EntityManagers::create()
     * as its `configure:` closure.
     */
    public static function configure(\Doctrine\ORM\Configuration $configuration): void
    {
        $configuration->setRepositoryFactory(new RepositoryFactory());
    }

    /**
     * The repository of an entity: omnibase's (Base\Repository\*, App\Repository\*), or the plain one of the class.
     *
     * @return Entities<object>
     */
    public function getRepository(string $className): Entities
    {
        return $this->repositories->of($className);
    }
}
