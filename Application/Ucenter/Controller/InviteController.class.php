<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 15-3-25
 * Time: 上午10:19
 * @author 郑钟良<zzl@ourstu.com>
 */

namespace Ucenter\Controller;


use Think\Controller;

class InviteController extends BaseController
{
    protected $mInviteModel;
    protected $mInviteTypeModel;
    protected $mInviteBuyLogModel;
    protected $mInviteUserInfoModel;

    public function _initialize()
    {
        parent::_initialize();
        $this->mInviteModel=D('Ucenter/Invite');
        $this->mInviteTypeModel=D('Ucenter/InviteType');
        $this->mInviteBuyLogModel=D('Ucenter/InviteBuyLog');
        $this->mInviteUserInfoModel=D('Ucenter/InviteUserInfo');
    }

    public function index()
    {
        //获取邀请码类型列表
        $typeList=$this->mInviteTypeModel->getUserTypeList();
        $this->assign('invite_type_list',$typeList);
        $this->defaultTabHash('invite');
        $this->assign('type','index');
        $this->display();
    }

    public function invite()
    {
        $this->defaultTabHash('invite');
        $this->assign('type','invite');
        $this->display();
    }

    public function info()
    {
        $this->defaultTabHash('invite');
        $this->assign('type','info');
        $this->display();
    }

    public function exchange()
    {
        if(IS_POST){
            $aTypeId=I('post.invite_id',0,'intval');
            $aNum=I('post.exchange_num',0,'intval');
            $this->_checkCanBuy($aTypeId,$aNum);
            $inviteType=$this->mInviteTypeModel->where(array('id'=>$aTypeId))->find();
            D('Ucenter/Score')->setUserScore(array(is_login()),$aNum*$inviteType['pay_score'],$inviteType['pay_score_type'],'dec');//扣积分
            $result=$this->mInviteBuyLogModel->buy($aTypeId,$aNum);
            if($result){
                $this->mInviteUserInfoModel->addNum($aTypeId,$aNum);
                $data['status']=1;
            }else{
                $data['status']=0;
                $data['info']="兑换失败！如有疑问请联系管理员！";
            }
            $this->ajaxReturn($data);
        }else{
            $aId=I('id',0,'intval');
            $can_buy_num=$this->_getCanBuyNum($aId);
            $this->assign('can_buy_num',$can_buy_num);
            $this->assign('id',$aId);
            $this->display();
        }
    }

    /**
     * 判断是否可兑换
     * @param int $inviteType
     * @param int $num
     * @return bool
     * @author 郑钟良<zzl@ourstu.com>
     */
    private function _checkCanBuy($inviteType=0,$num=0)
    {
        $result['status']=0;
        if($num<=0){
            $result['info']="请填写正确的兑换个数！";
            $this->ajaxReturn($result);
        }
        if($inviteType==0){
            $result['info']="参数错误！";
            $this->ajaxReturn($result);
        }
        if($num>($this->_getCanBuyNum($inviteType))){
            $result['info']="您要兑换的名额超过了你当前可兑换的最大值！";
            $this->ajaxReturn($result);
        }
        return true;
    }

    /**
     * 获取可兑换最大值
     * @param int $inviteType
     * @return int
     * @author 郑钟良<zzl@ourstu.com>
     */
    private function _getCanBuyNum($inviteType=0)
    {
        $inviteType=$this->mInviteTypeModel->where(array('id'=>$inviteType))->find();

        //以积分算，获取最多购买
        $max_num_score=query_user('score'.$inviteType['pay_score_type']);
        $max_num_score=intval($max_num_score/$inviteType['pay_score']);
        //以积分算，获取最多购买 end

        //以周期算，获取最多购买
        $map['invite_type']=$inviteType;
        $map['create_time']=array('gt',unitTime_to_time($inviteType['cycle_time'],'-'));
        $buyList=$this->mInviteBuyLogModel->where($map)->select();
        $can_buy_num=0;
        foreach($buyList as $val){
            $can_buy_num+=$val['num'];
        }
        $can_buy_num=$inviteType['cycle_num']-$can_buy_num;
        //以周期算，获取最多购买 end

        $can_buy_num=$max_num_score>$can_buy_num?$can_buy_num:$max_num_score;
        return $can_buy_num;
    }
} 