<?php
namespace App;

/**
 * DB Abstraction Layer
 * Just a primitive robust version for test-task
 *
 * @package App
 */
class DBAL
{
    private $config;

    /** @var \PDO */
    private $db;

    private $connRetries;
    private $connTimeoutMs;
    private $executeRetries;
    private $executeTimeoutMs;


    public function __construct($dbConfig)
    {
        $this->config = $dbConfig;
        $this->connRetries = $this->config["server"]["connRetries"] ?? 3;
        $this->connTimeoutMs = $this->config["server"]["connTimeoutMs"] ?? 500;
        $this->executeRetries = $this->config["server"]["executeRetries"] ?? 3;
        $this->executeTimeoutMs = $this->config["server"]["executeTimeoutMs"] ?? 100;

        $this->connect();
    }

    /**
     * Send a message to logging output
     *
     * @param string $message
     * @param string $type
     */
    private function log($message, $type = 'info')
    {
        echo $message . "\n";
    }

    /**
     * Connect to server data source
     *
     * @return \PDO
     */
    private function connect()
    {
        // Eliminate some "Warning: Error while sending QUERY packet" errors.
        $options = [
            //\PDO::MYSQL_ATTR_MAX_BUFFER_SIZE => 64*1024*1024,
            1005 => 64*1024*1024,
        ];

        $dsn = "mysql:dbname={$this->config['dbname']};host={$this->config['server']};charset={$this->config['charset']}";

        // Disconnect to be sure in status of db
        $this->db = null;

        // Try to connect again
        $connException = null;
        for ($attemptsLeft = $this->connRetries; $attemptsLeft > 0; $attemptsLeft--) {
            try {
                $this->db = new \PDO($dsn, $this->config['user'], $this->config['password'], $options);
                break;
            }
            catch (\PDOException $connException) {
                usleep($this->connTimeoutMs * 1000);
            }
        }
        if (!$this->db && $connException) {
            throw $connException;
        }

        return $this->db;
    }

    /**
     * Reconnect to server data source
     *
     * @return \PDO
     */
    private function reconnect()
    {
        $this->db = null;
        usleep($this->connTimeoutMs * 1000);

        return $this->connect();
    }

    /**
     * Execute query with several attempts if needs
     *
     * It is a little more stable that just simple single executing.
     *
     * @param string $query
     * @param array $dataBindings
     * @param array $specificDataBindingsTypes
     * @return \PDOStatement
     */
    private function executeQuery($query, $dataBindings = [], $specificDataBindingsTypes = [])
    {
        $stmt = $this->createStatement($query, $dataBindings, $specificDataBindingsTypes);
        $result = @$stmt->execute();
        if ($result) {
            // Fast exit in normal (default) case
            return $stmt;
        }

        // Attempts left between first and final attempts
        $attemptsLeft = $this->executeRetries > 2 ? $this->executeRetries : 0;

        for (; $attemptsLeft > 0; $attemptsLeft--) {
            usleep($this->executeTimeoutMs * 1000);

            $stmt = $this->createStatement($query, $dataBindings, $specificDataBindingsTypes);
            $result = @$stmt->execute();
            if ($result) break;
        }

        if (!$result) {
            $this->log("DB Problems... Try to reconnect to DB.");
            // What f**king wrong with PDO?!... Try to reconnect
            $this->reconnect();

            $stmt = $this->createStatement($query, $dataBindings, $specificDataBindingsTypes);
            $result = @$stmt->execute();
            if (!$result) {
                $this->log("Severe DB Problem. Degradation. Cant execute query \n"
                    . "ERROR=" . $stmt->errorCode() . ": " . print_r($stmt->errorInfo(), 1)
                );
                //throw new \Exception("PDO workaround totally broken!");
            }
        }

        return $stmt;
    }

