<?php

namespace App\Http\Controllers;

use App\Search\GlobalSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __construct(private readonly GlobalSearch $search) {}

    /**
     * Answer the search bar.
     *
     * The scope list rides along with every response rather than living in a
     * second endpoint or in the shared Inertia props: it is eleven permission
     * checks, and it has to stay in step with a term the user may narrow at any
     * moment.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'scope' => ['nullable', 'string', 'max:60'],
        ]);

        $user = $request->user();
        $term = trim($validated['q'] ?? '');
        $scope = $validated['scope'] ?? null;

        return response()->json([
            'term' => $term,
            'scope' => $scope,
            'min_length' => GlobalSearch::MIN_TERM_LENGTH,
            'scopes' => $this->search->scopesFor($user),
            'groups' => $this->search->search($user, $term, $scope),
        ]);
    }
}
