<?php
// wizard_steps/paso1_inicio.php

// Verificar y crear tablas principales si no existen
$tablas_sql = [
    'coches' => "CREATE TABLE IF NOT EXISTS coches (
        id INT PRIMARY KEY,
        modelo VARCHAR(100) NULL,
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    'cubiertas' => "CREATE TABLE IF NOT EXISTS cubiertas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL UNIQUE,
        estado ENUM('casanova', 'silacor', 'baja') DEFAULT 'casanova',
        coche_id INT NULL,
        posicion VARCHAR(50) NULL,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (coche_id) REFERENCES coches(id) ON DELETE SET NULL
    )",
    
    'historial_cubiertas' => "CREATE TABLE IF NOT EXISTS historial_cubiertas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cubierta_id INT NOT NULL,
        coche_id INT NOT NULL,
        fecha_colocacion DATETIME NOT NULL,
        fecha_retiro DATETIME NULL,
        kilometraje_colocacion INT NULL,
        kilometraje_retiro INT NULL,
        observaciones TEXT NULL,
        FOREIGN KEY (cubierta_id) REFERENCES cubiertas(id) ON DELETE CASCADE,
        FOREIGN KEY (coche_id) REFERENCES coches(id) ON DELETE CASCADE
    )",
    
    'reconstrucciones' => "CREATE TABLE IF NOT EXISTS reconstrucciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cubierta_id INT NOT NULL,
        fecha_reconstruccion DATE NOT NULL,
        tipo_reconstruccion VARCHAR(50) DEFAULT 'silacor',
        costo DECIMAL(10,2) NULL,
        observaciones TEXT NULL,
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (cubierta_id) REFERENCES cubiertas(id) ON DELETE CASCADE
    )"
];

// Crear tablas si no existen
$tablas_creadas = [];
$errores_creacion = [];

foreach ($tablas_sql as $tabla => $sql_create) {
    if ($conn->query($sql_create)) {
        $tablas_creadas[] = $tabla;
    } else {
        $errores_creacion[] = "Error al crear tabla '$tabla': " . $conn->error;
    }
}

// Crear tablas auxiliares
$tablas_auxiliares = [
    'kilometraje_diario' => "CREATE TABLE IF NOT EXISTS kilometraje_diario (
        id INT AUTO_INCREMENT PRIMARY KEY,
        coche_id INT NOT NULL,
        fecha DATE NOT NULL,
        kilometraje INT NOT NULL,
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_coche_fecha (coche_id, fecha),
        FOREIGN KEY (coche_id) REFERENCES coches(id) ON DELETE CASCADE
    )",
    
    'historial_bajas' => "CREATE TABLE IF NOT EXISTS historial_bajas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cubierta_id INT NOT NULL,
        cubierta_nombre VARCHAR(100) NOT NULL,
        tipo_operacion ENUM('alta', 'baja') NOT NULL,
        motivo VARCHAR(100) NULL,
        fecha_operacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        fecha_colocacion DATE NULL,
        fecha_retiro DATE NULL,
        kilometraje_retiro INT NULL,
        coche_id INT NULL,
        fecha_reconstruccion DATE NULL,
        usuario VARCHAR(50) NULL
    )"
];

foreach ($tablas_auxiliares as $tabla => $sql_create) {
    $conn->query($sql_create);
}

// Verificar si la columna posicion existe en cubiertas
$sql_check_posicion = "SHOW COLUMNS FROM cubiertas LIKE 'posicion'";
$result_posicion = $conn->query($sql_check_posicion);
if ($result_posicion->num_rows == 0) {
    $sql_add_posicion = "ALTER TABLE cubiertas ADD COLUMN posicion VARCHAR(50) NULL";
    $conn->query($sql_add_posicion);
}
?>

