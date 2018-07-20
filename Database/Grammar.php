<?php

namespace Curia\Framework\Database;

Class Grammar
{
	/**
     * The value of the expression.
     *
     * @var mixed
     */
    protected static $value;

	/**
     * The grammar table prefix.
     *
     * @var string
     */
    protected static $tablePrefix = '';

    /**
     * The grammar specific operators.
     *
     * @var array
     */
    protected static $operators = [];

    /**
     * The components that make up a select clause.
     *
     * @var array
     */
    protected static $selectComponents = [
        'aggregate',
        'columns',
        'from',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        'limit',
        'offset',
        'unions',
        'lock',
    ];

    /**
     * Compile a select query into SQL.
     *
     * @param  \Curia\Framework\Database\Builder $query
     * @return string
     */
    public static function compileSelect(QueryBuilder $query)
    {
        // If the query does not have any columns set, we'll set the columns to the
        // * character to just get all of the columns from the database. Then we
        // can build the query and concatenate all the pieces together as one.
        $original = $query->columns;

        if (is_null($query->columns)) {
            $query->columns = ['*'];
        }

        // To compile the query, we'll spin through each component of the query and
        // see if that component exists. If it does we'll just call the compiler
        // function for the component which is responsible for making the SQL.
        $sql = trim(static::concatenate(
            static::compileComponents($query))
        );

        $query->columns = $original;

        return $sql;
    }

    /**
     * Concatenate an array of segments, removing empties.
     *
     * @param  array   $segments
     * @return string
     */
    protected static function concatenate($segments)
    {
        return implode(' ', array_filter($segments, function ($value) {
            return (string) $value !== '';
        }));
    }

    /**
     * Compile the components necessary for a select clause.
     *
     * @param  QueryBuilder  $query
     * @return array
     */
    protected static function compileComponents(QueryBuilder $query)
    {
        $sql = [];

        foreach (static::$selectComponents as $component) {
            // To compile the query, we'll spin through each component of the query and
            // see if that component exists. If it does we'll just call the compiler
            // function for the component which is responsible for making the SQL.
            if (! is_null($query->$component)) {
                $method = 'compile'.ucfirst($component);

                $sql[$component] = static::$method($query, $query->$component);
            }
        }

        return $sql;
    }

    /**
     * Compile the "select *" portion of the query.
     *
     * @param  \Curia\Framework\Database\Builder $query
     * @param  array  $columns
     * @return string|null
     */
    protected static function compileColumns(QueryBuilder $query, $columns)
    {
        // If the query is actually performing an aggregating select, we will let that
        // compiler handle the building of the select clauses, as it will need some
        // more syntax that is best handled by that function to keep things neat.
        if (! is_null($query->aggregate)) {
            return;
        }

        $select = $query->distinct ? 'select distinct ' : 'select ';

        return $select.static::columnize($columns);
    }

    /**
     * Convert an array of column names into a delimited string.
     *
     * @param  array   $columns
     * @return string
     */
    public static function columnize(array $columns)
    {
        return implode(', ', array_map(['static', 'wrap'], $columns));
    }

    /**
     * Wrap a value in keyword identifiers.
     *
     * @param  \Illuminate\Database\Query\Expression|string  $value
     * @param  bool    $prefixAlias
     * @return string
     */
    public static function wrap($value)
    {
        if ($value instanceof Expression) {
            return $value->getValue();
        }

        if (strpos(strtolower($value), ' as ') !== false) {
            return static::wrapAliasedValue($value);
        }

        return static::wrapSegments(explode('.', $value));
    }

    /**
     * Wrap a value that has an alias.
     *
     * @param  string  $value
     * @param  bool  $prefixAlias
     * @return string
     */
    protected static function wrapAliasedValue($value)
    {
        $segments = preg_split('/\s+as\s+/i', $value);

        return static::wrap($segments[0]) . ' as ' . static::wrapValue($segments[1]);
    }

    /**
     * Wrap the given value segments.
     *
     * @param  array  $segments
     * @return string
     */
    static function wrapSegments($segments)
    {
        return collect($segments)->map(function ($segment, $key) use ($segments) {
            return $key == 0 && count($segments) > 1
                            ? static::wrapTable($segment)
                            : static::wrapValue($segment);
        })->implode('.');
    }

    /**
     * Wrap a table in keyword identifiers.
     *
     * @param  \Illuminate\Database\Query\Expression|string  $table
     * @return string
     */
    public static function wrapTable($table)
    {
        if (! $table instanceof Expression) {
            return static::wrap(static::$tablePrefix.$table, true);
        }

        return static::getValue($table);
    }

    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param  string  $value
     * @return string
     */
    protected static function wrapValue($value)
    {
        if ($value !== '*') {
        	return '`' . str_replace('`', '``', $value) . '`';
	    }

	    return $value;
    }

    /**
     * Compile the "from" portion of the query.
     *
     * @param  \Curia\Framework\Database\Builder $query
     * @param  string  $table
     * @return string
     */
    protected static function compileFrom(QueryBuilder $query, $table)
    {
        return 'from ' . static::wrap($table);
    }

    /**
     * Compile the "limit" portions of the query.
     *
     * @param  \Curia\Database\QueryBuilder  $query
     * @param  int  $limit
     * @return string
     */
    protected static function compileLimit(QueryBuilder $query, $limit)
    {
        return 'limit '.(int) $limit;
    }

    /**
     * Compile the "offset" portions of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $offset
     * @return string
     */
    protected static function compileOffset(QueryBuilder $query, $offset)
    {
        return 'offset '.(int) $offset;
    }

    /**
     * Compile the "where" portions of the query.
     *
     * @param  \Curia\Database\QueryBuilder  $query
     * @return string
     */
    protected static function compileWheres(QueryBuilder $query)
    {
        // Each type of where clauses has its own compiler function which is responsible
        // for actually creating the where clauses SQL. This helps keep the code nice
        // and maintainable since each clause has a very small method that it uses.
        if (is_null($query->wheres)) {
            return '';
        }

        // If we actually have some where clauses, we will strip off the first boolean
        // operator, which is added by the query builders for convenience so we can
        // avoid checking for the first clauses in each of the compilers methods.
        if (count($sql = static::compileWheresToArray($query)) > 0) {
            return static::concatenateWhereClauses($query, $sql);
        }

        return '';
    }

    /**
     * Get an array of all the where clauses for the query.
     *
     * @param  \Curia\Database\QueryBuilder  $query
     * @return array
     */
    protected static function compileWheresToArray($query)
    {
        return collect($query->wheres)->map(function ($where) use ($query) {
            return $where['boolean'].' '.static::{"where{$where['type']}"}($query, $where);
        })->all();
    }

    /**
     * Compile a nested where clause.
     *
     * @param  \Curia\Database\QueryBuilder  $query
     * @param  array  $where
     * @return string
     */
    protected static function whereNested(QueryBuilder $query, $where)
    {
        // Here we will calculate what portion of the string we need to remove. If this
        // is a join clause query, we need to remove the "on" portion of the SQL and
        // if it is a normal query we need to take the leading "where" of queries.
        $offset = $query instanceof JoinClause ? 3 : 6;

        return '('.substr(static::compileWheres($where['query']), $offset).')';
    }

    /**
     * Compile a basic where clause.
     *
     * @param  \Curia\Database\QueryBuilder  $query
     * @param  array  $where
     * @return string
     */
    protected static function whereBasic(QueryBuilder $query, $where)
    {
        $value = static::parameter($where['value']);

        return static::wrap($where['column']).' '.$where['operator'].' '.$value;
    }

    /**
     * Compile a where condition with a sub-select.
     *
     * @param  \Curia\Database\QueryBuilder $query
     * @param  array   $where
     * @return string
     */
    protected static function whereSub(QueryBuilder $query, $where)
    {
        $select = static::compileSelect($where['query']);

        return static::wrap($where['column']).' '.$where['operator']." ($select)";
    }

    /**
     * Compile a "where null" clause.
     *
     * @param  \Curia\Database\QueryBuilder  $query
     * @param  array  $where
     * @return string
     */
    protected static function whereNull(QueryBuilder $query, $where)
    {
        return static::wrap($where['column']).' is null';
    }

    /**
     * Compile a "where not null" clause.
     *
     * @param  \Curia\Database\QueryBuilder  $query
     * @param  array  $where
     * @return string
     */
    protected static function whereNotNull(QueryBuilder $query, $where)
    {
        return static::wrap($where['column']).' is not null';
    }

    /**
     * Compile a "between" where clause.
     *
     * @param  \Curia\Database\QueryBuilder  $query
     * @param  array  $where
     * @return string
     */
    protected static function whereBetween(QueryBuilder $query, $where)
    {
        $between = $where['not'] ? 'not between' : 'between';

        return static::wrap($where['column']).' '.$between.' ? and ?';
    }

    /**
     * Compile a "where in" clause.
     *
     * @param  \Curia\Database\QueryBuilder  $query
     * @param  array  $where
     * @return string
     */
    protected static function whereIn(QueryBuilder $query, $where)
    {
        if (! empty($where['values'])) {
            return static::wrap($where['column']).' in ('.static::parameterize($where['values']).')';
        }

        return '0 = 1';
    }

    /**
     * Compile a "where not in" clause.
     *
     * @param  \Curia\Database\QueryBuilder  $query
     * @param  array  $where
     * @return string
     */
    protected static function whereNotIn(QueryBuilder $query, $where)
    {
        if (! empty($where['values'])) {
            return static::wrap($where['column']).' not in ('.static::parameterize($where['values']).')';
        }

        return '1 = 1';
    }

    /**
     * Compile a where in sub-select clause.
     *
     * @param  \Curia\Database\QueryBuilder  $query
     * @param  array  $where
     * @return string
     */
    protected static function whereInSub(QueryBuilder $query, $where)
    {
        return static::wrap($where['column']).' in ('.static::compileSelect($where['query']).')';
    }

    /**
     * Compile a where not in sub-select clause.
     *
     * @param  \Curia\Database\QueryBuilder  $query
     * @param  array  $where
     * @return string
     */
    protected static function whereNotInSub(QueryBuilder $query, $where)
    {
        return static::wrap($where['column']).' not in ('.static::compileSelect($where['query']).')';
    }

    /**
     * Get the appropriate query parameter place-holder for a value.
     *
     * @param  mixed   $value
     * @return string
     */
    public static function parameter($value)
    {
        return $value instanceof Expression ? $value->getValue($value) : '?';
    }

    /**
     * Format the where clause statements into one string.
     *
     * @param  \Curia\Database\QueryBuilder  $query
     * @param  array  $sql
     * @return string
     */
    protected static function concatenateWhereClauses($query, $sql)
    {
        $conjunction = $query instanceof JoinClause ? 'on' : 'where';

        return $conjunction.' ' . static::removeLeadingBoolean(implode(' ', $sql));
    }

    /**
     * Remove the leading boolean from a statement.
     *
     * @param  string  $value
     * @return string
     */
    protected static function removeLeadingBoolean($value)
    {
        return preg_replace('/and |or /i', '', $value, 1);
    }

    /**
     * Get the grammar specific operators.
     *
     * @return array
     */
    public static function getOperators()
    {
        return static::$operators;
    }

    /**
     * Create query parameter place-holders for an array.
     *
     * @param  array   $values
     * @return string
     */
    public static function parameterize(array $values)
    {
        return implode(', ', array_map(['static', 'parameter'], $values));
    }

    /**
     * Compile a "where date" clause.
     *
     * @param  \Curia\Database\QueryBuilder  $query
     * @param  array  $where
     * @return string
     */
    protected static function whereDate(QueryBuilder $query, $where)
    {
        return static::dateBasedWhere('date', $query, $where);
    }

    /**
     * Compile a "where time" clause.
     *
     * @param  \Curia\Database\QueryBuilder  $query
     * @param  array  $where
     * @return string
     */
    protected static function whereTime(QueryBuilder $query, $where)
    {
        return static::dateBasedWhere('time', $query, $where);
    }

    /**
     * Compile a "where day" clause.
     *
     * @param  \Curia\Database\QueryBuilder  $query
     * @param  array  $where
     * @return string
     */
    protected static function whereDay(QueryBuilder $query, $where)
    {
        return static::dateBasedWhere('day', $query, $where);
    }

    /**
     * Compile a "where month" clause.
     *
     * @param  \Curia\Database\QueryBuilder  $query
     * @param  array  $where
     * @return string
     */
    protected static function whereMonth(QueryBuilder $query, $where)
    {
        return static::dateBasedWhere('month', $query, $where);
    }

    /**
     * Compile a "where year" clause.
     *
     * @param  \Curia\Database\QueryBuilder  $query
     * @param  array  $where
     * @return string
     */
    protected static function whereYear(QueryBuilder $query, $where)
    {
        return static::dateBasedWhere('year', $query, $where);
    }

    /**
     * Compile a date based where clause.
     *
     * @param  string  $type
     * @param  \Curia\Database\QueryBuilder  $query
     * @param  array  $where
     * @return string
     */
    protected static function dateBasedWhere($type, QueryBuilder $query, $where)
    {
        $value = static::parameter($where['value']);

        return $type.'('.static::wrap($where['column']).') '.$where['operator'].' '.$value;
    }

    /**
     * Compile a where clause comparing two columns..
     *
     * @param  \Curia\Database\QueryBuilder  $query
     * @param  array  $where
     * @return string
     */
    protected static function whereColumn(QueryBuilder $query, $where)
    {
        return static::wrap($where['first']).' '.$where['operator'].' '.static::wrap($where['second']);
    }

    /**
     * Compile a where exists clause.
     *
     * @param  \Curia\Database\QueryBuilder  $query
     * @param  array  $where
     * @return string
     */
    protected static function whereExists(QueryBuilder $query, $where)
    {
        return 'exists ('.static::compileSelect($where['query']).')';
    }

    /**
     * Compile a where exists clause.
     *
     * @param  \Curia\Database\QueryBuilder  $query
     * @param  array  $where
     * @return string
     */
    protected static function whereNotExists(QueryBuilder $query, $where)
    {
        return 'not exists ('.static::compileSelect($where['query']).')';
    }

    /**
     * Compile a raw where clause.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected static function whereRaw(QueryBuilder $query, $where)
    {
        return $where['sql'];
    }

    /**
     * Compile the "order by" portions of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $orders
     * @return string
     */
    protected static function compileOrders(QueryBuilder $query, $orders)
    {
        if (! empty($orders)) {
            return 'order by '.implode(', ', static::compileOrdersToArray($query, $orders));
        }

        return '';
    }

    /**
     * Compile the query orders to an array.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $orders
     * @return array
     */
    protected static function compileOrdersToArray(QueryBuilder $query, $orders)
    {
        return array_map(function ($order) {
            return ! isset($order['sql'])
                        ? static::wrap($order['column']).' '.$order['direction']
                        : $order['sql'];
        }, $orders);
    }

    /**
     * Compile the random statement into SQL.
     *
     * @param  string  $seed
     * @return string
     */
    public static function compileRandom($seed)
    {
        return 'RAND('.$seed.')';
    }

    /**
     * Compile the "group by" portions of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $groups
     * @return string
     */
    protected static function compileGroups(QueryBuilder $query, $groups)
    {
        return 'group by '.static::columnize($groups);
    }

    /**
     * Compile the "having" portions of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $havings
     * @return string
     */
    protected static function compileHavings(QueryBuilder $query, $havings)
    {
        $sql = implode(' ', array_map(['static', 'compileHaving'], $havings));

        return 'having '.static::removeLeadingBoolean($sql);
    }

    /**
     * Compile a single having clause.
     *
     * @param  array   $having
     * @return string
     */
    protected static function compileHaving(array $having)
    {
        // If the having clause is "raw", we can just return the clause straight away
        // without doing any more processing on it. Otherwise, we will compile the
        // clause into SQL based on the components that make it up from builder.
        if ($having['type'] === 'Raw') {
            return $having['boolean'].' '.$having['sql'];
        }

        return static::compileBasicHaving($having);
    }

    /**
     * Compile a basic having clause.
     *
     * @param  array   $having
     * @return string
     */
    protected static function compileBasicHaving($having)
    {
        $column = static::wrap($having['column']);

        $parameter = static::parameter($having['value']);

        return $having['boolean'].' '.$column.' '.$having['operator'].' '.$parameter;
    }

    /**
     * Compile a where row values condition.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected static function whereRowValues(QueryBuilder $query, $where)
    {
        $values = static::parameterize($where['values']);

        return '('.implode(', ', $where['columns']).') '.$where['operator'].' ('.$values.')';
    }

    /**
     * Compile the "join" portions of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $joins
     * @return string
     */
    protected static function compileJoins(QueryBuilder $query, $joins)
    {
        return collect($joins)->map(function ($join) use ($query) {
            $table = static::wrapTable($join->table);

            $nestedJoins = is_null($join->joins) ? '' : ' '.static::compileJoins($query, $join->joins);

            return trim("{$join->type} join {$table}{$nestedJoins} " . static::compileWheres($join));
        })->implode(' ');
    }
}