webpackJsonp([4],{X0aD:function(e,a,r){"use strict";Object.defineProperty(a,"__esModule",{value:!0});var o=r("3cXf"),t=r.n(o),n=r("hEt0"),i=r("rDiw"),l={name:"transfer",beforeCreate:function(){var e=this;if(console.log("transferPage"),window.location.href.indexOf("growth.taojiji.com")>0)window.localStorage.removeItem("gocheap"),this.$loading.show(),this.$fetch.getData(i.h,{},function(a){1==a.result?(!a.data.user_type&&a.data.user_num&&"0"!=a.data.user_num?e.$router.replace(n.a+"/played/0"):"no"==a.data.user_play_log&&a.data.user_num&&"0"!=a.data.user_num?e.$router.replace(n.a+"/played/1"):e.$router.replace(""+n.b),errorLogUpload&&!a.data&&errorLogUpload({logLevel:3,errType:2,apiError:{params:t()({}),uri:location.href,apiUri:window.location.origin+i.h,data:t()(a.data),message:a.message,method:"get",code:200}})):(console.log("transferPage异常"),console.log(a),e.$router.replace(""+n.b))});else{var a=window.location.href.split("growth.tjjshop.cn"),r=a[0]+"growth.taojiji.com"+a[1];console.log(111),window.location.replace(r+"/played/1")}}},s={render:function(){var e=this.$createElement;return(this._self._c||e)("div")},staticRenderFns:[]};var c=r("C7Lr")(l,s,!1,function(e){r("tttr")},"data-v-60768efa",null);a.default=c.exports},tttr:function(e,a){}});