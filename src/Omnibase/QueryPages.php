<?php

namespace Omnibridge\Doctrine\Omnibase;

use Omnibridge\Doctrine\Omnibase\Database\Walker\CountWalker;
use Omnibridge\Doctrine\Omnibase\Database\Walker\GroupByWalker;
use Omnibridge\Doctrine\Omnibase\Database\Walker\TranslatableWalker;
use Base\Service\Model\Pages;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\OffsetPaginator;

/**
 * A DQL query paged (plan "omnibase agnostic", step F2e): what Base\Service\Model\Pagination did with a
 * Query, moved here as it was - the rows grouped by id (GroupByWalker), the translations joined
 * (TranslatableWalker), a page the paged query's own window, the total counted by CountWalker on a clone.
 * The pages never go through a Doctrine paginator: paginator() gives doctrine/orm 3.8's OffsetPaginator (4.0;
 * the Paginator of before is deprecated there) to whoever pages the paged query themselves.
 */
final class QueryPages implements Pages
{
    private readonly Query $paged;
    private ?int $count = null;

    public function __construct(private readonly Query $query)
    {
        $paged = self::copy($query, false);
        $paged->setHint(Query::HINT_CUSTOM_TREE_WALKERS, [GroupByWalker::class]);
        $paged->setHint(GroupByWalker::HINT_GROUP_ARRAY, ['id']);
        $paged->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, TranslatableWalker::class);
        $this->paged = $paged;
    }

    public function count(): int
    {
        return $this->count ??= (int) self::countQuery($this->query, false)->getSingleScalarResult();
    }

    public function slice(int $offset, int $limit): array
    {
        return $this->query($offset, $limit)->getResult();
    }

    /** The paged query of that page (Pagination::getQuery()). */
    public function query(int $offset, int $limit): Query
    {
        return $this->paged->setFirstResult($offset)->setMaxResults($limit);
    }

    /**
     * doctrine/orm 3.8's OffsetPaginator, for the paged query (4.0; Doctrine's Paginator before, deprecated in 3.8).
     * It holds no query: it is given one with the window to read, the rows grouped and translated as slice() reads them:
     *
     *     $page = $pages->paginator()->paginate($pages->query(0, $size), Window::fromPageNumberAndSize($number, $size));
     *
     * @return OffsetPaginator<mixed>
     */
    public function paginator(): OffsetPaginator
    {
        return new OffsetPaginator();
    }

    /** The query counting the rows of $query (Pagination::getCountQuery()). */
    public static function countQuery(Query $query, bool $bHint = true): Query
    {
        $clone = self::copy($query, $bHint);
        $clone->setCacheable(false);
        $clone->setHint(Query::HINT_CUSTOM_TREE_WALKERS, [CountWalker::class]);
        $clone->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, TranslatableWalker::class);

        return $clone;
    }

    private static function copy(Query $query, bool $bHint = true): Query
    {
        $clone = clone $query;
        foreach ($query->getParameters() as $parameter) {
            $clone->setParameter($parameter->getName(), $parameter->getValue());
        }
        if ($bHint) {
            foreach ($query->getHints() as $hint => $class) {
                $clone->setHint($hint, $class);
            }
        }

        return $clone;
    }
}
