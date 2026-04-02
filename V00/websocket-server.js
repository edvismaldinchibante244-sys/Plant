const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const mysql = require('mysql2');
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

const db = mysql.createConnection({
  host: 'localhost',
  user: 'root',
  password: '',
  database: 'restaurante_saas' // Adjust to your DB
});

db.connect(err => {
  if (err) throw err;
  console.log('MySQL Connected');
});

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
  const numeroPedido = pedido && (pedido.numero_pedido || pedido.numero || pedido.id) ? (pedido.numero_pedido || pedido.numero || pedido.id) : 'N/A';
  const mesa = pedido && (pedido.mesa || pedido.mesa_numero || pedido.mesa_id) ? (pedido.mesa || pedido.mesa_numero || pedido.mesa_id) : '-';
  console.log(`[${new Date().toISOString()}] Novo pedido recebido: ${numeroPedido} | Mesa: ${mesa} | Restaurante: ${restaurante_id || 'N/A'}`);
  
  // Emit to all connected clients
  io.emit('novo_pedido', {
    pedido,
    timestamp: new Date().toISOString()
  });
  
  // Or per room
  if (restaurante_id) {
    io.to(`restaurante_${restaurante_id}`).emit('novo_pedido', pedido);
  }
  
  res.json({ success: true });
});

const PORT = process.env.PORT || 3001;
server.listen(PORT, () => {
  console.log(`WebSocket server on port ${PORT}`);
  console.log('Run: npm start');
});
