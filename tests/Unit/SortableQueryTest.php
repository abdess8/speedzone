<?php

use App\Support\SortableQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

function sortableMap(): array
{
    return [
        'created_at' => 'created_at',
        'full_name' => ['first_name', 'last_name'],
    ];
}

function sortState(array $query): array
{
    return SortableQuery::state(Request::create('/', 'GET', $query), sortableMap(), 'created_at');
}

/** Identifier quoting is driver specific and not what these tests are about. */
function unquoted(string $sql): string
{
    return str_replace(['`', '"'], '', $sql);
}

test('an unknown key falls back to the default rather than reaching the SQL', function () {
    expect(sortState(['sort' => 'password']))->toBe(['sort' => 'created_at', 'direction' => 'desc']);
});

test('a non-string sort parameter is not a 500', function () {
    expect(sortState(['sort' => ['created_at']]))->toBe(['sort' => 'created_at', 'direction' => 'desc'])
        ->and(sortState(['direction' => ['asc']]))->toBe(['sort' => 'created_at', 'direction' => 'desc']);
});

test('only asc and desc come out of the direction parameter', function () {
    expect(sortState(['direction' => 'asc'])['direction'])->toBe('asc')
        ->and(sortState(['direction' => 'ASC'])['direction'])->toBe('asc')
        ->and(sortState(['direction' => 'asc, id desc; drop table users'])['direction'])->toBe('desc');
});

test('a multi column key orders on each column in turn, then on the tie breaker', function () {
    $query = DB::table('users');

    SortableQuery::apply(
        $query,
        Request::create('/', 'GET', ['sort' => 'full_name', 'direction' => 'asc']),
        sortableMap(),
        'created_at'
    );

    expect(unquoted($query->toSql()))->toContain('order by first_name asc, last_name asc, id desc');
});

test('the tie breaker is not repeated when it is the sort itself', function () {
    $query = DB::table('users');

    SortableQuery::apply(
        $query,
        Request::create('/', 'GET', ['sort' => 'id']),
        ['id' => 'id'],
        'id'
    );

    expect(unquoted($query->toSql()))->toEndWith('order by id desc');
});
