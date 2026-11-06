<?php

namespace Omnibridge\Doctrine\Omnibase\Repository\Thread;

use Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository;
use Base\Entity\Thread;
use Base\Entity\Thread\Comment;
use Base\Enum\CommentState;
use Base\Repository\Thread\Comments;
use Base\Database\Repository\Finder\Key;

/**
 * @method Comment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Comment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Comment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommentRepository extends ServiceEntityRepository implements Comments
{
    /**
     * The comments online under a thread - or the visitors' book when
     * $thread is null -, the top-level ones, newest first; their replies
     * hang under each (Comment::getVisibleReplies()).
     *
     * @return list<Comment>
     */
    public function findVisible(?Thread $thread, int $limit = 200): array
    {
        if (self::finderReadsKeys()) {
            return $this->findBy($this->visible($thread) + ['parent' => Key::of(null)], ['createdAt' => 'DESC'], $limit);
        }

        // omnisense/omnibase 4.x: the query of before.
        $query = $this->createQueryBuilder('c')
            ->andWhere('c.state = :approved')->setParameter('approved', CommentState::APPROVED)
            ->andWhere('c.parent IS NULL')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit);
        $query = $thread ? $query->andWhere('c.thread = :thread')->setParameter('thread', $thread) : $query->andWhere('c.thread IS NULL');

        return $query->getQuery()->getResult();
    }

    public function countVisible(?Thread $thread): int
    {
        if (self::finderReadsKeys()) {
            return $this->count($this->visible($thread));
        }

        // omnisense/omnibase 4.x: the query of before.
        $query = $this->createQueryBuilder('c')->select('COUNT(c.id)')
            ->andWhere('c.state = :approved')->setParameter('approved', CommentState::APPROVED);
        $query = $thread ? $query->andWhere('c.thread = :thread')->setParameter('thread', $thread) : $query->andWhere('c.thread IS NULL');

        return (int) $query->getQuery()->getSingleScalarResult();
    }

    /**
     * The criteria of the comments online under a thread, the visitors' book for null (step E2b):
     * the thread compared by its key - `c.thread = :thread`, `c.thread IS NULL` - and not through a
     * join, where Doctrine puts Thread's trash filter: a comment under a trashed thread would read
     * as one of the visitors' book.
     *
     * @return array<string, mixed>
     */
    private function visible(?Thread $thread): array
    {
        return ['state' => CommentState::APPROVED, 'thread' => Key::of($thread)];
    }

    public function countPending(): int
    {
        return $this->count(['state' => CommentState::PENDING]);
    }

    /** @return list<Comment> */
    public function findPending(int $limit = 20): array
    {
        return $this->findBy(['state' => CommentState::PENDING], ['createdAt' => 'ASC'], $limit);
    }

    /** The last comment this address left, to hold the flood interval. */
    public function findLastFromIp(string $ip): ?Comment
    {
        return $this->findOneBy(['ip' => $ip], ['createdAt' => 'DESC']);
    }
}
