<?php
/**
 * 迷你云入口.
 *
 * @author app <app@miniyun.cn>
 * @link http://www.miniyun.cn
 * @copyright 2014 Chengdu MiniYun Technology Co. Ltd.
 * @license http://www.miniyun.cn/license.html
 * @since 1.6
 */
class Util{
    /**
     * 获得迷你云Host
     */
    public static function getMiniHost(){
        return 'https://app.miniyun.cn/';
    } 
    /**
     * 获得RequestUri,如果是二级目录、三级目录则自动去掉路径前缀
     * @return string
     */
    public static function getRequestUri(){
        $host = Util::getMiniHost();
        $host = str_replace("http://","",$host);
        $host = str_replace("https://","",$host);
        $host = str_replace("//","/",$host);
        $info = explode("/",$host);
        $relativePath = "";
        for($i=1;$i<count($info);$i++){
            $relativePath .= "/".$info[$i];
        }
        $requestUri = $_SERVER["REQUEST_URI"];
        //增加过滤//的URL地址
        $requestUri = str_replace("//","/",$requestUri);
        return substr($requestUri,strlen($relativePath),strlen($requestUri)-strlen($relativePath));

    }
    public static function getPhysicalRoot(){
        $indexFile = $_SERVER["SCRIPT_FILENAME"];
        $serverPath = dirname($indexFile)."/";
        return $serverPath;
    }
    public static function getParam($name){
        switch($_SERVER['REQUEST_METHOD'])
        {
            case 'GET': $request = &$_GET; break;
            case 'POST': $request = &$_POST; break;
        }
        if(array_key_exists($name,$request)){
            return $request[$name];
        }
        return NULL;
    }

    /**
     * 判断是否是IE浏览器
     * @return bool
     */
    public static function isIE(){
        $pos = strpos(strtolower($_SERVER["HTTP_USER_AGENT"]),"trident");
        if($pos){
            return true;
        }
        return false;
    }

    /**
     * 获得IE浏览器版本
     */
    public static function getIEVersion(){

        if(Util::isIE()){
            $agent = strtolower($_SERVER["HTTP_USER_AGENT"]);
            $start = strpos($agent,"trident");
            $end = strpos($agent,";",$start);
            if(!$end){
                $end = strlen($agent)-1;
            }
            $versionStr = substr($agent,$start+8,$end);
            return intval($versionStr)+4;
        }
        return -1;
    }
    /**
     * 判断是否是PC客户端
     */
    public static function isPCClient(){
        $pos = strpos($_SERVER["HTTP_USER_AGENT"],"miniClient");
        if($pos){
            return true;
        }
        return false;
    }
    /**
     * 判断是否是Chrome浏览器
     * @return bool
     */
    public static function isChrome(){
        $pos = strpos(strtolower($_SERVER["HTTP_USER_AGENT"]),"chrome");
        if($pos){
            return true;
        }
        return false;
    }
    /**
     * 判断是否是IE浏览器
     * @return bool
     */
    public static function isFirefox(){
        $pos = strpos(strtolower($_SERVER["HTTP_USER_AGENT"]),"firefox");
        if($pos){
            return true;
        }
        return false;
    }
    public static function createI18nUrl($host,$mainPath,$appName,$language,$version){
        if(!($language=="zh_tw"||$language=="zh_cn"||$language=="en")){
            $language="zh_cn";
        }
        return $host."static/".$mainPath."/i18n/".$language."/".$appName.".js?v=".$version;
    }
}

class SiteAppInfo{

