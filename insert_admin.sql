-- ============================================
-- INSERT PARA CREAR ADMINISTRADOR GENERAL
-- ============================================
-- Este script crea un usuario administrador general
-- Email: admin@rcelbosque.com
-- Contraseña: admin123
-- ============================================

INSERT INTO users (email, password_hash, name, role, farm_id) 
VALUES (
    'admin@rcelbosque.com',
    '$2y$12$7.8hRIBGBPz9nqZJDo6YuO8s.AtR.TM9ryccjUBPC8N.q0kChBYX2',
    'Administrador General',
    'admin_general',
    NULL
);

-- ============================================
-- VERIFICAR QUE SE CREÓ CORRECTAMENTE
-- ============================================
SELECT id, email, name, role, farm_id, created_at 
FROM users 
WHERE email = 'admin@rcelbosque.com';

-- ============================================
-- OPCIONAL: Si quieres crear otro admin con diferentes credenciales
-- ============================================
-- Cambia los valores según necesites:
-- INSERT INTO users (email, password_hash, name, role, farm_id) 
-- VALUES (
--     'tu_email@ejemplo.com',
--     '$2y$12$TU_HASH_AQUI',  -- Genera el hash con: php -r "echo password_hash('tu_contraseña', PASSWORD_DEFAULT);"
--     'Nombre del Admin',
--     'admin_general',
--     NULL
-- );

