<?php
/**
 * Conexion a la base de datos MySQL (MariaDB / XAMPP)
 * Dashboard de Gestion Interna
 */

$db_host = 'localhost';
$db_name = 'dbs13710048';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('Error de conexion DB: ' . $e->getMessage());
    die('Error al conectar con la base de datos. Contacte al administrador.');
}
