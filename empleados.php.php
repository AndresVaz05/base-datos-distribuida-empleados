<?php
// ==========================================
// CONFIGURACIÓN DE LA BASE DE DATOS (MAESTRO)
// ==========================================
$host = '127.0.0.1';      // Localhost porque el servidor web y MySQL están en la misma máquina (Juan)
$user = 'root';
$pass = 'jcsl28034';      // Cambia si la contraseña es diferente
$db   = 'db_distribuida';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// ==========================================
// MANEJAR PETICIONES AJAX
// ==========================================

// 1. Listar empleados (devuelve JSON)
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
    if (isset($_GET['action']) && $_GET['action'] == 'listar') {
        $result = $conn->query("SELECT * FROM empleados ORDER BY id");
        $empleados = [];
        while ($row = $result->fetch_assoc()) {
            $empleados[] = $row;
        }
        header('Content-Type: application/json');
        echo json_encode($empleados);
        $conn->close();
        exit;
    }

    // 2. Insertar empleado (vía POST)
    if (isset($_POST['action']) && $_POST['action'] == 'insertar') {
        $nombre = $_POST['nombre'];
        $puesto = $_POST['puesto'];
        $salario = $_POST['salario'];
        $fecha = $_POST['fecha'];
        $email = $_POST['email'];
        $telefono = $_POST['telefono'];
        $stmt = $conn->prepare("INSERT INTO empleados (nombre, puesto, salario, fecha_contratacion, email, telefono) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdsss", $nombre, $puesto, $salario, $fecha, $email, $telefono);
        $success = $stmt->execute();
        $stmt->close();
        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
        $conn->close();
        exit;
    }

    // 3. Eliminar empleado (vía POST)
    if (isset($_POST['action']) && $_POST['action'] == 'eliminar' && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM empleados WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
        $conn->close();
        exit;
    }
}

// ==========================================
// SI NO ES AJAX, MOSTRAR LA PÁGINA HTML
// ==========================================
$result = $conn->query("SELECT * FROM empleados ORDER BY id");
$empleados = [];
while ($row = $result->fetch_assoc()) {
    $empleados[] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Empleados - Empresa Tech</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        form div { margin-bottom: 8px; }
        label { display: inline-block; width: 150px; }
        .mensaje { margin-top: 10px; padding: 8px; background: #d4edda; color: #155724; border-radius: 4px; display: none; }
        button.eliminar { background-color: #f44336; color: white; border: none; padding: 4px 8px; cursor: pointer; border-radius: 4px; }
        button.eliminar:hover { background-color: #d32f2f; }
    </style>
</head>
<body>
    <h1>Gestión de Empleados</h1>
    
    <h2>Agregar nuevo empleado</h2>
    <form id="formEmpleado">
        <div><label>Nombre:</label> <input type="text" name="nombre" required></div>
        <div><label>Puesto:</label> <input type="text" name="puesto"></div>
        <div><label>Salario:</label> <input type="number" step="0.01" name="salario"></div>
        <div><label>Fecha contratación:</label> <input type="date" name="fecha"></div>
        <div><label>Email:</label> <input type="email" name="email"></div>
        <div><label>Teléfono:</label> <input type="text" name="telefono"></div>
        <button type="submit">Guardar</button>
    </form>
    <div id="mensaje" class="mensaje"></div>

    <h2>Lista de empleados (actualiza cada 5 segundos)</h2>
    <table id="tablaEmpleados">
        <thead>
            <tr><th>ID</th><th>Nombre</th><th>Puesto</th><th>Salario</th><th>Fecha contratación</th><th>Email</th><th>Teléfono</th><th>Acciones</th></tr>
        </thead>
        <tbody id="cuerpoTabla">
            <!-- se llenará con JavaScript -->
        </tbody>
    </table>

    <script>
        // Función para mostrar mensajes temporales
        function mostrarMensaje(texto, esError = false) {
            const msgDiv = document.getElementById('mensaje');
            msgDiv.style.backgroundColor = esError ? '#f8d7da' : '#d4edda';
            msgDiv.style.color = esError ? '#721c24' : '#155724';
            msgDiv.innerText = texto;
            msgDiv.style.display = 'block';
            setTimeout(() => { msgDiv.style.display = 'none'; }, 3000);
        }

        // Función para escapar HTML (seguridad)
        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }

        // Cargar empleados desde el servidor y pintar la tabla
        function cargarEmpleados() {
            fetch('empleados.php?action=listar', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                let html = '';
                data.forEach(emp => {
                    html += `<tr>
                        <td>${emp.id}</td>
                        <td>${escapeHtml(emp.nombre)}</td>
                        <td>${escapeHtml(emp.puesto)}</td>
                        <td>${emp.salario}</td>
                        <td>${emp.fecha_contratacion}</td>
                        <td>${escapeHtml(emp.email)}</td>
                        <td>${escapeHtml(emp.telefono)}</td>
                        <td><button class="eliminar" data-id="${emp.id}">Eliminar</button></td>
                    </tr>`;
                });
                document.getElementById('cuerpoTabla').innerHTML = html;

                // Asignar evento a cada botón eliminar
                document.querySelectorAll('.eliminar').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        if (confirm('¿Eliminar este empleado?')) {
                            eliminarEmpleado(id);
                        }
                    });
                });
            })
            .catch(error => console.error('Error al cargar:', error));
        }

        // Eliminar empleado vía AJAX
        function eliminarEmpleado(id) {
            const formData = new FormData();
            formData.append('action', 'eliminar');
            formData.append('id', id);
            fetch('empleados.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarMensaje('Empleado eliminado correctamente');
                    cargarEmpleados(); // recargar la tabla
                } else {
                    mostrarMensaje('Error al eliminar', true);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarMensaje('Error de conexión', true);
            });
        }

        // Insertar empleado vía AJAX
        document.getElementById('formEmpleado').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'insertar');
            fetch('empleados.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarMensaje('Empleado agregado correctamente');
                    this.reset(); // limpiar formulario
                    cargarEmpleados(); // recargar tabla
                } else {
                    mostrarMensaje('Error al insertar', true);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarMensaje('Error de conexión', true);
            });
        });

        // Cargar empleados al inicio y cada 5 segundos
        cargarEmpleados();
        setInterval(cargarEmpleados, 5000);
    </script>
</body>
</html>