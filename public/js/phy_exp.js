$(document).ready(function() {
    var timer;

    (function(){
        var state = getUrlParam("state");
        var code = getUrlParam("code");
        $.ajax({
            url: '/api/students/lab/verifyState',
            type: 'POST',
            dataType: 'json',
            timeout: 5000,
            data: {
                state: state,
                code: code
            },
            success: function(res){
                if (res.status == 200) {
                    showPage(1);
                    return;
                }
                else {
                    showPage(3);
                }
            },
            error: function(err){
                showPage(3);
            }
        })
    }());

    function getUrlParam(sParam){
        var sPageURL = decodeURIComponent(window.location.search.substring(1)),
            sURLVariables = sPageURL.split('&'),
            sParameterName,
            i;

        for (i = 0; i < sURLVariables.length; i++) {
            sParameterName = sURLVariables[i].split('=');
            if (sParameterName[0] === sParam) {
                return sParameterName[1] === undefined ? true : sParameterName[1];
            }
        }
    }

    $("#submit").click(function(){
        if (!checkForm()){
            showPrompt("好粗心！你忘记输入账户或密码啦");
            return;
        }
        var btn = $(this);
        if (btn.attr("lock") != 1){
            btn.attr("lock", 1);
            btn.addClass("weui_btn_disabled");
            btn.text("提交中...");
            var state = getUrlParam('state');
            var data = JSON.stringify({
                "userid" : $("#userid").val(),
                "token" : $("#token").val()
            });
            data = encodeURIComponent(btoa(data));
            $.ajax({
                url: '../src/API/api.php',
                type: 'POST',
                dataType: 'json',
                timeout: 6000,
                data: {
                    "state": state,
                    "data": data
                },
                success: function(res){
                    if (res.status == 200) {
                        showPage(2);
                        return;
                    }
                    else if (res.status == 433) {
                        showPage(3);
                    }
                    else if (res.status == 403) {
                        showPrompt("账号或密码错误，请仔细核对一下哦");
                    }
                    else if (res.status == 423) {
                        showPrompt("冷静，冷静，慢慢来！");
                    }
                    else if (res.status == 443) {
                        showPrompt("你的尝试次数过多，请等5分钟后再试");
                    }
                    else {
                        showPrompt("服务器好像出问题了，请待会儿再试");
                    }
                    /* reset the btn */
                    resetBtn();
                },
                error: function(err){
                    showPrompt("咦，你的网络好像不通...");
                    resetBtn();
                }
            })
        }
    });

    function checkForm(){
        if (
            $("#userid").val().length == 0 ||
            $("#token").val().length == 0
        ) {
            return false;
        }
        return true;
    }

    function resetBtn(){
        var btn = $("#submit");
        setTimeout(function(){
            btn.attr("lock", 0);
            btn.removeClass("weui_btn_disabled");
            btn.text("提交");
        }, 2000);
    }

    function showPage(page){
        var className = ".page-" + page;
        $(".page").addClass("hide");
        $(className).removeClass("hide");
    }

    function showPrompt(str){
        var prompt = $("#prompt");
        prompt.text(str);
        prompt.removeClass("hide");
        clearTimeout(timer);
        timer = setTimeout(function(){
            prompt.addClass("hide");
        }, 3500);
    }

});
