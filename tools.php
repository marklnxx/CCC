<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$admin_username = $_SESSION['admin_username'] ?? 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Herramientas del Sistema</title>
    <!-- Enlaces a fuentes y bibliotecas externas -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles-dark.css">
    <link rel="stylesheet" href="header-fix.css">
    <link rel="stylesheet" href="tools/nuevo-header.css">
    <link rel="stylesheet" href="modal.css">
    
    <style>
	
	
	.tool-card.adjust-silacor .tool-icon {
    color: #e67e22; /* Color naranja para diferenciarlo */
}
	
	
	
        /* Estilos corregidos para tools.php */
.admin-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.admin-header {
    text-align: center;
    margin-bottom: 40px;
    padding: 30px;
    background: linear-gradient(135deg, #2c3e50, #34495e);
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    position: relative;
}

.admin-title {
    color: #3498db;
    font-size: 2.5rem;
    margin-bottom: 10px;
    font-weight: 700;
}

.admin-subtitle {
    color: #bdc3c7;
    font-size: 1.1rem;
    margin: 0;
}

.user-info {
    position: absolute;
    top: 20px;
    right: 20px;
    background: rgba(52, 152, 219, 0.1);
    border: 1px solid rgba(52, 152, 219, 0.3);
    border-radius: 10px;
    padding: 10px 15px;
    color: #3498db;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Corregir el botón de logout desalineado */
.logout-button {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 8px;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-left: 0; /* Corregido: era -1000px */
    display: flex;
    align-items: center;
    gap: 5px;
}

.logout-button:hover {
    background: linear-gradient(135deg, #c0392b, #a93226);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
}

.tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 25px;
    margin-top: 30px;
}

.tool-card {
    background: linear-gradient(135deg, #2c3e50, #34495e);
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
    border: 1px solid rgba(52, 152, 219, 0.1);
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 400px; /* Altura mínima consistente */
}

.tool-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #3498db, #2ecc71, #f39c12, #e74c3c);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.tool-card:hover::before {
    opacity: 1;
}

.tool-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
    border-color: rgba(52, 152, 219, 0.3);
}

.tool-icon {
    font-size: 3rem;
    margin-bottom: 20px;
    display: block;
    text-align: center;
    transition: all 0.3s ease;
}

/* Colores específicos para cada tipo de herramienta */
.tool-card.rescue .tool-icon {
    color: #2ecc71;
}

.tool-card.reset .tool-icon {
    color: #e74c3c;
}

.tool-card.add-bus .tool-icon {
    color: #9b59b6;
}

.tool-card.delete-bus .tool-icon {
    color: #e74c3c;
}

.tool-card.backup .tool-icon {
    color: #3498db;
}

.tool-card.update-km .tool-icon {
    color: #3498db;
}

.tool-card.daily-km .tool-icon {
    color: #2ecc71;
}

.tool-card.adjust-km .tool-icon {
    color: #f39c12;
}

.tool-card.populate-history .tool-icon {
    color: #9b59b6;
}

.tool-card:hover .tool-icon {
    transform: scale(1.1) rotate(5deg);
}

.tool-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #ecf0f1;
    margin-bottom: 15px;
    text-align: center;
}

.tool-description {
    color: #bdc3c7;
    line-height: 1.6;
    margin-bottom: 25px;
    text-align: center;
    font-size: 0.95rem;
    flex-grow: 1; /* Para que ocupe el espacio disponible */
}

.tool-features {
    list-style: none;
    padding: 0;
    margin: 20px 0;
}

.tool-features li {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
    color: #95a5a6;
    font-size: 0.9rem;
}

.tool-features li i {
    color: #27ae60;
    margin-right: 10px;
    width: 16px;
    text-align: center;
}

/* Botones unificados y corregidos */
.tool-button {
    width: 100%;
    padding: 15px 20px;
    border: none;
    border-radius: 10px;
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    position: relative;
    overflow: hidden;
    margin-top: auto; /* Empuja el botón hacia abajo */
    box-sizing: border-box;
}

