<?php

namespace Omnibridge\Doctrine\Omnibase\Repository\User;

use App\Entity\User;
use Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository;
use Base\Entity\User\Notification;
use Base\Repository\User\Notifications;
use Base\Database\Repository\Finder\Group;

/**
 * @method Notification|null find($id, $lockMode = null, $lockVersion = null)
 * @method Notification|null findOneBy(array $criteria, array $orderBy = null)
 * @method Notification[]    findAll()
 * @method Notification[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotificationRepository extends ServiceEntityRepository implements Notifications
{
    /**
     * A real COUNT. Not the inherited count()/countBy(): base-bundle's
     * ServiceEntityRepository redefines count() to return an array and
     * countBy() hydrates every row, and this runs on every page for the
     * toolbar badge.
     */
    public function countUnreadFor(User $user): int
    {
        if (!self::finderReadsGroups()) {
            return (int) $this->visibleFor($user)
                ->select('COUNT(n.id)')
                ->andWhere('n.isRead = false')
                ->getQuery()->getSingleScalarResult();
        }

        return $this->count($this->visibleTo($user) + ['isRead' => false]);
    }

    /**
     * The user's notifications that have something to show, newest first.
     *
     * @return Notification[]
     */
    public function findVisibleFor(User $user, int $limit = 100, int $offset = 0, bool $unreadOnly = false): array
    {
        if (!self::finderReadsGroups()) {
            $qb = $this->visibleFor($user);
            if ($unreadOnly) {
                $qb->andWhere('n.isRead = false');
            }

            return $qb
                ->orderBy('n.sentAt', 'DESC')->addOrderBy('n.id', 'DESC')
                ->setMaxResults($limit)->setFirstResult($offset)
                ->getQuery()->getResult();
        }

        return $this->findBy($this->visibleTo($user) + ($unreadOnly ? ['isRead' => false] : []), ['sentAt' => 'DESC', 'id' => 'DESC'], $limit, $offset);
    }

    /**
     * The criteria of the rows with a title, a subject or a content (step E2: a finder group,
     * NotEmpty being "neither null nor ''", what COALESCE(x, '') <> '' said).
     *
     * @return array<int|string, mixed>
     */
    private function visibleTo(User $user): array
    {
        return ['user' => $user, Group::by('TitleNotEmptyOrSubjectNotEmptyOrContentNotEmpty')];
    }

    /**
     * The query of countUnreadFor() and findVisibleFor() under omnisense/omnibase 4.x, whose finder
     * has no groups (visibleTo() since step E2).
     *
     * Rows with a title, a subject or a content. An e-mail-only notification
     * (a templated mail sent through setUser()) is persisted with all three
     * empty - 570 of them on this site - and has nothing a list or a toast
     * could display, so the notification center does not count or show it.
     */
    protected function visibleFor(User $user): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.user = :user')->setParameter('user', $user)
            ->andWhere("COALESCE(n.title, '') <> '' OR COALESCE(n.subject, '') <> '' OR COALESCE(n.content, '') <> ''");
    }
}
