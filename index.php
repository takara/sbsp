<?php
$url=$_REQUEST["url"];
$appHostName=get_cfg_var("sbsp.app.hostname");
//trace("[%s][%s]",__FILE__,print_r($_REQUEST,TRUE));
session_start();
switch(TRUE)
{
// mobage API 偽装
case (isset($_REQUEST['api']) && $_REQUEST['api']):
	//session_start("mobage_api");
	//session_name("mobage_api");
	$api=$_REQUEST['api'];
	trace("api request api[{$api}][{$_SESSION['id']}][{$_SERVER['REMOTE_ADDR']}]\n");
	if(isset($_REQUEST["id"]))
	{
		file_put_contents("id.txt",$_REQUEST["id"]);
	}
	$funcname=sprintf("mobage_api%03d",$_REQUEST['api']);
	$res=call_user_func_array($funcname,array());
	echo $res;
	break;
// ログアウト
case isset($_SESSION["id"]) && isset($_REQUEST['logout']):
	$_SESSION=array();
	session_destroy();
	print 
		"<html>".
		"<head>".
		"<META HTTP-EQUIV=\"Content-Type\" CONTENT=\"text/html; charset=utf-8\">".
		"</head>".
		"<body>".
		"<a href='/'>ログアウトしました</a>".
		"</body>".
		"</html>".
		"";
	break;
// サーバー接続
case isset($_SESSION["id"]):
	/*$url=empty($url)?"http://web/":$url;
	$url=str_replace("#","",$url);
	$id=$_SESSION["id"];
	$url="{$url}".((strpos($url,"?")!==FALSE)?"&":"?")."opensocial_app_id=12010871&opensocial_owner_id={$id}&opensocial_viewer_id={$id}";*/
	$url=getAppURL($url,$_SESSION["id"]);
	//trace("url0[$url]");
	//trace("url1[$url1]");
	$ch=curl_init();
	curl_setopt($ch,CURLOPT_URL,$url);
	curl_setopt($ch,CURLOPT_USERAGENT,$_SERVER["HTTP_USER_AGENT"]);
	/*
	"Host"=>"web",
	"Connection"=>"close",
	"User-Agent"=>"Mozilla/5.0 (iPhone; CPU iPhone OS 5_0 like Mac OS X) AppleWebKit/534.46 (KHTML, like Gecko) Version/5.1 Mobile/9A334 Safari/7534.48.3",
	"Authorization"=>"OAuth realm="http://sb.sp.app.mbga.jp/api/restful", oauth_consumer_key="cba3ed8029188425a2d2", oauth_nonce="255324e946e199f5938a", oauth_signature="6m7a%2Fld6ERZJTNlmuJ45GgUk2jQ%3D", oauth_signature_method="HMAC-SHA1", oauth_timestamp="1353310981", oauth_token="e706304a3c39b16e2d8f", oauth_token_secret="26ca527901269b343467", oauth_version="1.0"",
	"Content-Length"=>"0",
	"X-Forwarded-For"=>"111.110.115.131",
	*/
	curl_setopt($ch,CURLOPT_HTTPHEADER,array(
		"Connection: close",
		"Authorization: OAuth realm=\"http://sb.sp.mac/api/restful\",".
			" oauth_consumer_key=\"cba3ed8029188425a2d2\", ".
			"oauth_nonce=\"255324e946e199f5938a\", ".
			"oauth_signature=\"6m7a%2Fld6ERZJTNlmuJ45GgUk2jQ%3D\", ".
			"oauth_signature_method=\"HMAC-SHA1\", ".
			"oauth_timestamp=\"1353310981\", ".
			"oauth_token=\"e706304a3c39b16e2d8f\", ".
			"oauth_token_secret=\"26ca527901269b343467\", ".
			"oauth_version=\"1.0\"",
	));
	// ポストデータ
	if($_SERVER['REQUEST_METHOD']=="POST")
	{
		curl_setopt($ch,CURLOPT_POST,1);
		$data=$_POST;
		if(count($_FILES)>0)
		{
			foreach($_FILES as $field=>$file)
			{
				if(empty($file['tmp_name']))
				{
					continue;
				}
				$data[$field]="@{$file['tmp_name']};type={$file['type']}";
			}
		}
		$param=_array2param($data);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$param);
	}
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);
	curl_setopt($ch,CURLOPT_HEADER,TRUE);
	$res=curl_exec($ch);
	curl_close($ch);

	$n=strpos($res,"\r\n\r\n");
	do
	{
		$header=substr($res,0,$n+4);
		$html=substr($res,$n+4);
		// ロケーションの指定がある？
		if(strpos($header,"Location:")!==FALSE)
		{
			if(preg_match("/^(Location:[ ]?[^\r\n]+)\r\n/m",$header,$match))
			{
				header($match[1]);
				exit;
			}
		}
		// コンテントタイプも右から左へ
		if(strpos($header,"Content-Type:")!==FALSE)
		{
			if(preg_match("/^(Content-Type:[ ]?[^\r\n]+)\r\n/m",$header,$match))
			{
				header($match[1]);
			}
		}
		$res=$html;
	} while(strpos($header,"Continue")!==FALSE && ($n=strpos($res,"\r\n\r\n"))!==FALSE);
	echo $html;
	break;
