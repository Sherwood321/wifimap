<?php
$server   = getenv('DB_SERVER')   ?: 'localhost';
$database = getenv('DB_NAME')     ?: 'wifimap';
$username = getenv('DB_USER')     ?: 'wifimap';
$password = getenv('DB_PASSWORD') ?: '';
