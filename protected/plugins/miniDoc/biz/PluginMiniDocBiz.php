<?php
/**
 * Created by PhpStorm.
 * User: gly
 * Date: 15-1-13
 * Time: 上午10:25
 */
class PluginMiniDocBiz extends MiniBiz{
    /**
     *根据文件的Hash值下载内容
     * @param $fileHash 文件hash值
     * @throws 404错误
     */
    public function download($fileHash){
        $version = MiniVersion::getInstance()->getBySignature($fileHash);
        if(!empty($version)){
            //根据文件内容输出文件内容
            MiniFile::getInstance()->getContentBySignature($fileHash,$fileHash,$version["mime_type"]);
        }else{
            throw new MFileopsException(
                Yii::t('api','File Not Found'),
                404);
        }

    }
    /**
     *给迷你云报告文件转换过程
     * @param $fileHash 文件hash值
     * @param $status 文件状态
     * @return array
     */
    public function report($fileHash,$status){
        $version = MiniVersion::getInstance()->getBySignature($fileHash);
        if(!empty($version)){
            //文件转换成功
            if($status==="1"){
                PluginMiniDocVersion::getInstance()->updateDocConvertStatus($fileHash,2);
                //通过回调方式让迷你搜索把文件文本内容编制索引到数据库中
                do_action("pull_text_search",$fileHash);
            }
            //文件转换失败
            if($status==="0"){
                PluginMiniDocVersion::getInstance()->updateDocConvertStatus($fileHash,-1);
            }
        }
        return array("success"=>true);
    }
    /**
     * 获得账号里所有的指定文件类型列表
     * @param $page 当前页码
     * @param $pageSize 当前每页大小
     * @param $mimeType 文件类型
     * @return array
     */
    public function getList($page,$pageSize,$mimeType){
        $mimeTypeList = array("ppt"=>"application/mspowerpoint","word"=>"application/msword","excel"=>"application/msexcel","pdf"=>"application/pdf");
        $userId = $this->user['id'];
        $fileTotal = MiniFile::getInstance()->getTotalByMimeType($userId,$mimeTypeList[$mimeType]);
        $pageSet=($page-1)*$pageSize;
        $albumBiz = new AlbumBiz();
        $filePaths = $albumBiz->getAllSharedPath($userId);
        $sharedTotal = 0;
        $files = array();
        if(count($filePaths)!=0){
             //获取当前文件夹下的子文件
            foreach($filePaths as $filePath){
                $sharedFiles = MiniFile::getInstance()->getSharedDocByPathType($filePath,$mimeTypeList[$mimeType]);
                if(count($sharedFiles)==0){
                    continue;
                }
                foreach($sharedFiles as $sharedFile){
                    $sharedDocs[] = $sharedFile;
                }
            }
            $sharedTotal = count($sharedDocs);
        }
         if($pageSet>=$sharedTotal){
            $pageSet = $pageSet-$sharedTotal;
            $files = MiniFile::getInstance()->getByMimeType($userId,$mimeTypeList[$mimeType],$pageSet,$pageSize);
        }else{
            if($page*$pageSize<$sharedTotal){
                 for($index=$pageSet;$index<=$page*$pageSize-1;$index++){
                     $files[] = $sharedDocs[$index];
                 }
            }else{
                for($index=$pageSet;$index<$sharedTotal;$index++){
                   $fileArr[] = $sharedDocs[$index];
                }
                $fileList =  MiniFile::getInstance()->getByMimeType($userId,$mimeTypeList[$mimeType],0,$pageSize*$page-$sharedTotal);
                if(count($fileList)!=0){
                    $files = array_merge($fileArr,$fileList);
                }else{
                    $files = $fileArr;
                }
            }
        }
        // $files = MiniFile::getInstance()->getByMimeType($userId,$mimeTypeList[$mimeType],($page-1)*$pageSize,$pageSize);
        $items = array();
        foreach($files as $file){
            $version = PluginMiniDocVersion::getInstance()->getVersion($file['version_id']);
            $item['file_name'] = $file['file_name'];
            $item['path'] = $file['file_path'];
            $item['signature'] = $version['file_signature'];
            $item['mime_type'] = $version['mime_type'];
            $item['createTime'] = $version['createTime'];
            $item['type'] = $file['file_type'];
            if($file['user_id']!=$userId){
                $item['type'] = 2;
            }
            $item['updated_at'] = $version['created_at'];
            $item['doc_convert_status'] = $version['doc_convert_status'];
            if($version['doc_convert_status']==2){
                $url = "http://".$_SERVER['HTTP_HOST']."/temp/".$version['file_signature'].'/'.$version['file_signature'].".png" ;
                if(!file_exists($url)){
                    $this->cache($version['file_signature'],'png');
                }
                $item['url'] = $url;
            }
            $items[] = $item;
        }
        $data['list'] = $items;
        $data['totalPage'] = ceil(($fileTotal+$sharedTotal)/$pageSize);
        return $data;
    } 
}