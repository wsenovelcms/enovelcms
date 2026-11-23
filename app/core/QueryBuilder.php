<?php

class QueryBuilder
{
    private PDO $pdo;
    private string $prefix;

    private string $mainTable;
    private string $mainAlias;
    private array $joins = [];
    private string $select = '*';
    private bool $distinct = false;
    private array $wheres = [];
    private array $params = [];
    private string $groupBy = '';
    private string $having = '';
    private string $orderBy = '';
    private int $limit = 0;
    private int $offset = 0;

    public function __construct(PDO $pdo, string $prefix, string $table, string $alias = '')
    {
        $this->pdo = $pdo;
        $this->prefix = $prefix;
        $this->mainTable = $table;
        $this->mainAlias = $alias;
    }

    public function select(string $fields): self
    {
        $this->select = $fields;
        return $this;
    }

    public function selectRaw(string $sql): self
    {
        $this->select = $sql;
        return $this;
    }

    public function distinct(bool $value = true): self
    {
        $this->distinct = $value;
        return $this;
    }

    public function join(string $table, string $alias, string $on): self
    {
        $this->joins[] = "INNER JOIN `" . $this->prefix . $table . "` {$alias} ON {$on}";
        return $this;
    }

    public function leftJoin(string $table, string $alias, string $on): self
    {
        $this->joins[] = "LEFT JOIN `" . $this->prefix . $table . "` {$alias} ON {$on}";
        return $this;
    }

    public function rightJoin(string $table, string $alias, string $on): self
    {
        $this->joins[] = "RIGHT JOIN `" . $this->prefix . $table . "` {$alias} ON {$on}";
        return $this;
    }

    public function where(string $condition, array $params = []): self
    {
        if (trim($condition) === '') return $this;
        $this->wheres[] = ['AND', $condition];
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    public function orWhere(string $condition, array $params = []): self
    {
        if (trim($condition) === '') return $this;
        $this->wheres[] = ['OR', $condition];
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    public function whereIn(string $field, array $values): self
    {
        if (empty($values)) {
            $this->wheres[] = ['AND', '1=0'];
            return $this;
        }
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $this->wheres[] = ['AND', "{$field} IN ({$placeholders})"];
        $this->params = array_merge($this->params, array_values($values));
        return $this;
    }

    public function whereNotIn(string $field, array $values): self
    {
        if (empty($values)) return $this;
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $this->wheres[] = ['AND', "{$field} NOT IN ({$placeholders})"];
        $this->params = array_merge($this->params, array_values($values));
        return $this;
    }

    public function whereNull(string $field): self
    {
        $this->wheres[] = ['AND', "{$field} IS NULL"];
        return $this;
    }

    public function whereNotNull(string $field): self
    {
        $this->wheres[] = ['AND', "{$field} IS NOT NULL"];
        return $this;
    }

    public function whereBetween(string $field, $min, $max): self
    {
        $this->wheres[] = ['AND', "{$field} BETWEEN ? AND ?"];
        $this->params[] = $min;
        $this->params[] = $max;
        return $this;
    }

    public function whereLike(string $field, string $keyword): self
    {
        $this->wheres[] = ['AND', "{$field} LIKE ?"];
        $this->params[] = '%' . $keyword . '%';
        return $this;
    }

    public function groupBy(string $expr): self
    {
        $this->groupBy = $expr;
        return $this;
    }

    public function having(string $condition, array $params = []): self
    {
        $this->having = $condition;
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    public function orderBy(string $expr): self
    {

        $this->orderBy = Database::sanitizeOrderExpr($expr);
        return $this;
    }

    public function limit(int $n, int $offset = 0): self
    {
        $this->limit = $n;
        $this->offset = $offset;
        return $this;
    }

    public function get(): array
    {
        $sql = $this->buildSql();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->fetchAll();
    }

    public function first(): ?array
    {
        $this->limit = 1;
        $rows = $this->get();
        return $rows[0] ?? null;
    }

    public function count(): int
    {
        $countSql = "SELECT COUNT(*) AS cnt FROM `" . $this->prefix . $this->mainTable . "`"
                  . ($this->mainAlias ? " {$this->mainAlias}" : '')
                  . ($this->joins ? ' ' . implode(' ', $this->joins) : '')
                  . $this->buildWhereSql();
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($this->params);
        return (int)($stmt->fetch()['cnt'] ?? 0);
    }

    public function paginate(int $perPage = 20, int $page = 1): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $total = $this->count();
        $this->limit = $perPage;
        $this->offset = ($page - 1) * $perPage;
        $list = $this->get();

        return [
            'list'       => $list,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int)ceil($total / $perPage),
        ];
    }

    private function buildWhereSql(): string
    {
        if (empty($this->wheres)) return '';
        $parts = [];
        foreach ($this->wheres as $i => [$bool, $cond]) {
            if ($i === 0) {
                $parts[] = $cond;
            } else {
                $parts[] = $bool . ' ' . $cond;
            }
        }
        return ' WHERE ' . implode(' ', $parts);
    }

    private function buildSql(): string
    {
        $select = $this->distinct ? 'SELECT DISTINCT ' : 'SELECT ';
        $sql = $select . $this->select . ' FROM `' . $this->prefix . $this->mainTable . '`'
             . ($this->mainAlias ? " {$this->mainAlias}" : '');

        if ($this->joins) {
            $sql .= ' ' . implode(' ', $this->joins);
        }
        $sql .= $this->buildWhereSql();
        if ($this->groupBy) {
            $sql .= " GROUP BY {$this->groupBy}";
        }
        if ($this->having) {
            $sql .= " HAVING {$this->having}";
        }
        if ($this->orderBy) {
            $sql .= " ORDER BY {$this->orderBy}";
        }
        if ($this->limit > 0) {
            $sql .= " LIMIT {$this->limit}";
            if ($this->offset > 0) {
                $sql .= " OFFSET {$this->offset}";
            }
        }
        return $sql;
    }
}
