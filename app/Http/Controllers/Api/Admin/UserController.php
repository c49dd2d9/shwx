<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AdminApiException;
use App\Http\Controllers\Controller;

class UserController extends Controller
{
    public function resetUserPassword(Request $request)
    {
        $validrules = [
            'userid' => 'required|numeric',
        ];
        validateParams($request->all(), $validrules);
        $user = User::find($request->input('userid'));
        if (!$user) {
            throw new AdminApiException('USER_DATA_NOT_FOUND');
        }
        $userPassword = rand(100000, 9999999);
        $newPassword = generatePassword($userPassword, '', true);
        $user->password = $newPassword['password'];
        $user->passsalt = $newPassword['salt'];
        $user->save();
        return successJson($userPassword);
    }
    public function changeUserInfo(Request $request)
    {
        $validrules = [
            'userid' => 'required|numeric',
            'nickname' => 'required|max:15',
            'email' => 'required',
            'phone' => 'required',
            'islock' => 'required|boolean',
        ];
        validateParams($request->all(), $validrules);
        $changeNicknameBool = false;
        $user = User::find($request->input('userid'));
        if (!$user) {
            throw new AdminApiException('USER_DATA_NOT_FOUND');
        }
        if ($request->input('nickname') != $user->nickname) {
            $nicknameCheck  = DB::table('ds_user_info')->where('nickname', $request->input('nickname'))->first();
            if ($nicknameCheck) {
                throw new AdminApiException('USER_INFO_CHANGE_FAILED');
            }
            $changeNicknameBool = true;
        }
        if ($request->input('phone') != $user->phone) {
            $phoneCheck = DB::table('ds_user_info')->where('phone', $request->input('phone'))->first();
            if ($phoneCheck) {
                throw new AdminApiException('USER_INFO_CHANGE_FAILED'); 
            }
        }
        if ($request->input('email') != $user->email) {
            $emailCheck = DB::table('ds_user_info')->where('email', $request->input('email'))->first();
            if ($emailCheck) {
                throw new AdminApiException('USER_INFO_CHANGE_FAILED'); 
            }
        }
        try {
            DB::beginTransaction();
            $user->nickname = $request->input('nickname');
            $user->email = $request->input('email');
            $user->phone = $request->input('phone');
            $user->islock = $request->input('islock') == true ? 1 : 0;
            $user->save();
            if ($changeNicknameBool) {
                DB::table('ds_book_info')->where('userid', $user->id)->update([
                    'writername' => $request->input('nickname'),
                ]);
                DB::table('ds_book_comment')->where('userid', $user->id)->update([
                    'nickname' => $request->input('nickname')
                ]);
            }
            DB::commit();
            return successJson();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw new AdminApiException('DATA_UPDATE_FAILED');
        }
        
    }
    /**
     * @router /admin/change/user/gold
     * @params { userid, gold, incr_type, reason }
     * @response BaseResponse
     * @name 修改用户山海币
     */
    public function changeUserGold(Request $request)
    {
        $validrules = [
            'userid' => 'required|numeric',
            'gold' => 'required|min:1|max:6000|numeric',
            'incr_type' => 'required|boolean',
            'reason' => 'required|max:30'
        ];
        validateParams($request->all(), $validrules);
        $user = DB::table('ds_user_info')->where('id', $request->input('userid'))->first();
        $gold = $request->input('gold');
        if (!$user) {
            throw new AdminApiException('USER_DATA_NOT_FOUND');
        }
        try {
            DB::beginTransaction();
            if ($request->input('incr_type') == true) {
                // 如果是增加
                DB::table('ds_user_info')->where('id', $user->id)->increment('gold', $gold);
            } else {
                // 否则为减少
                DB::table('ds_user_info')->where('id', $user->id)->decrement('gold', $gold);
            }
            DB::table('ds_pay_recharge')->insert([
                'userid' => $user->id,
                'bookid' => 0,
                'chapterid' => 0,
                'cztype' => 0,
                'paymoney' => 0,
                'paytype' => 1,
                'orderno' => generateOrderId($user->id, 1),
                'gold' => $request->input('incr_type') != true ? $gold *= -1 : $gold,
                'payon' => 1,
                'message_info' => '站内管理员转账操作(理由为:'.$request->input('reason').')'
            ]);
            DB::commit();
            return successJson();
        } catch (\Throwable $th) {
            DB::rollBack();
            return errorJson();
        }
    }
    public function changeAuthorInfo(Request $request)
    {

    }
    public function getAuthorInfo(Request $request)
    {
        $validrules = [
            'userid' => 'required|numeric',
            'type' => 'required',
        ];
        $allowTypeList = [ 'get_author_info', 'get_author_bank' ];
        if (!in_array($request->input('type'), $allowTypeList)) {
            throw new AdminApiException('CHANGE_AUTHOR_INFO_MODULE_NAME_DENY_ALLOW');
        }
        switch ($request->input('type')) {
            case 'get_author_info':
                $authorInfo = DB::table('ds_user_authorinfo')->where('userid', $request->input('userid'))->first();
                break;
            
            default:
                $authorInfo = DB::table('ds_user_bankinfo')->where('userid', $request->input('userid'))->first();
                break;
        }
        if (!$authorInfo) {
            throw new AdminApiException('USER_DATA_NOT_FOUND');
        }
        $data = [
            'type' => $request->input('type'),
            'authorinfo' => $authorInfo
        ];
        return successJson($data);
    }
}
