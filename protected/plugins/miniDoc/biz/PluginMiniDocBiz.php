<?php
/**
 * 迷你文档业务层
 * @author app <app@miniyun.cn>
 * @link http://www.miniyun.cn
 * @copyright 2015 Chengdu MiniYun Technology Co. Ltd.
 * @license http://www.miniyun.cn/license.html
 * @since 1.7
 */
class PluginMiniDocBiz extends MiniBiz{
    /**
     *根据文件的Hash值下载内容
     * @param string $signature 文件hash值
     * @throws 404错误
     */
    public function download($signature){
        $version = MiniVersion::getInstance()->getBySignature($signature);
        if(!empty($version)){
            //根据文件内容输出文件内容
            MiniFile::getInstance()->getContentBySignature($signature,$signature,$version["mime_type"]);
        }else{
            throw new MFileopsException(
                Yii::t('api','File Not Found'),
                404);
        }

    }
    /**
     *给迷你云报告文件转换过程
     * @param int $nodeId 文档转换服务器Id
     * @param string $signature 文件hash值
     * @param string $status 文件状态
     * @return array
     */
    public function report($nodeId,$signature,$status){
        $version = MiniVersion::getInstance()->getBySignature($signature);
        if(!empty($version)){
            //文件转换成功
            if($status==="1"){
                //更新迷你存储节点状态，把新上传的文件数+1
                PluginMiniDocNode::getInstance()->newConvertFile($nodeId);
                PluginMiniDocVersion::getInstance()->updateDocConvertStatus($nodeId,$signature,2);
                //通过回调方式让迷你搜索把文件文本内容编制索引到数据库中
                do_action("pull_text_search",$signature);
            }
            //文件转换失败
            if($status==="0"){
                PluginMiniDocVersion::getInstance()->updateDocConvertStatus($nodeId,$signature,-1);
            }
        }
        return array("success"=>true);
    }
    private function getSharedDoc($page,$pageSize,$sharedList,$mimeType){
        $cursor = $pageSize;
        $data   = array();
        $num    = 0;
        $index  = 0;
        for($i=0;$i<count($sharedList);$i++){
            $item  = $sharedList[$i];
            $path  = $item[0];
            $count = $item[1];
            //当共享文件为共享者的时候进行过滤
            $userId = $this->user['id'];
            $arr = explode("/",$path);
            $slaveId = $arr[1];
            if($slaveId==$userId){
                continue;
            }
            if($cursor==0){
                return $data;
            }
            if($page<=$num+$count){
                if(($page-$num+$cursor)<=$count&&$page>0){
                    $doc = MiniFile::getInstance()->getSharedDocByPathType($path,$mimeType,$page-$num,$cursor);
                    array_splice($data,count($data),0,$doc);
                    break;
                }else{
                    if($index==0){
                        $doc = MiniFile::getInstance()->getSharedDocByPathType($path,$mimeType,$page-$num,$cursor);
                        array_splice($data,count($data),0,$doc);
                        $cursor = $cursor-($num+$count-$page);
                        if($cursor<=0){
                            $cursor = 0;
                        }
                    }else{
                        $doc = MiniFile::getInstance()->getSharedDocByPathType($path,$mimeType,0,$cursor);
                        array_splice($data,count($data),0,$doc);
                        $cursor = $cursor-$count;
                        if($cursor<=0){
                            $cursor = 0;
                        }
                    }
                    $index++;
                }
            }
            $num = $num+$count;
        }
        return $data;

    }
    /**
     * 获得账号里所有的指定文件类型列表
     * @param int $page 当前页码
     * @param int $pageSize 当前每页大小
     * @param string $mimeType 文件类型
     * @return array
     */
    public function getList($page,$pageSize,$mimeType){
        $isValid = false;
        $sharedList = array();
        $mimeTypeList = array("application/mspowerpoint","application/msword","application/msexcel","application/pdf");
        foreach ($mimeTypeList as $validMimeType) {
            if($validMimeType===$mimeType){
                $isValid = true;
            }
        }
        $data = array();
        if(!$isValid){
            $data['list'] = array();
            $data['totalPage'] = 0;
            return $data;
        }
        $userId = $this->user['id'];
        $fileTotal = MiniFile::getInstance()->getTotalByMimeType($userId,$mimeType);
        $pageSet=($page-1)*$pageSize;
        $albumBiz = new AlbumBiz();
        $shares = $albumBiz->getAllSharedPath($userId);
        $sharedTotal = 0;
        $files = array();
        if(count($shares)!=0){
            //获取当前文件夹下的子文件
            $sharedTotal = 0;
            foreach($shares as $filePath){
                $count = MiniFile::getInstance()->getSharedDocTotal($filePath,$mimeType);
                $sharedTotal = $count+$sharedTotal;
                if($count>0){
                    $item = array();
                    $item[] = $filePath;
                    $item[] = $count;
                    $sharedList[] = $item;
                }
            }
        }
        if($pageSet>=$sharedTotal){
            $pageSet = $pageSet-$sharedTotal;
            $files = MiniFile::getInstance()->getByMimeType($userId,$mimeType,$pageSet,$pageSize);
        }else{
            if($page*$pageSize<$sharedTotal){
                $files = $this->getSharedDoc($pageSet,$pageSize,$sharedList,$mimeType);
            }else{
                $fileArr = $this->getSharedDoc($pageSet,$sharedTotal,$sharedList,$mimeType);
                $fileList =  MiniFile::getInstance()->getByMimeType($userId,$mimeType,0,$pageSize*$page-$sharedTotal);
                if(count($fileList)!=0){
                    $files = array_merge($fileArr,$fileList);
                }else{
                    $files = $fileArr;
                }
            }
        }
        // $files = MiniFile::getInstance()->getByMimeType($userId,$mimeType,($page-1)*$pageSize,$pageSize);
        $items = array();
        foreach($files as $file){
            $version = PluginMiniDocVersion::getInstance()->getVersion($file['version_id']);
            $item['file_name'] = $file['file_name'];
            $item['path'] = $file['file_path'];
            $item['mime_type'] = $version['mime_type'];
            $item['createTime'] = $version['createTime'];
            $item['type'] = $file['file_type'];
            if($file['user_id']!=$userId){
                $item['type'] = 2;
            }
            $item['updated_at'] = $version['created_at'];
            $item['doc_convert_status'] = $version['doc_convert_status'];
            $items[] = $item;
        }
        $data['list'] = $items;
        $data['totalPage'] = ceil(($fileTotal+$sharedTotal)/$pageSize);
        return $data;
    }
    /**
     * 在线浏览文件获得内容
     * @param string $path 文件当前路径
     * @param string $type 文件类型，可选择pdf/png
     * @throws
     * @return NULL
     */
    public function previewContent($path,$type){
        $file = MiniFile::getInstance()->getByPath($path);
        // 权限处理
        if(empty($file)){
            return array('success' =>false ,'msg'=>'file not existed');
        }
        $fileBiz = new FileBiz();
        $canRead = $fileBiz->privilege($path);
        if(!$canRead){
            throw new MFileopsException( Yii::t('api','no permission'),MConst::HTTP_CODE_409);
        }
        //获得文件当前版本对应的version
        $version   = PluginMiniDocVersion::getInstance()->getVersion($file["version_id"]);
        $signature = $version["file_signature"];
        $localPath = PluginMiniDocOption::getInstance()->getMiniDocCachePath().$signature."/".$signature.".".$type;
        if(!file_exists($localPath)){
            //文档还在转换中
            $node = PluginMiniDocNode::getInstance()->getConvertNode($signature);
            if(empty($node)){
                throw new MFileopsException( Yii::t('api','convert error'),MConst::HTTP_CODE_412);
            }
            //根据情况判断是否需要向迷你文档拉取内容
            $parentPath = dirname($localPath);
            //如果缓存目录不存在，则需要创建
            if(!file_exists($parentPath)){
                MUtils::MkDirsLocal($parentPath);
            }
            //文件不存在，则需要从迷你文档拉取文件内容
            $url  = PluginMiniDocNode::getInstance()->getDownloadUrl($node["id"],$version,$type);
            $http = new HttpClient();
            $http->get($url);
            $status = $http->get_status();
            if($status=="200"){
                $content = $http->get_body();
                //把文件内容存储到本地硬盘
                file_put_contents($localPath, $content);
                Yii::log($signature." get ".$type." success",CLogger::LEVEL_INFO,"doc.convert");
            }else{
                if(!($version["doc_convert_status"]==-1)){
                    //如迷你文档服务器不存在该文档，说明迷你文档服务器发生了变动
                    //这个时候自动启动负载均衡机制，把文档重新转换
                    PluginMiniDocVersion::getInstance()->pushConvertSignature($signature,"");
                    Yii::log($signature." get ".$type." error",CLogger::LEVEL_ERROR,"doc.convert");
                }                
            }
        }

        if(file_exists($localPath)){
            if($type==="png"){
                $contentType = "image/png";
            }
            if($type==="pdf"){
                $contentType = "Content-type: application/pdf";
            } 
            //Firefox+混合云模式下直接输出内容 
            //其它浏览器使用sendfile模式输出内容
            $isSendFile = true;
            if(MiniUtil::isMixCloudVersion()){
                $ua = isset($_SERVER ["HTTP_USER_AGENT"]) ? $_SERVER ["HTTP_USER_AGENT"] : NULL;
                if (strpos($ua,"Firefox")>0 || strpos($ua,"Safari")>0){
                    $isSendFile = false; 
                }
            }
            if($isSendFile){
                header('Location: '.MiniHttp::getMiniHost()."assets/minidoc/".$signature."/".$signature.".".$type);
            }else{
                Header("Content-type: ".$contentType);
                echo(file_get_contents($localPath));exit;
            }
        }
    }
    /**
     *获得迷你文档节点信息列表
     * @return array
     */
    public function getNodeList(){
        return PluginMiniDocNode::getInstance()->getNodeList();
    }
    /**
     * 创建迷你文档节点
     * @param int $id 节点ID
     * @param string $name 节点名称
     * @param string $host 节点域名
     * @param string $safeCode 节点访问的安全码
     * @throws MiniException
     * @return array
     */
    public function createOrModifyNode($id,$name,$host,$safeCode){
        $node = PluginMiniDocNode::getInstance()->createOrModifyNode($id,$name,$host,$safeCode);
        if(empty($node)){
            throw new MiniException(100205);
        }
        return $node;
    }
    /**
     * 修改迷你文档节点状态
     * @param string $name 节点名称
     * @param string $status 节点状态
     * @throws MiniException
     */
    public function modifyNodeStatus($name,$status){
        $node = PluginMiniDocNode::getInstance()->getNodeByName($name);
        if(empty($node)){
            throw new MiniException(100203);
        }
        if($status==1){
            //检查服务器状态，看看是否可以连接迷你文档服务器
            $nodeStatus = PluginMiniDocNode::getInstance()->checkNodeStatus($node["host"]);
            if($nodeStatus==-1){
                throw new MiniException(100204);
            }
        }
        return PluginMiniDocNode::getInstance()->modifyNodeStatus($name,$status);
    }
    /**
     * 获得当前文档转换状态
     * 如状态为0，可能是老文件，以补偿形式开始转换
     * @param string $path 文件路径 
     */
    public function convertStatus($path){
        $file    = MiniFile::getInstance()->getByPath($path);
        $version = PluginMiniDocVersion::getInstance()->getVersion($file['version_id']);
        if($version["doc_convert_status"]==0){
            PluginMiniDocVersion::getInstance()->pushConvertSignature($version["file_signature"],""); 
        }
        return array('status'=>$version['doc_convert_status']);
    }
}