    private $user;
    public function  SiteAppInfo(){

    }
    /**
     * 获得站点信息，获得自定义的名称与Logo
     * @return array|null
     */
    public  function getSiteInfo(){
        $app = new SiteService();
        return $app->info();
    }
    /**
     * 判断是否是默认账号
     * @return array|null
     */
    public  function defaultAccount(){
        $app = new SiteService();
        return $app->onlyDefaultAccount();
    }
    /**
     * 获得当前用户
     * @return array|null
     */
    public  function getUser(){
        if(isset($this->user)){
            return $this->user;
        }
        $user     = MUserManager::getInstance()->getCurrentUser();
        if(!empty($user)){
            $user = MiniUser::getInstance()->getUser($user["id"]);
            $data = array();
            $data['id']                = $user["id"];
            $data['user_uuid']         = $user["user_uuid"];
            $data['user_name']         = $user["user_name"];
            $data['display_name']      = $user["nick"];
            $data['space']             = (double)$user["space"];
            $data['used_space']        = (double)$user["usedSpace"];
            $data['email']             = $user["email"];
            $data['phone']             = $user["phone"];
            $data['avatar']            = $user["avatar"];
            $data['is_admin']          = $user["is_admin"];
            if(!empty($user['file_sort_type'])&&!empty($user['file_sort_order'])){
                $data['file_sort_type']    = $user['file_sort_type'];
                $data['file_sort_order']   = $user['file_sort_order'];
            }
            $code = MiniOption::getInstance()->getOptionValue("code");
            if(empty($code)){
                $code = "";
            }
            $data['code']              = $code;
            $userMeta=MiniUserMeta::getInstance()->getUserMetas($user["id"]);
            $policy1='';
            if(array_key_exists('upload_policy_white_list',$userMeta)){
                $policy1=$userMeta['upload_policy_white_list'];
            }
            $policy2='';
            if(array_key_exists('upload_policy_black_list',$userMeta)){
                $policy2=$userMeta['upload_policy_black_list'];
            }
            $policy3='';
            if(array_key_exists('upload_policy_file_size',$userMeta)){
                $policy3=$userMeta['upload_policy_file_size'];
            } 
            if(empty($policy1)){
                $policy1  = MiniOption::getInstance()->getOptionValue('upload_policy_white_list');
                if(empty($policy1)){
                    $policy1 = '*';
                }
            }
            if(empty($policy2)){
                $policy2  = MiniOption::getInstance()->getOptionValue('upload_policy_black_list');
                if(empty($policy2)){
                    $policy2 = '';
                }
            }
            if(empty($policy3)){
                $policy3  = MiniOption::getInstance()->getOptionValue('upload_policy_file_size');
                if(empty($policy3)){
                    $policy3 = 102400;
                }
            }
            $policy3 = intval($policy3);
            $data['upload_policy_white_list'] = $policy1;
            $data['upload_policy_black_list'] = $policy2;
            $data['upload_policy_file_size'] = $policy3;
            $data['mini_host'] = Util::getMiniHost();
            //查询是否激活迷你云服务器
            $actived = false;
            $node = PluginMiniStoreNode::getInstance()->getUploadNode();
            if(!empty($node)){
                $actived = true;
            }
            $data['minicloud_actived'] = $actived;
            $data['minicloud_host'] = $node['host'];
            $this->user = $data;
            return $data;
        }
        return NULL;
    }
}
/**
 *
 * 加载资源
 */
class MiniBox{
    /**
     * 控制器名称
     * @var
     */
    private $controller;
    /**
     * 动作名称
     * @var
     */
    private $action;
    /**
     * 迷你云服务器地址
     * @var
     */
    private $staticServerHost;
    /**
     * 当前用户选择的语言版本
     * @var
     */
    private $language;
    /**
     * 网页客户端版本号
     * @var
     */
    private $version;
    /**
     * 云端存储的主目录
     * @var
     */
    private $cloudFolderName; 
    private $appInfo; 

    /**
     *
     */
    private $webApp = NULL;
    public function MiniBox(){
        $requestUri   = Util::getRequestUri();
        //如果系统尚未初始化，则直接跳转到安装页面
        $configPath  = dirname(__FILE__).'/protected/config/miniyun-config.php';
        if (!file_exists($configPath) && !strpos($requestUri,"install")) {
            header('Location: '.Util::getMiniHost()."index.php/install/index");
            exit;
        }
        //加载系统信息
        $config = dirname(__FILE__).'/protected/config/main.php';
        $yii    = dirname(__FILE__).'/yii/framework/yii.php';
        require_once($yii);
        $this->webApp = Yii::createWebApplication($config);
        MiniAppParam::getInstance()->load();
        MiniPlugin::getInstance()->load(); 
        //初始化cookie等信息
        $accessToken = Util::getParam("accessToken");
        if(!empty($accessToken)){
            Yii::app()->session["accessToken"] = $accessToken;
            setcookie("accessToken",$accessToken,time()+10*24*3600,"/");
        }
        $version = Util::getParam("cloudVersion");
        if(!empty($version)){
            setcookie("cloudVersion",$version,time()+10*24*3600,"/");
        }
        $appKey = Util::getParam("appKey");
        if(!empty($appKey)){
            setcookie("appKey",$appKey,time()+10*24*3600,"/");
        }
        $appSecret = Util::getParam("appSecret");
        if(!empty($appSecret)){
            setcookie("appSecret",$appSecret,time()+10*24*3600,"/");
        }
        $this->staticServerHost = "https://jt.miniyun.cn/";
        //解析形如/index.php/site/login?backUrl=/index.php/box/index这样的字符串
        //提取出controller与action
        $uriInfo      = explode("/",$requestUri); 
        if(count($uriInfo)===1){
            //用户输入的是根路径
            $url = Util::getMiniHost()."index.php/box/index";
            $this->redirectUrl($url);
        }
        //兼容/index.php/box，自动到/index.php/box/index下
        $this->controller = $uriInfo[1];
        if($this->controller==="k"){
            //外链短地址
            $key = $uriInfo[2];
            $url = Util::getMiniHost()."index.php/link/access/key/".$key;
            $this->redirectUrl($url);
        }
        if(count($uriInfo)===2||empty($uriInfo[2])){
            if(empty($this->controller)){
                $this->controller = "box";
            }
            $url = Util::getMiniHost()."index.php/".$this->controller."/index";
            $this->redirectUrl($url);
        }else{
            $actionInfo   = explode("?",$uriInfo[2]);
            $this->action = $actionInfo[0];
        } 
        if(empty($this->controller)){
            $accessToken = $this->getCookie("accessToken");
            if(!empty($accessToken)){
                //根目录访问
                $url = Util::getMiniHost()."index.php/box/index";
            }else{
                $url = Util::getMiniHost()."login";
            }
            $this->redirectUrl($url);
        } 
    }

