<?php
/**
 * Script para generar hash de contraseña para INSERT en la base de datos
 * 
 * Uso:
 * 1. Cambia la contraseña en la línea 8
 * 2. Ejecuta: php generar_hash_password.php
 * 3. Copia el hash generado y úsalo en tu INSERT
 */

$password = 'admin123'; // Cambia esta contraseña por la que quieras

$hash = password_hash($password, PASSWORD_DEFAULT);

echo "═══════════════════════════════════════════════════════════\n";
echo "GENERADOR DE HASH DE CONTRASEÑA\n";
echo "═══════════════════════════════════════════════════════════\n\n";
echo "Contraseña: {$password}\n";
echo "Hash generado: {$hash}\n\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "INSERT SQL:\n";
echo "═══════════════════════════════════════════════════════════\n\n";
echo "INSERT INTO users (email, password_hash, name, role, farm_id) \n";
echo "VALUES (\n";
echo "    'admin@rcelbosque.com',\n";
echo "    '{$hash}',\n";
echo "    'Administrador General',\n";
echo "    'admin_general',\n";
echo "    NULL\n";
echo ");\n\n";

