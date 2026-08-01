<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Terminates a user's live access.
 *
 * Flipping `users.status` alone only takes effect on the member's next request
 * through EnsureAccountIsActive; deleting the session rows makes the current
 * browser tab unusable immediately, which is what a vendor expects when he
 * revokes an employee.
 *
 * Requires the database session driver (the app's default). Under any other
 * driver the sessions live outside our reach and revocation silently degrades
 * to "next request", so the caller is told how many sessions were killed.
 */
class UserSessionService
{
    /**
     * Kill every browser session and API token of the user.
     *
     * @return int Number of sessions destroyed.
     */
    public function revokeAll(User $user): int
    {
        $user->tokens()->delete();

        return $this->purgeSessions($user);
    }

    /**
     * Live session count and last activity per user.
     *
     * Read from the session store rather than a `last_login_at` column so the
     * team screen reports what is actually open right now, which is what the
     * vendor is deciding on when he revokes someone.
     *
     * @param  array<int, int>  $userIds
     * @return array<int, array{sessions: int, last_activity: int|null}>
     */
    public function activityFor(array $userIds): array
    {
        if ($userIds === [] || config('session.driver') !== 'database') {
            return [];
        }

        $table = config('session.table', 'sessions');
        $connection = config('session.connection');

        if (! Schema::connection($connection)->hasTable($table)) {
            return [];
        }

        return DB::connection($connection)
            ->table($table)
            ->selectRaw('user_id, COUNT(*) as sessions, MAX(last_activity) as last_activity')
            ->whereIn('user_id', $userIds)
            ->groupBy('user_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (int) $row->user_id => [
                    'sessions' => (int) $row->sessions,
                    'last_activity' => $row->last_activity ? (int) $row->last_activity : null,
                ],
            ])
            ->all();
    }

    private function purgeSessions(User $user): int
    {
        if (config('session.driver') !== 'database') {
            return 0;
        }

        $table = config('session.table', 'sessions');
        $connection = config('session.connection');

        if (! Schema::connection($connection)->hasTable($table)) {
            return 0;
        }

        return DB::connection($connection)
            ->table($table)
            ->where('user_id', $user->id)
            ->delete();
    }
}
