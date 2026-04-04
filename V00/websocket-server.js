const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const { Client } = require('pg'); // ✅ TROCA MYSQL → POSTGRESQL
const bodyParser = require('body-parser');

const app = express();
const server = http.createServer(app);
const io = socketIo(server, {
  cors: {
    origin: '*',
    methods: ['GET', 'POST']
  }
});

app.use(bodyParser.json());
app.use(express.static('.'));

// ✅ CONEXÃO COM POSTGRESQL (RENDER)
const db = new Client({
  connectionString: process.env.DATABASE_URL,
  ssl: {
    rejectUnauthorized: false
  }
});

// ✅ NÃO QUEBRA O SERVIDOR SE DER ERRO
db.connect()
  .then(() => console.log('PostgreSQL Connected'))
  .catch(err => console.error('Erro ao conectar:', err.message));

// Socket connections
io.on('connection', socket => {
  console.log('Client connected:', socket.id);
  
  socket.on('join-room', room => {
    socket.join(room);
    console.log(`Socket ${socket.id} joined room ${room}`);
  });
  
  socket.on('disconnect', () => {
    console.log('Client disconnected:', socket.id);
  });
});

// HTTP endpoint from PHP
app.post('/notify', (req, res) => {
  const { restaurante_id, pedido } = req.body || {};

  const numeroPedido =
    pedido?.numero_pedido ||
    pedido?.numero ||
    pedido?.id ||
    'N/A';

  const mesa =
    pedido?.mesa ||
    pedido?.mesa_numero ||
    pedido?.mesa_id ||
    '-';

  console.log(
    `[${new Date().toISOString()}] Novo pedido: ${numeroPedido} | Mesa: ${mesa} | Restaurante: ${restaurante_id || 'N/A'}`
  );

  // 🔥 ENVIA PARA TODOS
  io.emit('novo_pedido', {
    pedido,
    timestamp: new Date().toISOString()
  });

  // 🔥 ENVIA POR RESTAURANTE
  if (restaurante_id) {
    io.to(`restaurante_${restaurante_id}`).emit('novo_pedido', pedido);
  }

  res.json({ success: true });
});

const PORT = process.env.PORT || 3001;
server.listen(PORT, () => {
  console.log(`WebSocket server on port ${PORT}`);
});
