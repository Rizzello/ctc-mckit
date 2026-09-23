<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Enums\SessionizePresenceStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\ConferenceSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['room_id', 'sessionize_id', 'title', 'description', 'starts_at', 'ends_at', 'status', 'is_confirmed', 'is_service_session', 'is_plenum_session', 'categories', 'sessionize_status', 'mc_description', 'mc_script'])]
/**
 * @property CarbonInterface|null $starts_at
 * @property CarbonInterface|null $ends_at
 */
class ConferenceSession extends Model
{
    /** @use HasFactory<ConferenceSessionFactory> */
    use HasFactory;

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** @return BelongsToMany<Speaker, $this> */
    public function speakers(): BelongsToMany
    {
        return $this->belongsToMany(Speaker::class)
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    /** @return BelongsToMany<User, $this> */
    public function mcs(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conference_session_mc')
            ->withTimestamps();
    }

    /** @return HasMany<SessionNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(SessionNote::class);
    }

    /** @param Builder<ConferenceSession> $query */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('sessionize_status', SessionizePresenceStatus::Active->value);
    }

    /** @param Builder<ConferenceSession> $query */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('starts_at')->orderBy('id');
    }

    /** @param Builder<ConferenceSession> $query */
    #[Scope]
    protected function forRoom(Builder $query, int $roomId): void
    {
        $query->where('room_id', $roomId);
    }

    /** @param Builder<ConferenceSession> $query */
    #[Scope]
    protected function roomSchedule(Builder $query, int $roomId): void
    {
        $query->active()->forRoom($roomId)->ordered();
    }

    /** @param Builder<ConferenceSession> $query */
    #[Scope]
    protected function activeSchedule(Builder $query): void
    {
        $query->active()->ordered();
    }

    /** @param Builder<ConferenceSession> $query */
    #[Scope]
    protected function assignedTo(Builder $query, User $user): void
    {
        $query
            ->whereHas('mcs', fn (Builder $mcQuery): Builder => $mcQuery->whereKey($user))
            ->ordered();
    }

    /** @param Builder<ConferenceSession> $query */
    #[Scope]
    protected function removed(Builder $query): void
    {
        $query->where('sessionize_status', SessionizePresenceStatus::Removed->value);
    }

    /** @param Builder<ConferenceSession> $query */
    #[Scope]
    protected function currentAt(Builder $query, CarbonImmutable $instant): void
    {
        $query
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->where('starts_at', '<=', $instant)
            ->where('ends_at', '>', $instant);
    }

    /** @param Builder<ConferenceSession> $query */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $query->where(function (Builder $searchQuery) use ($term): void {
            $searchQuery
                ->where('title', 'like', "%{$term}%")
                ->orWhereHas('speakers', fn (Builder $speakerQuery): Builder => $speakerQuery->where('name', 'like', "%{$term}%"));
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => UtcDateTime::class,
            'ends_at' => UtcDateTime::class,
            'is_confirmed' => 'boolean',
            'is_service_session' => 'boolean',
            'is_plenum_session' => 'boolean',
            'categories' => 'array',
            'sessionize_status' => SessionizePresenceStatus::class,
        ];
    }
}
