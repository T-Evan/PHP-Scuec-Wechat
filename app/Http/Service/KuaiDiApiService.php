<?php
/**
 * Created by PhpStorm.
 * User: YiWan
 * Date: 2018/6/17
 * Time: 18:58
 */

namespace App\Http\Service;

class KuaiDiApiService
{
    private $num;

    public function __construct($num)
    {
        $this->num=$num;
    }

    public function getKuaiDiInfo($com, $num)
    {
        $rand_num = rand(1000, 9000);
        $url = "http://www.kuaidi100.com/query?type=".$com."&postid=".$num."&id=1&valicode=&temp=0.".
            $rand_num."522651".$rand_num."61";
        $info_json_data = HelperService::get($url, null, 'http://www.kuaidi100.com/auto.shtml')['res']
            ->getbody();
        $info_array_data = json_decode($info_json_data, true);
        return $info_array_data;
    }

    public function guessKuaiDiInfo()
    {
        // 从单号猜测快递公司，找出最可能的一个并输出
        $url = "http://www.kuaidi100.com/autonumber/auto?num=".$this->num;
        $com_joson_data=HelperService::get($url, null, 'http://www.kuaidi100.com/auto.shtml')['res']
            ->getbody();

        $com_array_data = json_decode($com_joson_data, true);
        // 从单号猜测快递公司
        $guess_com_count = count($com_array_data);
        // 如果只猜出一个快递公司则说明极有可能就是这个
        if ($guess_com_count == 1) {
            $com = $com_array_data[0]['comCode'];
            $kuaidi_info = $this->getKuaiDiInfo($com, $this->num);
            return $kuaidi_info;
        } else { // 如果有多个结果就一个个的试一下（最多试3次）
            $for_count = $guess_com_count > 3 ? 3 : $guess_com_count;

            for ($i=0; $i < $for_count; $i++) {
                $com = $com_array_data[$i]['comCode'];
                $kuaidi_info = $this->getKuaiDiInfo($com, $this->num);
                if ($kuaidi_info['status'] == '200') {
                    return $kuaidi_info;
                    break;
                } else {
                    if ($i == $for_count-1) { // 最后一个不做试探，直接输出（目测单号错误或者是个新单号）
                        return $kuaidi_info;
                    }
                }
            }
        }
    }

    public function kuaiDi()
    {
        if (strlen($this->num)) {
            $info_array = $this->guessKuaiDiInfo();
            $status = $info_array['status'];
            $com = $info_array['com'];   // 公司
            $com_cn_array = array(
                'shentong' => "申通",
                'ems' => "EMS",
                'shunfeng' => "顺丰",
                'yunda' => "韵达",
                'yuantong' => "圆通",
                'zhongtong' => "中通",
                'huitongkuaidi' => "汇通",
                'tiantian' => "天天",
                'zhaijisong' => "宅急送",
                'cces' => "国通",
                'quanyikuaidi' => "全一",
                'debangwuliu' => "德邦",
                'shenghuiwuliu' => "盛辉",
                'youzhengguonei' => "邮政包裹/平邮",
                'youshuwuliu' => "优速",
                'ganzhongnengda' => "能达",
                'shengfengwuliu' => "盛丰",
                'lianbangkuaidi' => "联邦",
                'rufengda' => "如风达",
                'emsguoji' => "EMS-国际件",
                'neweggozzo' => "新蛋奥硕",
                'vancl' => "凡客",
                'guotongkuaidi' => "国通",
                'feiyuanvipshop' => "飞远",
                'suer' => "速尔",
                'kuaijiesudi' => "快捷速递",
            );
            $com_cn = (isset($com_cn_array[$com])) ? $com_cn_array[$com] : $com; // 如果存在中文则将快递名称转换成中文
            $nu = $info_array['nu'];     // 快递单号
            $kuaidi_info_url = 'http://m.kuaidi100.com/result.jsp?nu='.$nu;
            $kuaidi_info_str = "--数据来源快递100--\n".'☞<a href="'.$kuaidi_info_url.'">完整物流信息</a>'; // 完整物流
            $kuaidi_hand_str = '<a href="http://m.kuaidi100.com/">手动查询</a>';  // 手动查询地址
            if ($status=='200') {
                $state = $info_array['state'];   // 运单状态代码
                $state_array = array("在途，即货物处于运输过程中", "揽件，货物已由快递公司揽收并且产生了第一条跟踪信息",
                    "疑难，货物寄送过程出了问题", "签收，收件人已签收", "退签，即货物由于用户拒签、超区等原因退回，而且发件人已经签收",
                    "派件，即快递正在进行同城派件", "退回，货物正处于退回发件人的途中");
                $state_str = $state_array[$state];    // 运单状态
                $data = $info_array['data']; // 运单详情
                $data_num = count($data);
                // 取出3条最新运单信息
                $data_num = ($data_num > 3) ? 3 : $data_num;
                for ($i=0,$data_str=''; $i < $data_num; $i++) {
                    $data_str .= $data[$i]['time']."\n".$data[$i]['context']."\n";
                }

                $content = sprintf(
                    "%s-%s\n状态: %s\n---最新物流信息---\n%s\n%s\n查询结果有误？尝试一下%s",
                    $com_cn,
                    $nu,
                    $state_str,
                    $data_str,
                    $kuaidi_info_str,
                    $kuaidi_hand_str
                );
            } else {
                $content = "没有查询到物流信息，请检查运单号是否输入准确，然后再试一次。\n这也可能是一个新的单号，暂时还未产生物流信息，你可以稍后再试或尝试".$kuaidiHandStr;
            }
        } else {
            $content = "输入括号里的关键字【快递+单号】即可查询物流信息，如【快递966650707261】。不用指明具体快递公司哦，是不是很酷呀 /::+";
        }

        return $content;
    }
}
