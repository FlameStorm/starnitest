<?php
namespace App;

/**
 * Simple JSON RPC 2.0 Request class
 * @package App
 */
class Request
{
    private $id;
    private $method;
    private $params;

    /** @var array|null */
    private $result;

    private $attemptsCount;

    /**
     * @param string $id
     * @param string $method
     * @param array $params
     */
    public function __construct($id = '', $method = '', $params = [])
    {
        $this->id = $id;
        $this->method = $method;
        $this->params = $params;

        $this->attemptsCount = 0;
    }

    public function getData()
    {
        return [
            "jsonrpc" => "2.0",
            "id" => $this->id,
            "method" => $this->method,
            "params" => $this->params,
        ];
    }

    public function getAttemptsCount()
    {
        return $this->attemptsCount;
    }

    /**
     * Call this when this request result was wrong and you need to make same request again
     *
     * @param string $newId
     * @return int
     */
    public function nextAttempt($newId = '')
    {
        if ($newId) {
            $this->id = $newId;
        }
        $this->attemptsCount++;
        return $this->attemptsCount;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function setMethod($value)
    {
        $this->method = $value;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function setParams($value)
    {
        $this->method = $value;
    }

    public function getResult()
    {
        return $this->result;
    }

    public function setResult($data)
    {
        $this->result = $data;
    }

}
