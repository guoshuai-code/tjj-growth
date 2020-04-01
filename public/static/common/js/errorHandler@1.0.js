(function(win){

    var endTime = ''; //启动时间
    var u = navigator.userAgent; //用户设备信息
    var ios = !!u.match(/\(i[^;]+;( U;)? CPU.+Mac OS X/);
    var Android = u.indexOf('Android') > -1 || u.indexOf('Linux') > -1;
    var trident = u.indexOf('Trident') > -1; //IE内核
    var presto = u.indexOf('Presto') > -1; //opera内核
    var webKit = u.indexOf('AppleWebKit') > -1; //苹果、谷歌内核
    var gecko = u.indexOf('Gecko') > -1 && u.indexOf('KHTML') == -1; //火狐内核
    var safari =u.indexOf('Safari') == -1; //safari
    var mobile = !!u.match(/AppleWebKit.*Mobile.*/) || !!u.match(/AppleWebKit/); //是否为移动终端
    var os = ios ? 'ios' : Android ? 'Android' : trident ? 'Trident' : presto ? 'Presto' : webKit ? 'AppleWebKit' : gecko ? 'Gecko' : safari ? 'safari' : 'other'; //操作系统
    // var networkStr = u.match(/NetType\/\w+/) ? u.match(/NetType\/\w+/)[0] : 'NetType/other'; //网络状态
    // networkStr = networkStr.toLowerCase().replace('nettype/', ''); //网络状态

    win.TJJ_BUGCOLLECT = [];
    // win.onerror = function (message, source, lineno, colno, error){
    //     console.log(message, source, lineno, colno, error)
    //     win.TJJ_BUGCOLLECT.push({
    //         message: error.toString(),
    //         uri: source,
    //         // lineno: lineno,
    //         // colno: colno,
    //         // error: error
    //     })
    // }

    // 监听资源加载错误(JavaScript Scource failed to load)
    win.addEventListener('error', function(event) {
        console.log(event)
        win.TJJ_BUGCOLLECT.push({
            message: (event.error && event.error.stack) || event.message || event.type + ' in ' + event.target.outerHTML,
            uri: event.target.baseURI || location.href,
        })
    }, true);

    //首次进入系统，加载完成后执行
    win.onload = function(){
        var title = document.title; // 标题
        // 程序启动信息
        // var errorObj = {
        //     title: title,
        //     userAgent: u,
        //     errorCode: TJJ_BUGCOLLECT.length > 0 ? '-1' : '200',
        //     endTime: new Date().getTime(),
        //     os: os,
        //     networkStr: networkStr,
        //     errorArr: TJJ_BUGCOLLECT
        // };
        // // console.log('onload');
        // // console.log('loadedTime', loadedTime);
        // // 创建标签存储信息
        // var script = document.createElement('script');
        // script.id = "tjjFissileErrorObj";
        // script.type = "text/javascript";
        // script.innerHTML = 'tjjFissileBuger=' + JSON.stringify(errorObj);
        // document.body.appendChild(script)

        if(TJJ_BUGCOLLECT.length > 0){
            errorLogUpload({
                logLevel: 4,
                errType: 1,
                codeError: {
                    message: (function(){
                        var _msg = '';
                        TJJ_BUGCOLLECT.map(function(item){
                            _msg += item.message + ' && '
                        })
                        return _msg
                    })(),
                    uri: TJJ_BUGCOLLECT[0].uri
                }
            });
        }
    };

    // 获取url参数(&符号拼接参数)
    function getQueryString(name){
        var reg = new RegExp("(^|&)" + name + "=([^&]*)", "ig");
        var r;
        r = window.location.search.substr(1).match(reg);
        r == 'null' || r == 'undefined' ? r = null : '';
        if (r){
            let val = r[r.length-1].split('=')[1];
            return val == 'null' || val == 'undefined' ? null : val;
        }
        // if (r) return unescape(r[2]);
        r = getParams(name);
        r == 'null' || r == 'undefined' ? r = null : '';
        if (r) return r;
        return null;
    }
    // 获取url参数(/符号拼接参数)
    function getParams(key) {
        var url = window.location.pathname;
        var arr = url.split('/');
        var index = arr.lastIndexOf(key);
        return (index < 0 ? null : arr[index + 1]);
    }
// 获取网络状态
function getNetWork(){
    var networkStr = navigator.userAgent.match(/NetType\/\w+/) ? navigator.userAgent.match(/NetType\/\w+/)[0] : 'NetType/'; //网络状态
    networkStr = networkStr.toLowerCase().replace('nettype/', ''); //网络状态
    var connection = navigator.connection||navigator.mozConnection||navigator.webkitConnection||{tyep:'unknown'};
    var netWorkType = connection.type;
    return networkStr || netWorkType || 'unknown'
}
    // 错误上报
    win.errorLogUpload = function(obj){
        var networkStr=getNetWork();
        var uuid = getQueryString('version') || '';
        var xhr = new XMLHttpRequest();
        // console.log('报错：',obj)
        var params = Object.assign({
            base: {
                title: document.title || '',
                userAgent: u || '',
                logTime: new Date().getTime() || '', // 日志时间
                os: 'h5',
                appVer: getQueryString('version') || '',
                webId: uuid, //设备号
                token: getQueryString('token') || '',
                uid: getQueryString('user_id') || '', //用户id
                netType: networkStr || '', // 网络状态
                sysVer: '',
                imei: '',
                uuid: uuid,
                deviceId: uuid,
                chan: '',
                appType: 4,
                traceId: '',
                sessionId: getQueryString('sessionid') || getQueryString('session_id') || '',
                logType: 5,
                cuid: uuid,
                login_mode: '',

            },
        }, obj);
        // console.log('报错：', params)
        console.log('报错：', JSON.stringify(params))
        // xhr.open("POST", "http://app-log.tjjshop.cn/app/log", true);
        if(window.location.href.indexOf('fissile.taojiji.com') != -1 || window.location.href.indexOf('fissile.tjjshop.cn') != -1){
            xhr.open("POST", "/safety.php/index/pageLog", true);
        } else if(window.location.href.indexOf('growth.taojiji.com') != -1){
            xhr.open("POST", "https://fissile.taojiji.com/safety.php/index/pageLog", true);
        }else{
            xhr.open("POST", "https://fissile.tjjshop.cn/safety.php/index/pageLog", true);
        }

        //设置发送数据的请求格式
        // xhr.setRequestHeader('content-type', 'application/json');
        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4 && (xhr.status == 200 || xhr.status == 304)) {
                //根据服务器的响应内容格式处理响应结果
                // if(xhr.getResponseHeader('content-type')==='application/json'){
                //     var result = JSON.parse(xhr.responseText);
                //     //根据返回结果判断验证码是否正确
                //     if(result.code===-1){
                //         alert('验证码错误');
                //     }
                // } else {
                //     console.log(xhr.responseText);
                // }
                console.log('上报成功!');
            }
        }
        // let sendData = {"tjjFissileBuger":errorObj,"safety_microtime":getQueryString("safety_microtime")};
        //将用户输入值序列化成字符串
        xhr.send(JSON.stringify(params));
    }

})(window)
