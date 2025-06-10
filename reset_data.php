<?php
// reset_data_mejorado.php - Versión compatible con init_database_safe.php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "prueba4";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

$accion = isset($_POST['accion']) ? $_POST['accion'] : '';

if ($accion === 'reset_historial') {
    echo "<h2>🧹 Reset de Historial Completo</h2>";
    
    // Desactivar restricciones de clave foránea
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    // RESET COMPLETO de historial manteniendo estructura
    $tablas_historial = [
        'historial_cubiertas',
        'reconstrucciones', 
        'historial_bajas',
        'kilometraje_diario',
        'temp_operaciones',
        'auditoria_validaciones',
        'intercambios_cubiertas',
        'log_loops_detectados'
    ];
    
    foreach ($tablas_historial as $tabla) {
        $conn->query("TRUNCATE TABLE `$tabla`");
        $conn->query("ALTER TABLE `$tabla` AUTO_INCREMENT = 1");
        echo "<div class='success'>✅ Tabla '$tabla' limpiada y AUTO_INCREMENT reseteado</div>";
    }
    
    // Reactivar restricciones
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    
    mostrarExitoYRedirigir("Reset de historial completado. Estructura preservada, datos eliminados, AUTO_INCREMENT reseteado.", false);
    
} elseif ($accion === 'reset_bajas') {
    echo "<h2>🧹 Reset Solo Historial de Bajas</h2>";
    
    $conn->query("TRUNCATE TABLE historial_bajas");
    $conn->query("ALTER TABLE historial_bajas AUTO_INCREMENT = 1");
    
    mostrarExitoYRedirigir("Historial de bajas limpiado completamente.", false);
    
} elseif ($accion === 'reset_completo_limpio') {
    echo "<h2>🧹 Reset Completo Limpio (Compatible con init_database_safe)</h2>";
    
    // Desactivar restricciones de clave foránea
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    // Lista COMPLETA de todas las tablas del sistema
    $todas_las_tablas = [
        'historial_cubiertas',
        'reconstrucciones', 
        'cubiertas',
        'coches',
        'historial_bajas',
        'kilometraje_diario',
        'wizard_config',
        'temp_operaciones',
        'auditoria_validaciones',
        'intercambios_cubiertas',
        'log_loops_detectados'
    ];
    
    // TRUNCATE + Reset AUTO_INCREMENT para TODAS las tablas
    foreach ($todas_las_tablas as $tabla) {
        // Verificar si la tabla existe antes de truncar
        $sql_check = "SHOW TABLES LIKE '$tabla'";
        $result_check = $conn->query($sql_check);
        
        if ($result_check->num_rows > 0) {
            $conn->query("TRUNCATE TABLE `$tabla`");
            $conn->query("ALTER TABLE `$tabla` AUTO_INCREMENT = 1");
            echo "<div class='success'>✅ Tabla '$tabla' completamente limpiada</div>";
        } else {
            echo "<div class='warning'>⚠️ Tabla '$tabla' no existe, saltando</div>";
        }
    }
    
    // Reactivar restricciones
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    
    // Insertar marca de reset limpio
    $conn->query("INSERT INTO wizard_config (clave, valor) VALUES ('ultimo_reset_limpio', NOW()) ON DUPLICATE KEY UPDATE valor = NOW()");
    
    mostrarExitoYRedirigir("Reset completo limpio ejecutado. Sistema listo para init_database_safe.php", true);
    
} elseif ($accion === 'reset_conservador') {
    echo "<h2>🧹 Reset Conservador (Solo Datos de Prueba)</h2>";
    
    // Solo limpiar datos que claramente son de prueba
    $conn->query("DELETE FROM historial_cubiertas WHERE fecha_colocacion > DATE_SUB(NOW(), INTERVAL 1 DAY)");
    $conn->query("DELETE FROM historial_bajas WHERE fecha_operacion > DATE_SUB(NOW(), INTERVAL 1 DAY)");
    $conn->query("DELETE FROM kilometraje_diario WHERE fecha > DATE_SUB(NOW(), INTERVAL 1 DAY)");
    
    // Limpiar cubiertas de prueba (que contengan 'test', 'prueba', etc.)
    $conn->query("DELETE FROM cubiertas WHERE nombre LIKE '%test%' OR nombre LIKE '%prueba%' OR nombre LIKE '%demo%'");
    
    mostrarExitoYRedirigir("Reset conservador completado. Solo se eliminaron datos de prueba recientes.", false);
    
} else {
    // Mostrar formulario de confirmación MEJORADO
    mostrarFormularioMejorado();
}

