<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AdminApiException;
use App\Http\Controllers\Controller;

class OrderController extends Controller
{
    public function searchOrder(Request $request)
    {
        $validrules = [
            'orderno' => 'numeric',
        ];
        validateParams($request->only('orderno'), $validrules);
        $orderNo = $request->input('orderno') ? $request->input('orderno') : 0;
        if ($orderNo <= 0) {
            $orderList = DB::table('ds_pay_recharge')
                             ->leftJoin('ds_user_info', 'ds_pay_recharge.userid', '=', 'ds_user_info.id')
                             ->select('ds_user_info.nickname', 'ds_pay_recharge.*')
                             ->paginate(100);
        } else {
            $orderList = DB::table('ds_pay_recharge')
                             ->where('ds_pay_recharge.orderno', '=', $orderNo)
                             ->leftJoin('ds_user_info', 'ds_pay_recharge.userid', '=', 'ds_user_info.id')
                             ->select('ds_user_info.nickname', 'ds_pay_recharge.*')
                             ->paginate(100);
        }
        return successJson($orderList);
    }
    public function adminConfirmOrder($id)
    {
        $order = DB::table('ds_pay_recharge')->where('id', $id)->first();
        if (!$order) {
            throw new AdminApiException('ADMIN_CENTER_ORDER_NOT_FOUND');
        }
        if ($order->payon == 1) {
            throw new AdminApiException('ADMIN_CENTER_ORDER_PROCESSED');
        }
        $user = User::find($order->userid);
        if (!$user || $user->islock == 1) {
            throw new AdminApiException('USER_DATA_NOT_FOUND');
        }
        try {
            DB::beginTransaction();
            $user->gold = $user->gold + $order->gold;
            $user->yuepiao = $user->yuepiao + $order->yuepiao;
            $user->cuigengpiao = $user->cuigengpiao + $order->tuijian;
            $user->save();
            DB::table('ds_pay_recharge')->where('id', $order->id)->update([
                'payon' => 1,
                'message_info' => now().'管理员手动处理完成'
            ]);
            DB::commit();
            return successJson();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw new AdminApiException('DEFAULT_ERROR');
        }
    }
}
