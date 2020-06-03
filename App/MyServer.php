<?php

namespace App;

use PHPSocketIO\Socket;
use Workerman\Lib\Timer;
use Workerman\Worker;
use PHPSocketIO\SocketIO;

class MyServer
{
    private $config;
    private $debugLevel;

    private $broadcastChannel = "EITHER";
    private $broadcastInterval;
    private $broadcastLastDateTime;
    /** @var SocketIO */
    private $socketIo;
    /** @var array */
    private $usernames;

    /** @var DBAL */
    private $dbal;


    public function __construct($config)
    {
        $this->config = $config;
        $this->debugLevel = $this->config["server"]["debug"] ?? DEBUG_NONE;
        set_time_limit($this->config["server"]["timeLimit"] ?? 0);

        $this->broadcastInterval = $this->config['broadcastInterval'] ?? 10;
        $this->broadcastLastDateTime = $this->getBroadcastDatetimeNew();
        $this->usernames = [];

        $this->dbal = new DBAL($this->config["db"]);

        $this->run();
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
     * Run server
     */
    private function run()
    {
        $io = new SocketIO($this->config["server"]["port"]);
        $this->socketIo = $io;

        $this->socketIo->on("workerStart", function() {
            $timerId = Timer::add($this->broadcastInterval,
                function() {
                    // Let think about it as of atomic exchange
                    // Also we use seconds approximation
                    $bcDatetimeFrom = $this->broadcastLastDateTime;
                    $bcDatetimeTo = $this->getBroadcastDatetimeNew();
                    if ($bcDatetimeFrom === $bcDatetimeTo) {
                        // Zero seconds interval - just skip it
                        return;
                    }
                    $this->broadcastLastDateTime = $bcDatetimeTo;


                    // New blocks broadcast

                    $blocks = $this->dbal->getBlocksFromToDatetime($bcDatetimeFrom, $bcDatetimeTo);
                    $blocksCount = count($blocks);

                    if ($this->debugLevel >= DEBUG_BASIC) {
                        if ($blocksCount > 0) {
                            $this->log("$bcDatetimeFrom - $bcDatetimeTo: cnt = $blocksCount");
                        }
                    }

                    if (!empty($blocks)) {
                        foreach ($blocks as &$block) {
                            unset($block['json_data']);
                        }
                        $this->socketIo->to($this->broadcastChannel)->emit("new blocks", [
                            "blocks" => $blocks,
                        ]);
                    }


                    // New transactions broadcast

                    if (!empty($blocks)) {
                        foreach ($blocks as $block) {
                            $txs = $this->dbal->getTransactionsByBlockHash($block['hash']);
                            if (empty($txs)) {
                                continue;
                            }
                            foreach ($txs as &$tx) {
                                unset($tx['json_data']);
                            }
                            $this->socketIo->to($this->broadcastChannel)->emit("new transactions", [
                                "transactions" => $txs,
                            ]);
                        }
                    }

                }
            );
        });

        $this->socketIo->on("connection", function ($socket) {
            /** @var Socket $socket */
            $socket->join($this->broadcastChannel);

            $socket->addedUser = false;
            // when the client emits "new message", this listens and executes
            $socket->on("new message", function ($data) use ($socket) {
                // we tell the client to execute "new message"
                $socket->broadcast->emit("new message", array(
                    "username" => $socket->username,
                    "message" => $data
                ));
            });

            // when the client emits "add user", this listens and executes
            $socket->on("add user", function ($username) use ($socket) {
                // we store the username in the socket session for this client
                $socket->username = $username;
                // add the client"s username to the global list
                $this->usernames[$username] = $username;
                $numUsers = count($this->usernames);
                $socket->addedUser = true;
                $socket->emit("login", array(
                    "numUsers" => $numUsers
                ));
                // echo globally (all clients) that a person has connected
                $socket->broadcast->emit("user joined", array(
                    "username" => $socket->username,
                    "numUsers" => $numUsers
                ));
            });

            // when the client emits "typing", we broadcast it to others
            $socket->on("typing", function () use ($socket) {
                $socket->broadcast->emit("typing", array(
                    "username" => $socket->username
                ));
            });

            // when the client emits "stop typing", we broadcast it to others
            $socket->on("stop typing", function () use ($socket) {
                $socket->broadcast->emit("stop typing", array(
                    "username" => $socket->username
                ));
            });

            // when the user disconnects.. perform this
            $socket->on("disconnect", function () use ($socket) {
                // remove the username from global usernames list
                if ($socket->addedUser) {
                    unset($this->usernames[$socket->username]);
                    $numUsers = count($this->usernames);

                    // echo globally that this client has left
                    $socket->broadcast->emit("user left", array(
                        "username" => $socket->username,
                        "numUsers" => $numUsers
                    ));
                }
            });

        });

        Worker::runAll();

    }

    /**
     * Get new time (accordingly to now) for the next broadcasting loop
     *
     * @return string
     */
    public function getBroadcastDatetimeNew()
    {
        //$ethDatetime = EthTool::getDatetime();
        $timeCommitmentLag = 1; //sec
        return date('Y-m-d H:i:s', time() - $timeCommitmentLag);
    }

}
