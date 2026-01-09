<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Notification extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'data',
        'status',
        'sent_at',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeForDriver($query, User $driver)
    {
        return $query->where('user_id', $driver->id);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Helpers
     */
    public function markAsRead(): self
    {
        $this->update(['read_at' => now()]);
        return $this;
    }

    public function markAsUnread(): self
    {
        $this->update(['read_at' => null]);
        return $this;
    }

    public function markAsSent(): self
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        return $this;
    }

    public function markAsFailed(): self
    {
        $this->update(['status' => 'failed']);
        return $this;
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
