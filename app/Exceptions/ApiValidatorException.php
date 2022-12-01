<?php

namespace App\Exceptions;

use Exception;

class ApiValidatorException extends Exception
{
  const HTTP_OK = 200;
  const ERROR_MESSAGE = 'FORM_ERROR';

  protected $data;

  public function __construct($data = [], string $message = self::ERROR_MESSAGE, int $code = self::HTTP_OK)
  {
    $this->data = $data;
    parent::__construct($message, $code);
  }
  public function render()
  {
    return errorJson('FORM_PARAMS_ERROR', $this->data);
  }
}
