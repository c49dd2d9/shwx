<?php

namespace App\Exceptions;

use Exception;

class AdminApiException extends Exception
{
  const HTTP_OK = 200;

  protected $data;

  public function __construct($message, $data = [], int $code = self::HTTP_OK)
  {
    $this->data = $data;
    parent::__construct($message, $code);
  }
  public function render()
  {
    return errorJson($this->message, $this->data);
  }
}