    /**
     * Make new db statement by query and its optional data bindings
     *
     * @param string $query
     * @param array $dataBindings
     * @param array $specificDataBindingsTypes
     * @return \PDOStatement
     */
    private function createStatement($query, $dataBindings = [], $specificDataBindingsTypes = [])
    {
        $stmt = $this->db->prepare($query);

        foreach ($dataBindings as $bind => $value) {
            $fieldType = $specificDataBindingsTypes[$bind] ?? \PDO::PARAM_STR;
            $stmt->bindParam(":" . $bind, $dataBindings[$bind], $fieldType);
        }

        return $stmt;
    }


    public function saveEntity($tableName, $data, $specificFieldsTypes = [])
    {
        $fields = array_keys($data);

        $query = "INSERT INTO `{$tableName}` (";
        $query .= join(", ", array_map(function($field){
            return "`" . $field . "`";
        }, $fields));
        // we can do this faster:  ;)
        //foreach ($fields as $field) {
        //    $query .= "`" . join("`, `", $fields) . "`";
        //}
        $query .= ") VALUES (";
        $query .= join(", ", array_map(function($field){
            return ":" . $field . "";
        }, $fields));
        $query .= ") ON DUPLICATE KEY UPDATE ";
        $query .= join(", ", array_map(function($field){
            return "`" . $field . "` = :" . $field;
        }, $fields));

        $stmt = $this->executeQuery($query, $data, $specificFieldsTypes);

        //$rowsAffected = $stmt->rowCount();
        return $stmt;
    }

    public function dateFormat($ts)
    {
        return EthTool::getDatetime($ts);
    }

    public function jsonFormat($json)
    {
        return json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }


    public function getBlockByHash($value)
    {
        $stmt = $this->executeQuery("SELECT * FROM `blocks` WHERE `hash` = :hash", [
            "hash" => $value
        ]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function getBlocksFromToDatetime($from, $to)
    {
        $stmt = $this->executeQuery("
            SELECT * FROM `blocks`
            WHERE `create_dt` >= :from AND `create_dt` < :to
            ORDER BY `create_dt` ASC
        ", [
            "from" => $from,
            "to" => $to,
        ]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function saveBlock($data)
    {
        //$query = "INSERT INTO `blocks` (`hash`) VALUES (:hash) ON DUPLICATE KEY UPDATE `hash`=:hash";
        $blockId = $data["hash"];

        $txCount = count($data['transactions']);
        unset($data['transactions']); // too much child data to save

        $dataPrepared = [
            "hash" => $data["hash"],
            "number" => hexdec($data["number"]),
            "tx_count" => $txCount,

            "gas_used" => hexdec($data["gasUsed"]),
            "gas_limit" => hexdec($data["gasLimit"]),

            "ts" => $this->dateFormat(hexdec($data["timestamp"])),
            "json_data" => $this->jsonFormat($data),
        ];

        $stmt = $this->saveEntity("blocks", $dataPrepared, [
            "json_data" => \PDO::PARAM_LOB,
        ]);

        //$rowsAffected = $stmt->rowCount();
        //return $rowsAffected ? $blockId : false;

        return $blockId;
    }


    public function getTransactionByHash($value)
    {
        $stmt = $this->executeQuery("SELECT * FROM `transactions` WHERE `hash` = :hash", [
            "hash" => $value,
        ]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function getTransactionsByBlockHash($value)
    {
        $stmt = $this->executeQuery("
            SELECT * FROM `transactions`
            WHERE `block_hash` = :hash
            ORDER BY `transaction_index` ASC
        ", [
            "hash" => $value,
        ]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function saveTransaction($data)
    {
        $txId = $data["hash"];

        //unset($data['input']);

        $dataPrepared = [
            "hash" => $data["hash"],
            "block_hash" => $data["blockHash"],
            "block_number" => hexdec($data["blockNumber"]),
            "transaction_index" => hexdec($data["transactionIndex"]),

            "gas" => hexdec($data["gas"]),
            "gas_price" => EthTool::hex2eth($data["gasPrice"]),
            "value" => EthTool::hex2eth($data["value"]),

            "json_data" => $this->jsonFormat($data),
        ];

        $stmt = $this->saveEntity("transactions", $dataPrepared, [
            "json_data" => \PDO::PARAM_LOB,
        ]);

        return $txId;
    }

}
