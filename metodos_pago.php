<?php
// metodos_pago.php
class MetodosPago {
    private $con;
    
    public function __construct($conexion) {
        $this->con = $conexion;
    }
    
    /**
     * Obtener métodos de pago disponibles para un país
     */
    public function obtenerParaPais($pais_codigo) {
        $sql = "SELECT mp.* 
                FROM metodos_pago mp
                LEFT JOIN metodos_pago_paises mpp ON mp.id = mpp.metodo_pago_id AND mpp.pais_codigo = '$pais_codigo'
                WHERE mp.activo = 1
                AND (mpp.activo = 1 OR mpp.id IS NULL) -- Si no hay registro, está disponible
                ORDER BY mp.orden";
        
        $result = mysqli_query($this->con, $sql);
        $metodos = [];
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $metodos[] = $row;
            }
        }
        
        return $metodos;
    }
    
    /**
     * Obtener todos los métodos de pago (para administración)
     */
    public function obtenerTodos() {
        $sql = "SELECT mp.*, 
                (SELECT COUNT(*) FROM metodos_pago_paises WHERE metodo_pago_id = mp.id) as paises_asignados
                FROM metodos_pago mp
                ORDER BY mp.orden";
        
        $result = mysqli_query($this->con, $sql);
        $metodos = [];
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $metodos[] = $row;
            }
        }
        
        return $metodos;
    }
    
    /**
     * Obtener información de un método específico
     */
    public function obtenerPorId($id) {
        $id = intval($id);
        $sql = "SELECT * FROM metodos_pago WHERE id = $id";
        $result = mysqli_query($this->con, $sql);
        
        if ($result && mysqli_num_rows($result) > 0) {
            return mysqli_fetch_assoc($result);
        }
        
        return null;
    }
    
    /**
     * Obtener países donde está disponible un método
     */
    public function obtenerPaisesDisponibles($metodo_pago_id) {
        $metodo_pago_id = intval($metodo_pago_id);
        $sql = "SELECT p.*, mpp.activo 
                FROM paises p
                LEFT JOIN metodos_pago_paises mpp ON p.codigo = mpp.pais_codigo AND mpp.metodo_pago_id = $metodo_pago_id
                ORDER BY p.nombre";
        
        $result = mysqli_query($this->con, $sql);
        $paises = [];
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $paises[] = $row;
            }
        }
        
        return $paises;
    }
    
    /**
     * Actualizar disponibilidad por país
     */
    public function actualizarDisponibilidad($metodo_pago_id, $pais_codigo, $activo) {
        $metodo_pago_id = intval($metodo_pago_id);
        $pais_codigo = mysqli_real_escape_string($this->con, $pais_codigo);
        $activo = $activo ? 1 : 0;
        
        if ($activo) {
            $sql = "INSERT INTO metodos_pago_paises (metodo_pago_id, pais_codigo, activo) 
                    VALUES ($metodo_pago_id, '$pais_codigo', 1)
                    ON DUPLICATE KEY UPDATE activo = 1";
        } else {
            $sql = "DELETE FROM metodos_pago_paises 
                    WHERE metodo_pago_id = $metodo_pago_id AND pais_codigo = '$pais_codigo'";
        }
        
        return mysqli_query($this->con, $sql);
    }
}
?>