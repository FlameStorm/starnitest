<?php
namespace App;

use \Ratchet\Client\Connector;
use \Ratchet\Client\WebSocket as WsClient;
use \Ratchet\RFC6455\Messaging\MessageInterface as MsgInterface;
use React\EventLoop\LoopInterface;


class MyClient
{
    private $config;
    private $debugLevel;

    private $srcServerUrl;
    /** @var Request[] */
    private $wsRpcRequests = [];
    private $wsRpcReqNumber = 1;
    private $wsRpcReqAttemptsLimit;
    private $wsRpcReqAttemptsTimeoutMs;

    /** @var LoopInterface */
    private $loop;
    /** @var Connector */
    private $connector;
    /** @var WsClient */
    private $wsConnection;

    /** @var string[] */
    private $knownMethodsMap;
    /** @var string[] */
    private $knownMethodsAnswersMap;

    /** @var DBAL */
    private $dbal;

    public function __construct($config)
    {
        $this->config = $config;
        $this->debugLevel = $this->config["client"]["debug"] ?? DEBUG_NONE;
        set_time_limit($this->config["client"]["timeLimit"] ?? 90);

        $this->wsRpcReqAttemptsLimit = $this->config["client"]["wsRpcReqAttemptsLimit"] ?? 3;
        $this->wsRpcReqAttemptsTimeoutMs = $this->config["client"]["wsRpcReqAttemptsTimeoutMs"] ?? 500;

        $this->srcServerUrl = $this->config["client"]["srcServerUrl"];

        $this->knownMethodsMap = [
            "eth_subscription" => "onSubscriptionMessage",
        ];
        $this->knownMethodsAnswersMap = [
            "eth_getBlockByHash" => "onGetBlockByHashResult",
        ];

        $this->dbal = new DBAL($this->config["db"]);

        $this->runSrcServerListening();
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
     * Connect and initialize interaction with data source server
     */
    private function runSrcServerListening()
    {
        if ($this->debugLevel >= DEBUG_BASIC) {
            $this->log("Try to connect to data source server...");
        }

        $this->loop = \React\EventLoop\Factory::create();
        $reactConnector = new \React\Socket\Connector($this->loop, [
            "dns" => "8.8.8.8",
            "timeout" => 10
        ]);
        $this->connector = $wsConnector = new Connector($this->loop, $reactConnector);

        $wsConnector($this->srcServerUrl,
            [], // subprotocols
            [// extra headers
                //"Origin" => $this->config["client"]["localServerOrigin"],
                //"Connection" => "keep-alive, Upgrade",
            ]
        )->then(function(WsClient $conn) {
            if ($this->debugLevel >= DEBUG_BASIC) {
                $this->log("Connected.");
            }
            $this->wsConnection = $conn;

            // Subscribe to new block heads
            $this->makeRequest("eth_subscribe", ["newHeads"]);

            $this->wsConnection->on("message", function(MsgInterface $msg) {
                $this->parseMessage($msg);
                //$this->wsConnection->close();
            });

            $this->wsConnection->on("close", function($code = null, $reason = null) {
                if ($this->debugLevel >= DEBUG_BASIC) {
                    $this->log("Connection closed ({$code} - {$reason})");
                }

                // Restart on close
                $this->runSrcServerListening();
            });

        }, function(\Exception $e) {
            if ($this->debugLevel >= DEBUG_BASIC) {
                $this->log("EXCEPTION: {$e->getMessage()}");
            }
            if ($this->debugLevel >= DEBUG_MORE) {
                $this->log($e->getTraceAsString());
            }
            $this->loop->stop();
        });

        $this->loop->run();
    }

    /**
     * Generate a request ID for new api call
     *
     * @return string
     */
    private function generateNewRequestId()
    {
        return strval($this->wsRpcReqNumber++);
    }

    /**
     * Make request to data source server
     *
     * @param string $method
     * @param array $params
     * @return bool
     */
    public function makeRequest($method, $params = [])
    {
        $reqId = $this->generateNewRequestId();
        $request = new Request($reqId, $method, $params);

        return $this->doMakeRequest($request);
    }

    /**
     * Make same request [great] again
     *
     * todo: We need some deferred logic further
     *
     * @param Request $request
     * @return bool
     */
    public function makeRequestAgain($request)
    {
        if ($request->getAttemptsCount() >= $this->wsRpcReqAttemptsLimit) {
            if ($this->debugLevel >= DEBUG_MORE) {
                $this->log("WARNING! Request repeating not helped (" . $request->getMethod() . ").");
            }
            return false;
        }

        usleep($this->wsRpcReqAttemptsTimeoutMs * 1000);

        unset($this->wsRpcRequests[$request->getId()]);

        $reqId = $this->generateNewRequestId();
        $request->nextAttempt($reqId);

        return $this->doMakeRequest($request);
    }

    /**
     * Make request to data source server
     *
     * @param Request $request
     * @return bool
     */
    private function doMakeRequest($request)
    {
        $rpcRequestData = $request->getData();

        $this->wsRpcRequests[$request->getId()] = $request;

        $message = json_encode($rpcRequestData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->wsConnection->send($message);

        if ($this->debugLevel >= DEBUG_MORE) {
            $logMsg = "Sent: ";
            if ($request->getAttemptsCount() > 0) {
                $logMsg .= "(REP " . $request->getAttemptsCount() . ")";
            }
            if ($this->debugLevel >= DEBUG_FULL) {
                $logMsg .= print_r($rpcRequestData, 1) . "\n";
            } else {
                $logMsg .= "#" . $rpcRequestData["id"] . " (" . $rpcRequestData["method"] . ")";
            }
            $this->log($logMsg);
        }

        return true;
    }

    /**
     * Parse inbound message from data source server
     * (Format JSON RPC 2.0)
     *
     * @param string $message
     * @return bool
     * @throws \Exception
     */
    private function parseMessage($message)
    {
        $rpcData = @json_decode($message, true);
        if (!$rpcData || empty($rpcData["jsonrpc"])) {
            throw new \Exception("Invalid Json RPC 2.0 message received.");
        }

        $isAnswer = array_key_exists("result", $rpcData) && !empty($rpcData["id"]);
        $isMethodCall = !empty($rpcData["method"]);

        // Determine required method
        $method = '';
        $request = null;
        if ($isAnswer) {
            $reqId = $rpcData["id"];
            if (!array_key_exists($reqId, $this->wsRpcRequests)) {
                if ($this->debugLevel >= DEBUG_BASIC) {
                    $this->log("WARNING! Unknown request id.");
                }
                return false;
            }
            $request = &$this->wsRpcRequests[$reqId];
            $method = $request->getMethod();
        }
        elseif ($isMethodCall) {
            $method = $rpcData["method"];
        }

        // A little verbosity
        if ($this->debugLevel >= DEBUG_MORE) {
            $logMsg = "Received: ";
            if ($this->debugLevel >= DEBUG_FULL) {
                $logMsg .= mb_substr(print_r($rpcData, 1), 0, 4096) . "[...]\n";
            } else {
                $logMsg .= empty($rpcData["id"]) ? '[broadcast]' : "#" . $rpcData["id"];
                if ($isAnswer) {
                    $logMsg .= " answer for ($method)";
                    if (empty($rpcData["result"])) {
                        $logMsg .= " - is Empty";
                    }
                } elseif ($isMethodCall) {
                    $logMsg .= " ($method)";
                } else {
                    $logMsg .= ' ***UNKNOWN*** ';
                    $logMsg .= mb_substr(print_r($rpcData, 1), 0, 1024) . "[...]\n";
                }
            }
            $this->log($logMsg);
        }

        // Server answered for some request
        if ($isAnswer) {
            if (!array_key_exists($method, $this->knownMethodsAnswersMap)) {
                if ($this->debugLevel >= DEBUG_FULL) {
                    $this->log("Skip answer (has no handler).");
                }
                return false;
            }
            $handlerFunc = $this->knownMethodsAnswersMap[$method];
            return $this->$handlerFunc($rpcData["result"], $request);
        }

        // Server sent some subscription event or asked us to do smth
        if ($isMethodCall) {
            $params = $rpcData["params"] ?? [];

            if (!array_key_exists($method, $this->knownMethodsMap)) {
                if ($this->debugLevel >= DEBUG_BASIC) {
                    $this->log("WARNING! Unknown method called.");
                }
                return false;
            }
            $handlerFunc = $this->knownMethodsMap[$method];
            return $this->$handlerFunc($params);
        }

        return false;
    }

    /**
     * Deal with blockchain new blocks subscription message
     *
     * Notice: We use only single subscription, so ignore subscription-id itself for now
     *
     * @see https://infura.io/docs/ethereum/wss/eth-subscribe , "newHeads"
     * @see https://infura.io/docs/ethereum/json-rpc/eth-getBlockByNumber for transactions list
     * @param array $params
     */
    private function onSubscriptionMessage($params)
    {
        //$subscriptionId = $params["subscription"];
        $data = $params["result"];

        $blockHash = $data["hash"] ?? "";
        if (!$blockHash) {
            if ($this->debugLevel >= DEBUG_BASIC) {
                $this->log("WARNING! Block hash not found at subscription message.");
            }
            return;
        }

        // Second param of `true` means we want to get full info about all
        // of transactions of block. (prepare to big enough response message!)
        $this->makeRequest("eth_getBlockByHash", [$blockHash, true]);
    }

    /**
     * On block data received
     *
     * @param array|null $result
     * @param Request $request
     */
    private function onGetBlockByHashResult($result, $request)
    {
        // Sometimes we get an empty result on call
        if (!$result) {
            $this->makeRequestAgain($request);
            return;
        }

        // Store block itself
        $blockId = $this->dbal->saveBlock($result);

        // Store transactions
        if (!empty($result["transactions"])) {
            foreach ($result["transactions"] as $tx) {
                $txId = $this->dbal->saveTransaction($tx);
            }
        }
    }

}
