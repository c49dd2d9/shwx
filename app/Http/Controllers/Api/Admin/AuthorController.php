<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Exceptions\AdminApiException;
use App\Http\Controllers\Controller;

class AuthorController extends Controller
{
    public function getAuthorList()
    {
        $page = isset($_GET['page']) ? $_GET['page'] : 1;
        $list = Cache::remember('admin:get:author:list_'.$page, 1800, function() {
            return DB::table('ds_user_authorinfo')->leftJoin('ds_user_info', 'ds_user_authorinfo.userid', '=', 'ds_user_info.id')->select('ds_user_authorinfo.id', 'ds_user_authorinfo.userid', 'ds_user_authorinfo.name', 'ds_user_authorinfo.qq', 'ds_user_authorinfo.idcard', 'ds_user_authorinfo.address', 'ds_user_info.nickname')->paginate(50);
        });
        return successJson($list);
    }
    public function getAuthorInfo(Request $request)
    {
        $validrules = [
            'userid' => 'required|numeric'
        ];
        validateParams($request->only('userid'), $validrules);
        $authorInfo = DB::table('ds_user_authorinfo')->where('userid', $request->input('userid'))->first();
        $authorBankInfo = DB::table('ds_user_bankinfo')->where('userid', $request->input('userid'))->first();
        $authorData = [
            'author_info' => $authorInfo,
            'author_bank_info' => $authorBankInfo,
        ];
        if (!$authorInfo || !$authorBankInfo) {
            throw new AdminApiException('AUTHORINFO_CANNOT_FOUND');
        }
        return successJson($authorData);
    }
    public function changeAuthorInfo(Request $request)
    {
        $validrules = [
            'author_info_id' => 'required|numeric',
            'author_bank_info_id' => 'required|numeric',
            'name' => 'required',
            'qq' => 'required',
            'idcard' => 'required',
            'address' => 'required',
            'contactphone' => 'required|numeric',
            'bankuser' => 'required',
            'bankname' => 'required',
            'bankaccount' => 'required'
        ];
        $authorInfo = DB::table('ds_user_authorinfo')->where('id', $request->input('author_info_id'))->first();
        if (!$authorInfo) {
            throw new AdminApiException('AUTHORINFO_CANNOT_FOUND'); 
        }
        $authorBankInfo = DB::table('ds_user_bankinfo')->where('id', $request->input('author_bank_info_id'))->first();
        if (!$authorBankInfo) {
            throw new AdminApiException('AUTHORINFO_CANNOT_FOUND');  
        }
        try {
            DB::beginTransaction();
            DB::table('ds_user_authorinfo')->where('id', $authorInfo->id)->update([
                'name' => e($request->input('name')),
                'qq' => e($request->input('qq')),
                'idcard' => e($request->input('idcard')),
                'address' => e($request->input('address')),
            ]);
            DB::table('ds_user_bankinfo')->where('id', $authorBankInfo->id)->update([
                'contactphone' => e($request->input('contactphone')),
                'bankuser' => e($request->input('bankuser')),
                'bankname' => e($request->input('bankname')),
                'bankaccount' => e($request->input('bankaccount')),
            ]);
            DB::commit();
            return successJson();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw new AdminApiException('DEFAULT_ERROR');
        }
    }
}
