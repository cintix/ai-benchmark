<?php

declare(strict_types=1);

namespace Modolus\Core;

use PDO;

final class BlogLedger
{
    private string $dbPath;

    public function __construct(string $dbPath)
    {
        $this->dbPath = $dbPath;
    }

    public function init(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('CREATE TABLE IF NOT EXISTS posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, body TEXT NOT NULL, created_at TEXT NOT NULL)');
        $count = (int) $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn();
        if ($count === 0) {
            $stmt = $pdo->prepare('INSERT INTO posts(title, body, created_at) VALUES(:title, :body, :created_at)');
            $seed = [
                ['title' => 'Hello Modolus', 'body' => 'Building event-first systems in native PHP.', 'created_at' => '2026-04-01'],
                ['title' => 'Stateless Notes', 'body' => 'Modules communicate through signals only.', 'created_at' => '2026-04-02'],
            ];
            foreach ($seed as $row) {
                $stmt->execute($row);
            }
        }
    }

    public function allPosts(): array
    {
        $pdo = $this->pdo();
        $rows = $pdo->query('SELECT id, title, body, created_at FROM posts ORDER BY id DESC');
        return $rows ? $rows->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function addPost(string $title, string $body, string $createdAt): void
    {
        $pdo = $this->pdo();
        $stmt = $pdo->prepare('INSERT INTO posts(title, body, created_at) VALUES(:title, :body, :created_at)');
        $stmt->execute(['title' => $title, 'body' => $body, 'created_at' => $createdAt]);
    }

    private function pdo(): PDO
    {
        $dir = dirname($this->dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $pdo = new PDO('sqlite:' . $this->dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }
}
