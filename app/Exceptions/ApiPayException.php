<?php

namespace App\Exceptions;

use Exception;

class ApiPayException extends Exception
{
  const HTTP_OK = 200;

  protected $money;

  protected $aisle;

  public function __construct($message, $money, $aisle, int $code = self::HTTP_OK)
  {
    $this->money = $money;
    $this->aisle = $aisle;
    parent::__construct($message, $code);
  }
  public function render()
  {
    $errorId = generatePayErrorLog($this->message, $this->money, $this->aisle);
    return errorJson($this->message, $errorId);
  }
}
