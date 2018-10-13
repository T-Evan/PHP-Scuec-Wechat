<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>大物实验账号绑定|资讯民大</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <style type="text/css">
        * {
            margin: 0;
            padding: 0;
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
        }
        body {
            background: #FCFCFC;
        }
        .container {
            font-size: 1em;
        }
        .page {
            position: absolute;
            width: 100%;
            top: 0;
            left: 0;
        }
        .header {
            margin: 0 auto;
            margin-top: 70px;
        }
        .header img {
            margin: 0 auto;
            display: block;
            padding: 20px;
            width: 100px;
            border: #F2F2F2 1px solid;
        }
        .error {
            height: 2.5em;
            background: #CC0000;
            font-size: 1em;
            color: #fff;
            line-height: 2.5em;
            text-align: center;
        }
        .full-screen-prompt {
            margin-top: 47px;
        }
        .tips {
            font-size: 0.8em;
            margin: 1em;
            color: #888;
        }
        .hide {
            display: none;
        }
        img {
            width: 100px;
        }
    </style>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/weui/1.1.2/style/weui.min.css" rel="stylesheet">
</head>
<body>
<div class="container">
    <div class="page-0 page error hide" id="prompt"></div>
    <div class="page-1 fill-form page hide">
        <div class="header">
            <img src="{{ asset('img/rocket.svg') }}" alt="lab" />
        </div>
        <div class="weui_cells weui_cells_form">
            <div class="weui_cell">
                <div class="weui_cell_hd"><label class="weui_label">账号</label></div>
                <div class="weui_cell_bd weui_cell_primary">
                    <input id="userid" class="weui_input" type="number" pattern="[0-9]*" placeholder="请输入大物实验账号">
                </div>
            </div>
            <div class="weui_cell">
                <div class="weui_cell_hd"><label class="weui_label">密码</label></div>
                <div class="weui_cell_bd weui_cell_primary">
                    <input id="token" class="weui_input" type="password" placeholder="请输入密码">
                </div>
            </div>
        </div>
        <div class="weui_btn_area">
            <a id="submit" class="weui_btn weui_btn_primary">确定</a>
        </div>
        <div class="tips">我们充分了解用户对个人隐私的要求，您的账户名与密码将被严格保密。</div>
    </div>
    <div class="page-2 full-screen-prompt hide">
        <div class="msg">
            <div class="weui_msg">
                <div class="weui_icon_area"><i class="weui_icon_success weui_icon_msg"></i></div>
                <div class="weui_text_area">
                    <h2 class="weui_msg_title">绑定成功</h2>
                    <p class="weui_msg_desc">请关闭本页面，点击"大物实验"菜单项，或者发送关键字"大物实验"查看结果。</p>
                </div>
            </div>
        </div>
    </div>
    <div class="page-3 full-screen-prompt hide">
        <div class="msg">
            <div class="weui_msg">
                <div class="weui_icon_area"><i class="weui_icon_warn weui_icon_msg"></i></div>
                <div class="weui_text_area">
                    <h2 class="weui_msg_title">哎呀，小塔凌乱了</h2>
                    <p class="weui_msg_desc">请关闭本页面，然后重新访问</p>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
<script type="text/javascript" src="{{ asset('js/phy_exp.js') }}"></script>
</body>
</html>