    /**
     * 调整URL，如是PC客户端发起的请求，在后面自动加上client_type=pc类型
     * 区分是否是PC客户端还是网页客户端发起的请求
     * @param $url
     */
    private function redirectUrl($url){
        header('Location: '.$url);
        exit;
    }
    private  function loadHtml($head){
        $metaHead = "";
        $metaHead .= "<meta name='renderer' content='webkit'/><meta http-equiv='X-UA-Compatible' content='IE=Edge,chrome=1'/>";//强制360安全浏览器使用急速模式
        //输出头信息
        $content = "<!doctype html><html id='ng-app'><head><meta http-equiv=\"content-type\" content=\"text/html;charset=utf-8\"/>".$metaHead.$head."<script>";
        $appInfo = $this->appInfo;
        //打印APP INFO
        $content .= "var appInfo={};appInfo.info = JSON.parse('".json_encode($appInfo->getSiteInfo())."');";
        //是否系统仅有管理员
        $content .= "appInfo.only_default_account = JSON.parse('".json_encode($appInfo->defaultAccount())."');";
        //打印用户是否登录
        $user    = $appInfo->getUser();
        $info = array("success"=>true);
        if(empty($user)){
            $info = array("success"=>false);
        }
        $content .= "appInfo.login = JSON.parse('".json_encode($info)."');";
        if(!empty($user)){
            $content .= "appInfo.user = JSON.parse('".json_encode($user)."');";
        }
        //打印用户是否是管理员
        $info = array("success"=>false);
        if(!empty($user)){
            if($user["is_admin"]){
                $info = array("success"=>true);
            }
        }
        $content .= "appInfo.is_admin = JSON.parse('".json_encode($info)."');";
        //输出服务器时间
        $info = array("time"=>time());
        $content .= "appInfo.time = JSON.parse('".json_encode($info)."');";
        //输出body信息
        $content .= "</script></head><body><div ng-view></div></body></html>";
        echo($content);
    }
    /**
     * 加载资源
     */
    public function load(){
        date_default_timezone_set("PRC");
        @ini_set('display_errors', '1');
        $this->appInfo = new SiteAppInfo();
        //默认业务主路径
        $this->cloudFolderName = "mini-box";

        $language = $this->getCookie("language");
        if(empty($language)){
            $language = "zh_cn";
            setcookie("language",$language,time()+10*24*3600,"/");
        }
        $this->language = $language;
        $v = $this->getCookie("cloudVersion");
        if (empty($v)) {
            //这里为空，只有一种情况就是PC客户端第一次访问的时候，由于没有进行syncNewVersion操作
            //PC客户端使用Get方式初始化
            $v = "1.0";
        }
        $this->version = $v;
        $header = "";
        $site          = $this->appInfo->getSiteInfo();
        $serverVersion = $site["version"];
        //生产状态，将会把js/css文件进行合并处理，提高加载效率
        $header .= "<script id='miniBox' static-server-host='".$this->staticServerHost."' host='".Util::getMiniHost()."' version='".$v."' type=\"text/javascript\"  src='".$this->staticServerHost."miniLoad.php?t=js&c=".$this->controller."&a=".$this->action."&v=".$serverVersion."&l=".$this->language."' charset=\"utf-8\"></script>";
        $header .= "<link rel=\"stylesheet\" type=\"text/css\"  href='".$this->staticServerHost."miniLoad.php?t=css&c=".$this->controller."&a=".$this->action."&v=".$serverVersion."&l=".$this->language."'/>";
        $this->loadHtml($header);
    }
    /**
     *
     * 根据浏览器的版本返回语言
     * @param $name
     * @return string
     */
    private function getCookie($name){
        $value = NULL;
        if(array_key_exists($name,$_COOKIE)){
            $value = $_COOKIE[$name];
        }
        return $value;
    }
}
//下面这一行不能删除，删除后setcookie不能成功
?>
