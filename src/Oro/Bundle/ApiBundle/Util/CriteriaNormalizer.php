<?php

namespace Oro\Bundle\ApiBundle\Util;

use Oro\Bundle\ApiBundle\Collection\Criteria;
use Oro\Bundle\ApiBundle\Collection\Join;

/**
 * Performs the following normalizations of the Criteria object:
 * * sets missing join aliases
 * * replaces LEFT JOIN with INNER JOIN where it is possible
 */
class CriteriaNormalizer
{
    private const JOIN_ALIAS_TEMPLATE = 'alias%d';

    private const FIELD_OPTION         = 'field';
    private const PARENT_PATH_OPTION   = 'parentPath';
    private const NESTING_LEVEL_OPTION = 'nestingLevel';

    /**
     * @param Criteria $criteria
     */
    public function normalizeCriteria(Criteria $criteria): void
    {
        $this->ensureJoinAliasesSet($criteria);
        $this->completeJoins($criteria);
        $this->optimizeJoins($criteria);
    }

    /**
     * Sets missing join aliases.
     *
     * @param Criteria $criteria
     */
    private function ensureJoinAliasesSet(Criteria $criteria): void
    {
        $counter = 0;
        $joins = $criteria->getJoins();
        foreach ($joins as $join) {
            $counter++;
            if (!$join->getAlias()) {
                $join->setAlias(\sprintf(self::JOIN_ALIAS_TEMPLATE, $counter));
            }
        }
    }

    /**
     * Makes sure that this criteria object contains all required joins and aliases are set for all joins.
     *
     * @param Criteria $criteria
     */
    private function completeJoins(Criteria $criteria): void
    {
        $pathMap = $this->getJoinPathMap($criteria);
        if (!empty($pathMap)) {
            $aliases = [];
            $joins = $criteria->getJoins();
            foreach ($joins as $join) {
                $aliases[] = $join->getAlias();
            }

            $this->sortJoinPathMap($pathMap);
            foreach ($pathMap as $path => $item) {
                if (!$criteria->hasJoin($path)) {
                    $parentPath = $item[self::PARENT_PATH_OPTION];
                    $parentAlias = $parentPath
                        ? $criteria->getJoin($parentPath)->getAlias()
                        : Criteria::ROOT_ALIAS_PLACEHOLDER;

                    $alias = $item[self::FIELD_OPTION];
                    $count = 0;
                    while (\in_array($alias, $aliases, true)) {
                        $alias = \sprintf('%s%d', $item[self::FIELD_OPTION], ++$count);
                    }
                    $aliases[] = $alias;

                    $criteria
                        ->addLeftJoin($path, $parentAlias . '.' . $item[self::FIELD_OPTION])
                        ->setAlias($alias);
                }
            }
        }
    }

    /**
     * Replaces LEFT JOIN with INNER JOIN where it is possible.
     *
     * @param Criteria $criteria
     */
    private function optimizeJoins(Criteria $criteria): void
    {
        $fields = $this->getWhereFields($criteria);
        foreach ($fields as $field) {
            $lastDelimiter = \strrpos($field, '.');
            while (false !== $lastDelimiter) {
                $field = \substr($field, 0, $lastDelimiter);
                $lastDelimiter = false;
                $join = $criteria->getJoin($field);
                if (null !== $join && Join::LEFT_JOIN === $join->getJoinType()) {
                    $join->setJoinType(Join::INNER_JOIN);
                    $lastDelimiter = \strrpos($field, '.');
                }
            }
        }
    }

    /**
     * @param Criteria $criteria
     *
     * @return array
     *  [
     *      path => [
     *          'field' => string,
     *          'parentPath' => string|null,
     *          'nestingLevel' => integer
     *      ],
     *      ...
     *  ]
     */
    private function getJoinPathMap(Criteria $criteria): array
    {
        $pathMap = [];

        $joins = $criteria->getJoins();
        foreach ($joins as $path => $join) {
            $pathMap[$path] = $this->buildJoinPathMapValue($path);
        }

        $rootPath = \substr(Criteria::ROOT_ALIAS_PLACEHOLDER, 1, -1);
        $fields = $this->getFields($criteria);
        foreach ($fields as $field) {
            while ($field) {
                $path = $this->getPath($field, $rootPath);
                $field = null;
                if ($path && Criteria::ROOT_ALIAS_PLACEHOLDER !== $path && !isset($pathMap[$path])) {
                    $pathMap[$path] = $this->buildJoinPathMapValue($path);
                    $field = $path;
                }
            }
        }

        return $pathMap;
    }

    /**
     * @param string $field
     * @param string $rootPath
     *
     * @return string|null
     */
    private function getPath(string $field, string $rootPath): ?string
    {
        $path = null;
        if (0 === \strpos($field, '{')) {
            if ('}' === \substr($field, -1)) {
                $path = \substr($field, 1, -2);
                if ($rootPath === $path) {
                    $path = null;
                }
            } else {
                $lastDelimiter = \strrpos($field, '.');
                if (false !== $lastDelimiter && '}' === $field[$lastDelimiter - 1]) {
                    $path = \substr($field, 1, $lastDelimiter - 2);
                    if ($rootPath === $path) {
                        $path = null;
                    }
                }
            }
        } else {
            $lastDelimiter = \strrpos($field, '.');
            if (false !== $lastDelimiter) {
                $path = \substr($field, 0, $lastDelimiter);
            }
        }

        return $path;
    }

    /**
     * @param string $path
     *
     * @return array
     */
    private function buildJoinPathMapValue(string $path): array
    {
        $lastDelimiter = \strrpos($path, '.');
        if (false === $lastDelimiter) {
            return [
                self::FIELD_OPTION         => $path,
                self::PARENT_PATH_OPTION   => null,
                self::NESTING_LEVEL_OPTION => 0

            ];
        }

        $parentPath = \substr($path, 0, $lastDelimiter);

        return [
            self::FIELD_OPTION         => \substr($path, $lastDelimiter + 1),
            self::PARENT_PATH_OPTION   => $parentPath,
            self::NESTING_LEVEL_OPTION => \substr_count($parentPath, '.') + 1
        ];
    }

    /**
     * @param Criteria $criteria
     *
     * @return string[]
     */
    private function getWhereFields(Criteria $criteria): array
    {
        $whereExpr = $criteria->getWhereExpression();
        if (!$whereExpr) {
            return [];
        }

        $visitor = new FieldVisitor();
        $visitor->dispatch($whereExpr);

        return $visitor->getFields();
    }

    /**
     * @param Criteria $criteria
     *
     * @return string[]
     */
    private function getFields(Criteria $criteria): array
    {
        $fields = $this->getWhereFields($criteria);

        $orderBy = $criteria->getOrderings();
        foreach ($orderBy as $field => $direction) {
            if (!\in_array($field, $fields, true)) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * @param array $pathMap
     */
    private function sortJoinPathMap(array &$pathMap)
    {
        \uasort(
            $pathMap,
            function (array $a, array $b) {
                if ($a[self::NESTING_LEVEL_OPTION] === $b[self::NESTING_LEVEL_OPTION]) {
                    return 0;
                }

                return $a[self::NESTING_LEVEL_OPTION] < $b[self::NESTING_LEVEL_OPTION] ? -1 : 1;
            }
        );
    }
}
