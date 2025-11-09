<?php
/**
 * Página para restablecer la contraseña usando un token
 */

require_once __DIR__ . '/../app/config.php';

$error = '';
$success = '';
$token_valid = false;
$token = '';
$user_id = null;

// Verificar si hay un token
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = trim($_GET['token']);
    
    try {
        $pdo = get_pdo();
        
        // Buscar token válido
        $stmt = $pdo->prepare("
            SELECT pr.*, u.email, u.name 
            FROM password_resets pr
            INNER JOIN users u ON pr.user_id = u.id
            WHERE pr.token = ? 
            AND pr.used = 0 
            AND pr.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();
        
        if ($reset) {
            $token_valid = true;
            $user_id = $reset['user_id'];
        } else {
            $error = 'El enlace de recuperación no es válido o ha expirado. Por favor, solicita un nuevo enlace.';
        }
    } catch (Exception $e) {
        error_log("❌ [RESET_PASSWORD] Error al validar token: " . $e->getMessage());
        $error = 'Error al procesar la solicitud. Por favor, intenta nuevamente.';
    }
} else {
    $error = 'No se proporcionó un token de recuperación válido.';
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password']) && $token_valid) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validaciones
    if (empty($new_password)) {
        $error = 'La contraseña no puede estar vacía.';
    } elseif (strlen($new_password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        try {
            $pdo = get_pdo();
            
            // Actualizar contraseña
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$password_hash, $user_id]);
            
            // Marcar token como usado
            $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);
            
            $success = 'Tu contraseña ha sido restablecida exitosamente. Ahora puedes iniciar sesión con tu nueva contraseña.';
            $token_valid = false; // Ya no mostrar el formulario
            
        } catch (Exception $e) {
            error_log("❌ [RESET_PASSWORD] Error al cambiar contraseña: " . $e->getMessage());
            $error = 'Error al cambiar la contraseña. Por favor, intenta nuevamente.';
        }
    }
}

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <title>Restablecer Contraseña - RC El Bosque</title>
    <style>
        :root {
            --primary-green: #2d5a27;
            --accent-green: #3e7b2e;
            --light-green: #4a9a3d;
            --bg-green: #f0f8ec;
            --text-dark: #1a3315;
            --gray-50: #f9fafb;
            --gray-200: #e5e7eb;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
        }
        
        body {
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, var(--bg-green) 0%, #ffffff 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: Inter, system-ui, 'Segoe UI', Roboto, Arial, sans-serif;
        }
        
        /* Navbar unificado */
        .nav {
            display: flex;
            gap: 1.5rem;
            align-items: center;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, var(--primary-green), var(--accent-green));
            box-shadow: 0 4px 20px rgba(45, 90, 39, 0.2);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .nav a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav a:first-child {
            font-size: 1.3rem;
            font-weight: bold;
            margin-right: auto;
        }
        
        .nav a:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }
        
        .reset-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .reset-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .reset-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .reset-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-green), var(--accent-green));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            box-shadow: 0 8px 20px rgba(45, 90, 39, 0.3);
        }
        
        .reset-icon i {
            font-size: 2rem;
            color: white;
        }
        
        .reset-header h1 {
            color: var(--primary-green);
            margin-bottom: 0.5rem;
            font-size: 1.75rem;
        }
        
        .reset-header p {
            color: var(--gray-600);
            font-size: 0.95rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--gray-700);
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-green);
        }
        
        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, var(--primary-green), var(--accent-green));
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(45, 90, 39, 0.3);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .alert.err {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert.ok {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #34d399;
        }
        
        .alert i {
            margin-right: 0.5rem;
        }
        
        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .back-link a {
            color: var(--primary-green);
            text-decoration: none;
            font-size: 0.875rem;
            transition: color 0.3s ease;
        }
        
        .back-link a:hover {
            color: var(--accent-green);
        }
        
        @media (max-width: 768px) {
            .nav {
                flex-wrap: wrap;
                gap: 1rem;
                padding: 1rem;
            }
            
            .nav a:first-child {
                margin-right: 0;
                width: 100%;
                text-align: center;
                justify-content: center;
            }
            
            .reset-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <nav class="nav">
        <a href="index.php" style="display:flex;align-items:center;gap:.7rem;font-size:1.3rem;font-weight:bold;text-decoration:none;color:inherit;">
            <img src="assets/images/logo-rc-el-bosque.png" alt="Logo RC El Bosque" style="height:40px;width:auto;border-radius:50%;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.08);">
            <span>RC El Bosque</span>
        </a>
        <a href="catalogo.php"><i class="fas fa-list"></i> Catálogo</a>
        <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
    </nav>
    
    <div class="reset-container">
        <div class="reset-card">
            <div class="reset-header">
                <div class="reset-icon">
                    <i class="fas fa-key"></i>
                </div>
                <h1>Restablecer Contraseña</h1>
                <?php if ($token_valid && !$success): ?>
                    <p>Ingresa tu nueva contraseña</p>
                <?php elseif ($success): ?>
                    <p>Contraseña restablecida exitosamente</p>
                <?php else: ?>
                    <p>Enlace inválido o expirado</p>
                <?php endif; ?>
            </div>
            
            <?php if ($error): ?>
                <div class="alert err">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert ok">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
                <div class="back-link">
                    <a href="login.php">
                        <i class="fas fa-arrow-left"></i> Volver al inicio de sesión
                    </a>
                </div>
            <?php elseif ($token_valid): ?>
                <form method="POST" id="resetForm">
                    <div class="form-group">
                        <label for="new_password">
                            <i class="fas fa-lock" style="margin-right: 0.5rem; color: var(--primary-green);"></i>
                            Nueva Contraseña
                        </label>
                        <input 
                            type="password" 
                            name="new_password" 
                            id="new_password" 
                            required 
                            minlength="8"
                            autocomplete="new-password"
                            placeholder="Mínimo 8 caracteres"
                        >
                        <small style="color: var(--gray-500); font-size: 0.875rem; margin-top: 0.25rem; display: block;">
                            La contraseña debe tener al menos 8 caracteres
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">
                            <i class="fas fa-lock" style="margin-right: 0.5rem; color: var(--primary-green);"></i>
                            Confirmar Contraseña
                        </label>
                        <input 
                            type="password" 
                            name="confirm_password" 
                            id="confirm_password" 
                            required 
                            minlength="8"
                            autocomplete="new-password"
                            placeholder="Repite la contraseña"
                        >
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i>
                        <span>Restablecer Contraseña</span>
                    </button>
                </form>
                
                <div class="back-link">
                    <a href="login.php">
                        <i class="fas fa-arrow-left"></i> Volver al inicio de sesión
                    </a>
                </div>
            <?php else: ?>
                <div class="back-link">
                    <a href="login.php">
                        <i class="fas fa-arrow-left"></i> Volver al inicio de sesión
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Validar que las contraseñas coincidan
        document.getElementById('resetForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Las contraseñas no coinciden. Por favor, verifica e intenta nuevamente.');
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('La contraseña debe tener al menos 8 caracteres.');
                return false;
            }
        });
    </script>
</body>
</html>

