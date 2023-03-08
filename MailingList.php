<?php 

class MailingList
{
    private PDO $conn;
    
    public function __construct()
    {
        $this->connectToDB();
        
        if (!$this->conn) {
            throw new PDOException('No connection.');
        }
        
    }
    
    /**
     * Загружает пользователей из CSV файла
     * @param string $file путь к csv файлу
     * @throws Exception
     */
    public function loadUsers(string $file): void
    {
        if (!($ch = fopen($file, 'r'))) {
            throw new Exception('File not found.');
        }
        
        $stmt = $this->conn->prepare('INSERT INTO users (number, name) VALUES (:number, :name) ON DUPLICATE KEY UPDATE name = :name2');
        
        while ($data = fgetcsv($ch)) {
            $number = trim($data[0]);
            $name = trim($data[1]);
            $res = $stmt->execute(['number' => $number, 'name' => $name, 'name2' => $name]);
            if (!$res) {
                // 
                null;
            }
        }
    }
    
    /**
     * Добавляет новую рассылку
     * @param string $name имя
     * @param string $template шаблон сообщения
     */
    public function addMailingList(string $name, string $template): void
    {
        $stmt = $this->conn->prepare("INSERT IGNORE INTO mailing_lists (name, template, last_sent, finished) VALUES (:name, :template, '', 0)");
        $res = $stmt->execute(['name' => $name, 'template' => $template]);
    }
    
    /**
     * Запускает рассылку
     * @param string $name имя рассылки, которую необходимо запусить
     * @throws ErrorException
     */
    public function startMailingList(string $name): void
    {
        $mlStmt = $this->conn->prepare('SELECT * FROM mailing_lists WHERE name = :name');
        
        $mlStmt->execute(['name' => $name]);
        $mailingList = $mlStmt->fetch(PDO::FETCH_ASSOC);
        
        // рассылка не найдена
        if (!$mailingList) {
            throw new ErrorException('Mailing list not found.');
        }
        
        // рассылка уже завершена
        if ($mailingList['finished'] == 1) {
            throw new ErrorException('This mailing list already sent.');
        }
        
        // получаем список пользователей по возрастанию номеров,
        // начиная с последнего отправленного (или весь, если ничего не отправлялось)
        $usersStmt = $this->conn->prepare('SELECT * FROM users WHERE number > :last ORDER BY number');
        $usersStmt->execute(['last' => $mailingList['last_sent']]);
        
        $updateStmt = $this->conn->prepare('UPDATE mailing_lists SET last_sent = :number, finished = :finished WHERE name = :name');
        
        while ($user = $usersStmt->fetch(PDO::FETCH_ASSOC)) {
            $text = preg_replace('/%name%/', $user['name'], $mailingList['template']);
            $this->sendMessage($user['number'], $text);
            // пишем в БД последний номер, на который было отправлено сообщение
            $updateStmt->execute(['number' => $user['number'], 'finished' => 0, 'name' => $mailingList['name']]);
        }
        
        // помечаем рассылку как отправленную
        $updateStmt->execute(['number' => '', 'finished' => 1, 'name' => $mailingList['name']]);
    }
    
    /**
     * Отправляем сообщение в очередь
     * @param string $number номер
     * @param string $text текст сообщения
     */
    private function sendMessage(string $number, string $text): void
    {
        // код-заглушка, вместо реальной отправки
        echo "To $number: $text\n";
    }
    
    /**
     * Подключение к MySQL
     */
    private function connectToDB(): void
    {
        // да, по-хорошему надо создание подключения выносить в отдельный класс, а параметры в конфиг
        // но это всё наживное, а пока пусть будет так
        $dsn = 'mysql:host=localhost;dbname=test';
        $user = 'test';
        $pass = 'password';
        $dbh = new PDO($dsn, $user, $pass);
        
        $this->conn = $dbh;
    }
}

