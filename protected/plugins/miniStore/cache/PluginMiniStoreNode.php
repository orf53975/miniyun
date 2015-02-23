<?php
/**
 * 缓存miniyun_store_nodes表的记录
 * @author app <app@miniyun.cn>
 * @link http://www.miniyun.cn
 * @copyright 2015 Chengdu MiniYun Technology Co. Ltd.
 * @license http://www.miniyun.cn/license.html
 * @since 1.8
 */
class PluginMiniStoreNode extends MiniCache{
    /**
     *
     * Cache Key的前缀
     * @var string
     */
    private static $CACHE_KEY = "cache.model.PluginMiniStoreNode";

    /**
     *  静态成品变量 保存全局实例
     *  @access private
     */
    static private $_instance = null;

    /**
     *  私有化构造函数，防止外界实例化对象
     */
    private function  __construct()
    {
        parent::MiniCache();
    }

    /**
     * 静态方法, 单例统一访问入口
     * @return object  返回对象的唯一实例
     */
    static public function getInstance()
    {
        if (is_null(self::$_instance) || !isset(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    /**
     * 把数据库记录集合序列化
     * @param $items 数据库对象集合
     * @return array
     */
    private function db2list($items){
        $data  = array();
        foreach($items as $item) {
            array_push($data, $this->db2Item($item));
        }
        return $data;
    }
    /**
     * 把数据库记录序列化
     * @param $item 数据库对象
     * @return array
     */
    private function db2Item($item){
        if(empty($item)) return NULL;
        $value                        = array();
        $value["id"]                  = $item->id;
        $value["name"]                = $item->name;
        $value["host"]                = $item->host;
        $value["access_token"]        = $item->access_token;
        $value["status"]              = $item->status;
        $value["saved_file_count"]    = $item->saved_file_count;
        $value["downloaded_file_count"] = $item->downloaded_file_count;
        $value["created_at"]          = $item->created_at;
        $value["updated_at"]          = $item->updated_at;
        return $value;
    } 
    /**
    * 根据ID获得迷你存储节点
    * 找到min(saved_file_count) and status=1的记录分配
     * @param int $id 迷你存储节点ID
     * @return array
    */
    public function getNodeById($id){
        $item = StoreNode::model()->find("id=:id",array("id"=>$id));
        if(isset($item)){
            return $this->db2Item($item);
        }
        return null;
    }
    /**
    * 获得有效文件上传服务器节点
    * 找到min(saved_file_count) and status=1的记录分配
    */
    public function getUploadNode(){
        //TODO 对用户进行分区文件管理，需要找到迷你存储节点与用户的关系，然后进行分配处理
        $savedFileCount = 0;
        $uploadNode = null;
        $nodes = $this->getNodeList();
        foreach ($nodes as $node) { 
            if($node["status"]==1){ 
                $currentFileCount = $node["saved_file_count"];
                //初始化第一次
                if($savedFileCount===0){
                    $savedFileCount = $currentFileCount;
                    $uploadNode = $node;
                }
                //轮训最小上传文件数的节点
                if($savedFileCount>$currentFileCount){
                    $savedFileCount = $currentFileCount;
                    $uploadNode = $node;
                }
            }
        }
        return $uploadNode;
    }
    /**
     * 获得迷你存储所有节点列表
     */
    public function getNodeList(){
        $items = StoreNode::model()->findAll();
        return $this->db2list($items);
    }
    /**
     * 检查所有节点状态
     */
    public function checkNodesStatus(){
        $items = StoreNode::model()->findAll();
        foreach($items as $item){
            $host = $item->host;
            $oldStatus = $item->status;
            $status = $this->checkNodeStatus($host);
            if($status!=$oldStatus){
                $item->status = $status;
                $item->save();
            }
        }
    }
    /**
     * 检查存储节点状态
     * @param $host
     * @return int
     */
    private function checkNodeStatus($host){
        $url = $host."/api.php?route=node/status";
        $content = @file_get_contents($url);
        if(!empty($content)){
            $nodeStatus = @json_decode($content);
            if($nodeStatus->{"status"}=="1"){
                return 1;
            }
        }
        return -1;
    }
    /**
     * 节点新上传文件
     * @param $nodeId
     */
    public function newUploadFile($nodeId){
        $item = StoreNode::model()->find("id=:id",array("id"=>$nodeId));
        if(isset($item)){
            $item->saved_file_count+=1;
            $item->save();
        }
    }
    /**
     * 节点新新下载了文件
     * @param $nodeId
     */
    public function newDownloadFile($nodeId){
        $item = StoreNode::model()->find("id=:id",array("id"=>$nodeId));
        if(isset($item)){
            $item->downloaded_file_count+=1;
            $item->save();
        }
    }
    /**
     * 创建迷你存储节点
     * @param $name 节点名称
     * @param $host 节点域名
     * @param $accessToken 节点访问的accessToken
     * @return array
     */
    public function createOrModifyNode($name,$host,$accessToken){
        $item = StoreNode::model()->find("name=:name",array("name"=>$name));
        if(!isset($item)){
            $item = new StoreNode();
            $item->saved_file_count=0;
            $item->downloaded_file_count=0;
        }
        $item->name = $name;
        $item->host = $host;
        $item->access_token = $accessToken;
        $item->status = -1;//所有新建或修改节点状态都是无效的
        $item->save();
        return $this->db2Item($item);
    }
    /**
     * 修改迷你存储节点状态
     * @param $name 节点名称
     * @param $status 节点状态
     * @return array
     */
    public function modifyNodeStatus($name,$status){
        //迷你存储节点状态只保留2个
        //1表示迷你存储节点生效,-1表示迷你存储节点无效
        if($status!=="1"){
            $status = "-1";
        }
        $item = StoreNode::model()->find("name=:name",array("name"=>$name));
        if(isset($item)){
            $item->status = $status;
            $item->save();
        }
        return $this->db2Item($item);
    }
    /**
     * 为文件生成其它冗余备份节点
     * 找到不属当前迷你存储节点，且status=1，saved_file_count最小的记录
     * @param string $signature 文件内容hash
     * @return array
     */
    public function getReplicateNodes($signature){
        $version = MiniVersion::getInstance()->getBySignature($signature);
        if(!empty($version)) {
            $metaKey = "store_id";
            $meta = MiniVersionMeta::getInstance()->getMeta($version["id"], $metaKey);
            if (!empty($meta)) {
                $value = $meta["meta_value"];
                $ids = explode(",",$value);
                $validNodes = array();
                $nodes = PluginMiniStoreNode::getInstance()->getNodeList();
                foreach ($nodes as $node) {
                    //排除当前节点的迷你存储服务器
                    $isValidNode = false;
                    foreach ($ids as $validNodeId) {
                        if($validNodeId!=$node["id"]){
                            $isValidNode = true;
                        }
                    }
                    if(!$isValidNode) continue;
                    //然后判断服务器是否有效
                    if($node["status"]==1){
                        array_push($validNodes,$node);
                    }
                }
                //可用节点大于2，选出save_file_count最小的2个节点
                PluginMiniStoreUtils::array_sort($validNodes,"saved_file_count",SORT_ASC);
                if(count($validNodes)>2){
                    return array($validNodes[0],$validNodes[1]);
                }
                return $validNodes;
            }
        }

    }
    /**
     * 获得有效文件下载地址
     * @param string $signature 文件内容hash
     * @param string $fileName 文件名
     * @param string $mimeType 文件的mimeType
     * @param int $forceDownload 是否要文件下载
     * @return string
     */
    public function getDownloadUrl($signature,$fileName,$mimeType,$forceDownload){
        $node = $this->getDownloadNode($signature);
        if(!empty($node)){
            //迷你存储服务器下载文件地址
            //对网页的处理分为2种逻辑，-1种是直接显示内容，1种是文件直接下载
            $data = array(
                "route"=>"file/download",
                "signature"=>$signature,
                "node_id"=>$node["id"],
                "file_name"=>$fileName,
                "mime_type"=>$mimeType,
                "force_download"=>$forceDownload
            );
            $url = $node["host"]."/api.php?";
            foreach($data as $key=>$value){
                $url .="&".$key."=".$value;
            }
            //更新迷你存储节点状态，把新上传的文件数+1
            PluginMiniStoreNode::getInstance()->newDownloadFile($node["id"]);

            return $url;
        }
        return null;
    }
    /**
     * 获得有效文件下载服务器节点
     * 找到min(downloaded_file_count) and status=1的记录分配
     * @param string $signature 文件内容hash
     * @return array
     */
    private function getDownloadNode($signature){
        $version = MiniVersion::getInstance()->getBySignature($signature);
        if(!empty($version)){
            $metaKey = "store_id";
            $meta = MiniVersionMeta::getInstance()->getMeta($version["id"],$metaKey);
            if(!empty($meta)){
                $value = $meta["meta_value"];
                $ids = explode(",",$value);
                $downloadFileCount = 0;
                $downloadNode = null;
                $nodes = $this->getNodeList();
                foreach ($nodes as $node) {
                    //先找到当前文件存储的节点
                    $isValidNode = false;
                    foreach ($ids as $validNodeId) {
                        if($validNodeId==$node["id"]){
                            $isValidNode = true;
                        }
                    }
                    if(!$isValidNode) continue;
                    //然后判断节点是否有效，并在有效的节点找到下载次数最小的节点
                    if($node["status"]==1){
                        $currentFileCount = $node["downloaded_file_count"];
                        //初始化第一次
                        if($downloadFileCount===0){
                            $downloadFileCount = $currentFileCount;
                            $downloadNode = $node;
                        }
                        //轮训最小上传文件数的节点
                        if($downloadFileCount>$currentFileCount){
                            $downloadFileCount = $currentFileCount;
                            $downloadNode = $node;
                        }
                    }
                }
                return $downloadNode;
            }
        }
        return null;
    }
}