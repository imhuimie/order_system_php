<?php
class Database {
    private $host = 'localhost';
    private $user = 'dbname';
    private $pass = 'yourpassword';
    private $dbname = 'dbname';
    
    public function getConnection() {
        try {
            $conn = new PDO("mysql:host={$this->host};dbname={$this->dbname}", $this->user, $this->pass);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $conn;
        } catch(PDOException $e) {
            die("连接失败: " . $e->getMessage());
        }
    }
}
?>