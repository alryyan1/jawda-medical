<?php

namespace App\Events;

use App\Models\DoctorVisit;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LabPaymentUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The DoctorVisit model instance that contains the updated lab requests.
     * We'll send the entire visit_id and let the frontend refetch to get the latest comprehensive data.
     * Or, we can send a more specific payload. Let's send the specific data needed for the UI update.
     */
    public $visitId;
    public $allRequestsPaid;
    public $isLastResultPending;
    public $isReadyForPrint;

    /**
     * Create a new event instance.
     */
    public function __construct(DoctorVisit $visit)
    {
        $this->visitId = $visit->id;
        
        // --- Calculate the latest status for the patient square ---
        // This logic should be centralized, perhaps in a service or on the DoctorVisit model.
        // For now, we'll replicate the logic from PatientLabQueueItemResource here.

        $labRequestIds = $visit->patientLabRequests()->pluck('id');
        $totalResultsCount = 0;
        $pendingResultsCount = 0;

        if ($labRequestIds->isNotEmpty()) {
            $totalResultsCount = \App\Models\RequestedResult::whereIn('lab_request_id', $labRequestIds)->count();
            $pendingResultsCount = \App\Models\RequestedResult::whereIn('lab_request_id', $labRequestIds)
                ->where(function ($query) {
                    $query->whereNull('result')->orWhere('result', '=', '');
                })->count();
        }

        $allLabRequestsPaid = $visit->patientLabRequests->where('is_paid', false)->isEmpty();
        $allResultsEntered = ($totalResultsCount > 0 && $pendingResultsCount === 0);
        $isPrinted = $visit->is_printed ?? false; // Assuming 'is_printed' exists on DoctorVisit

        $this->allRequestsPaid = $allLabRequestsPaid;
        $this->isLastResultPending = ($totalResultsCount > 0 && $pendingResultsCount === 1);
        $this->isReadyForPrint = ($allResultsEntered && !$isPrinted);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * We use a public channel here. Anyone can listen. For sensitive data,
     * you'd use a PrivateChannel or PresenceChannel.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Broadcast on a public channel named 'lab-updates'.
        // All connected clients listening to this channel will receive the event.
        return [
            new Channel('lab-updates'),
        ];
    }

    /**
     * The name of the event as broadcast.
     * By default, it's the class name. Let's customize it for clarity on the frontend.
     */
    public function broadcastAs(): string
    {
        return 'lab.payment.updated';
    }
}