function mostrarExitoYRedirigir($mensaje, $reset_completo = false) {
    $tiempo_espera = $reset_completo ? 3 : 5;
    $url_destino = $reset_completo ? '../tools/init_database_safe.php' : '../index.php';
    $titulo_destino = $reset_completo ? 'Inicializar Base de Datos (Seguro)' : 'Sistema Principal';
    
    echo "<div style='background: #27ae60; color: white; padding: 20px; text-align: center; font-family: Arial, sans-serif; border-radius: 10px; margin: 20px 0;'>";
    echo "<h2>✅ Operación Completada</h2>";
    echo "<p>$mensaje</p>";
    echo "<p>Redirigiendo a $titulo_destino en <span id='countdown'>$tiempo_espera</span> segundos...</p>";
    echo "<p><a href='$url_destino' style='color: white; font-weight: bold; text-decoration: none; background: rgba(255,255,255,0.2); padding: 10px 15px; border-radius: 5px;'>Ir ahora</a></p>";
    echo "</div>";
    
    echo "<script>
    let timeLeft = $tiempo_espera;
    const countdown = document.getElementById('countdown');
    const timer = setInterval(function() {
        timeLeft--;
        countdown.textContent = timeLeft;
        if (timeLeft <= 0) {
            clearInterval(timer);
            window.location.href = '$url_destino';
        }
    }, 1000);
    </script>";
    
    exit;
}

