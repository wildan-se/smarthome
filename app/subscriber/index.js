const mqtt = require('mqtt');
const mysql = require('mysql2/promise');
const cfg = require('./config.json');

async function main() {
  const db = await mysql.createPool({
    host: cfg.db.host,
    port: cfg.db.port,
    user: cfg.db.username,
    password: cfg.db.password,
    database: cfg.db.database,
    waitForConnections: true,
    connectionLimit: 10,
  });

  const wsUrl = `wss://${cfg.mqtt.host}:443`;
  const client = mqtt.connect(wsUrl, { username: cfg.mqtt.username, password: cfg.mqtt.password, protocol: 'wss' });

  client.on('connect', () => {
    console.log('MQTT subscriber connected');
    client.subscribe(`${cfg.mqtt.topic_root}/#`, { qos: 1 });
  });

  client.on('message', async (topic, message) => {
    try {
      const payload = message.toString();
      console.log(new Date().toISOString(), topic, payload);

      if (topic.endsWith('/dht/temperature') || topic.endsWith('/dht/humidity')) {
        // read latest temperature & humidity from topics
        if (topic.endsWith('/dht/temperature')) {
          await db.query('INSERT INTO sensor_logs (temperature) VALUES (?)', [parseFloat(payload)]);
        } else {
          await db.query('UPDATE sensor_logs SET humidity = ? WHERE id = (SELECT id FROM (SELECT id FROM sensor_logs ORDER BY ts DESC LIMIT 1) x)', [parseFloat(payload)]);
        }
      } else if (topic.endsWith('/rfid/access')) {
        let status = 'denied';
        try { const j = JSON.parse(payload); status = j.status || status; } catch(e) { status = payload; }
        // extract UID if present? ESP32 sends only status; UID might be in other topics
        await db.query('INSERT INTO rfid_logs (uid, status) VALUES (?, ?)', ['', status]);
      } else if (topic.endsWith('/pintu/status')) {
        await db.query('INSERT INTO door_status (status) VALUES (?)', [payload]);
      }
    } catch (err) {
      console.error('Error handling message', err);
    }
  });

  client.on('error', (err) => console.error('MQTT error', err));
}

main().catch(err => { console.error(err); process.exit(1); });
