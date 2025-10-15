<?php
// Konfigurasi aplikasi
return [
  'app' => [
    'name' => 'SmartHome Admin',
    'base_url' => '/',
  ],

  'db' => [
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'smarthome',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
  ],

  'mqtt' => [
    'host' => 'iotsmarthome.cloud.shiftr.io',
    'port' => 1883,
    'username' => 'iotsmarthome',
    'password' => 'gxBVaUn5Bvf9yfIm',
    'topic_root' => 'smarthome/12345678',
  ],

  // session
  'session' => [
    'name' => 'smarthome_session',
    'timeout' => 1800, // seconds
  ],
];
