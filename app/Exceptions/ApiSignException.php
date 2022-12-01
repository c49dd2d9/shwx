<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class ApiSignException extends Exception
{
  const HTTP_OK = 200;

  protected $sign;

  protected $true_sign;

  public function __construct($message, $sign, $true_sign, int $code = self::HTTP_OK)
  {
    $this->sign = $sign;
    $this->true_sign = $true_sign;
    parent::__construct($message, $code);
  }
  public function render()
  {
    Log::info($this->message.$this->sign.$this->true_sign);
    return errorJson('SIGN_ERROR');
  }
}
