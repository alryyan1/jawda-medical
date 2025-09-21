import express from 'express';
import http from 'http';
import cors from 'cors';
import { Server as SocketIOServer } from 'socket.io';

const PORT = process.env.PORT || 4001;
const ALLOWED_ORIGINS = (process.env.ALLOWED_ORIGINS || 'http://localhost:5173').split(',');
const SERVER_AUTH_TOKEN = process.env.SERVER_AUTH_TOKEN || 'changeme';

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

server.listen(PORT, () => {
  console.log(`Realtime server listening on :${PORT}`);
});


