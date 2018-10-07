
$('.active,.slogan span').css('font-size',$(window).width()/24+'px');
$('.footer .code').css({'width':$(window).width()/5.5+'px',});
$('.active').css("margin-right",-$(window).width()/20-1+'px');

function getUrlParam(sParam){
  var sPageURL = decodeURIComponent(window.location.search.substring(1)),
        sURLVariables = sPageURL.split('?'),
        sParameterName,
        i;

    for (i = 0; i < sURLVariables.length; i++) {
        sParameterName = sURLVariables[i].split('=');
        if (sParameterName[0] === sParam) {
            return sParameterName[1] === undefined ? true : sParameterName[1];
        }
    }
}

var openid = getUrlParam('openid');
var date =getUrlParam('date');
var day = new Date(parseInt(date) * 1000);

var createActive = function () {
  $('body').prepend("<div class='shadow'></div><div class='dialog'><img src='./img/top.png' class='top-text'></img><p id='active-rank'></p><p id='score'></p></div><img src='./img/icon_add.png' class='cancel'></img>")
  $('.main').after("<div class='active-button'>活动</div>")

  $('.active-button').on('click',function(){
    $('.cancel,.shadow,.dialog').css('display','block')
  })

  $('.cancel').on('click',function(){
    $('.cancel,.shadow,.dialog').css('display','none')
  })
}

//获取天气
var weatherParam = {
    type:'weather',
    date:date
}

$.ajax({
  type: "GET",
  dataType: "json",
  data:weatherParam,
  url: "https://wechat.stuzone.com/iscuecer/lab_query/src/API/sign_post_api.php",
  success:function(result){

    var numtq = result.numtq,
         tq = result.tq,
         day = new Date(parseInt(date) * 1000);
 
        day = "&nbsp;"+day.getFullYear()+"年"+(day.getMonth()+1)+"月"+day.getDate()+"日";
    $('#weather').children('img').attr('src','./img/'+numtq+','+tq+'.png'),
    $('#qw').html(result.qw+"℃");
    $('#tq').html(result.tq+day);

  },

})


//获取排名及时间 获取头像及姓名
var signParam = {
    type:'getsignday',
    date:date,
    openid:openid,
    message:'早起'
};


$.ajax({
  type: "GET",
  dataType: "json",
  data:signParam,
  url: "https://wechat.stuzone.com/iscuecer/lab_query/src/API/sign_post_api.php",
  success:function(result){
    $('.touxiang').css('background-image','url('+result.headimgurl+')')
      console.log(result);
    $('#name').html(result.nickname);
    $('#time').children('span').html(result.signtime);
    $('#days').children('span').html(result.signday);
    $('#rank').children('span').html(result.signrank);
   
    var day = new Date(parseInt(date) * 1000);
       
    if(day.getFullYear() == 2018 && day.getMonth() + 1 == 1 && day.getDate() == 15){
      createActive()
    }
    $('#active-rank').html(result.pointrank);
    $('#score').html('积分：'+result.point+'分');
    signday = result.signday;
    signrank = result.signrank;

    //设置分享链接
    $.ajax({
      type: "GET",
      url: "https://wechat.stuzone.com/iscuecer/lab_query/src/API/get_jsApi.php",
      success:function(data){
          var result = eval('('+data+')');
          wx.config({
            appId: 'wxdfff0a26d620924e',
            timestamp:result.timestamp,
            nonceStr: result.nonceStr,
            signature: result.signature,
            jsApiList: ['onMenuShareTimeline','onMenuShareAppMessage','onMenuShareQQ','onMenuShareQZone']
          });

          wx.ready(function(){
    //分享到朋友圈
               var share = {
                  title: '早起打卡|我可是民大第'+signrank+'名的人', // 分享标题
                  imgUrl: 'https://wechat.stuzone.com/iscuecer/lab_query/web/punch_card/img/shoutu4.png' // 分享图标
                }

                if(signday!="undefined"){
                  share.desc='这是我坚持的第'+signday+'天打卡'
                }
            wx.onMenuShareTimeline(share);

    //分享给朋友
            wx.onMenuShareAppMessage({
                title: '早起打卡|我可是民大第'+signrank+'名的人', // 早起打卡|资讯民大
                desc: '这是我坚持的第'+signday+'天打卡', // 这是我坚持的第N天打卡
                imgUrl: 'https://wechat.stuzone.com/iscuecer/lab_query/web/punch_card/img/shoutu3.png' // 早起打卡首页图片
            });

    //分享到qq
            wx.onMenuShareQQ({
                title: '早起打卡|我可是民大第'+signrank+'名的人', // 如上
                desc: '这是我坚持的第'+signday+'天打卡', // 如上
                imgUrl: 'https://wechat.stuzone.com/iscuecer/lab_query/web/punch_card/img/shoutu2.png' // 如上
            });

    //分享到qq空间        
            wx.onMenuShareQZone({
                title: '早起打卡|我可是民大第'+signrank+'名的人', // 如上
                desc: '这是我坚持的第'+signday+'天打卡', // 如上
                imgUrl: 'https://wechat.stuzone.com/iscuecer/lab_query/web/punch_card/img/shoutu1.png' // 如上
            });

          });
      }

    })
 }

})






