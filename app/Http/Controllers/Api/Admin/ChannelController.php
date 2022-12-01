<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Exceptions\AdminApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;

class ChannelController extends Controller
{
    public function create(Request $request)
    {
        $appKey = md5(Str::random(64)); // 生成appKey
        $appSecret = md5($appKey.time()); //生成appSecret
        $validrules = [
            'name' => 'required|max:10',
            'end_date' => 'required|date',
            'userid' => 'required|numeric'
        ];
        validateParams($request->only('name', 'end_date', 'userid'), $validrules);
        DB::table('ds_channel_info')->insert([
            'userid' => $request->input('userid'),
            'channelname' => $request->input('name'),
            'channelkey' => $appKey,
            'channelsecret' => $appSecret,
            'endtime' => $request->input('end_date'),
        ]);
        return successJson();
    }
    public function updateChannelInfo(Request $request)
    {
        $validrules = [
            'id' => 'required|numeric',
            'name' => 'required',
            'end_date' => 'required',
        ];
        validateParams($request->only('id', 'name', 'end_date'), $validrules);
        $channelInfo = DB::table('ds_channel_info')->where('id', $request->input('id'))->first();
        if (!$channelInfo) {
            throw new AdminApiException('QD_CHANNEL_NOT_FOUND');
        }
        DB::table('ds_channel_info')->where('id', $channelInfo->id)->update([
            'channelname' => $request->input('name'),
            'endtime' => $request->input('end_date'),
        ]);
        return successJson();
    }
    public function getChannelInfo($id)
    {
        $channelInfo = DB::table('ds_channel_info')->where('id', $request->input('id'))->first();
        if (!$channelInfo) {
            throw new AdminApiException('QD_CHANNEL_NOT_FOUND');  
        } else {
            return successJson($channelInfo);
        }
    }
    public function getChannelList()
    {
        $channelList = DB::table('ds_channel_info')->get();
        return successJson($channelList);
    }
    public function deleteChannel($id)
    {
        $channelInfo = DB::table('ds_channel_info')->where('id', $id)->first();
        if (!$channelInfo) {
            throw new AdminApiException('QD_CHANNEL_NOT_FOUND');  
        }
        DB::table('ds_channel_info')->where('id', $id)->delete();
    }
    public function addBookToChannel(Request $request)
    {
        //ds_channel_book
        $validrules = [
            'bookid' => 'required',
            'channelid' => 'required|numeric',
        ];
        $bookList = explode(',', $request->input('bookid'));
        $getCurrentBookList = DB::table('ds_channel_book')->where('channelid', $request->input('channelid'))->pluck('bookid');
        foreach ($bookList as $bookListItem) {
            if (in_array($bookListItem, $getCurrentBookList)) {
                unset($bookListItem);
            }
        }
        $bookInfoList = DB::table('ds_book_info')->whereIn('id', $bookList)->select('id', 'bookname')->get();
        $data = [];
        foreach($bookInfoList as $bookInfo) {
            $bookInfoData = [
                'channelid' => $request->input('channelid'),
                'bookid' => $bookInfo->id,
                'bookname' => $bookInfo->bookname
            ];
            array_push($data, $bookInfoData);
        }
        DB::table('ds_channel_book')->insert($data);
        return successJson();
    }
    public function deleteBookToChannel($id)
    {
        DB::table('ds_channel_book')->where('id', $id)->delete();
        Cache::forget('qudao_admin_book_list');
        return successJson();
    }
    public function getChannelBookList($id)
    {
        $bookList = Cache::remember('qudao_admin_book_list', 3600, function()use($id) {
            return DB::table('ds_channel_book')->where('channelid', $id)->get();
        });
        return successJson($bookList);
    }
    public function createChannelMap(Request $request)
    {
        $validrules = [
            'channelid' => 'required|numeric',
            'classid' => 'required|numeric',
            'channelclassid' => 'required|numeric',
        ];
        validateParams($request->only('channelid', 'classid', 'channelclassid'), $validrules);
        $channelInfo = DB::table('ds_channel_info')->where('id', $request->input('channelid'))->first();
        if (!$channelInfo) {
            throw new AdminApiException('QD_CHANNEL_NOT_FOUND'); 
        }
        $classInfo = DB::table('ds_book_class')->where('id', $request->input('classid'))->first();
        if (!$classInfo) {
            throw new AdminApiException('CLASS_DATA_NOT_FOUND');
        }
        DB::table('ds_channel_classmap')->insert([
            'channelid' => $channelInfo->id,
            'classid' => $classInfo->id,
            'channelclassid' => $request->input('channelclassid'),
        ]);
        return successJson();
    }
    public function getChannelMap($id)
    {
        $mapList = DB::table('ds_channel_classmap')->where('ds_channel_classmap.channelid', $id)->leftJoin('ds_book_class', 'ds_channel_classmap.classid', '=', 'ds_book_class.id')->select('ds_channel_classmap.*', 'ds_book_class.classname')->get();
        return successJson($mapList);
    }
    public function updateChannelMap(Request $request)
    {
        $validrules = [
            'mapid' => 'required|numeric',
            'classid' => 'required|numeric',
            'channelclassid' => 'required|numeric',
        ];
        $mapInfo = DB::table('ds_channel_classmap')->where('id', $request->input('mapid'))->first();
        if (!$mapInfo) {
            throw new AdminApiException('QD_CHANNEL_CLASS_MAP_NOT_FOUND');
        }
        $newClassinfo = DB::table('ds_book_class')->where('id', $request->input('classid'))->first();
        if (!$newClassinfo) {
            throw new AdminApiException('CLASS_DATA_NOT_FOUND');
        }
        DB::table('ds_channel_classmap')->where('id', $mapInfo->id)->update([
            'classid' => $newClassinfo->id,
            'channelclassid' => $request->input('channelclassid'),
        ]);
        return successJson();
    }
    public function deleteChannelMap($id)
    {
        $mapInfo = DB::table('ds_channel_classmap')->where('id', $request->input('mapid'))->first();
        if (!$mapInfo) {
            throw new AdminApiException('QD_CHANNEL_CLASS_MAP_NOT_FOUND');
        }
        DB::table('ds_channel_classmap')->where('id', $mapInfo->id)->delete();
        return successJson();
    }
}
