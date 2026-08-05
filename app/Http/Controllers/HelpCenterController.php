<?php

namespace App\Http\Controllers;

use App\Support\WorkflowDocumentation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Help Center: the partnership terms, and an interactive reading of the
 * workflows the application enforces.
 *
 * No permission guard. Both pages document rules the reader is already subject
 * to, and hiding the contract from the people it binds helps nobody.
 */
class HelpCenterController extends Controller
{
    public function partnership(Request $request): Response
    {
        return Inertia::render('help/partnership', [
            'sections' => $this->partnershipSections(),
            'currency' => config('app.currency', 'MAD'),
        ]);
    }

    public function processes(Request $request): Response
    {
        return Inertia::render('help/processes', WorkflowDocumentation::all());
    }

    /**
     * The contract as sections, so the page stays a rendering concern and the
     * wording lives with the rest of the translations.
     *
     * @return array<int, array<string, mixed>>
     */
    private function partnershipSections(): array
    {
        $keys = [
            'scope' => 'ri-shake-hands-line',
            'pricing' => 'ri-price-tag-3-line',
            'payouts' => 'ri-wallet-3-line',
            'returns' => 'ri-arrow-go-back-line',
            'liability' => 'ri-shield-check-line',
            'obligations' => 'ri-file-list-3-line',
        ];

        return collect($keys)
            ->map(fn (string $icon, string $key) => [
                'key' => $key,
                'icon' => $icon,
                'title' => __("help.partnership.{$key}.title"),
                'summary' => __("help.partnership.{$key}.summary"),
                'points' => array_values((array) __("help.partnership.{$key}.points")),
            ])
            ->values()
            ->all();
    }
}
