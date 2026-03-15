<?php
class Database {
    private $host = "localhost";
    private $user = "root";
    private $pass = "";
    private $db   = "alazon";
    private $con;

    public function __construct() {
        $this->con = mysqli_connect($this->host, $this->user, $this->pass, $this->db);
        
        if (!$this->con) {
            die("Error de conexión: " . mysqli_connect_error());
        }
    }

    // Este es el método que te falta:
    public function getCon() {
        return $this->con;
    }

    public function sanitize($data) {
        return mysqli_real_escape_string($this->con, $data);
    }
}
?>