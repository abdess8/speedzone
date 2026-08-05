<?php

namespace App\Support;

use App\Models\User;

/**
 * Scores how much of a seller profile is filled in.
 *
 * The weights say how much each piece of information is worth to the platform
 * rather than treating every field alike: an unpaid seller is a support ticket,
 * so the banking and identity documents carry most of the score, while the
 * cosmetic fields are worth a few points each.
 */
final class ProfileCompletion
{
    /**
     * Attribute => points it is worth. The values add up to 100.
     *
     * @var array<string, int>
     */
    private const WEIGHTS = [
        'phone_number' => 10,
        'city_id' => 5,
        'address' => 5,
        'pickup_address_1' => 10,
        'pickup_address_2' => 5,
        'cin' => 10,
        'ice_number' => 5,
        'bank_name' => 5,
        'rib' => 10,
        'rib_attachment' => 10,
        'cin_front_attachment' => 10,
        'cin_back_attachment' => 10,
        'photo' => 5,
    ];

    /**
     * @return array{
     *     score: int,
     *     filled_count: int,
     *     field_count: int,
     *     is_complete: bool,
     *     level: string,
     *     missing: array<int, array{key: string, label: string, weight: int}>
     * }
     */
    public static function forUser(User $user): array
    {
        $score = 0;
        $filled = 0;
        $missing = [];

        foreach (self::WEIGHTS as $field => $weight) {
            if (self::isFilled($user, $field)) {
                $score += $weight;
                $filled++;

                continue;
            }

            $missing[] = [
                'key' => $field,
                'label' => __('profile.completion.fields.'.$field),
                'weight' => $weight,
            ];
        }

        return [
            'score' => $score,
            'filled_count' => $filled,
            'field_count' => count(self::WEIGHTS),
            'is_complete' => $score >= 100,
            'level' => self::level($score),
            'missing' => $missing,
        ];
    }

    /**
     * The profile picture lives under two columns depending on who uploaded it
     * (Jetstream writes one, the admin form writes the other), so it counts as
     * present when either holds a path.
     */
    private static function isFilled(User $user, string $field): bool
    {
        if ($field === 'photo') {
            return filled($user->profile_photo_path) || filled($user->photo);
        }

        return filled($user->{$field});
    }

    /**
     * Bootstrap contextual colour driving every progress bar and badge.
     */
    private static function level(int $score): string
    {
        return match (true) {
            $score >= 100 => 'success',
            $score >= 70 => 'info',
            $score >= 40 => 'warning',
            default => 'danger',
        };
    }
}
