import express from 'express';
import http from 'http';
import cors from 'cors';
import { Server as SocketIOServer } from 'socket.io';
import fs from 'fs';
import os from 'os';
import path from 'path';
import axios from 'axios';
import pkg from 'pdf-to-printer';
const { print } = pkg;

const PORT = process.env.PORT || 4001;
const ALLOWED_ORIGINS = (process.env.ALLOWED_ORIGINS || 'http://localhost:5173').split(',');
const SERVER_AUTH_TOKEN = process.env.SERVER_AUTH_TOKEN || 'changeme';
const API_BASE = process.env.VITE_API_BASE_URL || 'http://192.168.100.12/jawda-medical/public/api';
const SANCTUM_TOKEN = process.env.SANCTUM_TOKEN || '';
console.log(API_BASE,'API_BASE')
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
});

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

server.listen(PORT, () => {
  console.log(`Realtime server listening on :${PORT}`);
});


