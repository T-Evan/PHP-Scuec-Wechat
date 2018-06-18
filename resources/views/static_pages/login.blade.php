<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>账号绑定|资讯民大</title>
    <!-- Bootstrap core CSS -->
    <link href="/css/app.css" rel="stylesheet">
</head>
<body>
<div class="container">
    @include('shared._errors')
    @include('shared._messages')
    <form method="POST" action="{{ route('students.store') }}" class="form-signin">
        {{ csrf_field() }}
        <h2 class="form-signin-heading">账号绑定</h2>
        @if ($type === 'ssfw')
            <p>输入您教务系统/研究生管理系统的账号同你的微信账号绑定</p>
        @elseif ($type === 'lib')
            <p>输入您图书馆的账号（用户名为学号）同你的微信账号绑定</p>
        @else

        @endif
        <div class="form-group">
            <input type="text" name="account" class="form-control" placeholder="学号" autofocus value="{{ old('account') }}">
            <input type="hidden" name="type" value={{$type}}>
            <input type="hidden" name="openid" value={{$openid}}>
            <input type="password" name="password" class="form-control" placeholder="密码" value="{{ old('password') }}">
        </div>
        <button class="btn btn-lg btn-primary btn-block" type="submit">绑&nbsp;定</button>

    </form>
    <div class="declare">
        <p>我们理解您的隐私的重要性，并对您的用户名和密码严格保密。</p>
    </div>

</div>
<script type="text/javascript" src="/js/app.js"></script>
</body>
</html>
