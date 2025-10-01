<?php

namespace App\Services\HL7\Contracts;

use Aranyasen\HL7\Message;
use Aranyasen\HL7\Segments\MSH;

interface DeviceHandlerInterface
{
    /**
     * Process HL7 message from a specific device
     *
     * @param Message $msg The HL7 message
     * @param MSH $msh The MSH segment
     * @param mixed $connection The connection object
     * @return void
     */
    public function processMessage(Message $msg, MSH $msh, $connection): void;
}
