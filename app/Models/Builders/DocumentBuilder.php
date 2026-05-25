<?php

namespace App\Models\Builders;

use Illuminate\Database\Eloquent\Builder;

class DocumentBuilder extends Builder
{
    /** @var list<string> */
    private const BINARY_COLUMNS = ['id', 'type', 'state', 'user_id', 'period_id'];

    /**
     * @param  \Closure|string|array|\Illuminate\Contracts\Database\Query\Expression>  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if ($column instanceof \Closure) {
            return parent::where($column, $operator, $value, $boolean);
        }

        if (is_array($column)) {
            return $this->addArrayOfWheres($column, $boolean);
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        if ($this->shouldUseBinaryComparison($column, $operator, $value)) {
            $wrapped = $this->getGrammar()->wrap($column);

            return $this->whereRaw(
                "BINARY {$wrapped} {$operator} BINARY ?",
                [$value],
                $boolean
            );
        }

        return parent::where($column, $operator, $value, $boolean);
    }

    /**
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  mixed  $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        if ($this->isBinaryColumn($column) && is_array($values) && $values !== []) {
            $wrapped = $this->getGrammar()->wrap($column);
            $operator = $not ? 'not in' : 'in';
            $placeholders = implode(', ', array_fill(0, count($values), 'BINARY ?'));

            return $this->whereRaw(
                "BINARY {$wrapped} {$operator} ({$placeholders})",
                array_values($values),
                $boolean
            );
        }

        return parent::whereIn($column, $values, $boolean, $not);
    }

    /**
     * @param  array<string, mixed>  $column
     * @param  string  $boolean
     * @param  string  $method
     * @return $this
     */
    protected function addArrayOfWheres($column, $boolean, $method = 'where')
    {
        return $this->whereNested(function ($query) use ($column, $method, $boolean) {
            foreach ($column as $key => $value) {
                if (is_numeric($key) && $value instanceof \Closure) {
                    $query->where($value, boolean: 'and');
                } elseif ($this->shouldUseBinaryComparison($key, '=', $value)) {
                    $wrapped = $this->getGrammar()->wrap($key);
                    $query->whereRaw("BINARY {$wrapped} = BINARY ?", [$value], 'and');
                } else {
                    $query->{$method}($key, '=', $value, 'and');
                }
            }
        }, $boolean);
    }

    private function shouldUseBinaryComparison(mixed $column, mixed $operator, mixed $value): bool
    {
        if (! is_string($column) || ! $this->isBinaryColumn($column)) {
            return false;
        }

        if (! is_string($operator)) {
            return false;
        }

        $operator = strtolower($operator);

        if (! in_array($operator, ['=', '!=', '<>'], true)) {
            return false;
        }

        return is_string($value) || is_int($value) || (is_object($value) && method_exists($value, '__toString'));
    }

    private function isBinaryColumn(mixed $column): bool
    {
        if (! is_string($column)) {
            return false;
        }

        if (str_contains($column, '.')) {
            $column = substr($column, strrpos($column, '.') + 1);
        }

        return in_array($column, self::BINARY_COLUMNS, true);
    }
}
