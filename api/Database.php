<?php

class Database {
    private $host = 'localhost';
    private $username = 'root';
    private $senha = '';
    private $banco = 'educaletra';

    public $conn;

    public function conectar() {
        $this->conn = new mysqli($this->host, $this->username, $this->senha, $this->banco);

        if ($this->conn->connect_error) {
            die("Algo deu errado na conexão: " . $this->conn->connect_error);
        }

        $this->conn->set_charset("utf8mb4");
        return $this->conn;
    }
}

?>