<?php

namespace App\Repository;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class Paginator
{
    public static function paginate(
        QueryBuilder $query,
        int $page,
        int $maxResults = 10
    ) {
        $firstResult = ($page - 1) * $maxResults;

        $query->setFirstResult($firstResult);
        $query->setMaxResults($maxResults);

        $paginator = new DoctrinePaginator($query);

        $paginator->setUseOutputWalkers(true);

        if (($paginator->count() <= $firstResult) && $page != 1) {
            throw new NotFoundHttpException();
        }

        return $paginator;
    }

}