function mostrarFormularioMejorado() {
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset de Datos - Versión Mejorada</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: #ecf0f1;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: rgba(44, 62, 80, 0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        h1 {
            text-align: center;
            color: #3498db;
            margin-bottom: 30px;
            font-size: 2.5rem;
        }
        
        .warning-global {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
            font-weight: 600;
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }
        
        .reset-option {
            background: rgba(52, 73, 94, 0.8);
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            border-left: 5px solid;
            transition: all 0.3s ease;
        }
        
        .reset-option:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .reset-option.safe { border-color: #27ae60; }
        .reset-option.warning { border-color: #f39c12; }
        .reset-option.danger { border-color: #e74c3c; }
        .reset-option.conservative { border-color: #3498db; }
        
        .option-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .option-description {
            margin-bottom: 15px;
            color: #bdc3c7;
        }
        
        .option-details {
            background: rgba(0, 0, 0, 0.2);
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        
        .btn {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }
        
        .btn.danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }
        
        .btn.warning {
            background: linear-gradient(135deg, #f39c12, #d35400);
        }
        
        .btn.safe {
            background: linear-gradient(135deg, #27ae60, #219653);
        }
        
        .nav-buttons {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .fade-in {
            opacity: 0;
            animation: fadeIn 0.6s ease forwards;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }
    </style>
</head>
<body>
    <div class="container fade-in">
        <h1><i class="fas fa-database"></i> Reset de Datos - Versión Mejorada</h1>
        
        <div class="nav-buttons fade-in delay-1">
            <a href="../tools.php" class="btn">
                <i class="fas fa-arrow-left"></i> Volver a Herramientas
            </a>
        </div>
        
        <div class="warning-global fade-in delay-2">
            <i class="fas fa-exclamation-triangle"></i> 
            <strong>VERSIÓN COMPATIBLE CON INIT_DATABASE_SAFE.PHP</strong>
            <br>Esta versión resuelve conflictos de AUTO_INCREMENT y preserva la integridad referencial.
        </div>
        
        <form method="POST">
            
            <!-- Opción Conservadora -->
            <div class="reset-option conservative fade-in delay-3">
                <div class="option-title">
                    <i class="fas fa-broom"></i> Reset Conservador
                </div>
                <div class="option-description">
                    Elimina solo datos de prueba recientes y mantiene todo lo importante.
                </div>
                <div class="option-details">
                    <strong>Elimina:</strong> Datos de prueba (últimas 24h), cubiertas con 'test', 'prueba', 'demo'<br>
                    <strong>Preserva:</strong> Todas las cubiertas reales, historial importante, configuraciones<br>
                    <strong>Ideal para:</strong> Limpiar pruebas sin perder datos importantes
                </div>
                <button type="submit" name="accion" value="reset_conservador" class="btn safe"
                        onclick="return confirm('¿Eliminar solo datos de prueba recientes?')">
                    <i class="fas fa-leaf"></i> Reset Conservador
                </button>
            </div>
            
            <!-- Opción Solo Historial de Bajas -->
            <div class="reset-option safe fade-in delay-4">
                <div class="option-title">
                    <i class="fas fa-history"></i> Reset Solo Historial de Bajas
                </div>
                <div class="option-description">
                    Limpia únicamente la tabla historial_bajas manteniendo todo lo demás.
                </div>
                <div class="option-details">
                    <strong>Elimina:</strong> Todos los registros de historial_bajas<br>
                    <strong>Preserva:</strong> Cubiertas, coches, historial_cubiertas, reconstrucciones<br>
                    <strong>Ideal para:</strong> Repoblar historial de bajas desde cero
                </div>
                <button type="submit" name="accion" value="reset_bajas" class="btn safe"
                        onclick="return confirm('¿Limpiar solo el historial de bajas?')">
                    <i class="fas fa-eraser"></i> Limpiar Historial Bajas
                </button>
            </div>
            
            <!-- Opción Historial Completo -->
            <div class="reset-option warning fade-in delay-4">
                <div class="option-title">
                    <i class="fas fa-archive"></i> Reset Historial Completo
                </div>
                <div class="option-description">
                    Limpia todo el historial pero mantiene las cubiertas y coches actuales.
                </div>
                <div class="option-details">
                    <strong>Elimina:</strong> historial_cubiertas, reconstrucciones, historial_bajas, kilometraje_diario<br>
                    <strong>Preserva:</strong> Cubiertas actuales, coches, configuraciones<br>
                    <strong>Ideal para:</strong> Empezar historial desde cero con elementos existentes
                </div>
                <button type="submit" name="accion" value="reset_historial" class="btn warning"
                        onclick="return confirm('¿Eliminar TODO el historial manteniendo cubiertas y coches?')">
                    <i class="fas fa-trash-alt"></i> Reset Historial
                </button>
            </div>
            
            <!-- Opción Reset Completo Limpio -->
            <div class="reset-option danger fade-in delay-4">
                <div class="option-title">
                    <i class="fas fa-bomb"></i> Reset Completo Limpio
                </div>
                <div class="option-description">
                    Elimina TODOS los datos y resetea AUTO_INCREMENT. Compatible con init_database_safe.php.
                </div>
                <div class="option-details">
                    <strong>Elimina:</strong> TODOS los datos de TODAS las tablas<br>
                    <strong>Resetea:</strong> AUTO_INCREMENT a 1 en todas las tablas<br>
                    <strong>Ideal para:</strong> Empezar completamente desde cero<br>
                    <strong>⚠️ Después ejecutar:</strong> init_database_safe.php → wizard_setup.php
                </div>
                <button type="submit" name="accion" value="reset_completo_limpio" class="btn danger"
                        onclick="return confirm('¿RESETEAR COMPLETAMENTE TODO EL SISTEMA? Esta acción eliminará TODOS los datos.')">
                    <i class="fas fa-nuclear"></i> Reset Completo Limpio
                </button>
            </div>
            
        </form>
    </div>
</body>
</html>
<?php
}

$conn->close();
?>