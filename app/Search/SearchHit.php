<?php

namespace App\Search;

use Illuminate\Contracts\Support\Arrayable;

/**
 * One row of the global search dropdown, together with everything its preview
 * panel shows.
 *
 * The preview travels with the row instead of being fetched when the cursor
 * lands on it: the panel has to be there the instant the row is hovered, and a
 * request per row would both lag behind the cursor and hammer the server while
 * the user runs down the list.
 */
final readonly class SearchHit implements Arrayable
{
    /**
     * @param  array<string, string|null>  $preview  Label => value, in display
     *                                               order. Empty values are
     *                                               dropped rather than shown
     *                                               as a blank line.
     */
    public function __construct(
        public int|string $id,
        public string $title,
        public ?string $subtitle,
        public string $url,
        public array $preview = [],
        public ?string $badge = null,
        public ?string $badgeColor = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $preview = [];

        foreach ($this->preview as $label => $value) {
            $value = is_string($value) ? trim($value) : $value;

            if ($value === null || $value === '') {
                continue;
            }

            $preview[] = ['label' => $label, 'value' => (string) $value];
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'url' => $this->url,
            'badge' => $this->badge,
            'badge_color' => $this->badgeColor,
            'preview' => $preview,
        ];
    }
}
