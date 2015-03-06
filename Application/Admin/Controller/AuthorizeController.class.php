<?php

namespace Admin\Controller;


use Admin\Builder\AdminConfigBuilder;
use Admin\Builder\AdminListBuilder;
use Vendor\requester;

/**
 * Class AuthorizeController  后台授权控制器
 * @package Admin\Controller
 * @author:xjw129xjt(肖骏涛) xjt@ourstu.com
 */
class AuthorizeController extends AdminController
{

    public function ssoSetting()
    {

        $admin_config = new AdminConfigBuilder();
        $admin_config->callback("ssoCallback");
        $data = $admin_config->handleConfig();

        $admin_config->title('单点登录配置')


            ->keyRadio('SSO_SWITCH', '单点登录开关', '单点登录的开关', array(0 => '关闭单点登录', 1 => '开启单点登录', 2 => '作为用户中心开启单点登录'))
            ->keyTextArea('SSO_CONFIG', '单点登录配置', '单点登录配置文件中的配置（当开关为开启单点登录时有效，不包括作为用户中心开启单点登录）')


            ->buttonSubmit('', '保存')->data($data);
        $admin_config->display();
    }

    public function ssoCallback($config){

        $str = "<?php \n return ". $config['SSO_CONFIG'];
        file_put_contents('./OcApi/oc_config.php',$str);
    }

    private function check_link($url)
    {
        $requester = new requester($url);
        $requester->charset = "utf-8";
        $requester->content_type = 'application/x-www-form-urlencoded';
        $requester->data = "username=xzg&passwd=xzg";
        $requester->enableCookie = true;
        $requester->enableHeaderOutput = false;
        $requester->method = "post";

        $arr = $requester->request();
        return $arr[1];
    }

    public function ssoList()
    {
        //读取规则列表
        $map = array('status' => array('EGT', 0));
        $model = M('sso_app');
        $appList = $model->where($map)->order('id asc')->select();

        foreach ($appList as &$v) {
            $url = $v['url'] . '/' . $v['path'].'?code='.urlencode(think_encrypt('action=test'));
            $arr = $this->check_link($url);
            $v['link_status'] = $v['status'] ==1?( $arr === 'success' ? '<span style="color:green">连接成功</span>' : '<span style="color:red">连接失败</span>'):'<span style="color:red">连接失败-已被禁用</span>';
        }
        unset($v);
        //显示页面
        $builder = new AdminListBuilder();
        $builder->title('单点登录应用列表')
            ->buttonNew(U('editSsoApp'))
            ->setStatusUrl(U('setSsoAppStatus'))->buttonEnable()->buttonDisable()->buttonDelete()
            ->keyId()
            ->keyTitle()
            ->keyText('url', '网站路径')
            ->keyText('path', 'api目录')
            ->keyStatus()
            ->keyText('link_status', '连接状态')
            ->keyDoActionEdit('editSsoApp?id=###')
            ->data($appList)
            ->display();
    }
    public function editSsoApp(){
        $aId = I('id',0,'intval');
        $model = D('Sso');
        if (IS_POST) {
            $data['title'] = I('post.title','','op_t');
            $data['status'] = I('post.status',1,'intval');
            $data['url'] = I('post.url','','op_t');
            $data['path'] = I('post.path','','op_t');
            if ($aId != 0) {
                $data['id'] = $aId;
                $res = $model->editApp($data);
                $config = $this->getConfig();
                $config['APP_ID'] = $aId;
            } else {
                $res = $model->addApp($data);
                $config['APP_ID'] = $res;
            }
            D('sso_app')->where(array('id'=>$config['APP_ID']))->setField('config',serialize($config));
            $this->success(($aId == 0 ? '添加' : '编辑') . '成功');
/*            if ($res) {
                $this->success(($aId == 0 ? '添加' : '编辑') . '成功');
            } else {
                $this->error(($aId == 0 ? '添加' : '编辑') . '失败');
            }*/
        } else {
            $builder = new AdminConfigBuilder();
            if ($aId != 0) {
                $app = $model->getApp(array('id'=>$aId));
            } else {
                $app = array('status' => 1);
            }
            $app['config'] = $this->parseConfigToString(unserialize($app['config']));
            $builder->title(($aId == 0 ? '新增' : '编辑').'应用')->keyId()->keyText('title', '名称')
                ->keyText('url', '根目录','需要填写http://，末尾不要加"/"')
                ->keyText('path', '路径')
                ->keyStatus()
                ->keyLabel('config', '配置信息','保存后将以下内容复制到应用下的配置文件中。')
                ->data($app)
                ->buttonSubmit(U('editSsoApp'))->buttonBack()->display();
        }
    }


    public function setSsoAppStatus($ids, $status){
        $builder = new AdminListBuilder();
        $builder->doSetStatus('sso_app', $ids, $status);
    }

    private function parseConfigToString($config=array()){

        $note['SSO_SWITCH']= '单点登录开关';
        $note['SSO_DB_HOST']= '用户中心主机';
        $note['SSO_DB_NAME']= '用户中心数据库名';
        $note['SSO_DB_USER']= '用户中心数据库用户名';
        $note['SSO_DB_PWD']= '用户中心数据库密码';
        $note['SSO_DB_PORT']= '用户中心数据库端口';
        $note['SSO_DB_PREFIX']= '用户中心数据库前缀';
        $note['SSO_DATA_AUTH_KEY']= '用户中心数据库密钥';
        $note['OC_HOST'] = 'Ocenter主机地址';
        $note['APP_ID']= '应用ID';
        $note['OC_SESSION_PRE']= 'session前缀';

        $str = 'array(<br>';
        foreach($config as $key=>$val){
            $str .='\''.$key.'\'=>\''.$val.'\', //'.$note[$key].'<br>';
        }
        $str .= ');';
        return $str;
    }
    private function getConfig(){
        $db_config =  require( './Conf/common.php');
        $config = array(
            'SSO_SWITCH'=>1,
            'SSO_DB_HOST'=>$db_config['DB_HOST'],
            'SSO_DB_NAME'=>$db_config['DB_NAME'],
            'SSO_DB_USER'=>$db_config['DB_USER'],
            'SSO_DB_PWD'=>$db_config['DB_PWD'],
            'SSO_DB_PORT'=>$db_config['DB_PORT'],
            'SSO_DB_PREFIX'=>$db_config['DB_PREFIX'],
            'SSO_DATA_AUTH_KEY'=>$db_config['DATA_AUTH_KEY'],
            'OC_HOST'=>'http://' . $_SERVER['HTTP_HOST'].__ROOT__ ,
            'OC_SESSION_PRE'=>$db_config['SESSION_PREFIX'],
        );
        return $config;
    }


}