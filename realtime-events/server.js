import express from 'express';
import http from 'http';
import cors from 'cors';
import { Server as SocketIOServer } from 'socket.io';
import fs from 'fs';
import os from 'os';
import path from 'path';
import axios from 'axios';
import pkg from 'pdf-to-printer';
import net from 'net';
const { print } = pkg;

const PORT = process.env.PORT || 4001;
const ALLOWED_ORIGINS = (process.env.ALLOWED_ORIGINS || 'http://localhost:5173').split(',');
const SERVER_AUTH_TOKEN = process.env.SERVER_AUTH_TOKEN || 'changeme';
const API_BASE = process.env.VITE_API_BASE_URL || 'http://server2/jawda-medical/public/api';
const SANCTUM_TOKEN = process.env.SANCTUM_TOKEN || '';
const HL7_SERVER_HOST = process.env.HL7_SERVER_HOST || '127.0.0.1';
const HL7_SERVER_PORT = process.env.HL7_SERVER_PORT || 6400;
console.log(API_BASE,'API_BASE')
console.log('SANCTUM_TOKEN:', SANCTUM_TOKEN ? 'Set' : 'Not set')
const app = express();
app.use(express.json());
app.use(cors({ origin: (origin, cb) => cb(null, true), credentials: true }));

const server = http.createServer(app);
const io = new SocketIOServer(server, {
  cors: { origin: ALLOWED_ORIGINS, methods: ['GET', 'POST'], credentials: true }
});

io.on('connection', (socket) => {
  // Optional: Namespace/room handling can be added here
  socket.on('join', (room) => {
    if (room) socket.join(room);
  });
  console.log('New connection from:', socket.id);

  // Handle disconnection
  socket.on('disconnect', (reason) => {
    console.log('Client disconnected:', socket.id, 'Reason:', reason);
  });

  // Handle HL7 test messages
  socket.on('hl7-test-message', async (data) => {
    try {
      console.log('Received HL7 test message:', data.message?.substring(0, 100) + '...');
      
      const result = await sendHL7ToServer(data.message);
      
      socket.emit('hl7-test-response', {
        success: result.success,
        message: result.message,
        error: result.error,
        response: result.response
      });
    } catch (error) {
      console.error('Error handling HL7 test message:', error);
      socket.emit('hl7-test-response', {
        success: false,
        error: error.message || 'Unknown error occurred'
      });
    }
  });
});

// Function to send HL7 message to TCP server
function sendHL7ToServer(message) {
  return new Promise((resolve) => {
    const client = new net.Socket();
    let responseData = '';
    let hasResponded = false;

    const timeout = setTimeout(() => {
      if (!hasResponded) {
        hasResponded = true;
        client.destroy();
        resolve({
          success: false,
          error: 'Connection timeout to HL7 server'
        });
      }
    }, 10000); // 10 second timeout

    client.connect(HL7_SERVER_PORT, HL7_SERVER_HOST, () => {
      console.log(`Connected to HL7 server at ${HL7_SERVER_HOST}:${HL7_SERVER_PORT}`);
      
      // Send the HL7 message
      client.write(message);
    });

    client.on('data', (data) => {
      responseData += data.toString();
      console.log('Received response from HL7 server:', data.toString().substring(0, 100) + '...');
    });

    client.on('close', () => {
      clearTimeout(timeout);
      if (!hasResponded) {
        hasResponded = true;
        resolve({
          success: true,
          message: 'Message sent successfully',
          response: responseData || 'No response received'
        });
      }
    });

    client.on('error', (error) => {
      clearTimeout(timeout);
      if (!hasResponded) {
        hasResponded = true;
        console.error('HL7 server connection error:', error);
        resolve({
          success: false,
          error: `Connection error: ${error.message}`
        });
      }
    });
  });
}

// Simple header token check for internal emits
function verifyAuth(req, res, next) {
  const token = req.header('x-internal-token');
  if (!SERVER_AUTH_TOKEN || token === SERVER_AUTH_TOKEN) return next();
  return res.status(401).json({ message: 'Unauthorized' });
}

// Health check
app.get('/health', (_req, res) => res.json({ status: 'ok' }));

// Emit patient-registered
app.post('/emit/patient-registered', verifyAuth, (req, res) => {
  const payload = req.body; // Expecting { patient, visit }
  console.log('patient-registered', payload);
  io.emit('patient-registered', payload);
  return res.json({ ok: true });
});

