<?php

namespace App\Search\Providers;

use App\Enums\UserStatus;
use App\Models\User;
use App\Search\SearchHit;
use App\Support\RoleLabel;
use Illuminate\Database\Eloquent\Builder;

class UserSearchProvider extends AbstractSearchProvider
{
    public function key(): string
    {
        return 'users';
    }

    public function label(): string
    {
        return __('search.objects.users');
    }

    public function icon(): string
    {
        return 'ri-team-line';
    }

    public function availableTo(User $user): bool
    {
        return $user->hasPermission('users.read');
    }

    public function search(User $user, string $term, int $limit): array
    {
        $like = $this->like($term);

        $users = User::query()
            ->with('role')
            ->where(function (Builder $query) use ($like): void {
                $query->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('cin', 'like', $like)
                    ->orWhereRaw("CONCAT_WS(' ', first_name, last_name) like ?", [$like]);
            })
            ->orderBy('name')
            ->limit($this->overfetch($limit))
            ->get();

        return collect($this->readable($users, $user, $limit))->map(function (User $found): SearchHit {
            $status = $found->status instanceof UserStatus
                ? $found->status
                : UserStatus::tryFrom((string) $found->status);

            return new SearchHit(
                id: $found->id,
                title: $found->full_name !== '' ? $found->full_name : $found->name,
                subtitle: $found->email,
                url: route('users.show', $found),
                preview: [
                    __('search.fields.email') => $found->email,
                    __('search.fields.phone') => $found->phone_number,
                    __('search.fields.cin') => $found->cin,
                    __('search.fields.role') => RoleLabel::of($found->role),
                    __('search.fields.created_at') => $this->date($found->created_at),
                ],
                badge: $status ? trans('user_statuses.'.$status->value) : null,
                badgeColor: match ($status) {
                    UserStatus::Active => 'success',
                    UserStatus::PendingApproval, UserStatus::PendingEmailVerification => 'warning',
                    UserStatus::Rejected => 'danger',
                    UserStatus::Suspended => 'dark',
                    default => 'secondary',
                },
            );
        })->all();
    }
}
