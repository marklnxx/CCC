<?php
session_start();

// Si ya está logueado, redirigir a tools.php
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: tools.php");
    exit();
}

// Procesar el login
$error_message = '';
$logout_message = '';

// Mostrar mensaje de logout si viene de cerrar sesión
if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    $logout_message = 'Sesión cerrada correctamente';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Verificar credenciales
    if ($username === 'administrador' && $password === '123') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['login_time'] = time();
        header("Location: tools.php");
        exit();
    } else {
        $error_message = 'Usuario o contraseña incorrectos';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso de Administrador - Sistema de Gestión de Cubiertas</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles-dark.css">
    <link rel="stylesheet" href="header-fix.css">
    <link rel="stylesheet" href="nuevo-header.css">
    
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(135deg, #1a252f 0%, #2c3e50 50%, #34495e 100%);
        }
        
        .login-box {
            background: rgba(44, 62, 80, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 50px 40px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(52, 152, 219, 0.2);
            width: 100%;
            max-width: 450px;
            position: relative;
            overflow: hidden;
        }
        
        .login-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3498db, #2ecc71, #f39c12, #e74c3c);
            animation: gradientShift 3s ease-in-out infinite;
        }
        
        @keyframes gradientShift {
            0%, 100% { opacity: 0.8; }
            50% { opacity: 1; }
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo-section img {
            max-width: 380px;
            height: auto;
            margin-bottom: 20px;
            border-radius: 10px;           
        }
        
        .login-title {
            color: #3498db;
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .login-subtitle {
            color: #bdc3c7;
            font-size: 0.95rem;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            color: #ecf0f1;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        
        .form-group label i {
            margin-right: 8px;
            color: #3498db;
            width: 16px;
            text-align: center;
        }
        
        .form-input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid rgba(52, 152, 219, 0.3);
            border-radius: 10px;
            background: rgba(236, 240, 241, 0.05);
            color: #ecf0f1;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #3498db;
            background: rgba(236, 240, 241, 0.1);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            transform: translateY(-2px);
        }
        
        .form-input::placeholder {
            color: #7f8c8d;
        }
        
        .login-button {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .login-button::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transition: all 0.6s ease;
            transform: translate(-50%, -50%);
        }
        
        .login-button:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .login-button:hover {
            background: linear-gradient(135deg, #2980b9, #1f5f8b);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(52, 152, 219, 0.3);
        }
        
        .login-button:active {
            transform: translateY(0);
        }
        
        .error-message {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 500;
            animation: shake 0.5s ease-in-out;
            border: 1px solid rgba(231, 76, 60, 0.3);
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .logout-message {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 500;
            animation: fadeInDown 0.5s ease-in-out;
            border: 1px solid rgba(46, 204, 113, 0.3);
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .back-link {
            text-align: center;
            margin-top: 30px;
        }
        
        .back-link a {
            color: #95a5a6;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-link a:hover {
            color: #3498db;
        }
        
        .security-info {
            background: rgba(52, 152, 219, 0.1);
            border: 1px solid rgba(52, 152, 219, 0.2);
            border-radius: 10px;
            padding: 15px;
            margin-top: 25px;
            text-align: center;
        }
        
        .security-info i {
            color: #3498db;
            margin-right: 8px;
        }
        
        .security-info p {
            color: #bdc3c7;
            font-size: 0.85rem;
            margin: 0;
        }
        
        /* Animaciones de entrada */
        .fade-in {
            opacity: 0;
            animation: fadeInUp 0.6s ease forwards;
        }
        
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .login-box {
                padding: 30px 25px;
                margin: 10px;
            }
            
            .login-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box fade-in">
            <div class="logo-section fade-in delay-1">
                <img src="LOGO.PNG" alt="Logo de la empresa">
                <h1 class="login-title">Panel de Administración</h1>
                <p class="login-subtitle">Acceso restringido para administradores</p>
            </div>
            
            <?php if ($logout_message): ?>
                <div class="logout-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($logout_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="fade-in delay-2">
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i>
                        Usuario
                    </label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-input" 
                        value="administrador"
                        readonly
                        required
                    >
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i>
                        Contraseña
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-input" 
                        placeholder="Ingrese su contraseña"
                        required
                        autocomplete="current-password"
                    >
                </div>
                
                <button type="submit" class="login-button">
                    <i class="fas fa-sign-in-alt"></i>
                    Iniciar Sesión
                </button>
            </form>
            
            <div class="security-info fade-in delay-3">
                <p>
                    <i class="fas fa-shield-alt"></i>
                    Área de acceso restringido. Solo personal autorizado.
                </p>
            </div>
            
            <div class="back-link fade-in delay-3">
                <a href="index.php">
                    <i class="fas fa-arrow-left"></i>
                    Volver al sistema principal
                </a>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Enfocar automáticamente el campo de contraseña
            const passwordField = document.getElementById('password');
            if (passwordField) {
                passwordField.focus();
            }
            
            // Efecto de ondas en el botón
            const loginButton = document.querySelector('.login-button');
            loginButton.addEventListener('click', function(e) {
                let ripple = document.createElement('span');
                let rect = this.getBoundingClientRect();
                let size = Math.max(rect.width, rect.height);
                let x = e.clientX - rect.left - size / 2;
                let y = e.clientY - rect.top - size / 2;
                
                ripple.style.cssText = `
                    position: absolute;
                    width: ${size}px;
                    height: ${size}px;
                    left: ${x}px;
                    top: ${y}px;
                    background: rgba(255,255,255,0.3);
                    border-radius: 50%;
                    transform: scale(0);
                    animation: ripple 0.6s linear;
                    pointer-events: none;
                `;
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
            
            // Validación del formulario
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const password = document.getElementById('password').value;
                
                if (!password.trim()) {
                    e.preventDefault();
                    alert('Por favor, ingrese su contraseña');
                    document.getElementById('password').focus();
                }
            });
        });
        
        // Agregar keyframes para el efecto ripple
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>