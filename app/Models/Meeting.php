<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Meeting extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'attendees' => 'array',
    ];

    /**
     * @return BelongsTo<Client, Meeting>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, Meeting>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasOne<PreMeetingBrief>
     */
    public function preMeetingBrief(): HasOne
    {
        return $this->hasOne(PreMeetingBrief::class);
    }

    /**
     * @return HasMany<CalendarEventMapping>
     */
    public function calendarEventMappings(): HasMany
    {
        return $this->hasMany(CalendarEventMapping::class);
    }
}