// ログイン
case !isset($_SESSION["id"]) && isset($_REQUEST['id']):
	$_SESSION["id"]=$_REQUEST['id'];
	$url=urlencode(getAppURL());
	print 
		"<html>".
		"<head>".
		"<META HTTP-EQUIV=\"Content-Type\" CONTENT=\"text/html; charset=utf-8\">".
		"</head>".
		"<body>".
		"<a href='/?url={$url}'>ログインしました</a>".
		"</body>".
		"</html>".
		"";
	break;
case isset($_SESSION["id"]) && !isset($_REQUEST['id']):
	trace("logout\n");
	print
		"<html>".
		"<head>".
		"<META HTTP-EQUIV=\"Content-Type\" CONTENT=\"text/html; charset=utf-8\">".
		"</head>".
		"<body>".
		"session[{$_SESSION['id']}]<br/>\n".
		"request[{$_REQUEST['id']}]<br/>\n".
		"<a href='/?logout'>ログアウト</a><br/>".
		"</body>".
		"</html>".
		"";
	break;
default:
	trace("default [{$_REQUEST['api']}]\n");
	print
		"<html>".
		"<head>".
		"<META HTTP-EQUIV=\"Content-Type\" CONTENT=\"text/html; charset=utf-8\">".
		"</head>".
		"<body>".
		//"session[{$_SESSION['id']}]<br/>\n".
		//"request[{$_REQUEST['id']}]<br/>\n".
		"<form>".
		"id:<input type='text' name='id' value='85494'><br/>".
		"<input type='submit' value='ID設定'>".
		"</form>".
		"85494 たか<br/>\n".
		"85805 たか２<br/>\n".
		"89876 takara3<br/>\n".
		"</body>".
		"</html>".
		"";
	break;
}
exit;
/**
* 配列をPOST用パラメタへ変換
*
* 配列の２階層までしか対応していない
*/
function _array2param($ary,$akey=NULL)
{
	$ret=array();
	if(empty($ary))
	{
		return($ret);
	}
	if(!is_array($ary))
	{
		return($ary);
	}
	foreach($ary as $key=>$val)
	{
		if(is_array($val))
		{
			$ret=array_merge(
				$ret,
				_array2param($val,$key)
			);
		} else {
			if(is_null($akey))
			{
				$ret[$key]=$val;
			} else {
				$ret["{$akey}[{$key}]"]=$val;
			}
		}
	}
	return($ret);
}
/**
* アプリケーションサーバーＵＲＬ取得
*/
function getAppURL($url=NULL,$id=NULL)
{
	if(is_null($id))
	{
		$id=$_SESSION["id"];
	}
	$url=empty($url)?"http://{$GLOBALS['appHostName']}/":$url;
	$url=str_replace("#","",$url);
	$url="{$url}".((strpos($url,"?")!==FALSE)?"&":"?")."opensocial_app_id=12010871&opensocial_owner_id={$id}&opensocial_viewer_id={$id}";
	$ret=$url;
	return($ret);
}
/**
* トレースログ
*/
function trace()
{
	$fmt="%s ";
	$args=func_get_args();
	$fmt.=array_shift($args);
	$param=array_merge(
		array($fmt,date("Y/m/d H:i:s")),
		$args
	);
	$log=call_user_func_array("sprintf",$param);
	error_log($log);
}
/**
* アバター
*
* response
*	{
*	   "startIndex" : 1,
*	   "avatar" : {
*		  "extension" : "gif",
*		  "emotion" : "defined",
*		  "scene" : null,
*		  "size" : "large",
*		  "appId" : null,
*		  "view" : "upper",
*		  "fps" : "12",
*		  "dimension" : "3d",
*		  "motion" : "0",
*		  "transparent" : false,
*		  "url" : "http://sb-sp.mbga.jp/img_op/85805/10000000/1.0.0.0.1.gif?consumer_key=cba3ed8029188425a2d2&guid=ON&sign=0edccfbf601492c7fa193bfb8243df3535f278a82e3281f0a2b4cdfe8dac0270",
*		  "type" : "image"
*	   },
*	   "itemsPerPage" : 1,
*	   "totalResults" : 1
*	}
*/
function mobage_api001()
{
	$ret=json_encode(array(
		"startIndex"	=>1,
		"avatar"=>array(
			"extension"		=>"gif",
			"emotion"		=>"defined",
			"scene"			=>"",
			"size"			=>"large",
			"appId"			=>"",
			"view"			=>"upper",
			"fps"			=>12,
			"dimension"		=>"3d",
			"motion"		=>0,
			"transparent"	=>"",
			"url"			=>"http://{$_SERVER['HTTP_HOST']}/1.0.0.0.1.gif",
			"type"			=>"image",
		),
		"itemsPerPage"	=>1,
		"totalResults"	=>1,
	));

	return($ret);
}
/**
* 支払い
*
* request
* {
* 	"callbackUrl": "http://web/payment/cb.php",
* 	"finishUrl": "http://web/item/complete.php?amount=1&id=178&price=1000&q=&l=&ev=&orgMoney=&fgid=&fgfid=&fgts=&fgtrid=&ssid=ebe010839992a6e593fd93afa2b3de15cb51f9d2e8518e1697483e5d3d7325fa",
* 	"entry": [
* 		{
* 			"itemId": "178",
* 			"name": "商品名",
* 			"unitPrice": "1000",
* 			"amount": "1",
* 			"description": "商品説明",
* 			"imageUrl": "http://web/img/item/178.gif"
* 		}
* 	]
* }
* response
* {
* 	"payment":{
* 		"finishUrl":"http://web/item/complete.php?amount=1&id=178&price=1000&q=&l=&ev=&orgMoney=&fgid=&fgfid=&fgts=&fgtrid=&ssid=ebe010839992a6e545ccc60f695fe21bfaf4da97f0212dc10b67202af48c267b",
* 		"entry":[{
* 			"itemId":178,
* 			"amount":1,
* 			"imageUrl":"http://web/img/item/178.gif",
* 			"name":"商品名",
* 			"paymentId":"4A2903E5-68B2-351C-BC9F-0F0E8FB6AE8A",
* 			"unitPrice":1000,
* 			"description":"商品名説明"
* 		}],
* 		"status":0,
* 		"userId":"sb.mbga.jp:85494",
* 		"published":"2012-12-17T02:52:01",
* 		"endpointUrl":"http://sb-sp.mbga.jp/_pf_pay_confirm?p=4A2903E5-68B2-351C-BC9F-0F0E8FB6AE8A&app_id=12011394",
* 		"appId":12011394,
* 		"callbackUrl":"http://web/payment/cb.php",
* 		"updated":"2012-12-17T02:52:01",
* 		"id":"4A2903E5-68B2-351C-BC9F-0F0E8FB6AE8A"
* 	},
* 	"startIndex":1,
* 	"itemsPerPage":1,
* 	"totalResults":1
* }
*/
function mobage_api002()
{
	$post=json_decode($GLOBALS['HTTP_RAW_POST_DATA'],TRUE);
	//	00000000001111111111222222222233333333334444444444
	//	01234567890123456789012345678901234567890123456789
	//	B5C9AEEECE0C218C12CFF03E236C6A8A
	//	B5C9AEEE-CE0C-218C-12CF-F03E236C6A8A
	$p=strtoupper(md5(serialize($post)));
	$p=sprintf("%s-%s-%s-%s-%s",
		substr($p, 0,8),
		substr($p, 8,4),
		substr($p,12,4),
		substr($p,16,4),
		substr($p,20)
	);
	//trace("p[$p]");
	//$p="4A2903E5-68B2-351C-BC9F-0F0E8FB6AE8A";
	$id=file_get_contents("id.txt");
	//trace("id[$id]");
	$res=array(
		"payment"=>array(
			"finishUrl"=>"",
			"entry"=>array(),
			"status"		=> 0,
			"userId"		=> "{$_SERVER['HTTP_HOST']}:{$id}",
			"published"		=> gmdate("Y-m-d\TH:i:s"),
			"endpointUrl"	=> "http://{$_SERVER['HTTP_HOST']}/_pf_pay_confirm?p={$p}&app_id=12011394",
			"appId"			=> 12011394,
			"callbackUrl"	=> "",
			"updated"		=> gmdate("Y-m-d\TH:i:s"),
			"id"			=> $p,
		),
		"startIndex"	=>1,
		"itemsPerPage"	=>1,
		"totalResults"	=>1,
	);
	$res["payment"]["finishUrl"]	=$post["finishUrl"];
	$res["payment"]["callbackUrl"]	=$post["callbackUrl"];
	$res["payment"]["entry"]		=$post["entry"];
	foreach($res["payment"]["entry"] as $idx=>$entry)
	{
		$res["payment"]["entry"][$idx]["paymentId"]=$p;
	}
	//trace("ret[%s][{$_SESSION['id']}]",print_r(json_encode($res,TRUE),TRUE));
	header("HTTP/1.0 201 Created");
	file_put_contents("payment.txt",serialize($res));
	//trace("server[%s]",$_SERVER['REQUEST_METHOD']);
	//trace("post[%s]",print_r($_POST,TRUE));
	//trace("id[{$GLOBALS['_SESSION']['id']}]");
	//trace("id[%s]",print_r($GLOBALS,TRUE));
	//trace("raw1[%s]\n",print_r($GLOBALS['HTTP_RAW_POST_DATA'],TRUE));
	//return(mobage_api001());
	$ret=json_encode($res);
	return($ret);
}
/**
* 購入ページ
*
* mobage側の購入ページ
* ここでは画面は表示せずスキップして購入完了へ
*
* /payment/cb.php
* array(
* 	"opensocial_app_id"=>"12011394",
* 	"opensocial_owner_id"=>"85494",
* 	"opensocial_viewer_id"=>"85494",
* 	"payment_id"=>"DA93AD57-8AC4-3C43-B76F-D985AACBEF2B",
* 	"status"=>"10",
* 	"updated"=>"2012-12-17T05:55:19Z",
* )
*
* /item/complete.php
* array(
* 	"amount"=>"1",
* 	"id"=>"178",
* 	"price"=>"1000",
* 	"q"=>"",
* 	"l"=>"",
* 	"ev"=>"",
* 	"orgMoney"=>"",
* 	"fgid"=>"",
* 	"fgfid"=>"",
* 	"fgts"=>"",
* 	"fgtrid"=>"",
* 	"ssid"=>"d657ba8e40cb1093575ddd781301c4cc8d7233216c6574f49167e21c1727bb07",
* 	"payment_id"=>"28DA3A9F-AFB0-3FF7-A61D-A6AFE917C435",
* 	"opensocial_app_id"=>"12011394",
* 	"opensocial_viewer_id"=>"85494",
* 	"opensocial_owner_id"=>"85494",
* )
*/
function mobage_api003()
{
	$res=unserialize(file_get_contents("payment.txt"));
	$id=file_get_contents("id.txt");
	$url=getAppURL($res['payment']['callbackUrl'],$id);
	$url.=
		"&paymentId={$res['payment']['id']}".
		"&payment_id={$res['payment']['id']}".
		"";
	//trace("url[%s]",$url);
	$ch=curl_init();
	curl_setopt($ch,CURLOPT_URL,$url);
	curl_setopt($ch,CURLOPT_USERAGENT,$_SERVER["HTTP_USER_AGENT"]);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);
	$res1=curl_exec($ch);
	curl_close($ch);
	//trace("res[%s]",$res1);

	/*$posturl="http://{$_SERVER['HTTP_HOST']}/?url=".urlencode($res['payment']['callbackUrl']);
	$date=gmdate("Y-m-d\TH:i:s");
	$html=
		"<html>\n".
		"<head>\n".
		"<meta httt-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">".
		"</head>\n".
		"<body>\n".
		//"{$res['payment']['finishUrl']}\n".
		"<img src='{$res['payment']['entry'][0]['imageUrl']}'>\n".
		"<form action='{$posturl}' method='POST'>\n".
		"<input type='text' name='payment_id' value='{$res['payment']['id']}'><br/>\n".
		"<input type='text' name='status' value='10'><br/>\n".
		"<input type='text' name='updated' value='{$date}'><br/>\n".
		"<input type='submit'>\n".
		"</form>\n".
		"</body>\n".
		"</html>\n".
		"";
	print "$html";
	*/
	$finish_url="http://{$_SERVER['HTTP_HOST']}/?url=".urlencode($res['payment']['finishUrl']);
	header("Location: {$finish_url}");
}
?>
