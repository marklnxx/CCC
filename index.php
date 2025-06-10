<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Cubiertas</title>
    <!-- Enlaces a fuentes y bibliotecas externas -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles-dark.css">
    <link rel="stylesheet" href="styles-index.css">
    <link rel="stylesheet" href="tarjetas-fix.css">
    <link rel="stylesheet" href="header-fix.css">
    <link rel="stylesheet" href="nuevo-header.css">
    <link rel="stylesheet" href="modal.css">
	<link rel="stylesheet" href="modern-navbar.css">
    
    <!-- Bibliotecas JavaScript externas -->
    <script src="https://cdn.sheetjs.com/xlsx-0.19.3/package/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <script src="https://code.iconify.design/3/3.1.0/iconify.min.js"></script>
</head>
<body>

    <div class="main-container">
        <!-- Header con logo -->
        <header>
            <div class="logo-container">
                <img src="LOGO.PNG" alt="Logo de la empresa" class="fade-in">
                <h1 class="fade-in delay-1" style="text-align: center;">GESTIÓN DE CUBIERTAS</h1>
            </div>
        </header>

        <div class="content">
        
        <?php
        // Función para obtener el número de alertas de cubiertas
        function obtener_numero_alertas() {
            // Credenciales de la base de datos
            $servername = "localhost";
            $username = "root";
            $password = "";
            $dbname = "prueba4";

            $conn = new mysqli($servername, $username, $password, $dbname);
            
            if ($conn->connect_error) {
                return 0; // En caso de error, devolver 0 alertas
            }
            
            // Consulta para contar cubiertas que están cerca del límite o han superado el límite
            $sql = "SELECT COUNT(*) as num_alertas FROM (
                        SELECT c.id, c.nombre, c.coche_id, 
                            (SELECT MAX(h.kilometraje_colocacion) FROM historial_cubiertas h 
                             WHERE h.cubierta_id = c.id AND h.fecha_colocacion = 
                                (SELECT MAX(fecha_colocacion) FROM historial_cubiertas 
                                 WHERE cubierta_id = c.id AND fecha_retiro IS NULL)) AS km_inicial,
                            (SELECT MAX(h.kilometraje_retiro) FROM historial_cubiertas h 
                             WHERE h.cubierta_id = c.id) AS km_final
                        FROM cubiertas c
                        WHERE c.coche_id IS NOT NULL
                    ) AS subquery 
                    WHERE 
                        (km_final - km_inicial >= 45000 AND km_final - km_inicial < 50000) OR 
                        (km_final - km_inicial >= 50000)";
            
            $result = $conn->query($sql);
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $conn->close();
                return $row['num_alertas'];
            }
            
            $conn->close();
            return 0;
        }

        // Código para obtener el número de alertas
        $numero_alertas = obtener_numero_alertas();
        ?>
                
<!-- Reemplaza tu div .nav-buttons actual con esto -->
<nav class="modern-navbar">
        <button class="navbar-button" onclick="window.location.href='gomería.php'">
            <i class="fas fa-tools"></i> GOMERÍA
        </button>
        <button class="navbar-button" onclick="window.location.href='historial.php'">
            <i class="fas fa-history"></i> HISTORIAL
        </button>
        <button class="navbar-button" onclick="window.location.href='estadisticas.php'">
            <i class="fas fa-chart-bar"></i> ESTADÍSTICAS
		</button>
        <button class="navbar-button" onclick="window.location.href='baja.php'">
            <!-- SVG directo en lugar de iconify -->
            <svg class="tire-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="8"></circle>
                <path d="M8.5 8.5a4 4 0 0 1 7 0m0 7a4 4 0 0 1-7 0"></path>
                <circle cx="12" cy="12" r="2"></circle>
            </svg> ALTAS - BAJAS
        </button>
        <button class="navbar-button" onclick="window.location.href='login.php'">
            <i class="fas fa-user-shield"></i> ADMINISTRACIÓN
        </button>
</nav>
        <br>
		
        <!-- Área de notificaciones -->
        <div class="notification-area">
        <?php if ($numero_alertas > 0): ?>
		<a href="alertas.php" class="alerta-cubierta-link">
    <div class="alerta-cubierta pulse" id="notificacion-alerta">
        <i class="fas fa-exclamation-triangle"></i>
        <div class="contador-alertas"><?php echo $numero_alertas; ?></div>
    </div>
    <div class="tooltip-alerta">
        Hay <?php echo $numero_alertas; ?> cubierta(s) que requieren atención
        <span class="boton-alerta">Ver alertas</span>
    </div>
</a>
        <?php endif; ?>
        </div>

        <!-- Sección de selección de coche -->
        <div class="selector-container fade-in delay-2">
            <h2><i class="fa-solid fa-bus"></i>Seleccione un Bus</h2>
            <form method="GET" class="selector-form">
                <div class="form-group">
                    <label for="coche_id"><i class="fas fa-hashtag"></i> ID del Bus</label>
                    <select name="coche_id" id="coche_id">
                        <option value="">-- Seleccionar ID del Bus --</option>
                        <?php
                            $servername = "localhost";
                            $username = "root";
                            $password = "";
                            $dbname = "prueba4";

                            $conn = new mysqli($servername, $username, $password, $dbname);

                            if ($conn->connect_error) {
                                die("Conexión fallida: " . $conn->connect_error);
                            }

                            // Consulta para obtener los IDs de los coches
                            $sql_coches = "SELECT id FROM coches ORDER BY id";
                            $result_coches = $conn->query($sql_coches);

                            if ($result_coches && $result_coches->num_rows > 0) {
                                while ($row_coche = $result_coches->fetch_assoc()) {
                                    echo "<option value='" . $row_coche["id"] . "'>" . $row_coche["id"] . "</option>";
                                }
                            }
                            $conn->close();
                        ?>
                    </select>
                </div>
                <input type="submit" value="Mostrar Cubiertas">
            </form>
        </div>

        <?php      


