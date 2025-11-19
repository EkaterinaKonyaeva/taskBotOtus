<?php
namespace App\Domain\Repositories;
use App\Database\Database;
class UserRepository {
    private $pdo;
    public function __construct(){ $this->pdo = Database::connection(); }
    public function findByTelegramId($tgId){
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE telegram_id = ?');
        $stmt->execute([$tgId]);
        return $stmt->fetch();
    }
    public function find($id){
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    public function createFromTelegram($tgId, $username = null){
        $stmt = $this->pdo->prepare('INSERT INTO users (telegram_id, username) VALUES (?, ?)');
        $stmt->execute([$tgId, $username]);
        return $this->pdo->lastInsertId();
    }
    public function getAll(){
        return $this->pdo->query('SELECT * FROM users')->fetchAll();
    }
}
