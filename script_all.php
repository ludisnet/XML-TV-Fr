<?php
date_default_timezone_set('Europe/Paris');
set_time_limit(0);
function compare_classe($a,$b)
{
    if(class_exists($a) && class_exists($b))
    {
        if(call_user_func($a. "::getPriority") > call_user_func($b. "::getPriority"))
            return -1;
        return 1;
    } else {
        return 0;
    }
}
$classes = glob('classes/*.php');
$PROVIDER = 'Provider';
$UTILS = 'Provider';
$classes_priotity = array();
$XML_PATH = "channels/";
$CLASS_PREFIX = "EPG_";
$logs = array('channels'=>array(), 'xml'=>array(),'failed_providers'=>array());
foreach($classes as $classe) {
    require_once $classe;
    $class_name = explode('/',explode('.php',$classe)[0]);
    $class_name = $class_name[count($class_name)-1];
    if(class_exists($class_name) && $class_name != $PROVIDER && $class_name != $UTILS)
    {
        if(method_exists(new $class_name($XML_PATH),'getPriority' ) && method_exists(new $class_name($XML_PATH),'constructEPG' ))
            $classes_priotity[] = $class_name;
    }
}
usort($classes_priotity,"compare_classe");
if(!file_exists('channels.json'))
{
    echo 'channels.json manquant';
}
if(!file_exists('config.json'))
{
    $DAY_LIMIT = 8;
} else {
    $json = json_decode(file_get_contents('config.json'),true);
    if(isset($json["days"]))
    {
        $DAY_LIMIT = $json["days"];
    } else {
        $DAY_LIMIT = 8;
    }
}
$channels = json_decode(file_get_contents('channels.json'),true);
$channels_key = array_keys($channels);
foreach($channels_key as $channel)
{
    if(isset($channels[$channel]["priority"]) && count($channels[$channel]["priority"]) > 0)
    {
        $priority = $channels[$channel];
    } else {
        $priority = $classes_priotity;
    }
    for($i=-1;$i<$DAY_LIMIT;$i++)
    {
        $date = date('Y-m-d',time()+86400*$i);
        echo $channel." : ".$date;
        if(!file_exists(Utils::generateFilePath($XML_PATH,$channel,$date))) {
            $success = false;
            foreach ($priority as $classe) {
                if(!class_exists($classe))
                    break;
                if(!isset(${$CLASS_PREFIX.$classe}))
                    ${$CLASS_PREFIX.$classe} = new $classe($XML_PATH);
                if(${$CLASS_PREFIX.$classe}->constructEPG($channel,$date))
                {
                    $logs["channels"][$date][$channel]['success'] = true;
                    echo " : OK - ".$classe.chr(10);
                    $logs["channels"][$date][$channel]['provider'] = $classe;
                    break;
                }
                $logs["channels"][$date][$channel]['failed_providers'][] = $classe;
                $logs["channels"][$date][$channel]['success'] = false;
                $logs["failed_providers"][$classe] = true;
            }
            if(!$logs["channels"][$date][$channel]['success'])
                echo " : HS - ".chr(10);
        } else {
            $logs["channels"][$date][$channel]['provider'] = 'Cache';
            echo " : OK Cache - ".chr(10);
            $logs["channels"][$date][$channel]['success'] = true;

        }
    }
}
$xmltv = glob('xmltv/xmltv*');
foreach($xmltv as $file)
{
    if(time()-filemtime($file) > 86400*5)
        unlink($file);

}

if(file_exists("xmltv/xmltv.xml"))
{
    rename("xmltv/xmltv.xml","xmltv/xmltv_".date('Y-m-d H-i-s',"xmltv/xmltv.xml").".xml");
}
if(file_exists("xmltv/xmltv.zip"))
{
    rename("xmltv/xmltv.zip","xmltv/xmltv_".date('Y-m-d H-i-s',"xmltv/xmltv.zip").".zip");
}
if(file_exists("xmltv/xmltv.xml.gz"))
{
    rename("xmltv/xmltv.zip","xmltv/xmltv_".date('Y-m-d H-i-s',"xmltv/xmltv.xml.gz").".xml.gz");
}

$filepath = "xmltv/xmltv.xml";
$files = glob($XML_PATH.'*');
foreach($files as $file){
    if(time()-filemtime($file) > 864000)
        unlink($file);
}
$out = fopen($filepath, "w");
fwrite($out,'<?xml version="1.0" encoding="ISO-8859-1"?>
<!DOCTYPE tv SYSTEM "xmltv.dtd">

<tv source-info-url="http://allfrtv.com/" source-info-name="XML TV Fr" generator-info-name="XML TV Fr" generator-info-url="http://allfrtv.com/">
  ');
foreach($channels as $key => $channel)
{
    @$icon = $channel['icon'];
    @$name = $channel['name'];
    fwrite($out,'<channel id="'.$key.'">
    <display-name>'.$name.'</display-name>
    <icon src="'.$icon.'" />
  </channel>'.chr(10));
}


foreach($files as $file){
    $in = fopen($file, "r");
    while ($line = fgets($in)){
        fwrite($out, $line);
    }
    fclose($in);
}
fwrite($out,'</tv>');
fclose($out);
file_put_contents('logs/logs'.date('YmdHis').'.json',json_encode($logs));
$got = file_get_contents('xmltv/xmltv.xml');
$got1 = gzencode($got,true);
file_put_contents('xmltv/xmltv.xml.gz',$got1);
echo "GZ : OK".chr(10);


$zip = new ZipArchive();
$filename = "xmltv/xmltv.zip";

if ($zip->open($filename, ZipArchive::CREATE)!==TRUE) {
    echo "ZIP : HS".chr(10);
} else {
    echo "ZIP : OK".chr(10);
}
$zip->addFile("xmltv/xmltv.xml", "xmltv.xml");
$zip->close();