// Procesar el cambio de cubierta
if (isset($_POST['cambiar_cubierta']) && 
    isset($_POST['cubierta_vieja_id']) && 
    isset($_POST['nueva_cubierta_id']) && 
    isset($_POST['coche_id']) && 
    isset($_POST['kilometraje'])) {
    
    $cubierta_vieja_id = $_POST['cubierta_vieja_id'];
    $nueva_cubierta_id = $_POST['nueva_cubierta_id'];
    $coche_id = $_POST['coche_id'];
    $kilometraje = $_POST['kilometraje'];
    // Obtener la posición actual directamente del formulario
    $posicion = isset($_POST['posicion_actual']) ? $_POST['posicion_actual'] : null;

    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "prueba4";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Conexión fallida: " . $conn->connect_error);
    }
    
    // Si no tenemos la posición del formulario, intentar obtenerla de la base de datos
    if (!$posicion) {
        $sql_posicion = "SELECT posicion FROM cubiertas WHERE id = ?";
        $stmt_posicion = $conn->prepare($sql_posicion);
        $stmt_posicion->bind_param("i", $cubierta_vieja_id);
        $stmt_posicion->execute();
        $result_posicion = $stmt_posicion->get_result();
        
        if ($result_posicion->num_rows > 0) {
            $row_posicion = $result_posicion->fetch_assoc();
            $posicion = $row_posicion['posicion'];
        }
        $stmt_posicion->close();
    }
    
    // ===============================================
    // NUEVA VALIDACIÓN MEJORADA - INICIO
    // ===============================================
    
    // Incluir la clase validadora
    require_once 'includes/KilometrajeValidator.php';

    // Instanciar el validador
    $validator = new KilometrajeValidator($conn);

    // Realizar la validación completa
    $validacion = $validator->validarCambioKilometraje(
        $coche_id, 
        $cubierta_vieja_id, 
        $kilometraje, 
        1, // usuario_id (puedes obtenerlo de $_SESSION['usuario_id'] si tienes sistema de login)
        isset($_POST['ignorar_validacion'])
    );

    // Mostrar resultados de la validación
    if (!$validacion['valido']) {
        echo "<div class='mensaje-error fade-in shake'>";
        echo "<i class='fas fa-exclamation-circle'></i> <strong>Validación fallida:</strong><br>";
        
        foreach ($validacion['errores'] as $error) {
            echo "<div style='margin: 5px 0; padding-left: 15px;'>• $error</div>";
        }
        
        if (isset($validacion['requiere_autorizacion']) && $validacion['requiere_autorizacion']) {
            echo "<br><div style='background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin: 10px 0;'>";
            echo "<strong><i class='fas fa-shield-alt'></i> Se requiere autorización de supervisor para continuar.</strong>";
            echo "</div>";
        }
        
        echo "</div>";
        
        // Mostrar advertencias si las hay
        if (!empty($validacion['advertencias'])) {
            echo "<div class='mensaje-warning fade-in'>";
            echo "<i class='fas fa-exclamation-triangle'></i> <strong>Información adicional:</strong><br>";
            foreach ($validacion['advertencias'] as $advertencia) {
                echo "<div style='margin: 3px 0; padding-left: 15px;'>• $advertencia</div>";
            }
            echo "</div>";
        }
        
    } else {
        // VALIDACIÓN EXITOSA - Proceder con el cambio
        
        // Mostrar advertencias si las hay (incluso en validaciones exitosas)
        if (!empty($validacion['advertencias'])) {
            echo "<div class='mensaje-warning fade-in'>";
            echo "<i class='fas fa-info-circle'></i> <strong>Información de validación:</strong><br>";
            foreach ($validacion['advertencias'] as $advertencia) {
                echo "<div style='margin: 3px 0; padding-left: 15px;'>• $advertencia</div>";
            }
            echo "</div>";
        }
        
        // Mostrar mensaje especial si se ignoró la validación
        if (isset($validacion['ignorado']) && $validacion['ignorado']) {
            echo "<div class='mensaje-warning fade-in pulse'>";
            echo "<i class='fas fa-exclamation-triangle'></i> <strong>ATENCIÓN:</strong> Validaciones ignoradas por el usuario.";
            echo "</div>";
        }
        
        // ===============================================
        // CONTINUAR CON EL CÓDIGO ORIGINAL DE CAMBIO DE CUBIERTA
        // ===============================================
        
        // Iniciar transacción
        $conn->begin_transaction();
        
        try {
            // PASO 1: VALIDAR que la nueva cubierta existe y está disponible
            $sql_validar_nueva = "SELECT id FROM cubiertas WHERE id = ? AND coche_id IS NULL AND estado = 'casanova'";
            $stmt_validar = $conn->prepare($sql_validar_nueva);
            $stmt_validar->bind_param("i", $nueva_cubierta_id);
            $stmt_validar->execute();
            $result_validar = $stmt_validar->get_result();
            
            if ($result_validar->num_rows === 0) {
                throw new Exception("La cubierta nueva (ID: $nueva_cubierta_id) no existe o no está disponible");
            }
            $stmt_validar->close();
            
            // PASO 2: Registrar la colocación de la nueva cubierta PRIMERO
            $sql_registrar_nueva = "INSERT INTO historial_cubiertas (cubierta_id, coche_id, fecha_colocacion, kilometraje_colocacion) VALUES (?, ?, NOW(), ?)";
            $stmt_registrar_nueva = $conn->prepare($sql_registrar_nueva);
            $stmt_registrar_nueva->bind_param("iii", $nueva_cubierta_id, $coche_id, $kilometraje);
            if (!$stmt_registrar_nueva->execute()) {
                throw new Exception("Error al registrar colocación de nueva cubierta: " . $stmt_registrar_nueva->error);
            }
            $stmt_registrar_nueva->close();

            // PASO 3: Asignar la nueva cubierta al coche
            $sql_asignar_nueva = "UPDATE cubiertas SET coche_id = ?, posicion = ? WHERE id = ?";
            $stmt_asignar_nueva = $conn->prepare($sql_asignar_nueva);
            $stmt_asignar_nueva->bind_param("isi", $coche_id, $posicion, $nueva_cubierta_id);
            if (!$stmt_asignar_nueva->execute() || $stmt_asignar_nueva->affected_rows === 0) {
                throw new Exception("Error al asignar nueva cubierta al coche");
            }
            $stmt_asignar_nueva->close();

            // PASO 4: Registrar la retirada de la cubierta vieja
            $sql_retirar_vieja = "UPDATE historial_cubiertas SET fecha_retiro = NOW(), kilometraje_retiro = ? WHERE cubierta_id = ? AND coche_id = ? AND fecha_retiro IS NULL ORDER BY fecha_colocacion DESC LIMIT 1";
            $stmt_retirar_vieja = $conn->prepare($sql_retirar_vieja);
            $stmt_retirar_vieja->bind_param("iii", $kilometraje, $cubierta_vieja_id, $coche_id);
            if (!$stmt_retirar_vieja->execute()) {
                throw new Exception("Error al registrar retiro de cubierta vieja: " . $stmt_retirar_vieja->error);
            }
            $stmt_retirar_vieja->close();

            // PASO 5: Desasignar la cubierta vieja del coche (ÚLTIMO)
            $sql_desasignar_vieja = "UPDATE cubiertas SET coche_id = NULL, estado = 'casanova', posicion = NULL WHERE id = ?";
            $stmt_desasignar_vieja = $conn->prepare($sql_desasignar_vieja);
            $stmt_desasignar_vieja->bind_param("i", $cubierta_vieja_id);
            if (!$stmt_desasignar_vieja->execute()) {
                throw new Exception("Error al desasignar cubierta vieja: " . $stmt_desasignar_vieja->error);
            }
            $stmt_desasignar_vieja->close();

            // PASO 6: Actualizar kilometraje_diario si existe la tabla
            $sql_check_table = "SHOW TABLES LIKE 'kilometraje_diario'";
            $result_check_table = $conn->query($sql_check_table);
            
            if ($result_check_table->num_rows > 0) {
                $fecha_actual = date('Y-m-d');
                
                $sql_check = "SELECT id FROM kilometraje_diario WHERE coche_id = ? AND fecha = ?";
                $stmt_check = $conn->prepare($sql_check);
                $stmt_check->bind_param("is", $coche_id, $fecha_actual);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                
                if ($result_check->num_rows > 0) {
                    $sql_update_km = "UPDATE kilometraje_diario SET kilometraje = ? WHERE coche_id = ? AND fecha = ?";
                    $stmt_update_km = $conn->prepare($sql_update_km);
                    $stmt_update_km->bind_param("iis", $kilometraje, $coche_id, $fecha_actual);
                    $stmt_update_km->execute();
                    $stmt_update_km->close();
                } else {
                    $sql_insert_km = "INSERT INTO kilometraje_diario (coche_id, fecha, kilometraje) VALUES (?, ?, ?)";
                    $stmt_insert_km = $conn->prepare($sql_insert_km);
                    $stmt_insert_km->bind_param("isi", $coche_id, $fecha_actual, $kilometraje);
                    $stmt_insert_km->execute();
                    $stmt_insert_km->close();
                }
                $stmt_check->close();
            }
            
            // Si todo salió bien, confirmar la transacción
            $conn->commit();
            echo "<div class='mensaje-exito fade-in pulse'>";
            echo "<i class='fas fa-check-circle'></i> <strong>Cambio de cubierta realizado con éxito</strong>";
            
            // Mostrar resumen del cambio si hay información de validación
            if (isset($validacion['mensaje'])) {
                echo "<br><small style='opacity: 0.8;'>Validación: " . $validacion['mensaje'] . "</small>";
            }
            echo "</div>";
            
        } catch (mysqli_sql_exception $e) {
            // Revertir cambios en caso de error SQL
            $conn->rollback();
            echo "<div class='mensaje-error fade-in shake'>";
            echo "<i class='fas fa-exclamation-circle'></i> Error SQL al realizar el cambio de cubierta: " . $e->getMessage();
            echo "</div>";
            
            // Log del error para debugging
            error_log("Error en cambio de cubierta individual - SQL: " . $e->getMessage() . " - Coche: $coche_id, Vieja: $cubierta_vieja_id, Nueva: $nueva_cubierta_id");
            
        } catch (Exception $e) {
            // Revertir cambios en caso de error general
            $conn->rollback();
            echo "<div class='mensaje-error fade-in shake'>";
            echo "<i class='fas fa-exclamation-circle'></i> Error al realizar el cambio de cubierta: " . $e->getMessage();
            echo "</div>";
            
            // Log del error para debugging
            error_log("Error en cambio de cubierta individual - General: " . $e->getMessage() . " - Coche: $coche_id, Vieja: $cubierta_vieja_id, Nueva: $nueva_cubierta_id");
        }
    }
    
    // Cerrar conexión
    $conn->close();
}