<div class="step-content">
    <div class="step-header">
        <i class="fas fa-rocket"></i>
        <h2>Bienvenido al Sistema de Gestión de Cubiertas</h2>
        <p>Configuremos tu sistema desde cero paso a paso</p>
    </div>

    <div class="welcome-info">
        <div class="info-grid">
            <div class="info-card">
                <i class="fas fa-bus"></i>
                <h3>Buses</h3>
                <p>Configuraremos los buses de tu flota con sus IDs únicos</p>
            </div>
            
            <div class="info-card">
                <i class="fas fa-circle"></i>
                <h3>Cubiertas</h3>
                <p>Crearemos el inventario completo de todas tus cubiertas</p>
            </div>
            
            <div class="info-card">
                <i class="fas fa-link"></i>
                <h3>Asignaciones</h3>
                <p>Opcionalmente podrás asignar cubiertas a buses desde el inicio</p>
            </div>
        </div>
    </div>

    <div class="system-check">
        <h3>Verificación del Sistema</h3>
        
        <?php if (!empty($errores_creacion)): ?>
            <?php foreach ($errores_creacion as $error): ?>
                <div class="mensaje-error">
                    <i class='fas fa-exclamation-circle'></i>
                    <?php echo $error; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div class="check-results">
            <div class="check-item">
                <i class="fas fa-database text-success"></i>
                <span>Base de datos conectada</span>
            </div>
            
            <?php
            // Verificar tablas principales
            $tablas = ['coches', 'cubiertas', 'historial_cubiertas', 'reconstrucciones'];
            foreach ($tablas as $tabla) {
                $sql = "SHOW TABLES LIKE '$tabla'";
                $result = $conn->query($sql);
                echo "<div class='check-item'>";
                if ($result && $result->num_rows > 0) {
                    echo "<i class='fas fa-check-circle text-success'></i>";
                    echo "<span>Tabla '$tabla': OK</span>";
                } else {
                    echo "<i class='fas fa-times-circle text-error'></i>";
                    echo "<span>Tabla '$tabla': FALTANTE</span>";
                }
                echo "</div>";
            }
            
            // Verificar datos existentes con manejo de errores
            try {
                $sql_buses = "SELECT COUNT(*) as total FROM coches";
                $result_buses = $conn->query($sql_buses);
                $total_buses = $result_buses ? $result_buses->fetch_assoc()['total'] : 0;
                
                $sql_cubiertas = "SELECT COUNT(*) as total FROM cubiertas";
                $result_cubiertas = $conn->query($sql_cubiertas);
                $total_cubiertas = $result_cubiertas ? $result_cubiertas->fetch_assoc()['total'] : 0;
                
                $sql_historial = "SELECT COUNT(*) as total FROM historial_cubiertas";
                $result_historial = $conn->query($sql_historial);
                $total_historial = $result_historial ? $result_historial->fetch_assoc()['total'] : 0;
                
                echo "<div class='check-item'>";
                echo "<i class='fas fa-bus text-info'></i>";
                echo "<span>Buses registrados: $total_buses</span>";
                echo "</div>";
                
                echo "<div class='check-item'>";
                echo "<i class='fas fa-circle text-info'></i>";
                echo "<span>Cubiertas registradas: $total_cubiertas</span>";
                echo "</div>";
                
                echo "<div class='check-item'>";
                echo "<i class='fas fa-history text-info'></i>";
                echo "<span>Registros de historial: $total_historial</span>";
                echo "</div>";
                
            } catch (Exception $e) {
                echo "<div class='check-item'>";
                echo "<i class='fas fa-exclamation-triangle text-error'></i>";
                echo "<span>Error al verificar datos: " . $e->getMessage() . "</span>";
                echo "</div>";
            }
            ?>
        </div>
        
        <?php 
        $total_buses = $total_buses ?? 0;
        $total_cubiertas = $total_cubiertas ?? 0;
        
        if ($total_buses > 0 || $total_cubiertas > 0): 
        ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>¡Atención!</strong> Ya tienes algunos datos en el sistema. 
                Este wizard agregará nueva información sin eliminar la existente.
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <strong>¡Perfecto!</strong> El sistema está limpio y listo para la configuración inicial.
            </div>
        <?php endif; ?>

        <div class="system-overview">
            <h3>Resumen del Estado Actual:</h3>
            
            <?php
            // Cubiertas asignadas vs disponibles
            try {
                $sql_asignadas = "SELECT COUNT(*) as total FROM cubiertas WHERE coche_id IS NOT NULL";
                $result_asignadas = $conn->query($sql_asignadas);
                $cubiertas_asignadas = $result_asignadas ? $result_asignadas->fetch_assoc()['total'] : 0;
                $cubiertas_disponibles = $total_cubiertas - $cubiertas_asignadas;
            } catch (Exception $e) {
                $cubiertas_asignadas = 0;
                $cubiertas_disponibles = 0;
            }
            ?>
            
            <div class="overview-grid">
                <div class="overview-card">
                    <div class="card-icon"><i class="fas fa-bus"></i></div>
                    <div class="card-content">
                        <h4><?php echo $total_buses; ?></h4>
                        <p>Buses Totales</p>
                    </div>
                </div>
                
                <div class="overview-card">
                    <div class="card-icon"><i class="fas fa-circle"></i></div>
                    <div class="card-content">
                        <h4><?php echo $cubiertas_asignadas; ?></h4>
                        <p>Cubiertas Asignadas</p>
                    </div>
                </div>
                
                <div class="overview-card">
                    <div class="card-icon"><i class="fas fa-warehouse"></i></div>
                    <div class="card-content">
                        <h4><?php echo $cubiertas_disponibles; ?></h4>
                        <p>Cubiertas Disponibles</p>
                    </div>
                </div>
                
                <div class="overview-card">
                    <div class="card-icon"><i class="fas fa-history"></i></div>
                    <div class="card-content">
                        <h4><?php echo $total_historial ?? 0; ?></h4>
                        <p>Registros de Historial</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="warning-notice">
            <i class="fas fa-info-circle"></i>
            <div>
                <h4>¡Importante!</h4>
                <p>Este wizard configurará el sistema para comenzar a operar con los datos actuales. 
                Se recomienda hacer un backup de la base de datos antes de continuar.</p>
                <ul>
                    <li>Se mantendrán todos los datos existentes</li>
                    <li>Se agregarán campos y configuraciones necesarias</li>
                    <li>Se podrán añadir nuevas cubiertas y ajustar asignaciones</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="configuration-plan">
        <h3>Plan de Configuración</h3>
        <div class="steps-preview">
            <div class="step-preview">
                <div class="step-number">1</div>
                <div class="step-info">
                    <h4>Configuración de Buses</h4>
                    <p>Definir los IDs de todos los buses de la flota</p>
                </div>
            </div>
            
            <div class="step-preview">
                <div class="step-number">2</div>
                <div class="step-info">
                    <h4>Inventario de Cubiertas</h4>
                    <p>Crear el catálogo completo de cubiertas disponibles</p>
                </div>
            </div>
            
            <div class="step-preview">
                <div class="step-number">3</div>
                <div class="step-info">
                    <h4>Asignaciones Iniciales</h4>
                    <p>Opcionalmente asignar cubiertas a buses (se puede hacer después)</p>
                </div>
            </div>
            
            <div class="step-preview">
                <div class="step-number">4</div>
                <div class="step-info">
                    <h4>Finalización</h4>
                    <p>Revisar configuración y activar el sistema</p>
                </div>
            </div>
        </div>
    </div>

    <div class="important-notes">
        <h3>Notas Importantes</h3>
        <ul>
            <li><i class="fas fa-info-circle"></i> Todos los datos que ingreses se guardarán de forma permanente</li>
            <li><i class="fas fa-shield-alt"></i> Puedes agregar más buses y cubiertas después desde el sistema principal</li>
            <li><i class="fas fa-undo"></i> Si cometes un error, podrás corregirlo desde la administración del sistema</li>
            <li><i class="fas fa-clock"></i> Este proceso tomará aproximadamente 5-10 minutos</li>
        </ul>
    </div>

    <form method="POST" class="wizard-form">
        <input type="hidden" name="paso_actual" value="1">
        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-large">
                <i class="fas fa-play"></i>
                Comenzar Configuración
            </button>
        </div>
    </form>
