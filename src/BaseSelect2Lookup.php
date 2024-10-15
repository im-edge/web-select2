<?php

namespace IMEdge\Web\Select2;

use Ramsey\Uuid\Uuid;
use gipfl\ZfDb\Adapter\Adapter as Db;
use gipfl\ZfDb\Select;
use RuntimeException;
use stdClass;

abstract class BaseSelect2Lookup
{
    protected const UNDEFINED = '__UNDEFINED__';

    protected Db $db;
    protected string $table = self::UNDEFINED;
    protected string $idColumn = self::UNDEFINED;
    protected bool $usesUuid = false;
    /** @var string[] */
    protected array $searchColumns = [];
    /** @var string[] */
    protected array $textColumns = [];
    protected ?string $searchString = null;
    protected ?int $page = 1;

    public function __construct(
        Db $db,
        ?string $searchString = null,
        ?int $page = 1
    ) {
        $this->page = $page;
        $this->searchString = $searchString;
        $this->db = $db;
    }

    /**
     * @return array{results: array<array{id: string, text: string}>, pagination: array{more: bool}, query: string}
     */
    public function getResponse(): array
    {
        [$more, $rows] = $this->fetchLimited($this->prepareQuery());
        return [
            'results'    => $this->prepareResult($rows),
            'pagination' => [ 'more' => $more ],
            'query' => (string) $this->prepareQuery(),
        ];
    }

    /**
     * @return string[]
     */
    protected function getTextColumns(): array
    {
        if (empty($this->textColumns)) {
            throw new RuntimeException('No $textColumns have been defined');
        }

        return $this->textColumns;
    }

    protected function stripAlias(string $column): string
    {
        $result = preg_replace('/^[a-z]+\./', '', $column);
        if ($result === null) {
            throw new RuntimeException("Failed to strip alias from $column");
        }

        return $result;
    }

    protected function prepareQuery(): Select
    {
        $query = $this->eventuallySearch($this->select());

        // Order matters, we sort AFTER search order has been applied
        foreach ($this->getTextColumns() as $textColumn) {
            $query->order($textColumn);
        }

        return $query;
    }

    /**
     * @return string[]
     */
    protected function getSelectColumns(): array
    {
        return array_merge([$this->idColumn], $this->getTextColumns());
    }

    /**
     * @param stdClass[] $rows
     * @return array<array{id: string, text: string}>
     */
    protected function prepareResult(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->prepareResultRow($row);
        }

        return $result;
    }

    /**
     * @return array{id: string, text: string}
     */
    protected function prepareResultRow(stdClass $row): array
    {
        return [
            'id'   => $this->getFormattedId($row),
            'text' => $this->getFormattedText($row),
        ];
    }

    protected function getFormattedId(stdClass $row): string
    {
        $column = $this->stripAlias($this->idColumn);
        if ($this->usesUuid) {
            return Uuid::fromBytes($row->$column)->toString();
        }

        return $row->$column;
    }

    protected function getFormattedText(stdClass $row): string
    {
        $values = [];
        foreach ($this->getTextColumns() as $column) {
            $values[] = $row->{$this->stripAlias($column)};
        }

        return implode(' ', $values);
    }

    /**
     * @param string|int $id
     */
    public function hasId($id): bool
    {
        if (empty($id)) {
            return false;
        }

        return 1 === (int) $this->db->fetchOne(
            $this->filterId($this->select(['(1)']), $id)
        );
    }

    /**
     * @param array<int|string, string> $columns
     * @return Select
     */
    protected function select(?array $columns = null): Select
    {
        return $this->db->select()->from($this->table, $columns ?: $this->getSelectColumns());
    }

    /**
     * @param string|int $id
     */
    protected function filterId(Select $query, $id): Select
    {
        if ($this->usesUuid && is_string($id)) {
            return $query->where($this->idColumn . ' = ?', Uuid::fromString($id)->getBytes());
        }

        return $query->where($this->idColumn . ' = ?', $id);
    }

    protected function eventuallySearch(Select $query): Select
    {
        if (!empty($this->searchString)) {
            $this->searchInQuery($query);
        }

        return $query;
    }

    protected function searchInQuery(Select $query): Select
    {
        $search = $this->searchString;
        assert($search !== null);
        $first = true;
        $searchColumns = empty($this->searchColumns) ? $this->getTextColumns() : $this->searchColumns;
        foreach ($searchColumns as $searchColumn) {
            if ($first) {
                $first = false;
                $query->where("$searchColumn LIKE ?", "%$search%");
            } else {
                $query->orWhere("$searchColumn LIKE ?", "%$search%");
            }
            $this->applySearchOrder($query, $searchColumn, $search);
        }

        return $query;
    }

    /**
     * @param string|int $id
     * @return array{id: string, text: string}
     */
    public function getOptionalPair($id): ?array
    {
        if (empty($id)) {
            return null;
        }
        if ($row = $this->db->fetchRow($this->filterId($this->select(), $id))) {
            assert($row instanceof stdClass);
            return $this->prepareResultRow($row);
        }

        return null;
    }

    protected function applySearchOrder(Select $query, string $column, string $search): Select
    {
        return $query->order(
            $this->db->quoteInto("CASE WHEN $column = ? THEN 1", $search)
            . $this->db->quoteInto(" WHEN $column LIKE ? THEN 2", "$search %")
            . $this->db->quoteInto(" WHEN $column LIKE ? THEN 3 ELSE 4 END", "$search%")
        );
    }

    /**
     * @return array{0: bool, 1: stdClass[]}
     */
    protected function fetchLimited(Select $query): array
    {
        $limit = 25;
        $offset = ($this->page - 1) * $limit;
        $query->limit($limit + 1, $offset);
        $rows = $this->db->fetchAll($query);
        $more = count($rows) > $limit;
        if ($more) {
            unset($rows[$limit]);
        }

        return [$more, $rows];
    }
}
