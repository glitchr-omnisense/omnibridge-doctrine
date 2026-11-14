<?php

namespace Omnibridge\Doctrine\Omnibase\DatabaseSubscriber;

use Base\Demo\DemoAccountLock;
use Base\Demo\DemoAccountRegistry;
use Base\Entity\User;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Omnibridge\Contract\Requests;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A demonstration account stays what its declaration says, whoever asks and
 * through whichever form (registered in the `demo` environment only): its
 * identifier, its address, its password, its second factor are put back
 * before anything is written, it is not disabled, it is not deleted.
 *
 * The pages of omnibase that change these refuse first, with a message
 * (SecurityPolicy::isDemoAccount()); this is what holds for the others - the
 * application's own "my account" page, the back office's users. A visitor
 * signed in as "docteur" who could change that password would lock the next
 * one out until the night's reset.
 *
 * Only during a request: the fixtures and `demo:reset` write these accounts
 * from the console.
 *
 * The rule itself is Base\Demo\DemoAccountLock's (plan "omnibase agnostic",
 * step F5a); this listener is its Doctrine side - Doctrine's identity map and
 * original rows. The request and the flash message go through
 * Omnibridge\Contract\Requests (step F5c); a refusal is HttpKernel's 403
 * where HttpKernel is installed (Symfony), a plain RuntimeException
 * otherwise.
 *
 * Moved from omnisense/omnibase's Base\DatabaseSubscriber\DemoAccountLockSubscriber
 * (step F5c), its former name an alias the core declares.
 */
class DemoAccountLockSubscriber
{
    private bool $told = false;

    private readonly DemoAccountLock $lock;

    private readonly ?Requests $requests;

    public function __construct(
        DemoAccountRegistry $accounts,
        #[Autowire(service: Requests::class)] ?Requests $requests,
        PasswordHasherFactoryInterface $hashers,
        ?TranslatorInterface $translator = null,
        ?DemoAccountLock $lock = null,
    ) {
        $this->lock = $lock ?? new DemoAccountLock($accounts, $hashers, $translator);
        $this->requests = $requests;
    }

    public function preFlush(PreFlushEventArgs $event): void
    {
        if (null === $this->requests || !$this->requests->has()) {
            return;
        }

        $manager = $event->getObjectManager();
        $unitOfWork = $manager->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof User && $this->lock->declarationOf($entity, $unitOfWork->getOriginalEntityData($entity))) {
                throw self::refusal($this->lock->message('deleted'));
            }
        }

        $refused = false;
        foreach ($unitOfWork->getIdentityMap() as $entities) {
            foreach ($entities as $entity) {
                if (!$entity instanceof User) {
                    continue;
                }
                $original = $unitOfWork->getOriginalEntityData($entity);
                if ($account = $this->lock->declarationOf($entity, $original)) {
                    $metadata = $manager->getClassMetadata($entity::class);
                    $refused = $this->lock->restore(
                        $entity,
                        $account,
                        $original,
                        $metadata->getFieldNames(),
                        static fn (string $field): mixed => $metadata->getFieldValue($entity, $field),
                        static function (string $field, mixed $value) use ($metadata, $entity): void {
                            $metadata->setFieldValue($entity, $field, $value);
                        },
                    ) || $refused;
                }
            }
        }

        if ($refused) {
            $this->tell();
        }
    }

    /** A 403 under Symfony (HttpKernel's exception, which its kernel answers so), a RuntimeException elsewhere. */
    private static function refusal(string $message): \Throwable
    {
        return class_exists(AccessDeniedHttpException::class) ? new AccessDeniedHttpException($message) : new \RuntimeException($message);
    }

    private function tell(): void
    {
        if ($this->told) {
            return;
        }

        if (null === $this->requests || !$this->requests->has()) {
            return;
        }

        $this->requests->flash('warning', $this->lock->message('locked'));
        $this->told = true;
    }

    /** One message a request: the service lives across requests in a worker. */
    public function reset(): void
    {
        $this->told = false;
    }
}