// ===============================================
// NUEVA VALIDACIÓN MEJORADA - FIN
// ===============================================



        // Procesar la asignación inicial de cubiertas
if(isset($_POST['asignar_cubiertas']) && isset($_POST['coche_id']) && isset($_POST['cubiertas']) && isset($_POST['kilometraje_inicial'])) {
    $coche_id = $_POST['coche_id'];
    $cubiertas = $_POST['cubiertas'];
    $kilometraje = $_POST['kilometraje_inicial'];
    
    $posiciones = [
        "DELANTERA CHOFER",
        "DELANTERA PUERTA",
        "TRASERA CHOFER AFUERA",
        "TRASERA PUERTA AFUERA",
        "TRASERA CHOFER ADENTRO",
        "TRASERA PUERTA ADENTRO"
    ];
    
    // Validar que el kilometraje sea un número positivo
    if (!is_numeric($kilometraje) || $kilometraje <= 0) {
        echo "<div class='mensaje-error fade-in shake'>";
        echo "<i class='fas fa-exclamation-circle'></i> Error: El kilometraje inicial debe ser un número positivo mayor que cero.";
        echo "</div>";
    } else {
        $servername = "localhost";
        $username = "root";
        $password = "";
        $dbname = "prueba4";
        
        $conn = new mysqli($servername, $username, $password, $dbname);
        
        if ($conn->connect_error) {
            die("Conexión fallida: " . $conn->connect_error);
        }
        
        // Iniciar transacción
        $conn->begin_transaction();
        
        try {
            // Asignar cada cubierta seleccionada al coche
            foreach ($cubiertas as $indice => $cubierta_id) {
                if (!empty($cubierta_id)) {
                    // Obtener la posición correspondiente
                    $posicion = isset($posiciones[$indice]) ? $posiciones[$indice] : null;
                    
                    // 1. Verificar que la cubierta existe y está disponible
                    $sql_verificar = "SELECT id FROM cubiertas WHERE id = ? AND coche_id IS NULL AND estado = 'casanova'";
                    $stmt_verificar = $conn->prepare($sql_verificar);
                    $stmt_verificar->bind_param("i", $cubierta_id);
                    $stmt_verificar->execute();
                    $result_verificar = $stmt_verificar->get_result();
                    
                    if ($result_verificar->num_rows === 0) {
                        throw new Exception("La cubierta ID: $cubierta_id no está disponible para asignar");
                    }
                    $stmt_verificar->close();
                    
                    // 2. Asignar la cubierta al coche
                    $sql_asignar = "UPDATE cubiertas SET coche_id = ?, posicion = ? WHERE id = ?";
                    $stmt_asignar = $conn->prepare($sql_asignar);
                    $stmt_asignar->bind_param("isi", $coche_id, $posicion, $cubierta_id);
                    if (!$stmt_asignar->execute() || $stmt_asignar->affected_rows === 0) {
                        throw new Exception("Error al asignar cubierta ID: $cubierta_id al coche");
                    }
                    $stmt_asignar->close();
                    
                    // 3. Registrar la colocación en el historial
                    $sql_historial = "INSERT INTO historial_cubiertas (cubierta_id, coche_id, fecha_colocacion, kilometraje_colocacion) VALUES (?, ?, NOW(), ?)";
                    $stmt_historial = $conn->prepare($sql_historial);
                    $stmt_historial->bind_param("iii", $cubierta_id, $coche_id, $kilometraje);
                    if (!$stmt_historial->execute()) {
                        throw new Exception("Error al registrar historial para cubierta ID: $cubierta_id");
                    }
                    $stmt_historial->close();
                }
            }
            
            // 4. Actualizar también la tabla kilometraje_diario si existe
            $sql_check_table = "SHOW TABLES LIKE 'kilometraje_diario'";
            $result_check_table = $conn->query($sql_check_table);
            
            if ($result_check_table->num_rows > 0) {
                $fecha_actual = date('Y-m-d');
                
                // Verificar si ya existe un registro para esta fecha y coche
                $sql_check = "SELECT id FROM kilometraje_diario WHERE coche_id = ? AND fecha = ?";
                $stmt_check = $conn->prepare($sql_check);
                $stmt_check->bind_param("is", $coche_id, $fecha_actual);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                
                if ($result_check->num_rows > 0) {
                    // Actualizar registro existente
                    $sql_update_km = "UPDATE kilometraje_diario SET kilometraje = ? WHERE coche_id = ? AND fecha = ?";
                    $stmt_update_km = $conn->prepare($sql_update_km);
                    $stmt_update_km->bind_param("iis", $kilometraje, $coche_id, $fecha_actual);
                    if (!$stmt_update_km->execute()) {
                        throw new Exception("Error al actualizar kilometraje diario");
                    }
                    $stmt_update_km->close();
                } else {
                    // Insertar nuevo registro
                    $sql_insert_km = "INSERT INTO kilometraje_diario (coche_id, fecha, kilometraje) VALUES (?, ?, ?)";
                    $stmt_insert_km = $conn->prepare($sql_insert_km);
                    $stmt_insert_km->bind_param("isi", $coche_id, $fecha_actual, $kilometraje);
                    if (!$stmt_insert_km->execute()) {
                        throw new Exception("Error al insertar kilometraje diario");
                    }
                    $stmt_insert_km->close();
                }
                $stmt_check->close();
            }
            
            // Si todo salió bien, confirmar la transacción
            $conn->commit();
            echo "<div class='mensaje-exito fade-in pulse'><i class='fas fa-check-circle'></i> Cubiertas asignadas con éxito al coche ID: " . $coche_id . "</div>";
            
        } catch (mysqli_sql_exception $e) {
            // Revertir cambios en caso de error SQL
            $conn->rollback();
            echo "<div class='mensaje-error fade-in shake'>";
            echo "<i class='fas fa-exclamation-circle'></i> Error SQL al asignar cubiertas: " . $e->getMessage();
            echo "</div>";
            
            // Log del error para debugging
            error_log("Error en asignación múltiple - SQL: " . $e->getMessage() . " - Coche: $coche_id");
            
        } catch (Exception $e) {
            // Revertir cambios en caso de error general
            $conn->rollback();
            echo "<div class='mensaje-error fade-in shake'>";
            echo "<i class='fas fa-exclamation-circle'></i> Error al asignar cubiertas: " . $e->getMessage();
            echo "</div>";
            
            // Log del error para debugging
            error_log("Error en asignación múltiple - General: " . $e->getMessage() . " - Coche: $coche_id");
        }
        
        $conn->close();
    }
}

        // Mostrar las cubiertas si se ha seleccionado un coche
        if (isset($_GET["coche_id"]) && $_GET["coche_id"] != "") {
            $coche_seleccionado_id = $_GET["coche_id"];

            $servername = "localhost";
            $username = "root";
            $password = "";
            $dbname = "prueba4";

            $conn = new mysqli($servername, $username, $password, $dbname);

            if ($conn->connect_error) {
                die("Conexión fallida: " . $conn->connect_error);
            }

            // Crear un contenedor para ambos diagramas lado a lado
            echo "<div class='diagramas-container fade-in delay-2'>";

            // Diagrama Horizontal (existente)
            echo "<div class='diagrama-cubiertas'>";
            echo "<div class='diagrama-titulo'><i class='fas fa-car'></i> Vista del Coche ID: " . $coche_seleccionado_id . "</div>";

            echo "<div class='diagrama-fila'>";
            echo "<div class='cubierta-posicion hover-glow' data-position='1'><i class='fas fa-arrow-alt-circle-down'></i> DELANTERA CHOFER</div>";
            echo "<div class='cubierta-posicion hover-glow' data-position='2'><i class='fas fa-arrow-alt-circle-left'></i> DELANTERA PUERTA</div>";
            echo "</div>";

            echo "<div class='diagrama-fila'>";
            echo "<div class='cubierta-posicion hover-glow' data-position='3'><i class='fas fa-arrow-alt-circle-left'></i> TRASERA CHOFER AFUERA</div>";
            echo "<div class='cubierta-posicion hover-glow' data-position='6'><i class='fas fa-arrow-alt-circle-right'></i> TRASERA PUERTA AFUERA</div>";
            echo "</div>";

            echo "<div class='diagrama-fila'>";
            echo "<div class='cubierta-posicion hover-glow' data-position='4'><i class='fas fa-arrow-alt-circle-right'></i> TRASERA CHOFER ADENTRO</div>";
            echo "<div class='cubierta-posicion hover-glow' data-position='5'><i class='fas fa-arrow-alt-circle-left'></i> TRASERA PUERTA ADENTRO</div>";
            echo "</div>";
            echo "</div>";

            // Diagrama Vertical (nuevo)
            echo "<div class='diagrama-vertical'>";
            if (file_exists('vbi.php')) {
                include('vbi.php');
            }
            echo "</div>";

            echo "</div>"; // Cierre del contenedor de ambos diagramas
            
            // Consulta para obtener las cubiertas asignadas al coche seleccionado
            $sql_cubiertas_asignadas = "
                SELECT c.id, c.nombre, c.estado, c.posicion
                FROM cubiertas c
                WHERE c.coche_id = ?
                ORDER BY FIELD(c.posicion, 'DELANTERA CHOFER', 'DELANTERA PUERTA', 'TRASERA CHOFER AFUERA', 'TRASERA PUERTA AFUERA', 'TRASERA CHOFER ADENTRO', 'TRASERA PUERTA ADENTRO'), c.id ASC
            ";
            $stmt_cubiertas_asignadas = $conn->prepare($sql_cubiertas_asignadas);
            $stmt_cubiertas_asignadas->bind_param("i", $coche_seleccionado_id);
            $stmt_cubiertas_asignadas->execute();
            $result_cubiertas_asignadas = $stmt_cubiertas_asignadas->get_result();
            $stmt_cubiertas_asignadas->close();

            echo "<h2 class='fade-in delay-3'><i class='fas fa-cogs'> </i> Cubiertas Asignadas al Coche ID: " . $coche_seleccionado_id . "</h2>";
            echo "<div class='cubiertas-container'>";

            if ($result_cubiertas_asignadas->num_rows > 0) {
                // Obtener todas las cubiertas asignadas al coche
                $cubiertas_asignadas = $result_cubiertas_asignadas->fetch_all(MYSQLI_ASSOC);

                // Organizar las cubiertas por posición para un acceso más fácil
                $cubiertas_por_posicion = [];
                foreach ($cubiertas_asignadas as $cubierta) {
                    $cubiertas_por_posicion[$cubierta['posicion']] = $cubierta;
                }

                // Definir las posiciones fijas para mostrar
                $posiciones = [
                    "DELANTERA CHOFER",
                    "DELANTERA PUERTA",
                    "TRASERA CHOFER AFUERA",
                    "TRASERA PUERTA AFUERA",
                    "TRASERA CHOFER ADENTRO",
                    "TRASERA PUERTA ADENTRO"
                ];

                $iconos = [
                    "arrow-alt-circle-down",
                    "arrow-alt-circle-left",
                    "arrow-alt-circle-right",
                    "arrow-alt-circle-right",
                    "arrow-alt-circle-left",
                    "arrow-alt-circle-left"
                ];

                // Obtener el último kilometraje registrado para este coche
                $sql_ultimo_km = "SELECT MAX(kilometraje_retiro) as ultimo_km FROM historial_cubiertas WHERE coche_id = ?";
                $stmt_ultimo_km = $conn->prepare($sql_ultimo_km);
                $stmt_ultimo_km->bind_param("i", $coche_seleccionado_id);
                $stmt_ultimo_km->execute();
                $result_ultimo_km = $stmt_ultimo_km->get_result();
                $row_ultimo_km = $result_ultimo_km->fetch_assoc();
                $ultimo_km = $row_ultimo_km['ultimo_km'] ?: 0;
                $stmt_ultimo_km->close();

                // Primera fila - 2 tarjetas (delanteras)
                echo "<div class='fila-cubiertas fila-delantera'>";
                for ($i = 0; $i < 2; $i++) {
                    $posicion_actual = $posiciones[$i];
                    $icono = $iconos[$i];
                    
                    echo "<div class='cubierta-card fade-in delay-" . ($i + 3) . "'>";
                    echo "<div class='cubierta-title'><i class='fas fa-" . $icono . "'></i> " . $posicion_actual . "</div>";
                    
                    // Verificar si hay una cubierta en esta posición
                    if (isset($cubiertas_por_posicion[$posicion_actual])) {
                        $cubierta = $cubiertas_por_posicion[$posicion_actual];
                        
                        echo "<div class='cubierta-info'>";
                        echo "<p><i class='fas fa-tag'></i> <strong>Cubierta actual:</strong> " . $cubierta["nombre"] . "</p>";
                        echo "<p><i class='fas fa-hashtag'></i> <strong>ID:</strong> " . $cubierta["id"] . "</p>";
                        
                        // Obtener información de kilometraje para esta cubierta (considerando todos los ciclos)
                        $sql_km_cubierta = "SELECT 
                            (SELECT MIN(h1.kilometraje_colocacion) FROM historial_cubiertas h1 WHERE h1.cubierta_id = ?) as km_inicial,
                            COALESCE(
                                (SELECT MAX(h2.kilometraje_retiro) FROM historial_cubiertas h2 WHERE h2.cubierta_id = ? AND h2.fecha_retiro IS NULL),
                                ?
                            ) as km_actual,
                            (
                                SELECT SUM(GREATEST(0, h3.kilometraje_retiro - h3.kilometraje_colocacion))
                                FROM historial_cubiertas h3
                                WHERE h3.cubierta_id = ? AND h3.fecha_retiro IS NOT NULL
                            ) as km_historicos
                        FROM cubiertas 
                        WHERE id = ? LIMIT 1";

                        $stmt_km_cubierta = $conn->prepare($sql_km_cubierta);
                        $stmt_km_cubierta->bind_param("iiiii", $cubierta["id"], $cubierta["id"], $ultimo_km, $cubierta["id"], $cubierta["id"]);
                        $stmt_km_cubierta->execute();
                        $result_km_cubierta = $stmt_km_cubierta->get_result();
                        $km_info = $result_km_cubierta->fetch_assoc();
                        $stmt_km_cubierta->close();

                        // Calcular km del ciclo actual
                        $km_inicial = $km_info['km_inicial'] ?? 0;
                        $km_actual = $km_info['km_actual'] ?? $ultimo_km;
                        $km_historicos = $km_info['km_historicos'] ?? 0;

                        // Calcular km del ciclo actual
                        $km_ciclo_actual = 0;
                        if ($km_actual > $km_inicial) {
                            $km_ciclo_actual = $km_actual - $km_inicial;
                        }

                        // Kilometraje total: histórico + ciclo actual
                        $km_recorridos_totales = $km_historicos + $km_ciclo_actual;

                        echo "<p><i class='fas fa-tachometer-alt'></i> <strong>KM inicial:</strong> " . number_format($km_inicial, 0, ',', '.') . " km</p>";
                        echo "<p><i class='fas fa-road'></i> <strong>KM recorridos (total):</strong> " . number_format($km_recorridos_totales, 0, ',', '.') . " km</p>";
                        echo "</div>";
                        
                        echo "<form method='POST' class='formulario-cambio' id='form-cambio-" . $cubierta["id"] . "'>";
                        echo "<input type='hidden' name='cubierta_vieja_id' value='" . $cubierta["id"] . "'>";
                        echo "<input type='hidden' name='posicion_actual' value='" . $posicion_actual . "'>";
                        
                        echo "<label for='nueva_cubierta_" . $cubierta["id"] . "'><i class='fas fa-exchange-alt'></i> Cambiar por:</label>";
                        echo "<select name='nueva_cubierta_id' id='nueva_cubierta_" . $cubierta["id"] . "' class='selector-cambio-cubiertas' data-cubierta-id='" . $cubierta["id"] . "' required>";
                        echo "<option value=''>-- Seleccionar Cubierta --</option>";

                        // Consulta para obtener las cubiertas disponibles en Casanova
                        $sql_cubiertas_disponibles = "SELECT id, nombre FROM cubiertas WHERE estado = 'casanova' AND coche_id IS NULL";
                        $result_cubiertas_disponibles = $conn->query($sql_cubiertas_disponibles);

                        if ($result_cubiertas_disponibles && $result_cubiertas_disponibles->num_rows > 0) {
                            while ($row_disponible = $result_cubiertas_disponibles->fetch_assoc()) {
                                echo "<option value='" . $row_disponible["id"] . "' data-nombre='" . htmlspecialchars($row_disponible["nombre"], ENT_QUOTES) . "'>" . $row_disponible["nombre"] . "</option>";
                            }
                        }
                        echo "</select>";

                        echo "<label for='kilometraje_" . $cubierta["id"] . "'><i class='fas fa-tachometer-alt'></i> Kilometraje actual:</label>";
                        echo "<input type='number' name='kilometraje' id='kilometraje_" . $cubierta["id"] . "' min='1' value='" . $ultimo_km . "' required data-ultimo-km='" . $ultimo_km . "'>";
                                              
                        
                        echo "<input type='hidden' name='coche_id' value='" . $coche_seleccionado_id . "'>";
                        echo "<button type='submit' class='boton' name='cambiar_cubierta'><i class='fas fa-save'></i> Guardar cambio</button>";
                        echo "</form>";
                    } else {
                        // Si no hay cubierta en esta posición, mostrar mensaje
                        echo "<div class='cubierta-info'>";
                        echo "<p class='mensaje-info'><i class='fas fa-info-circle'></i> No hay cubierta asignada a esta posición</p>";
                        echo "</div>";
                    }
                    
                    echo "</div>"; // Cierre de cubierta-card
                }
                echo "</div>"; // Cierre de fila-delantera

                // Segunda fila - 4 tarjetas (traseras)
                echo "<div class='fila-cubiertas fila-trasera'>";
                for ($i = 2; $i < 6; $i++) {
                    $posicion_actual = $posiciones[$i];
                    $icono = $iconos[$i];
                    
                    echo "<div class='cubierta-card fade-in delay-" . ($i + 3) . "'>";
                    echo "<div class='cubierta-title'><i class='fas fa-" . $icono . "'></i> " . $posicion_actual . "</div>";
                    
                    // Verificar si hay una cubierta en esta posición
                    if (isset($cubiertas_por_posicion[$posicion_actual])) {
                        $cubierta = $cubiertas_por_posicion[$posicion_actual];
                        
                        echo "<div class='cubierta-info'>";
                        echo "<p><i class='fas fa-tag'></i> <strong>Cubierta actual:</strong> " . $cubierta["nombre"] . "</p>";
                        echo "<p><i class='fas fa-hashtag'></i> <strong>ID:</strong> " . $cubierta["id"] . "</p>";
                        
                        // Obtener información de kilometraje para esta cubierta (considerando todos los ciclos)
                        $sql_km_cubierta = "SELECT 
                            (SELECT MIN(h1.kilometraje_colocacion) FROM historial_cubiertas h1 WHERE h1.cubierta_id = ?) as km_inicial,
                            COALESCE(
                                (SELECT MAX(h2.kilometraje_retiro) FROM historial_cubiertas h2 WHERE h2.cubierta_id = ? AND h2.fecha_retiro IS NULL),
                                ?
                            ) as km_actual,
                            (
                                SELECT SUM(GREATEST(0, h3.kilometraje_retiro - h3.kilometraje_colocacion))
                                FROM historial_cubiertas h3
                                WHERE h3.cubierta_id = ? AND h3.fecha_retiro IS NOT NULL
                            ) as km_historicos
                        FROM cubiertas 
                        WHERE id = ? LIMIT 1";

                        $stmt_km_cubierta = $conn->prepare($sql_km_cubierta);
                        $stmt_km_cubierta->bind_param("iiiii", $cubierta["id"], $cubierta["id"], $ultimo_km, $cubierta["id"], $cubierta["id"]);
                        $stmt_km_cubierta->execute();
                        $result_km_cubierta = $stmt_km_cubierta->get_result();
                        $km_info = $result_km_cubierta->fetch_assoc();
                        $stmt_km_cubierta->close();

                        // Calcular km del ciclo actual
                        $km_inicial = $km_info['km_inicial'] ?? 0;
                        $km_actual = $km_info['km_actual'] ?? $ultimo_km;
                        $km_historicos = $km_info['km_historicos'] ?? 0;

                        // Calcular km del ciclo actual
                        $km_ciclo_actual = 0;
                        if ($km_actual > $km_inicial) {
                            $km_ciclo_actual = $km_actual - $km_inicial;
                        }

                        // Kilometraje total: histórico + ciclo actual
                        $km_recorridos_totales = $km_historicos + $km_ciclo_actual;

                        echo "<p><i class='fas fa-tachometer-alt'></i> <strong>KM inicial:</strong> " . number_format($km_inicial, 0, ',', '.') . " km</p>";
                        echo "<p><i class='fas fa-road'></i> <strong>KM recorridos (total):</strong> " . number_format($km_recorridos_totales, 0, ',', '.') . " km</p>";
                        echo "</div>";
                        
                        echo "<form method='POST' class='formulario-cambio' id='form-cambio-" . $cubierta["id"] . "'>";
                        echo "<input type='hidden' name='cubierta_vieja_id' value='" . $cubierta["id"] . "'>";
                        echo "<input type='hidden' name='posicion_actual' value='" . $posicion_actual . "'>";
                        
                        echo "<label for='nueva_cubierta_" . $cubierta["id"] . "'><i class='fas fa-exchange-alt'></i> Cambiar por:</label>";
                        echo "<select name='nueva_cubierta_id' id='nueva_cubierta_" . $cubierta["id"] . "' class='selector-cambio-cubiertas' data-cubierta-id='" . $cubierta["id"] . "' required>";
                        echo "<option value=''>-- Seleccionar Cubierta --</option>";

                        // Consulta para obtener las cubiertas disponibles en Casanova
                        $sql_cubiertas_disponibles = "SELECT id, nombre FROM cubiertas WHERE estado = 'casanova' AND coche_id IS NULL";
                        $result_cubiertas_disponibles = $conn->query($sql_cubiertas_disponibles);

                        if ($result_cubiertas_disponibles && $result_cubiertas_disponibles->num_rows > 0) {
                            while ($row_disponible = $result_cubiertas_disponibles->fetch_assoc()) {
                                echo "<option value='" . $row_disponible["id"] . "' data-nombre='" . htmlspecialchars($row_disponible["nombre"], ENT_QUOTES) . "'>" . $row_disponible["nombre"] . "</option>";
                            }
                        }
                        echo "</select>";

                        echo "<label for='kilometraje_" . $cubierta["id"] . "'><i class='fas fa-tachometer-alt'></i> Kilometraje actual:</label>";
                        echo "<input type='number' name='kilometraje' id='kilometraje_" . $cubierta["id"] . "' min='1' value='" . $ultimo_km . "' required data-ultimo-km='" . $ultimo_km . "'>";
                        
                                                
                        echo "<input type='hidden' name='coche_id' value='" . $coche_seleccionado_id . "'>";
                        echo "<button type='submit' class='boton' name='cambiar_cubierta'><i class='fas fa-save'></i> Guardar cambio</button>";
                        echo "</form>";
                    } else {
                        // Si no hay cubierta en esta posición, mostrar mensaje
                        echo "<div class='cubierta-info'>";
                        echo "<p class='mensaje-info'><i class='fas fa-info-circle'></i> No hay cubierta asignada a esta posición</p>";
                        echo "</div>";
                    }
                    
                    echo "</div>"; // Cierre de cubierta-card
                }
                echo "</div>"; // Cierre de fila-trasera
                
                // Agregar botón para guardar todos los cambios
                echo "<div class='guardar-todos-container'>";
                echo "<button id='guardar-todos-cambios' class='boton-grande'>";
                echo "<i class='fas fa-save'></i> Guardar Todos los Cambios";
                echo "</button>";
                echo "</div>";
                
            } else {
                // Código para cuando no hay cubiertas asignadas
                echo "<div class='selector-container fade-in delay-3' style='width: 52%; margin: 0 auto;'>";
                echo "<h2><i class='fas fa-plus-circle'></i> Asignar cubiertas al coche</h2>";
                echo "<p>Este coche no tiene cubiertas asignadas. Selecciona las cubiertas para asignar:</p>";
                
                echo "<form method='POST' class='selector-form' id='form-asignar-cubiertas'>";
                echo "<input type='hidden' name='coche_id' value='" . $coche_seleccionado_id . "'>";
                
                // Obtener cubiertas disponibles en Casanova
                $sql_cubiertas_disponibles = "SELECT id, nombre FROM cubiertas WHERE estado = 'casanova' AND coche_id IS NULL";
                $result_cubiertas_disponibles = $conn->query($sql_cubiertas_disponibles);
                
                // Posiciones de cubiertas en el coche
                $posiciones = [
                    "DELANTERA CHOFER",
                    "DELANTERA PUERTA",
                    "TRASERA CHOFER AFUERA",
                    "TRASERA PUERTA AFUERA",
                    "TRASERA CHOFER ADENTRO",
                    "TRASERA PUERTA ADENTRO"
                ];
                
                if ($result_cubiertas_disponibles && $result_cubiertas_disponibles->num_rows > 0) {
                    // Convertir resultado a un array para usarlo varias veces
                    $cubiertas_disponibles = [];
                    while ($row = $result_cubiertas_disponibles->fetch_assoc()) {
                        $cubiertas_disponibles[] = $row;
                    }
                    
                    // Crear selects para cada posición
                    foreach ($posiciones as $index => $posicion) {
                        echo "<div class='form-group'>";
                        echo "<label for='cubierta_pos_" . $index . "'><i class='fas fa-arrow-alt-circle-right'></i> " . $posicion . ":</label>";
                        echo "<select name='cubiertas[" . $index . "]' id='cubierta_pos_" . $index . "' class='selector-cubiertas' data-position='" . $index . "'>";
                        echo "<option value=''>-- Seleccionar Cubierta --</option>";
                        
                        foreach ($cubiertas_disponibles as $cubierta) {
                            echo "<option value='" . $cubierta['id'] . "' data-nombre='" . htmlspecialchars($cubierta['nombre'], ENT_QUOTES) . "'>" . $cubierta['nombre'] . "</option>";
                        }
                        
                        echo "</select>";
                        echo "</div>";
                    }
                    
                    echo "<div class='form-group'>";
                    echo "<label for='kilometraje_inicial'><i class='fas fa-tachometer-alt'></i> Kilometraje Actual del Tablero:</label>";
                    echo "<input type='number' name='kilometraje_inicial' id='kilometraje_inicial' min='1' required>";
                    echo "</div>";
                    
                    echo "<button type='submit' name='asignar_cubiertas' class='boton'>";
                    echo "<i class='fas fa-link'></i> Asignar Cubiertas";
                    echo "</button>";
                } else {
                    echo "<p class='mensaje-info'><i class='fas fa-exclamation-circle'></i> No hay cubiertas disponibles para asignar. Agregue nuevas cubiertas en la sección de Gomería.</p>";
                }
                
                echo "</form>";
                echo "</div>";
            }

            echo "</div>"; // Cierre de cubiertas-container

            // IMPORTANTE: Cerrar la conexión al final
            $conn->close();
        }
        ?>

        <div>
        </div>
        <br>

        <footer class="fade-in delay-5">
            <p>&copy; <?php echo date('Y'); ?> Sistema de Gestión de Cubiertas | Empresa Casanova S.A todos los derechos reservados 2025</p>
        </footer>
    </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Obtener todas las posiciones de cubierta
        const posiciones = document.querySelectorAll('.cubierta-posicion');
        
        // Para cada posición, agregar evento de mouse
        posiciones.forEach(posicion => {
            posicion.addEventListener('mouseenter', function() {
                // Obtener el número de posición
                const position = this.getAttribute('data-position');
                
                // Activar la rueda correspondiente en el diagrama vertical
                const wheel = document.getElementById('wheel' + position);
                if (wheel) {
                    wheel.classList.add('active');
                }
            });
            
            posicion.addEventListener('mouseleave', function() {
                // Obtener el número de posición
                const position = this.getAttribute('data-position');
                
                // Desactivar la rueda correspondiente en el diagrama vertical
                const wheel = document.getElementById('wheel' + position);
                if (wheel) {
                    wheel.classList.remove('active');
                }
            });
        });
    });
    </script>
        
    <script src="index.js"></script>
    <script src="modal.js"></script>
    <script src="selectores.js"></script>

</body>
</html>