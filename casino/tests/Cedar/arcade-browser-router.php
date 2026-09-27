<?php
// Loopback-only, ephemeral UI test server. No app config, real users, or wallet are loaded.
if(PHP_SAPI!=='cli-server'){http_response_code(404);exit;}
$root=dirname(__DIR__,3);require $root.'/localscripts/licensing_hub/private/cedar/arcade/Engine.php';
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
session_start();
if($path==='/fixture-command'){
    header('Content-Type: application/json');$in=json_decode(file_get_contents('php://input'),true);$game=$in['game'];$payload=$in['payload'];$id=$payload['command_id'];
    if(isset($_SESSION['commands'][$id])){echo json_encode($_SESSION['commands'][$id]);exit;}
    $engine=new CedarArcadeEngine($game,$_SESSION['engines'][$game]??[]);
    try{$payload['request_id']=$id;$r=$engine->handle($payload);$_SESSION['engines'][$game]=$engine->export();$r['balance']='1000.00';$_SESSION['commands'][$id]=$r;echo json_encode($r);}catch(Throwable $e){http_response_code(422);echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}exit;
}
if(!preg_match('#^/cedar/(games/[A-Za-z0-9]+/(?:index.html|cover.png)|runtime/(?:arcade.js|arcade.css|cedar-client.js))$#D',$path)){http_response_code(404);exit;}
$file=$root.'/localscripts/licensing_hub/public'.$path;
if(!is_file($file)){http_response_code(404);exit;}
$extension=pathinfo($file,PATHINFO_EXTENSION);header('Content-Type: '.(['html'=>'text/html','js'=>'text/javascript','css'=>'text/css','png'=>'image/png'][$extension]));
if($extension!=='html'){readfile($file);exit;}
$html=file_get_contents($file);
$fixture='<script>window.PromexHtmlGame={request:async(action,payload)=>{const game=JSON.parse(document.getElementById("arcade-config").textContent).id;const response=await fetch("/fixture-command",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({game,payload:{...payload,action}})});const r=await response.json();if(!response.ok)throw new Error(r.message);return r;},verify:proof=>window.CedarFairness.verify(proof)};</script><script src="/cedar/runtime/cedar-client.js"></script>';
echo preg_replace('#<script src="/cedar/runtime/promex-html-game.js"[^>]*></script>#',$fixture,$html);
