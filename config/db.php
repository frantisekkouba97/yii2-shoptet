<?php

$host = getenv('DB_HOST') ?: 'database';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'app';
$user = getenv('DB_USER') ?: 'app';
$pass = getenv('DB_PASS') ?: 'password';

return [
    'class' => 'yii\db\Connection',
    'dsn' => "mysql:host={$host};port={$port};dbname={$name}",
    'username' => $user,
    'password' => $pass,
    'charset' => 'utf8',

    // Schema cache options (for production environment)
    //'enableSchemaCache' => true,
    //'schemaCacheDuration' => 60,
    //'schemaCache' => 'cache',
];
