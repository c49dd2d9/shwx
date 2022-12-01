<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Enums\UploadConfig;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;

// 上传Controller
class UploadController extends Controller
{
    /**
     * @router /upload/image
     * @params { file }
     * @response url
     * @name 上传图片文件
     */
    public function uploadImage(Request $request)
    {
        $userInfo = $request->get('userInfo');
        $maxSize = $request->input('path') == 'headimg' ? 100 : 150;
        $imageMaxPx = $request->input('path') == 'book' ? [ 'dimensions:max_width=185,max_height:260', '您最大可上传185*260尺寸的图片' ] : [ 'dimensions:max_width=1024', '您不得上传宽度大于1024的图片' ];
        $validrules = [
            'file' => 'required|file|max:'.$maxSize.'|mimes:jpeg,jpg,gif,bmp,png|'.$imageMaxPx[0],
            'path' => [
                'required',
                Rule::in([ 'headimg', 'book', 'bbs' ]),
            ],
        ];
        $validmessages = [
            'file.required' => '您必须选择上传的文件',
            'file.file' => '选择的文件不合法',
            'file.max' => '文件太大了，最大只能上传 :max KB的文件',
            'file.mimes' => '文件后缀不合法，您只可以上传后缀名为 :values 的文件',
            'file.dimensions' => $imageMaxPx[1],
            'path.required' => '非法的接口调用',
            'path.in' => '非法的接口调用'
        ];
        validateParams($request->all(), $validrules, $validmessages);
        $fileName = time().rand(10000,999999);
        $fileExtension = $request->file('file')->extension();
        switch ($request->input('path')) {
            case 'headimg':
                $OSSUploadFilePath = 'headimg';
                break;
            case 'bbs': 
                $OSSUploadFilePath = 'bbs/static';
                $fileName = date('y/m/d/').sha1(Str::random(20));
                break;
            default:
                $OSSUploadFilePath = 'book';
                break;
        }
        $filePath = Storage::disk('aliyun')->putFileAs($OSSUploadFilePath ,$request->file('file'), $fileName.'.'.$fileExtension);
        $data = [
            'user' => $userInfo['id'],
            'path' => UploadConfig::AssetDomain.'/'.$filePath,
        ];
        return successJson($data);
    }
    public function adminUploadFile(Request $request)
    {
        $validrules = [
            'file' => 'required|file|max:5120',
        ];
        validateParams($request->only('file'), $validrules);
        $fileName = Str::random(30);
        $fileExtension = $request->file('file')->extension();
        $fileUploadPath = 'admin/'.date('Y/m');
        $filePath = Storage::disk('aliyun')->putFileAs($fileUploadPath, $request->file('file'), $fileName.'.'.$fileExtension);
        $data = [
            'user' => 0,
            'path' => UploadConfig::AssetDomain.'/'.$filePath,
        ];
        return successJson($data);
    }
}