// Emit patient-updated
app.post('/emit/patient-updated', verifyAuth, (req, res) => {
  const payload = req.body; // Expecting { patient }
  io.emit('patient-updated', payload);
  return res.json({ ok: true });
});

// Emit lab-payment
app.post('/emit/lab-payment', verifyAuth, (req, res) => {
  const payload = req.body; // Expecting { visit, patient, labRequests }
  console.log('lab-payment', payload);
  io.emit('lab-payment', payload);
  console.log('lab-payment emitted');
  return res.json({ ok: true });
});

// Print lab thermal receipt
app.post('/emit/print-lab-receipt', verifyAuth, async (req, res) => {
  try {
    const payload = req.body; // Expecting { visit_id, patient_id, lab_request_ids }
    const { visit_id, patient_id, lab_request_ids } = payload;
    
    console.log('print-lab-receipt', payload);
    
    if (!visit_id) {
      return res.status(400).json({ error: 'visit_id is required' });
    }

    // Generate PDF URL
    const pdfUrl = `${API_BASE}/lab-requests/visit/${visit_id}/thermal-receipt-pdf`;
    console.log(`[Print] Generating PDF for visit ${visit_id}: ${pdfUrl}`);

    // Download PDF
    const tmpFile = path.join(os.tmpdir(), `lab-receipt-${visit_id}-${Date.now()}.pdf`);
    const response = await axios.get(pdfUrl, {
      responseType: 'arraybuffer',
      headers: SANCTUM_TOKEN ? { Authorization: `Bearer ${SANCTUM_TOKEN}` } : {},
    });
    
    fs.writeFileSync(tmpFile, response.data);
    console.log(`[Print] PDF saved to: ${tmpFile}`);

    // Print the PDF using pdf-to-printer library
    try {
      console.log(`[Print] Printing PDF: ${tmpFile}`);
      
      // Print to default printer
      await print(tmpFile, { 
        printer: undefined, // Use default printer
        unix: ['-o fit-to-page'] // Fit to page for thermal printers
      });
      
      console.log(`[Print] Successfully printed lab receipt for visit ${visit_id}`);
      
      // Clean up temporary file
      setTimeout(() => {
        try {
          fs.unlinkSync(tmpFile);
          console.log(`[Print] Cleaned up temporary file: ${tmpFile}`);
        } catch (err) {
          console.error(`[Print] Error cleaning up file: ${err.message}`);
        }
      }, 5000); // Clean up after 5 seconds
      
      return res.json({ 
        ok: true, 
        message: `Lab receipt printed successfully for visit ${visit_id}`,
        temp_file: tmpFile
      });
      
    } catch (printError) {
      console.error(`[Print] Error printing PDF: ${printError.message}`);
      return res.status(500).json({ 
        error: 'Failed to print PDF', 
        details: printError.message 
      });
    }

  } catch (err) {
    console.error('[Print] Error handling print request:', err?.message || err);
    return res.status(500).json({ 
      error: 'Failed to process print request', 
      details: err?.message || err 
    });
  }
});

