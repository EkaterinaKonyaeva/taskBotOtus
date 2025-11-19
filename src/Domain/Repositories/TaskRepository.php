<?php
namespace App\Domain\Repositories;
use App\Database\Database;
class TaskRepository {
    private $pdo;
    public function __construct(){ $this->pdo = Database::connection(); }
    public function create($userId, $title, $nextRun = null, $type = 'once'){
        $stmt = $this->pdo->prepare('INSERT INTO tasks (user_id, title, next_run, schedule_type) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $title, $nextRun, $type]);
        return $this->pdo->lastInsertId();
    }
    public function getEnabledTasks(){
        $sql = 'SELECT t.*, u.telegram_id FROM tasks t JOIN users u ON t.user_id = u.id WHERE t.enabled = 1';
        return $this->pdo->query($sql)->fetchAll();
    }
    public function disable($id){
        $stmt = $this->pdo->prepare('UPDATE tasks SET enabled = 0 WHERE id = ?');
        $stmt->execute([$id]);
    }
    public function updateNextRun($id, $next){
        $stmt = $this->pdo->prepare('UPDATE tasks SET next_run = ? WHERE id = ?');
        $stmt->execute([$next, $id]);
    }
    public function all(){ return $this->pdo->query('SELECT * FROM tasks')->fetchAll(); }
}