</div>

<style>
/* Estilos para la verificación de tablas */
.check-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    background: rgba(255,255,255,0.05);
    border-radius: 6px;
    margin: 5px 0;
}

.text-success { 
    color: #2ecc71; 
}

.text-info { 
    color: #3498db; 
}

.text-error { 
    color: #e74c3c; 
}

.mensaje-error {
    padding: 15px 20px;
    border-radius: 6px;
    margin: 15px 0;
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(231, 76, 60, 0.2);
    border: 1px solid #e74c3c;
    color: #e74c3c;
}

.welcome-info {
    margin: 30px 0;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.info-card {
    background: #34495e;
    padding: 25px;
    border-radius: 8px;
    text-align: center;
    border: 2px solid transparent;
    transition: all 0.3s ease;
}

.info-card:hover {
    border-color: #3498db;
    transform: translateY(-3px);
}

.info-card i {
    font-size: 36px;
    color: #3498db;
    margin-bottom: 15px;
    display: block;
}

.info-card h3 {
    color: #ecf0f1;
    margin-bottom: 10px;
    font-size: 18px;
}

.info-card p {
    color: #bdc3c7;
    font-size: 14px;
    line-height: 1.5;
}

.system-check {
    background: #2c3e50;
    padding: 25px;
    border-radius: 8px;
    margin: 30px 0;
}

.system-check h3 {
    color: #ecf0f1;
    margin-bottom: 20px;
    font-size: 20px;
}

.check-results {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.overview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.overview-card {
    background: #34495e;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    border: 2px solid transparent;
    transition: all 0.3s ease;
}

.overview-card:hover {
    border-color: #3498db;
}

.card-icon i {
    font-size: 28px;
    color: #3498db;
    margin-bottom: 10px;
}

.card-content h4 {
    font-size: 24px;
    color: #ecf0f1;
    margin: 5px 0;
}

.card-content p {
    color: #bdc3c7;
    font-size: 12px;
}

.warning-notice {
    background: rgba(241, 196, 15, 0.1);
    border: 1px solid #f1c40f;
    border-radius: 8px;
    padding: 20px;
    margin: 25px 0;
    display: flex;
    gap: 15px;
}

.warning-notice i {
    color: #f1c40f;
    font-size: 24px;
    flex-shrink: 0;
    margin-top: 2px;
}

.warning-notice h4 {
    color: #f1c40f;
    margin: 0 0 10px 0;
}

.warning-notice p {
    color: #ecf0f1;
    margin: 0 0 10px 0;
}

.warning-notice ul {
    color: #ecf0f1;
    margin: 0;
    padding-left: 20px;
}

.warning-notice li {
    margin: 5px 0;
}

.steps-preview {
    display: grid;
    gap: 20px;
    margin: 20px 0;
}

.step-preview {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px;
    background: #34495e;
    border-radius: 8px;
}

.step-number {
    width: 40px;
    height: 40px;
    background: #3498db;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 18px;
    flex-shrink: 0;
}

.step-info h4 {
    color: #ecf0f1;
    margin: 0 0 5px 0;
    font-size: 16px;
}

.step-info p {
    color: #bdc3c7;
    margin: 0;
    font-size: 14px;
}

.important-notes {
    background: rgba(241, 196, 15, 0.1);
    border: 1px solid #f1c40f;
    border-radius: 8px;
    padding: 25px;
    margin: 30px 0;
}

.important-notes h3 {
    color: #f1c40f;
    margin-top: 0;
    margin-bottom: 15px;
}

.important-notes ul {
    list-style: none;
    padding: 0;
}

.important-notes li {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 10px 0;
    color: #ecf0f1;
}

.important-notes i {
    color: #f1c40f;
    width: 20px;
    text-align: center;
}

.btn-large {
    padding: 15px 30px;
    font-size: 16px;
    font-weight: 600;
}

.alert {
    padding: 15px 20px;
    border-radius: 6px;
    margin: 15px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: rgba(46, 204, 113, 0.2);
    border: 1px solid #2ecc71;
    color: #2ecc71;
}

.alert-warning {
    background: rgba(241, 196, 15, 0.2);
    border: 1px solid #f1c40f;
    color: #f1c40f;
}

.alert i {
    font-size: 18px;
}

@media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .overview-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .check-results {
        grid-template-columns: 1fr;
    }
    
    .warning-notice {
        flex-direction: column;
    }
}
</style>