// Print services thermal receipt
app.post('/emit/print-services-receipt', verifyAuth, async (req, res) => {
  console.log('print-services-receipt')
  try {
    const payload = req.body; // Expecting { visit_id, patient_id }
    const { visit_id, patient_id } = payload;
    
    console.log('print-services-receipt', payload);
    console.log('visit_id type:', typeof visit_id, 'value:', visit_id);
    
    if (!visit_id) {
      return res.status(400).json({ error: 'visit_id is required' });
    }
    
    // Ensure visit_id is a number
    const numericVisitId = parseInt(visit_id);
    if (isNaN(numericVisitId)) {
      return res.status(400).json({ error: 'visit_id must be a valid number' });
    }

    // Skip visit check since the PDF endpoint will handle validation

    // Generate PDF URL for services thermal receipt
    const pdfUrl = `${API_BASE}/visits/${numericVisitId}/thermal-receipt/pdf`;
    console.log(`[Print] Generating services PDF for visit ${numericVisitId}: ${pdfUrl}`);

    // Download PDF
    const tmpFile = path.join(os.tmpdir(), `services-receipt-${visit_id}-${Date.now()}.pdf`);
    console.log(`[Print] Making request to: ${pdfUrl}`);
    console.log(`[Print] Using SANCTUM_TOKEN: ${SANCTUM_TOKEN ? 'Yes' : 'No'}`);
    
    const response = await axios.get(pdfUrl, {
      responseType: 'arraybuffer',
      headers: SANCTUM_TOKEN ? { Authorization: `Bearer ${SANCTUM_TOKEN}` } : {},
      timeout: 30000, // 30 second timeout
      validateStatus: function (status) {
        // Accept any status code as valid to handle 404s properly
        return status >= 200 && status < 600;
      }
    });
    
    // Check if the response is an error
    if (response.status >= 400) {
      console.error(`[Print] API returned error status: ${response.status}`);
      console.error(`[Print] Response data:`, response.data.toString());
      throw new Error(`API returned ${response.status}: ${response.data.toString()}`);
    }
    
    fs.writeFileSync(tmpFile, response.data);
    console.log(`[Print] Services PDF saved to: ${tmpFile}`);

    // Print the PDF using pdf-to-printer library
    try {
      console.log(`[Print] Printing services PDF: ${tmpFile}`);
      
      // Print to default printer
      await print(tmpFile, { 
        printer: undefined, // Use default printer
        unix: ['-o fit-to-page'] // Fit to page for thermal printers
      });
      
      console.log(`[Print] Successfully printed services receipt for visit ${visit_id}`);
      
      // Clean up temporary file
      setTimeout(() => {
        try {
          fs.unlinkSync(tmpFile);
          console.log(`[Print] Cleaned up temporary file: ${tmpFile}`);
        } catch (err) {
          console.error(`[Print] Error cleaning up file: ${err.message}`);
        }
      }, 5000); // Clean up after 5 seconds
      
      return res.json({ 
        ok: true, 
        message: `Services receipt printed successfully for visit ${visit_id}`,
        temp_file: tmpFile
      });
      
    } catch (printError) {
      console.error(`[Print] Error printing services PDF: ${printError.message}`);
      return res.status(500).json({ 
        error: 'Failed to print services PDF', 
        details: printError.message 
      });
    }

  } catch (err) {
    console.error('[Print] Error handling services print request:', err?.message || err);
    
    // Log more details about the error
    if (err.response) {
      console.error('[Print] API Response Error:', {
        status: err.response.status,
        statusText: err.response.statusText,
        data: err.response.data
      });
    } else if (err.request) {
      console.error('[Print] Request Error:', err.request);
    } else {
      console.error('[Print] General Error:', err.message);
    }
    
    return res.status(500).json({ 
      error: 'Failed to process services print request', 
      details: err?.message || err,
      apiError: err.response?.data || null,
      statusCode: err.response?.status || null
    });
  }
});

// Emit close-general-shift
app.post('/emit/close-general-shift', verifyAuth, (req, res) => {
  try {
    const payload = req.body || {};
    console.log('close-general-shift', payload);
    io.emit('close-general-shift', payload);
    return res.json({ ok: true });
  } catch (err) {
    console.error('Error emitting close-general-shift:', err?.message || err);
    return res.status(500).json({ error: 'Failed to emit close-general-shift' });
  }
});

// Emit open-general-shift
app.post('/emit/open-general-shift', verifyAuth, (req, res) => {
  try {
    const payload = req.body || {};
    console.log('open-general-shift', payload);
    io.emit('open-general-shift', payload);
    return res.json({ ok: true });
  } catch (err) {
    console.error('Error emitting open-general-shift:', err?.message || err);
    return res.status(500).json({ error: 'Failed to emit open-general-shift' });
  }
});

// Emit sysmex-result-inserted
app.post('/emit/sysmex-result-inserted', verifyAuth, (req, res) => {
  try {
    const payload = req.body; // Expecting { sysmexResult, doctorVisit, patient }
    console.log('sysmex-result-inserted', payload);
    io.emit('sysmex-result-inserted', payload);
    console.log('sysmex-result-inserted emitted');
    return res.json({ ok: true });
  } catch (err) {
    console.error('Error emitting sysmex-result-inserted:', err?.message || err);
    return res.status(500).json({ error: 'Failed to emit sysmex-result-inserted' });
  }
});

// Emit bankak-image-inserted
app.post('/emit/bankak-image-inserted', verifyAuth, (req, res) => {
  try {
    const payload = req.body; // Expecting bankak image data
    console.log('bankak-image-inserted', payload);
    io.emit('bankak-image-inserted', payload);
    console.log('bankak-image-inserted emitted');
    return res.json({ ok: true });
  } catch (err) {
    console.error('Error emitting bankak-image-inserted:', err?.message || err);
    return res.status(500).json({ error: 'Failed to emit bankak-image-inserted' });
  }
});

server.listen(PORT, () => {
  console.log(`Realtime server listening on :${PORT}`);
});


