$(document).ready(function() {
    // 画成绩的
    var CanvasWidth = 0,
        CanvasHeight = 0;
    var scoreObj = {};

    function fillImg(str, num) {
        var strCanvas = $(".score-canvas");
        strCanvas = strCanvas[num];
        var ctx = strCanvas.getContext("2d");
        //resize canvas width and height
        ctx.canvas.width = CanvasWidth;
        ctx.canvas.height = CanvasHeight;
        ctx.font = "6em sans-serif";
        // ctx.font = "5em matchFont";
        str = "" + str; //成绩输入1
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(str, CanvasWidth / 2, CanvasHeight / 2); //成绩输入2
    }

    // 画阴影的 想把画阴影的和擦的放在一起了
    // todo
    function fillShadow(i) {
        var shadowCanvas = $(".score-shadow");
        shadowCanvas = shadowCanvas[i];
        var ctx = shadowCanvas.getContext("2d");
        //resize canvas width and height
        ctx.canvas.width = CanvasWidth;
        ctx.canvas.height = CanvasHeight;

        ctx.fillStyle = "#dbdbdb"; //涂层
        ctx.fillRect(0, 0, 2 * CanvasWidth, 2 * CanvasHeight);

    }

    function clearShadow() {
        var _this, ctx;
        var shadowCanvas = $(".score-shadow");
        var mousedown = false;

        function eventDown(e) {
            e.preventDefault(); //通知浏览器不要执行与事件关联的默认动作。
            mousedown = true;
        }

        function eventUp(e) {
            e.preventDefault();
            mousedown = false;
        }

        function eventMove(e) {
            e.preventDefault();
            if (mousedown) {
                if (!!event.touches) {
                    e = event.touches[0];
                    //每个触摸事件对象中都包括了touches这个属性，用于描述前位于屏幕上的所有手指的一个列表那么获取当前事件对象我们习惯性的使用  event = event.touches[0] 
                };

                var offsetX = Math.floor(_this.offset().left),
                    offsetY = Math.floor(_this.offset().top);
                var x = (e.clientX + document.body.scrollLeft || e.pageX) - offsetX || 0,
                    y = (e.clientY + document.body.scrollTop || e.pageY) - offsetY || 0;
                if (x > 15) {
                    x = x + 15;
                };
                with(ctx) {
                    beginPath();
                    arc(x, y, 15, 0, Math.PI * 2);
                    if (x > CanvasWidth / 3 && x < CanvasWidth / 3 * 2 && y < CanvasHeight) {
                        if (_this.attr("data-ok") !== "ok") {
                            var openId = getUrlParam("openid");
                            var className = _this.parent().parent().children("h1").text(); //TODO
                            // console.log(class_name);
                            $.ajax({ // 擦除成功 发送参数
                                url: 'api.php',
                                type: 'POST',
                                dataType: 'json',
                                data: {
                                    openid: openId,
                                    act: 'checked',
                                    data: className
                                },
                                success: function(res) {
                                    console.log("保存成功的效果");
                                },
                                error: function(err) {
                                    console.log(err);
                                    console.log("请求发送失败！");
                                }
                            });
                            console.log("saved");
                            //触发特效
                            hua("hua");
                        };

                        if (!_this.prop("data-ok")) {
                            _this.attr("data-ok", "ok");
                        };

                    };
                    fill();
                }
            }
        }
        $(".main-contain").on('mousedown', '.score-shadow', function(event) {
            _this = $(this);
            ctx = _this[0].getContext('2d');
            ctx.globalCompositeOperation = 'destination-out';
            eventDown(event);
        });
        $(".main-contain").on('mouseup', '.score-shadow', eventUp);
        $(".main-contain").on('mousemove', '.score-shadow', eventMove);

        $(".main-contain").on('touchstart', '.score-shadow', function(event) {
            _this = $(this);
            ctx = _this[0].getContext('2d');
            ctx.globalCompositeOperation = 'destination-out';

            eventDown(event);
        });
        $(".main-contain").on('touchend', '.score-shadow', eventUp);
        $(".main-contain").on('touchmove', '.score-shadow', eventMove);
    }
    // 获取url的openid
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
    // 渲染成绩的html
    function fillHtml() {
        // console.log("技术支持：比特工场。欢迎有互联网梦想的同学加入我们！")
        var openId = getUrlParam("openid");
        $.ajax({
                url: 'F:/wamp64/www/PHP/guaguale/api.php',
                type: 'GET',
                dataType: 'json',
                timeout: 15000,
                data: {
                    openid: openId
                },
            })
            .done(function(res) {
                if (res.status == 200) {
                    var cardLi = "",
                        tempArr = [];
                    var data = res.data;
                    for (i in data) {
                        var hideShadow = "";
                        if (data[i].is_checked) {
                            hideShadow = "hide";
                        }
                        cardLi += '<div class="card-li">';
                        cardLi += '<h1 class="black-color">' + data[i].class_name + '</h1>'; //scoreName
                        cardLi += '<div class="score-show">' +
                            '<canvas class="score-canvas">浏览器暂不支持该功能</canvas>' +
                            '<canvas class="score-shadow ' + hideShadow + '">浏览器暂不支持该功能</canvas>' +
                            //
                            '<canvas id="hua" width="0" height="0">浏览器暂不支持该功能</canvas>' +
                            '</div>';
                        cardLi += '<div class="dash-border"></div>';
                        cardLi += '<h3 class="black-color">【' + data[i].class_type + '】</h3>'; //scoreClass
                        cardLi += '</div>';
                    };
                    $(".card-ul").empty().append(cardLi);
                    $(".main-contain").removeClass("hide");
                    $(".prompt").fadeOut(1000);
                    CanvasWidth = $(".score-canvas").width();
                    CanvasHeight = $(".score-canvas").height();
                    for (i in data) {
                        fillShadow(i);
                        fillImg(data[i].score, i);
                    };
                    if (res.is_like == false) {
                        $(".score-shadow").addClass("hide");
                        $("#ifLike").prop("checked", false);
                    };
                } else {
                    // no results
                    var className = ".err-" + res.status;
                    $(".loading").addClass("hide");
                    $(className).removeClass("hide");
                }
            })
            .fail(function() {
                //TODO
                $(".loading").addClass("hide");
                $(".err-500").removeClass("hide");
            })
            .always(function() {
                clearShadow();
            });
    }

    function initNotLike() {
        // 弹窗是否出现
        $("#ifLike").click(function() {
            var ifLikeChecked = $(this).prop("checked");
            if (!ifLikeChecked) {
                $(".ui-dialog").addClass('show');
            } else {
                fnNotLikeAndClose("1");
            }
        });
        // 点击弹窗出现的btn按钮 处理结果
        $(".ui-dialog").delegate('button', 'click', function() {
            var _this = $(this);
            _this.parent().parent().parent().removeClass('show');
            var _btnIndex = _this.index();
            if (_btnIndex == 0) { // 取消
                $("#ifLike").prop("checked", true);
            } else { // 确定关闭
                fnNotLikeAndClose("0");
            }
        });
        //关闭按钮
        $(".not-like-closebtn").click(function() {
            $(this).parent().addClass('hide');
        });
    }

    // 关闭之后 确认的 函数
    function fnNotLikeAndClose(isLike) {
        var openId = getUrlParam("openid");
        if (isLike == 1) {
            var likeMark = 'true';
        } else {
            var likeMark = 'false';
        }
        $.ajax({
            url: 'api.php',
            type: 'POST',
            dataType: 'json',
            data: {
                openid: openId,
                act: 'like',
                data: likeMark
            },
            success: function(res) {
                $(".score-shadow").fadeOut();
                // $(".score-shadow").addClass('hide');
            },
            error: function(err) {
                console.log("失败，请检查你的网络问题。");
            }
        });
        //console.log( "switch: "+isLike );
    }
    //TODO
    $(".tryagain").click(function() {
        console.log("clicked");
        $(".err-prompt").addClass("hide");
        $(".loading").removeClass("hide");
        setTimeout(function() {
            fillHtml();
            initNotLike();
        }, 1800);
    });
    $(".binding").click(function() {
        var openId = getUrlParam("openid");
        var url = "http://www.stuzone.com/zixunminda/binding/login.php?type=ssfw&tousername=" + openId;
        window.location.href = url;
    });

    (function init() {
        fillHtml();
        initNotLike();
    }());
});
//撒花特效
function hua(id) {
    canvas = document.getElementById(id);
    context = canvas.getContext("2d");
    width = canvas.width = window.innerWidth;
    height = canvas.height = window.innerHeight;

    particle = [];
    colors = [
        '#f44336', '#e91e63', '#9c27b0', '#673ab7', '#3f51b5',
        '#2196f3', '#03a9f4', '#00bcd4', '#009688', '#4CAF50',
        '#8BC34A', '#CDDC39', '#FFEB3B', '#FFC107', '#FF9800',
        '#FF5722', '#795548'
    ];

    for (var i = 0; i < 300; i++) {
        //生成碎片数组
        particle.push({
            x: width / 2,
            y: height / 2,
            boxW: randomRange(5, 20), //产生范围内随机数
            boxH: randomRange(5, 20),
            size: randomRange(2, 8),
            velX: randomRange(-8, 8),
            velY: randomRange(-50, -10),
            angle: convertToRadians(randomRange(0, 360)), //角度转化为弧度
            color: colors[Math.floor(Math.random() * colors.length)], //颜色
            anglespin: randomRange(-0.2, 0.2),
            draw: function() {
                context.save();
                context.translate(this.x, this.y);
                context.rotate(this.angle);
                context.fillStyle = this.color;
                context.beginPath();
                context.fillRect(this.boxW / 2 * -1, this.boxH / 2 * -1, this.boxW, this.boxH);
                context.fill();
                context.closePath();
                context.restore();
                this.angle += this.anglespin;
                this.velY *= 0.999;
                this.velY += 0.8; //碎片下落速度
                this.x += this.velX;
                this.y += this.velY;
                if (this.y < 0) {
                    this.velY *= -0.2;
                    this.velX *= 0.9;
                };
                if (this.y > height) {
                    this.anglespin = 0;
                    this.y = height + 99; //+99为了隐藏碎片
                    this.velY *= -0.2;
                    this.velX *= 0.9;
                };
                if (this.x > width || this.x < 0) {
                    this.velX *= -0.5;
                };
            },
        });
    }


    function drawScreen() {
        //利用数组产生碎片
        for (var i = 0; i < particle.length; i++) {
            particle[i].draw();

        }
    }

    function randomRange(min, max) {
        return min + Math.random() * (max - min);
    }

    function convertToRadians(degree) {
        return degree * (Math.PI / 180);
    }

    function update() {
        context.clearRect(0, 0, width, height);
        drawScreen();
        times = requestAnimationFrame(update); //js帧动画
    }
    update();


    //重置

    timex = setTimeout(() => {
        canvas.width = 0;
        canvas.height = 0;
        cancelAnimationFrame(times);
        clearTimeout(timex);
    }, 1700);

}