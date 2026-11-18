<?php

namespace Omnibridge\Doctrine\Omnibase;

use Omnibridge\Doctrine\Omnibase\Database\Walker\CountWalker;
use Omnibridge\Doctrine\Omnibase\Database\Walker\GroupByWalker;
use Omnibridge\Doctrine\Omnibase\Database\Walker\TranslatableWalker;
use Base\Service\Model\Pages;
use Doctrine\ORM\Query;

/**
 * A DQL query paged (plan "omnibase agnostic", step F2e): what Base\Service\Model\Pagination did with a
 * Query, moved here as it was - the rows grouped by id (GroupByWalker), the translations joined
 * (TranslatableWalker), a page the paged query's own window, the total counted by CountWalker on a clone.
 * The pages never go through a Doctrine paginator: slice() runs the paged query with its own window, count() the
 * CountWalker clone (4.0 removes paginator(); Doctrine's Paginator is deprecated by doctrine/orm 3.8).
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
