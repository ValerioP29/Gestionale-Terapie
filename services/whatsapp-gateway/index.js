import 'dotenv/config';
import express from 'express';
import fs from 'fs';
import path from 'path';
import pino from 'pino';
import {
  default as makeWASocket,
  useMultiFileAuthState,
  DisconnectReason,
  fetchLatestBaileysVersion,
} from '@whiskeysockets/baileys';

const app = express();
app.use(express.json({ limit: '1mb' }));

const log = pino({ level: 'info' });

const PORT = process.env.PORT || 3001;
const INTERNAL_TOKEN = process.env.INTERNAL_TOKEN || '';
const SESSIONS_DIR = process.env.SESSIONS_DIR || './sessions';

if (!fs.existsSync(SESSIONS_DIR)) fs.mkdirSync(SESSIONS_DIR, { recursive: true });

function authMiddleware(req, res, next) {
  const token = req.header('X-Internal-Token') || '';
  if (!INTERNAL_TOKEN || token !== INTERNAL_TOKEN) {
    return res.status(401).json({ ok: false, error: 'unauthorized' });
  }
  next();
}

app.use(authMiddleware);

// sessions map
const sessions = new Map(); // pharmaId -> { sock, qr, status }

async function ensureSession(pharmaId) {
  const key = String(pharmaId);

  if (sessions.has(key) && sessions.get(key)?.sock) {
    return sessions.get(key);
  }

  const sessionPath = path.join(SESSIONS_DIR, key);
  if (!fs.existsSync(sessionPath)) fs.mkdirSync(sessionPath, { recursive: true });

  const { state, saveCreds } = await useMultiFileAuthState(sessionPath);
  const { version } = await fetchLatestBaileysVersion();

  const sock = makeWASocket({
    version,
    auth: state,
    logger: log,
    printQRInTerminal: false,
    generateHighQualityLinkPreview: false,
  });

  const entry = { sock, qr: null, status: 'connecting' };
  sessions.set(key, entry);

  sock.ev.on('creds.update', saveCreds);

  sock.ev.on('connection.update', (update) => {
    const { connection, lastDisconnect, qr } = update;

    if (qr) {
      entry.qr = qr;
      entry.status = 'qr';
    }

    if (connection === 'open') {
      entry.status = 'connected';
      entry.qr = null;
    }

    if (connection === 'close') {
      const reason = lastDisconnect?.error?.output?.statusCode;
      entry.status = 'disconnected';

      // se logout -> pulisci sessione
      if (reason === DisconnectReason.loggedOut) {
        try {
          fs.rmSync(sessionPath, { recursive: true, force: true });
        } catch {}
        sessions.delete(key);
      }
    }
  });

  return entry;
}

function normalizePhoneToJid(to) {
  // supporta +39..., 39..., 3...
  const digits = String(to).replace(/[^\d]/g, '');
  // whatsapp jid: "XXXXXXXXXXX@s.whatsapp.net"
  return `${digits}@s.whatsapp.net`;
}

// GET status
app.get('/sessions/:pharmaId/status', async (req, res) => {
  const pharmaId = req.params.pharmaId;
  const entry = await ensureSession(pharmaId);
  res.json({ ok: true, status: entry.status });
});

// GET qr
app.get('/sessions/:pharmaId/qr', async (req, res) => {
  const pharmaId = req.params.pharmaId;
  const entry = await ensureSession(pharmaId);

  // Se non è in stato QR, restituisci info
  res.json({
    ok: true,
    status: entry.status,
    qr: entry.qr, // stringa QR, in UI poi la trasformi in immagine
  });
});

// POST send
app.post('/sessions/:pharmaId/send', async (req, res) => {
  const pharmaId = req.params.pharmaId;
  const { to, message } = req.body || {};
  if (!to || !message) return res.status(422).json({ ok: false, error: 'to_and_message_required' });

  const entry = await ensureSession(pharmaId);
  if (entry.status !== 'connected') {
    return res.status(409).json({ ok: false, error: 'not_connected', status: entry.status });
  }

  const jid = normalizePhoneToJid(to);

  try {
    const result = await entry.sock.sendMessage(jid, { text: String(message) });
    res.json({ ok: true, provider_message_id: result?.key?.id || null });
  } catch (e) {
    log.error(e);
    res.status(500).json({ ok: false, error: 'send_failed' });
  }
});

app.listen(PORT, () => log.info(`Baileys gateway running on :${PORT}`));
