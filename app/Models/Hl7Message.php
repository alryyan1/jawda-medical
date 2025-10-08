<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * HL7Message Model
 * 
 * Represents an HL7 message received from laboratory devices
 *
 * @property int $id
 * @property string $raw_message
 * @property string|null $device_type
 * @property string|null $message_type
 * @property string|null $sending_facility
 * @property string|null $sending_application
 * @property string|null $receiving_facility
 * @property string|null $receiving_application
 * @property string|null $message_control_id
 * @property Carbon|null $message_datetime
 * @property array|null $parsed_data
 * @property bool $processed
 * @property string|null $processing_notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class HL7Message extends Model
{
    use HasFactory;

    protected $table = 'hl7_messages';

    protected $fillable = [
        'raw_message',
        'device',
        'message_type',
        'patient_id',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    /**
     * Scope to get unprocessed messages
     */
    public function scopeUnprocessed($query)
    {
        return $query->whereNull('processed_at');
    }

    /**
     * Scope to get processed messages
     */
    public function scopeProcessed($query)
    {
        return $query->whereNotNull('processed_at');
    }

    /**
     * Scope to filter by device type
     */
    public function scopeByDevice($query, string $deviceType)
    {
        return $query->where('device', $deviceType);
    }

    /**
     * Scope to filter by message type
     */
    public function scopeByMessageType($query, string $messageType)
    {
        return $query->where('message_type', $messageType);
    }

    /**
     * Mark message as processed
     */
    public function markAsProcessed(string $notes = null): void
    {
        $this->update([
            'processed_at' => now(),
        ]);
    }
}
