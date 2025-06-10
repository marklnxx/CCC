<?php
// actualizar_kilometraje_multiple_form.php
// Versión para procesar datos directamente desde un formulario (no JSON)

// Verificar que se haya enviado el formulario correctamente
if (!isset($_POST['actualizar_kilometraje_multiple']) || !isset($_POST['kilometraje']) || !is_array($_POST['kilometraje'])) {
    header("Location: index.php?error=datos_invalidos");
    exit;
}

// Obtener los datos de kilometraje
$datos = $_POST['kilometraje'];

// Conexión a la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "prueba4";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    header("Location: index.php?error=conexion&mensaje=" . urlencode($conn->connect_error));
    exit;
}

// Iniciar transacción
$conn->begin_transaction();

try {
    foreach ($datos as $coche_id => $cubiertas) {
        foreach ($cubiertas as $cubierta_id => $kilometraje) {
            // Actualizar kilometraje en historial_cubiertas
            $sql_actualizar = "UPDATE historial_cubiertas 
                              SET kilometraje_retiro = ? 
                              WHERE cubierta_id = ? AND coche_id = ? AND fecha_retiro IS NULL";
            $stmt_actualizar = $conn->prepare($sql_actualizar);
            $stmt_actualizar->bind_param("iii", $kilometraje, $cubierta_id, $coche_id);
            $stmt_actualizar->execute();
            
            // También actualizar kilometraje_diario
            $fecha_actual = date('Y-m-d');
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
                $stmt_update_km->execute();
            } else {
                // Insertar nuevo registro
                $sql_insert_km = "INSERT INTO kilometraje_diario (coche_id, fecha, kilometraje) VALUES (?, ?, ?)";
                $stmt_insert_km = $conn->prepare($sql_insert_km);
                $stmt_insert_km->bind_param("isi", $coche_id, $fecha_actual, $kilometraje);
                $stmt_insert_km->execute();
            }
        }
    }
    
    // Confirmar transacción
    $conn->commit();
    
    // Redirigir a la página principal con mensaje de éxito
    header("Location: index.php?coche_id=" . $coche_id . "&exito=1");
    
} catch (Exception $e) {
    // Revertir cambios en caso de error
    $conn->rollback();
    
    // Redirigir con mensaje de error
    header("Location: index.php?error=transaccion&mensaje=" . urlencode($e->getMessage()));
}

$conn->close();
?>