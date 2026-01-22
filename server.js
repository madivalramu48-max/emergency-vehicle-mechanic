const express = require('express');
const http = require('http');
const socketIO = require('socket.io');
const cors = require('cors');

const app = express();
app.use(cors());

const server = http.createServer(app);
const io = socketIO(server, {
  cors: {
    origin: "*",
    methods: ["GET", "POST"]
  }
});

const PORT = 3000;

const activeUsers = new Map();
const activeMechanics = new Map();

function calculateDistance(lat1, lon1, lat2, lon2) {
  const R = 6371;
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLon = (lon2 - lon1) * Math.PI / 180;
  const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
            Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
            Math.sin(dLon/2) * Math.sin(dLon/2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  return R * c;
}

io.on('connection', (socket) => {
  console.log('New connection:', socket.id);

  socket.on('user:location', (data) => {
    activeUsers.set(socket.id, {
      id: socket.id,
      lat: data.lat,
      lon: data.lon,
      timestamp: Date.now()
    });

    activeMechanics.forEach((mechanic, mechanicSocketId) => {
      const distance = calculateDistance(data.lat, data.lon, mechanic.lat, mechanic.lon);
      if (distance <= 50) {
        io.to(mechanicSocketId).emit('user:nearby', {
          userId: socket.id,
          lat: data.lat,
          lon: data.lon,
          distance: distance.toFixed(2)
        });
      }
    });
  });

  socket.on('mechanic:location', (data) => {
    activeMechanics.set(socket.id, {
      id: socket.id,
      lat: data.lat,
      lon: data.lon,
      timestamp: Date.now()
    });
  });

  socket.on('request:create', (data) => {
    const requestData = {
      requestId: `req_${Date.now()}_${socket.id.substr(0, 5)}`,
      userId: socket.id,
      lat: data.lat,
      lon: data.lon,
      phone: data.phone,
      description: data.description,
      timestamp: Date.now()
    };

    const user = activeUsers.get(socket.id);
    if (user) {
      user.request = requestData;
      activeUsers.set(socket.id, user);
    }

    activeMechanics.forEach((mechanic, mechanicSocketId) => {
      const distance = calculateDistance(data.lat, data.lon, mechanic.lat, mechanic.lon);
      io.to(mechanicSocketId).emit('request:new', {
        ...requestData,
        distance: distance.toFixed(2)
      });
    });

    socket.emit('request:created', { success: true, requestId: requestData.requestId });
  });

  socket.on('request:accept', (data) => {
    const { requestId, userId } = data;
    const user = activeUsers.get(userId);
    
    if (user && user.request && user.request.requestId === requestId) {
      user.request.status = 'assigned';
      user.request.mechanicId = socket.id;
      activeUsers.set(userId, user);

      io.to(userId).emit('request:assigned', {
        requestId: requestId,
        mechanicId: socket.id,
        message: 'A mechanic is on the way!'
      });

      socket.emit('request:accepted', {
        success: true,
        requestId: requestId,
        userLocation: { lat: user.lat, lon: user.lon }
      });

      activeMechanics.forEach((mechanic, mechanicSocketId) => {
        if (mechanicSocketId !== socket.id) {
          io.to(mechanicSocketId).emit('request:taken', { requestId });
        }
      });
    }
  });

  socket.on('disconnect', () => {
    activeUsers.delete(socket.id);
    activeMechanics.delete(socket.id);
  });
});

server.listen(PORT, () => {
  console.log(`Real-time server running on port ${PORT}`);
});