.tool-button::before {
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

.tool-button:hover::before {
    width: 300px;
    height: 300px;
}

.tool-button.primary {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
}

.tool-button.primary:hover {
    background: linear-gradient(135deg, #2980b9, #1f5f8b);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(52, 152, 219, 0.3);
}

.tool-button.success {
    background: linear-gradient(135deg, #2ecc71, #27ae60);
    color: white;
}

.tool-button.success:hover {
    background: linear-gradient(135deg, #27ae60, #1e8449);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(46, 204, 113, 0.3);
}

.tool-button.danger {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: white;
}

.tool-button.danger:hover {
    background: linear-gradient(135deg, #c0392b, #a93226);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(231, 76, 60, 0.3);
}

.warning-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: linear-gradient(135deg, #f39c12, #d35400);
    color: white;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
    z-index: 2;
}

.fade-in {
    opacity: 0;
    animation: fadeIn 0.6s ease forwards;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.delay-1 { animation-delay: 0.1s; }
.delay-2 { animation-delay: 0.2s; }
.delay-3 { animation-delay: 0.3s; }
.delay-4 { animation-delay: 0.4s; }
.delay-5 { animation-delay: 0.5s; }
.delay-6 { animation-delay: 0.6s; }
.delay-7 { animation-delay: 0.7s; }
.delay-8 { animation-delay: 0.8s; }
.delay-9 { animation-delay: 0.9s; }
.delay-10 { animation-delay: 1.0s; }
.delay-11 { animation-delay: 1.1s; }
.delay-12 { animation-delay: 1.2s; }

/* Contador de alertas */
.alert-counter {
    position: absolute;
    top: 10px;
    right: 10px;
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: white;
    border-radius: 50%;
    width: 25px;
    height: 25px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 600;
    animation: pulse 2s infinite;
    z-index: 2;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

/* Navegación mejorada */
.nav-buttons {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
    justify-content: flex-start;
    flex-wrap: wrap;
}

.nav-buttons .boton {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    background: linear-gradient(135deg, #e74c3c, #2c3e50);
    color: #ecf0f1;
    border: 1px solid rgba(52, 152, 219, 0.3);
    border-radius: 10px;
    transition: all 0.3s ease;
    text-decoration: none;
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
    font-weight: 500;
}

.nav-buttons .boton:hover {
    background: linear-gradient(135deg, #e74c3c, #e74c3c);
    border-color: #3498db;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
}

/* Responsive design */
@media (max-width: 768px) {
    .tools-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .admin-title {
        font-size: 2rem;
    }
    
    .tool-card {
        padding: 20px;
        min-height: 350px;
    }
    
    .tool-icon {
        font-size: 2.5rem;
    }
    
    .user-info {
        position: relative;
        top: auto;
        right: auto;
        margin-bottom: 20px;
        justify-content: center;
    }
    
    .nav-buttons {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .admin-container {
        padding: 10px;
    }
    
    .tool-card {
        padding: 15px;
        min-height: 300px;
    }
    
    .tool-title {
        font-size: 1.3rem;
    }
    
    .tool-description {
        font-size: 0.9rem;
    }
    
    .tool-button {
        padding: 12px 15px;
        font-size: 0.9rem;
    }
}

/* Efectos adicionales para mejorar la experiencia */
.tool-card {
    transform-origin: center;
}

.tool-card:hover {
    transform: translateY(-10px) scale(1.02);
}

/* Mejora en la legibilidad */
.tool-features li {
    text-align: left;
}

/* Asegurar que todos los botones tengan la misma altura */
.tool-button {
    min-height: 50px;
}

/* Hover effect para mejor feedback visual */
.tool-button:active {
    transform: translateY(0) scale(0.98);
}
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header con logo -->
        <header>
            <div class="logo-container">
                <img src="LOGO.PNG" alt="Logo de la empresa" class="fade-in">
                <h1 class="fade-in delay-1">PANEL DE ADMINISTRACIÓN</h1>
            </div>
        </header>

        <div class="content">
            <!-- Botones de navegación mejorados -->
            <div class="nav-buttons">

<button class="boton slide-in delay-2" onclick="window.location.href='logout.php'">
    <i class="fas fa-sign-out-alt"></i> CERRAR SESIÓN
</button>
            </div>

            <!-- Container principal -->
            <div class="admin-container">
                <!-- Header del panel -->
                <div class="admin-header fade-in delay-2">
                    <div class="user-info">
                        <i class="fas fa-user-circle"></i>
                        <span>Bienvenido, <?php echo htmlspecialchars($admin_username); ?></span>
                    </div>
                    <h2 class="admin-title">
                        <i class="fas fa-tools"></i> Herramientas del Sistema
                    </h2>
                    <p class="admin-subtitle">
                        Panel de administración para mantenimiento y gestión avanzada del sistema
                    </p>
                </div>

 <!-- Grid de herramientas -->
<div class="tools-grid">
    
    <!-- === HERRAMIENTAS DE USO DIARIO === -->
    
    <!-- Registro Diario de Kilometraje - MÁS USADO -->
    <div class="tool-card daily-km fade-in delay-3">
        <div class="frequency-badge daily">USO DIARIO</div>
        <i class="fas fa-calendar-day tool-icon"></i>
        <h3 class="tool-title">Actualizar KM por Día</h3>
        <p class="tool-description">
            Sistema de registro diario masivo de kilometrajes para todos los buses del sistema.
        </p>
        
        <ul class="tool-features">
            <li><i class="fas fa-check"></i> Registro masivo de todos los buses</li>
            <li><i class="fas fa-check"></i> Modalidades individual y grupal</li>
            <li><i class="fas fa-check"></i> Detección automática de alertas</li>
            <li><i class="fas fa-check"></i> Guardado automático de progreso</li>
        </ul>
        
        <a href="tools\registro_kilometraje.php" class="tool-button success">
            <i class="fas fa-list-alt"></i> Registro Diario
        </a>
    </div>

    <!-- Actualizar Kilometraje Individual -->
    <div class="tool-card update-km fade-in delay-4">
        <div class="frequency-badge frequent">USO FRECUENTE</div>
        <i class="fas fa-sync-alt tool-icon"></i>
        <h3 class="tool-title">Actualizar Kilometraje</h3>
        <p class="tool-description">
            Actualizar el kilometraje de las cubiertas activas de un coche específico cuando hay problemas.
        </p>
        
        <ul class="tool-features">
            <li><i class="fas fa-check"></i> Actualización por coche individual</li>
            <li><i class="fas fa-check"></i> Validación automática de kilometrajes</li>
            <li><i class="fas fa-check"></i> Información detallada de cubiertas</li>
            <li><i class="fas fa-check"></i> Actualización de historial automática</li>
        </ul>
        
        <a href="tools\actualizar_kilometraje.php" class="tool-button primary">
            <i class="fas fa-tachometer-alt"></i> Actualizar Kilometraje
        </a>
    </div>

    <!-- Wizard de Rescate -->
    <div class="tool-card rescue fade-in delay-5">
        <div class="frequency-badge frequent">USO FRECUENTE</div>
        <i class="fas fa-magic tool-icon"></i>
        <h3 class="tool-title">Asignar cubiertas perdidas</h3>
        <p class="tool-description">
            Herramienta para recuperar cubiertas perdidas y corregir automáticamente problemas en el sistema.
        </p>
        
        <ul class="tool-features">
            <li><i class="fas fa-check"></i> Detección automática de problemas</li>
            <li><i class="fas fa-check"></i> Recuperación de cubiertas perdidas</li>
            <li><i class="fas fa-check"></i> Reparación de historial</li>
            <li><i class="fas fa-check"></i> Asignación inteligente</li>
        </ul>
        
        <a href="tools\rescate_cubiertas.php" class="tool-button success">
            <i class="fas fa-magic"></i> Ejecutar Wizard
        </a>
    </div>

    <!-- Añadir Bus -->
    <div class="tool-card add-bus fade-in delay-6">
        <div class="frequency-badge occasional">USO OCASIONAL</div>
        <i class="fas fa-bus-alt tool-icon"></i>
        <h3 class="tool-title">Añadir Bus</h3>
        <p class="tool-description">
            Herramienta para agregar nuevos buses al sistema y configurar su información básica.
        </p>
        
        <ul class="tool-features">
            <li><i class="fas fa-check"></i> Registro de nuevos buses</li>
            <li><i class="fas fa-check"></i> Asignación de ID automática</li>
            <li><i class="fas fa-check"></i> Validación de datos</li>
            <li><i class="fas fa-check"></i> Integración inmediata</li>
        </ul>
        
        <a href="tools\añadir_bus.php" class="tool-button primary">
            <i class="fas fa-plus"></i> Añadir Nuevo Bus
        </a>
    </div>

    <!-- Respaldo de Datos -->
    <div class="tool-card backup fade-in delay-7">
        <div class="frequency-badge occasional">USO OCASIONAL</div>
        <i class="fas fa-database tool-icon"></i>
        <h3 class="tool-title">Respaldo de Datos</h3>
        <p class="tool-description">
            Sistema completo de respaldo automático y manual de la base de datos con notificaciones por email.
        </p>
        
        <ul class="tool-features">
            <li><i class="fas fa-check"></i> Respaldo automático programado</li>
            <li><i class="fas fa-check"></i> Respaldo manual instantáneo</li>
            <li><i class="fas fa-check"></i> Envío por correo electrónico</li>
            <li><i class="fas fa-check"></i> Configuración avanzada</li>
        </ul>
        
        <button id="boton-respaldo" class="tool-button primary">
            <i class="fas fa-database"></i> Opciones de Respaldo
        </button>
    </div>

    <!-- === SEPARADOR VISUAL === -->
    <div class="section-separator fade-in delay-8">
        <h3><i class="fas fa-cogs"></i> HERRAMIENTAS DE CONFIGURACIÓN Y AJUSTE</h3>
        <p>Herramientas para configuración inicial y ajustes ocasionales</p>
    </div>

    <!-- === HERRAMIENTAS DE CONFIGURACIÓN INICIAL === -->

    <!-- Inicializar Sistema - CONFIGURACIÓN INICIAL -->
    <div class="tool-card daily-km fade-in delay-9">
        <div class="frequency-badge setup">CONFIGURACIÓN INICIAL</div>
        <i class="fas fa-rocket tool-icon"></i>
        <h3 class="tool-title">Cargar Coches y cubiertas</h3>
        <p class="tool-description">
           Herramienta para cargar en la base de datos los omnibus y cubiertas. Usar al inicializar el sistema.
        </p>
        
        <ul class="tool-features">
            <li><i class="fas fa-check"></i> Carga todos los buses al sistema</li>
            <li><i class="fas fa-check"></i> Modalidades individual y grupal</li>                            
            <li><i class="fas fa-check"></i> Guardado automático de progreso</li>
            <li><i class="fas fa-check"></i> Actualiza los km de cada bus</li>
        </ul>
        
        <a href="wizard.php" class="tool-button primary">
            <i class="fas fa-rocket"></i> Inicializar Sistema
        </a>
    </div>

    <!-- Poblar Historial de Bajas -->
    <div class="tool-card populate-history fade-in delay-10">
        <div class="frequency-badge setup">CONFIGURACIÓN INICIAL</div>
        <i class="fas fa-database tool-icon"></i>
        <h3 class="tool-title">Poblar Historial Bajas</h3>
        <p class="tool-description">
            Usar 1 vez después de "Cargar Coches y cubiertas". Migra el historial de altas-bajas desde registros previos.
        </p>
        
        <ul class="tool-features">
            <li><i class="fas fa-check"></i> Migración masiva de registros históricos</li>
            <li><i class="fas fa-check"></i> Validación automática de datos</li>
            <li><i class="fas fa-check"></i> Preservación de información previa</li>
            <li><i class="fas fa-check"></i> Integración con sistema actual</li>
        </ul>
        
        <a href="poblar_historial_bajas.php" class="tool-button primary">
            <i class="fas fa-upload"></i> Cargar Historial
        </a>
    </div>

    <!-- === HERRAMIENTAS DE AJUSTE === -->

    <!-- Ajustar Kilometraje de Cubiertas -->
    <div class="tool-card adjust-km fade-in delay-11">
        <div class="frequency-badge adjustment">AJUSTE</div>
        <i class="fas fa-tachometer-alt tool-icon"></i>
        <h3 class="tool-title">Ajustar Kilometraje</h3>
        <p class="tool-description">
            Herramienta para ajustar kilómetros de cubiertas existentes y adaptar el sistema a procesos en marcha.
        </p>

        <ul class="tool-features">
            <li><i class="fas fa-check"></i> Ajuste de cubiertas asignadas y disponibles</li>
            <li><i class="fas fa-check"></i> Registro de auditoría completo</li>
            <li><i class="fas fa-check"></i> Validación automática de datos</li>
            <li><i class="fas fa-check"></i> Documentación de motivos</li>
        </ul>

        <a href="tools\ajustar_kilometraje.php" class="tool-button success">
            <i class="fas fa-wrench"></i> Ajustar Kilómetros
        </a>
    </div>

    <!-- Ajustar Envíos a Silacor -->
    <div class="tool-card adjust-silacor fade-in delay-12">
        <div class="frequency-badge adjustment">AJUSTE</div>
        <i class="fas fa-tools tool-icon"></i>
        <h3 class="tool-title">Ajustar Envíos a Silacor</h3>
        <p class="tool-description">
            Herramienta para registrar envíos históricos a Silacor de cubiertas existentes y mantener un registro completo.
        </p>
        
        <ul class="tool-features">
            <li><i class="fas fa-check"></i> Registro de múltiples fechas de envío</li>
            <li><i class="fas fa-check"></i> Validación automática de duplicados</li>
            <li><i class="fas fa-check"></i> Auditoría completa de cambios</li>
            <li><i class="fas fa-check"></i> Integración con historial existente</li>
        </ul>
        
        <a href="tools/ajustar_silacor.php" class="tool-button success">
            <i class="fas fa-wrench"></i> Ajustar Silacor
        </a>
    </div>

    <!-- === HERRAMIENTAS PELIGROSAS === -->

    <!-- Eliminar Bus -->
    <div class="tool-card delete-bus fade-in delay-13">
        <div class="warning-badge">¡CUIDADO!</div>
        <div class="frequency-badge dangerous">USO EXCEPCIONAL</div>
        <i class="fas fa-bus tool-icon"></i>
        <h3 class="tool-title">Eliminar Bus</h3>
        <p class="tool-description">
            Herramienta para eliminar buses del sistema, incluyendo todo su registro histórico.
        </p>
        
        <ul class="tool-features">
            <li><i class="fas fa-exclamation-triangle"></i> Eliminación segura</li>
            <li><i class="fas fa-exclamation-triangle"></i> Backup automático</li>
            <li><i class="fas fa-exclamation-triangle"></i> Validación de dependencias</li>
            <li><i class="fas fa-exclamation-triangle"></i> Confirmación múltiple</li>
        </ul>
        
        <a href="tools\eliminar_bus.php" class="tool-button danger">
            <i class="fas fa-trash-alt"></i> Eliminar Bus
        </a>
    </div>

    <!-- Reset de Datos -->
    <div class="tool-card reset fade-in delay-14">
        <div class="warning-badge">¡PELIGRO!</div>
        <div class="frequency-badge dangerous">USO EXCEPCIONAL</div>
        <i class="fas fa-bomb tool-icon"></i>
        <h3 class="tool-title">Resetar Base de Datos</h3>
        <p class="tool-description">
            Herramientas para resetear parcial o completamente los datos del sistema. Usar con extrema precaución.
        </p>
        
        <ul class="tool-features">
            <li><i class="fas fa-exclamation-triangle"></i> Reset de historial</li>
            <li><i class="fas fa-exclamation-triangle"></i> Reset de bajas/altas</li>
            <li><i class="fas fa-exclamation-triangle"></i> Reset completo</li>
            <li><i class="fas fa-exclamation-triangle"></i> Confirmaciones múltiples</li>
        </ul>
        
        <a href="tools\reset_data.php" class="tool-button danger">
            <i class="fas fa-bomb"></i> Acceder a Reset
        </a>
    </div>

</div>

<!-- CSS adicional para los nuevos badges y separador -->
<style>
/* Posicionamiento corregido de badges */
.tool-card {
    position: relative;
}

/* Badge de frecuencia - posición por defecto */
.frequency-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    z-index: 2;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Warning badge - siempre en la esquina superior derecha */
.warning-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: linear-gradient(135deg, #f39c12, #d35400);
    color: white;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
    z-index: 3; /* Mayor z-index que frequency-badge */
}

/* Cuando hay warning-badge, mover frequency-badge hacia abajo para evitar solapamiento */
.tool-card.reset .frequency-badge,
.tool-card.delete-bus .frequency-badge {
    top: 55px; /* Separación adicional para tarjetas con warning */
}

/* Colores de frequency badges */
.frequency-badge.daily {
    background: linear-gradient(135deg, #27ae60, #2ecc71);
    color: white;
    box-shadow: 0 2px 8px rgba(46, 204, 113, 0.3);
}

.frequency-badge.frequent {
    background: linear-gradient(135deg, #3498db, #5dade2);
    color: white;
    box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
}

.frequency-badge.occasional {
    background: linear-gradient(135deg, #f39c12, #f7dc6f);
    color: #2c3e50;
    box-shadow: 0 2px 8px rgba(243, 156, 18, 0.3);
}

.frequency-badge.setup {
    background: linear-gradient(135deg, #9b59b6, #bb8fce);
    color: white;
    box-shadow: 0 2px 8px rgba(155, 89, 182, 0.3);
}

.frequency-badge.adjustment {
    background: linear-gradient(135deg, #e67e22, #f8c471);
    color: white;
    box-shadow: 0 2px 8px rgba(230, 126, 34, 0.3);
}

.frequency-badge.dangerous {
    background: linear-gradient(135deg, #e74c3c, #ec7063);
    color: white;
    box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
    animation: danger-pulse 2s infinite;
}

@keyframes danger-pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.section-separator {
    grid-column: 1 / -1;
    text-align: center;
    padding: 30px 20px;
    background: linear-gradient(135deg, rgba(52, 73, 94, 0.8), rgba(44, 62, 80, 0.8));
    border-radius: 15px;
    border: 2px dashed rgba(52, 152, 219, 0.3);
    margin: 20px 0;
}

.section-separator h3 {
    color: #3498db;
    font-size: 1.3rem;
    margin-bottom: 10px;
    font-weight: 600;
}

.section-separator p {
    color: #bdc3c7;
    font-size: 0.9rem;
    margin: 0;
}

/* Colores específicos para iconos por categoría */
.tool-card.adjust-silacor .tool-icon {
    color: #e67e22;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .section-separator {
        padding: 20px 15px;
    }
    
    .section-separator h3 {
        font-size: 1.1rem;
    }
    
    .frequency-badge {
        font-size: 0.6rem;
        padding: 3px 6px;
    }
    
    .warning-badge {
        font-size: 0.7rem;
        padding: 4px 8px;
    }
    
    /* En móvil, posicionar frequency-badge más abajo para tarjetas con warning */
    .tool-card.reset .frequency-badge,
    .tool-card.delete-bus .frequency-badge {
        top: 50px;
    }
}
</style>
					
                </div>
            </div>

            <br>
            <footer class="fade-in delay-6">
                <p>&copy; <?php echo date('Y'); ?> Sistema de Gestión de Cubiertas | Panel de Administración</p>
            </footer>
        </div>
    </div>

    <!-- Scripts -->
    <script src="modal.js"></script>
    <script>
        // Función de logout
        function logout() {
            if (confirm('¿Está seguro de que desea cerrar sesión?')) {
                window.location.href = 'logout.php';
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Animaciones al cargar
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                setTimeout(() => {
                    element.style.opacity = '1';
                }, 100 * index);
            });
            
            // Efectos hover para las tarjetas
            const toolCards = document.querySelectorAll('.tool-card');
            toolCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-10px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });
            
            // Efecto de ondas en los botones
            const toolButtons = document.querySelectorAll('.tool-button');
            toolButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    // Solo para botones que no sean enlaces
                    if (this.tagName === 'BUTTON') {
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
                    }
                });
            });
            
            // Efectos de partículas al hacer hover (opcional)
            toolCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    // Crear efecto de brillo
                    let glowEffect = document.createElement('div');
                    glowEffect.className = 'glow-effect';
                    glowEffect.style.cssText = `
                        position: absolute;
                        top: -50%;
                        left: -50%;
                        width: 200%;
                        height: 200%;
                        background: radial-gradient(circle, rgba(52,152,219,0.1) 0%, transparent 70%);
                        pointer-events: none;
                        opacity: 0;
                        transition: opacity 0.3s ease;
                    `;
                    
                    this.appendChild(glowEffect);
                    
                    setTimeout(() => {
                        glowEffect.style.opacity = '1';
                    }, 50);
                });
                
                card.addEventListener('mouseleave', function() {
                    const glowEffect = this.querySelector('.glow-effect');
                    if (glowEffect) {
                        glowEffect.style.opacity = '0';
                        setTimeout(() => {
                            glowEffect.remove();
                        }, 300);
                    }
                });
            });
            
            // Atajo de teclado para logout (Ctrl+L)
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'l') {
                    e.preventDefault();
                    logout();
                }
            });
            
            // Auto-logout después de inactividad (30 minutos)
            let inactivityTimer;
            const INACTIVITY_TIME = 30 * 60 * 1000; // 30 minutos en ms
            
            function resetInactivityTimer() {
                clearTimeout(inactivityTimer);
                inactivityTimer = setTimeout(() => {
                    alert('Su sesión ha expirado por inactividad. Será redirigido al login.');
                    window.location.href = 'logout.php';
                }, INACTIVITY_TIME);
            }
            
            // Eventos que resetean el timer de inactividad
            ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'].forEach(event => {
                document.addEventListener(event, resetInactivityTimer, true);
            });
            
            // Inicializar el timer
            resetInactivityTimer();
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
	<script>

