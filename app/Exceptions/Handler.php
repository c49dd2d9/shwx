<?php

namespace App\Exceptions;

use Exception;
// use Illuminate\Routing\Router;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        return parent::render($request, $exception);
        /**
         * 统一错误返回格式 { code: Int, message: String, data: Any }
         * 
         * @ \App\Exceptions\ApiValidatorException Api验证异常类,错误统一返回 FORM_PARAMS_ERROR, 此 Exception 类由 validateParams() 控制
         * 
         * @ \App\Exceptions\AdminApiException Admin Api 异常错误类 
         * 
         * AdminApiException(errorCode:String, data:Any)
         * 
         * 其它错误使用 errorJson(errorCode:String, data:Any) 函数返回
         * 
         * errorCode 需先通过 \config\responsecode.php 定义, data可在需要时再传入数据使用，其它时候可以忽略
         * 
         * 服务器错误(500)，会统一返回 DEFAULT_ERROR 以及生成一串带有uuid的日志，uuid在data中返回
         */
        if ($exception instanceof ApiValidatorException) { 
            return $exception->render();
        }
        if ($exception instanceof AdminApiException) {
            return $exception->render();
        }
        if ($exception instanceof ApiPayException) {
            return $exception->render();
        }
        if ($exception instanceof ShanHaiCPException) {
            return $exception->render();
        }
        if ($request->has('password')) {
            $params = '********'; // 如果信息中含有password的字段的话， 则不会记录所有传递过来的参数
        } else {
            $params = $request->all();
        }
        $errorId = generateErrorLog('系统错误', $exception->getMessage(), $params, $request->path());
        return errorJson('DEFAULT_ERROR', $errorId);
    }
}
