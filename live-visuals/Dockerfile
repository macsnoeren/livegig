FROM node:20-alpine

WORKDIR /app

# Install dependencies first (layer caching)
COPY server/package*.json ./
RUN npm install --production

# Copy server code
COPY server/ ./

# Copy frontend files
COPY public/ ./public/

# Create data directory for SQLite
RUN mkdir -p /app/data

EXPOSE 3000

CMD ["node", "server.js"]
