<?php

namespace Omnibridge\Doctrine\Omnibase\Repository;

use Base\Entity\Thread;
use Base\Entity\ThreadIntl;

use Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository;
use Base\Repository\ThreadTranslations;
use Base\Database\Repository\Finder\Group;

/**
 * @method ThreadIntl|null find($id, $lockMode = null, $lockVersion = null)
 * @method ThreadIntl|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method ThreadIntl[]    findAll()
 * @method ThreadIntl[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */
class ThreadIntlRepository extends ServiceEntityRepository implements ThreadTranslations
{
    public function search(array $terms, array $states = [], Thread|int|string|null $parent = null): array
    {
        $terms = array_filter(
            array_intersect_key($terms, ["title" => true, "excerpt" => true, "content" => true]),
            fn($term) => is_string($term) && trim($term) !== ""
        );
        if (!$terms) {
            return [];
        }
        if (!self::finderReadsGroups()) {
            return $this->searchByQueryBuilder($terms, $states, $parent);
        }

        // (LOWER(title) LIKE %term% OR LOWER(excerpt) LIKE ... ), then the thread's states and parent.
        $names = $values = [];
        foreach ($terms as $field => $term) {
            $names[] = "InsensitivePartial" . ucfirst($field);
            $values[] = "%" . mb_strtolower(trim($term)) . "%";
        }

        $criteria = [Group::by(implode("Or", $names), ...$values)];
        if ($states) {
            $criteria["translatable.state"] = $states;
        }
        if ($parent) {
            $criteria["translatable.parent"] = $parent;
        }

        // The translated thread joined, always (TranslatableNotNull), as the inner join of before: its
        // trash filter keeps the texts of a deleted thread out.
        return $this->findByTranslatableNotNull($criteria)->getResult();
    }

    /** search() under omnisense/omnibase 4.x, whose finder has no groups: the query of before step E2. */
    private function searchByQueryBuilder(array $terms, array $states, Thread|int|string|null $parent): array
    {
        $queryBuilder = $this->createQueryBuilder("i")->join("i.translatable", "t");
        $expr = [];
        foreach ($terms as $field => $term) {
            $expr[] = "LOWER(i." . $field . ") LIKE :term_" . $field;
            $queryBuilder->setParameter("term_" . $field, "%" . mb_strtolower(trim($term)) . "%");
        }
        $queryBuilder->andWhere(implode(" OR ", $expr));

        if ($states) {
            $queryBuilder->andWhere("t.state IN (:states)")->setParameter("states", $states);
        }
        if ($parent) {
            $queryBuilder->andWhere("t.parent = :parent")->setParameter("parent", $parent);
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