// También cerrar sesión al cambiar de pestaña por mucho tiempo
let sessionTimeout;
let isActive = true;

// Detectar cuando la pestaña se oculta
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        isActive = false;
        // Iniciar timer de 5 minutos de inactividad
        sessionTimeout = setTimeout(function() {
            fetch('logout.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'auto_logout=1'
            }).then(() => {
                window.location.href = 'login.php?logout=1';
            });
        }, 5 * 60 * 1000); // 5 minutos
    } else {
        isActive = true;
        // Cancelar el timer si regresa antes de tiempo
        if (sessionTimeout) {
            clearTimeout(sessionTimeout);
        }
    }
});

// Cerrar sesión por inactividad (15 minutos sin actividad)
let inactivityTimer;
function resetInactivityTimer() {
    clearTimeout(inactivityTimer);
    inactivityTimer = setTimeout(function() {
        fetch('logout.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'auto_logout=1'
        }).then(() => {
            alert('Sesión cerrada por inactividad');
            window.location.href = 'login.php?logout=1';
        });
    }, 15 * 60 * 1000); // 15 minutos
}

// Eventos que reinician el timer de inactividad
['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'].forEach(function(event) {
    document.addEventListener(event, resetInactivityTimer, true);
});

// Iniciar el timer al cargar la página
resetInactivityTimer();
</script>
</body>
</html>