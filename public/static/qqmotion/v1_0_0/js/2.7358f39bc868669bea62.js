webpackJsonp([2],{"9nfg":function(t,a,s){"use strict";Object.defineProperty(a,"__esModule",{value:!0});var e=s("4+Dy"),i=s("hEn8"),n=s("oKbl"),c=s("mNlw"),l={name:"gainMoney",data:function(){return{uname:"",msgTxt:"",red_packet_balance:"",withdraw_count:0,user_id:Object(c.g)("user_id"),uuid:Object(c.g)("uuid"),token:Object(c.g)("token"),os:Object(c.g)("os"),listMoney:[{amount:1,active:!0},{amount:10,active:!1},{amount:30,active:!1}]}},computed:{needMoney:function(){return this.listMoney.filter(function(t){return t.active})[0].amount}},components:{ruleComponent:i.a,msgBox:e.a},created:function(){this.userInfo()},methods:{selectMoney:function(t){this.listMoney.forEach(function(t){t.active=!1}),t.active=!0},listMoneyInit:function(){var t=[{amount:1,active:!0},{amount:10,active:!1},{amount:30,active:!1}];switch(this.withdraw_count){case 0:break;case 1:t=[{amount:10,active:!0},{amount:30,active:!1}];break;case 2:default:t=[{amount:30,active:!0}]}this.listMoney=t},confirmWithdraw:function(){var t=this,a={withdraw_amount:100*this.needMoney},s=n.c+"?user_id="+this.user_id+"&uuid="+this.uuid+"&token="+this.token+"&os="+this.os+"&app_resource=0";this.$http.post(s,a,{headers:{}}).then(function(a){1==a.data.result?(t.userInfo(),t.msgTxt="提现成功"):t.msgTxt=a.data.message})},userInfo:function(){var t=this;this.$http.get(n.b,{params:{app_resource:0}}).then(function(a){if(1==a.data.result){var s=a.data.data;s.is_qq_user?(t.red_packet_balance=s.red_packet_balance/100,t.uname=s.uname,t.withdraw_count=s.withdraw_count,t.listMoneyInit()):t.msgTxt="请使用QQ账号登入"}else t.msgTxt=a.data.message}).catch(function(t){console.log("error"),console.log(t)})}}},o={render:function(){var t=this,a=t.$createElement,s=t._self._c||a;return s("div",{staticClass:"gainMoneyPage"},[s("div",{staticClass:"title"},[t._v("提现到QQ钱包账号："+t._s(t.uname))]),t._v(" "),s("div",{staticClass:"main"},[s("p",{staticClass:"gainMoneyTitle"},[t._v("提现金额（元）")]),t._v(" "),s("ul",{staticClass:"ul-money"},t._l(t.listMoney,function(a){return s("li",{staticClass:"li-money",class:a.active?"active-li":"",on:{click:function(s){return t.selectMoney(a)}}},[t._v("\n                "+t._s(a.amount)+"元\n            ")])}),0),t._v(" "),s("p",{staticClass:"balance"},[t._v("可提现余额："+t._s(t.red_packet_balance)+"元")]),t._v(" "),[t.red_packet_balance>=t.needMoney?s("div",{staticClass:"gainMoney-btn",on:{click:t.confirmWithdraw}},[t._v("确定提现")]):s("div",{staticClass:"gainMoney-btn gainMoney-btn-gray"},[t._v("确定提现")])]],2),t._v(" "),s("div",{staticClass:"rule-part"},[s("rule-component")],1),t._v(" "),s("msgBox",{attrs:{msgTxt:t.msgTxt}})],1)},staticRenderFns:[]};var u=s("C7Lr")(l,o,!1,function(t){s("NJhW")},"data-v-a0486092",null);a.default=u.exports},NJhW:function(t,a){},QRdD:function(t,a){},hEn8:function(t,a,s){"use strict";var e={render:function(){this.$createElement;this._self._c;return this._m(0)},staticRenderFns:[function(){var t=this,a=t.$createElement,s=t._self._c||a;return s("div",{staticClass:"ruleComponent"},[s("header",{staticClass:"head"},[t._v("\n        提取说明\n    ")]),t._v(" "),s("ul",{staticClass:"ul1"},[s("li",{staticClass:"li"},[t._v("1.领取运动红包的QQ号码可提现红包余额到该QQ号码的钱包；")]),t._v(" "),s("li",{staticClass:"li"},[t._v("2.用户每天只能申请提现一次；")]),t._v(" "),s("li",{staticClass:"li"},[t._v("3.第一次提现金额为1元，第二次提现金额为10元，第三次起每次提现金额为30元；")]),t._v(" "),s("li",{staticClass:"li"},[t._v("4.单个红包有效期：60天，过期系统回收；")]),t._v(" "),s("li",{staticClass:"li"},[t._v("5.提现后预计两个工作日将发放至QQ钱包，请到QQ钱包-点击QQ钱包余额数字-交易记录查看。")]),t._v(" "),s("li",{staticClass:"li li-extra"},[s("p",[t._v("6.账户异常的说明")]),t._v(" "),s("p",{staticClass:"sub-tip"},[t._v("您若有下列任何一种行为或情况的，腾讯有权单方面取消您的领取资格：")]),t._v(" "),s("ul",[s("li",{staticClass:"li-sub"},[t._v("①不符合参与资格的；")]),t._v(" "),s("li",{staticClass:"li-sub"},[t._v("②提供虚假信息的；")]),t._v(" "),s("li",{staticClass:"li-sub"},[t._v("③以任何机器人软件、蜘蛛软件、爬虫软件或其他任何自动方式、不正当手段参与本活动的；")]),t._v(" "),s("li",{staticClass:"li-sub"},[t._v("④有任何违反诚实信用、公序良俗、公平、公正等原则行为的；")]),t._v(" "),s("li",{staticClass:"li-sub"},[t._v("⑤其他违反相关法规、本规则行为的。")])])])]),t._v(" "),s("header",{staticClass:"head"},[t._v("\n        税费说明\n    ")]),t._v(" "),s("ul",[s("li",{staticClass:"li"},[t._v("\n            1.根据国家税务总局规定，对个人获得企业派发的网络红包（QQ运动红包），应按照所得相应金额缴纳个人所得税，税款由派发红包的企业代扣代缴。为配合国家税务总局政策规定，自2018年6月15日起，淘集集开始执行。\n        ")]),t._v(" "),s("li",{staticClass:"li",staticStyle:{"margin-bottom":"0"}},[t._v("\n            2.代缴方案：按提现金额的20%收取个人所得税。\n        ")])])])}]};var i=s("C7Lr")({name:"ruleComponent",data:function(){return{list:[]}}},e,!1,function(t){s("QRdD")},"data-v-7281173d",null);a.a=i.exports}});