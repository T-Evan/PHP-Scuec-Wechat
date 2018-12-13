$(document).ready(function() {
    var CanvasWidth = 0,
        CanvasHeight = 0;


    function fillImg(str, num) {
        var strCanvas = $(".score-canvas")[num];
        strCanvas.setAttribute("data-scorenum", str);
        var ctx = strCanvas.getContext("2d");
        //resize canvas width and height
        ctx.canvas.width = CanvasWidth;
        ctx.canvas.height = CanvasHeight;
        ctx.font = "6em sans-serif";
        // ctx.font = "5em matchFont";
        str = "" + str;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(str, CanvasWidth / 2, CanvasHeight / 2); //成绩输入2
    }


    var openId = getUrlParam("openid");
    $.ajax({ //主体请求
        url: '../api.php',
        type: 'GET',
        dataType: 'json',
        timeout: 15000,
        data: {
            openid: openId
        },
        success: function(res) {
            bb(res);
            banding();
        },
        error: function(err) {
            console.log(err);
        }
    });

    function bb(res) {
        if (res.status == 200) {
            var cardLi = "",
                tempArr = [];
            var data = res.data;
            for (i in data) {
                var hideShadow = "";
                if (data[i].is_checked) {
                    hideShadow = "hide";
                }
                var hiddenBlack = "";
                if (data[i].hidden == true) {
                    hiddenBlack = "hide1";
                }
                cardLi += '<div class="card-li ' + hiddenBlack + '">';
                cardLi += '<img class="li-img-r"  src="img/return.png">'
                cardLi += '<h1 class="black-color">' + data[i].class_name + '</h1>'; //scoreName
                cardLi += '<div class="score-show">' +
                    '<canvas class="score-canvas">浏览器暂不支持该功能</canvas>' +
                    '<canvas class="score-shadow ' + hideShadow + '">浏览器暂不支持该功能</canvas>' +

                    '</div>';
                cardLi += '<div class="dash-border"></div>';
                cardLi += '<h3 class="black-color">【' + data[i].class_type + '】</h3>'; //scoreClass
                cardLi += '</div>';
                // }
            };
            $(".card-ul").empty().append(cardLi);
            CanvasWidth = $(".score-canvas").width();
            CanvasHeight = $(".score-canvas").height();
            for (i in data) {
                // if (data[i].hidden == true) {
                fillImg(data[i].score, i);
                // }
            };
        }
    }

    function banding() { //解决绑定早于dom生成的问题 可优化
        $(".li-img-r").bind("click", function() {
            let cradLi = $(this).parent();
            let mod = '<div class = "mod">' +
                '<div class = "sec">' +
                '<img src = "img/emoj.png">' +
                '<p> 是否将该成绩移出黑名单？ </p>' +
                '<div class = "btn-box">' +
                '<div class = "mod-btn mod-btn-y">是</div>' +
                '<div class = "mod-btn mod-btn-n">否</div> ' +
                '</div> </div><div>';
            $(".main-contain").append(mod);
            $(".mod-btn-n").click(() => {
                $(".mod").remove();
            });
            $(".mod-btn-y").click(() => {
                _hmt.push(['_trackEvent', '黑名单', '移出']); //百度统计事件转化代码
                var className = cradLi.children("h1").text();
                var score = cradLi.children(".score-show").children(".score-canvas").data("scorenum");
                var openId = getUrlParam("openid");
                $.ajax({ //移出请求
                    url: '../hide.php',
                    type: 'PUT',
                    dataType: 'json',
                    data: {
                        openid: openId,
                        course_name: className,
                        course_score: score,
                        discard: true
                    },
                    success: function(res) {
                        cradLi.fadeOut(1000, () => {
                            cradLi.remove()
                        });
                        $(".mod").remove();
                    },
                    error: function(err) {
                        console.log("失败，请检查你的网络问题。");
                        let _mod = '<p style="font-size:1.75em;">黑历史移出失败。</p>';
                        $(".sec").empty().append(_mod);
                        setTimeout(() => {
                            $(".mod").remove();
                        }, 1500);
                    }
                });
            });
        });
    }

    function getUrlParam(sParam) {
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
});