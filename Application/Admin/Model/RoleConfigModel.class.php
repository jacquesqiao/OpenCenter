<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 15-3-10
 * Time: 下午3:27
 * @author 郑钟良<zzl@ourstu.com>
 */

namespace Admin\Model;
use Common\Model\Base;

class RoleConfigModel extends Base
{

    public function addData($data){
        $data=$this->create($data);
        if(!$data) return false;
        $data['update_time']=time();
        $result=$this->add($data);
        return $result;
    }

    public function saveData($map=array(),$data=array()){
        $data['update_time']=time();
        $result=$this->where($map)->save($data);
        return $result;
    }

} 