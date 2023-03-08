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
     * ��������� ������������� �� CSV �����
     * @param string $file ���� � csv �����
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
     * ��������� ����� ��������
     * @param string $name ���
     * @param string $template ������ ���������
     */
    public function addMailingList(string $name, string $template): void
    {
        $stmt = $this->conn->prepare("INSERT IGNORE INTO mailing_lists (name, template, last_sent, finished) VALUES (:name, :template, '', 0)");
        $res = $stmt->execute(['name' => $name, 'template' => $template]);
    }
    
    /**
     * ��������� ��������
     * @param string $name ��� ��������, ������� ���������� ��������
     * @throws ErrorException
     */
    public function startMailingList(string $name): void
    {
        $mlStmt = $this->conn->prepare('SELECT * FROM mailing_lists WHERE name = :name');
        
        $mlStmt->execute(['name' => $name]);
        $mailingList = $mlStmt->fetch(PDO::FETCH_ASSOC);
        
        // �������� �� �������
        if (!$mailingList) {
            throw new ErrorException('Mailing list not found.');
        }
        
        // �������� ��� ���������
        if ($mailingList['finished'] == 1) {
            throw new ErrorException('This mailing list already sent.');
        }
        
        // �������� ������ ������������� �� ����������� �������,
        // ������� � ���������� ������������� (��� ����, ���� ������ �� ������������)
        $usersStmt = $this->conn->prepare('SELECT * FROM users WHERE number > :last ORDER BY number');
        $usersStmt->execute(['last' => $mailingList['last_sent']]);
        
        $updateStmt = $this->conn->prepare('UPDATE mailing_lists SET last_sent = :number, finished = :finished WHERE name = :name');
        
        while ($user = $usersStmt->fetch(PDO::FETCH_ASSOC)) {
            $text = preg_replace('/%name%/', $user['name'], $mailingList['template']);
            $this->sendMessage($user['number'], $text);
            // ����� � �� ��������� �����, �� ������� ���� ���������� ���������
            $updateStmt->execute(['number' => $user['number'], 'finished' => 0, 'name' => $mailingList['name']]);
        }
        
        // �������� �������� ��� ������������
        $updateStmt->execute(['number' => '', 'finished' => 1, 'name' => $mailingList['name']]);
    }
    
    /**
     * ���������� ��������� � �������
     * @param string $number �����
     * @param string $text ����� ���������
     */
    private function sendMessage(string $number, string $text): void
    {
        // ���-��������, ������ �������� ��������
        echo "To $number: $text\n";
    }
    
    /**
     * ����������� � MySQL
     */
    private function connectToDB(): void
    {
        // ��, ��-�������� ���� �������� ����������� �������� � ��������� �����, � ��������� � ������
        // �� ��� �� ��������, � ���� ����� ����� ���
        $dsn = 'mysql:host=localhost;dbname=test';
        $user = 'test';
        $pass = 'password';
        $dbh = new PDO($dsn, $user, $pass);
        
        $this->conn = $dbh;
    }
}

