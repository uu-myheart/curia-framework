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
                            ? static::wrap($segment)
                            : static::wrapValue($segment);
        })->implode('.');
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
        	return '`' . $value . '`';

	        // return '"'.str_replace('"', '""', $value).'"';
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
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $limit
     * @return string
     */
    protected static function compileLimit(QueryBuilder $query, $limit)
    {
        return 'limit '.(int) $limit;
    }

    /**
     * Compile the "where" portions of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
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
     * @param  \Illuminate\Database\Query\Builder  $query
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
     * @param  \Illuminate\Database\Query\Builder  $query
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
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected static function whereBasic(QueryBuilder $query, $where)
    {
        $value = static::parameter($where['value']);

        return static::wrap($where['column']).' '.$where['operator'].' '.$value;
    }

    /**
     * Get the appropriate query parameter place-holder for a value.
     *
     * @param  mixed   $value
     * @return string
     */
    public static function parameter($value)
    {
        return static::isExpression($value) ? static::getValue($value) : '?';
    }

    /**
     * Determine if the given value is a raw expression.
     *
     * @param  mixed  $value
     * @return bool
     */
    public static function isExpression($value)
    {
        return $value instanceof Expression;
    }

    /**
     * Get the value of the expression.
     *
     * @return mixed
     */
    public function getValue()
    {
        return static::value;
    }

    /**
     * Format the where clause statements into one string.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
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
}