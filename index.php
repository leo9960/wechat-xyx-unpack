<?php
header("Content-Type: text/html; charset=utf-8");

$file_url=$_GET["wget"];
if($file_url=="")exit();

$unpackobj = new wxunpack($file_url);

class wxunpack{

private $debug=1;
private $file_url="";
private $file_name="";
private $file_path="";
private $dir_path="";
private $zip_file="";
private $logs="";
private $log_path="./logs/";
private $project_name="test";

public function __construct($url){
	$this->file_url=$url;
	$this->file_name=md5($url);
	$this->file_path="./tmp/".$this->file_name.".zip";
	$this->dir_path="./tmp/".$this->file_name."/";
	
	if(!file_exists($this->file_path)){
		$this->get_wxapkg();
	}
	try{
		$this->js();	//js解析
		$this->config();	//生成config文件
		$this->readme();	//生成readme
		$this->realexit();	//退出
	}
	catch(Exception $e){
		$this->logger("ERROR:".$e->getMessage());
		$this->realexit();
	}
	
}

private function logger($text){
	$this->logs.="<p>".$text."</p>";
}

private function realexit(){
	$this->logger("file_url:".$this->file_url);
	$this->logger("file_name:".$this->file_name);
	$this->logger("zip_file:".$this->zip_file);
	file_put_contents($this->log_path.date("Y-m-d_H-i-s").".log",$this->logs);
}

private function createDir($path){ 
	if (!file_exists($path)){ 
		$this->createDir(dirname($path)); 
		@mkdir($path); 
	} 
}

private function copydir($source, $dest){
    if (!file_exists($dest)) @mkdir($dest);
    $handle = opendir($source);
    while (($item = readdir($handle)) !== false) {
        if ($item == '.' || $item == '..') continue;
        $_source = $source . '/' . $item;
        $_dest = $dest . '/' . $item;
        if (is_file($_source)) copy($_source, $_dest);
        if (is_dir($_source)) copydir($_source, $_dest);
    }
    closedir($handle);
}

private function rm($file){
	unlink($file);
}

/*获取wxapkg文件*/
private function get_wxapkg($noget=0){
	if($this->file_url=="")exit();
	if($noget==0){
		if(strstr($this->file_url,"http")){
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $this->file_url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$output = curl_exec($ch);
			curl_close($ch);
			file_put_contents($this->file_path,$output);
			$this->logger("压缩包获取成功");
		}else{
			$raw_file_path="./tmp/".$this->file_name.".wxapkg";
			copy($this->file_url,$raw_file_path);
			include_once("wxapkg.php");
			$packageFile = $raw_file_path;
			if(is_file($packageFile)){
				$targetDir = $this->dir_path;
				unpack_wxapkg($packageFile, $targetDir);
				return true;
			}
		}
	}
	$zip = zip_open($this->file_path);
	while ($dir_resource = zip_read($zip)) {
		if (zip_entry_open($zip,$dir_resource)) {
			$file_name = ($this->dir_path).zip_entry_name($dir_resource);
			$unzip_path = substr($file_name,0,strrpos($file_name, "/"));
			if(!is_dir($unzip_path)){
				@mkdir($unzip_path,0777,true);
			}
			if(!is_dir($file_name)){
				$file_size = zip_entry_filesize($dir_resource);
				$file_content = zip_entry_read($dir_resource,$file_size);
				file_put_contents($file_name,$file_content);
			}
			zip_entry_close($dir_resource);
		}
	}
	zip_close($zip);
	$this->logger("文件解压成功");
}

private function js(){
	if(file_exists($this->dir_path."game.js")){
		$raw_str=file_get_contents($this->dir_path."game.js");
		$raw_str=substr($raw_str,strpos($raw_str,"define(\""));
		$util_str=$raw_str;
		$util_arr=array();
		while(strstr($util_str,"function(require, module, exports)")){
			$util_arr[]=substr($util_str,strrpos($util_str,"define(\""));
			$util_str=substr($util_str,0,strrpos($util_str,"define(\""));
		}
		foreach($util_arr as $tmp_util){
			$tmp_check=substr($tmp_util,0,round(strlen($tmp_util)/2));
			if(!strstr($tmp_check,"function(require, module, exports)")){continue;}
			$tmp_str=substr($tmp_util,strpos($tmp_util,"function(require, module, exports){")+35);
			$tmp_str=substr($tmp_str,0,strrpos($tmp_str,"});"));
			$tmp_name=substr($tmp_util,strpos($tmp_util,"define(\"")+8);
			$tmp_name=substr($tmp_name,0,strpos($tmp_name,"\", function(require"));
			$this->createDir(dirname($this->dir_path.$tmp_name));
			file_put_contents($this->dir_path.$tmp_name,$tmp_str);
		}
	}
	if(file_exists($this->dir_path."subContext.js")){
		$raw_str=file_get_contents($this->dir_path."subContext.js");
		$raw_str=substr($raw_str,strpos($raw_str,"define(\""));
		$util_str=$raw_str;
		$util_arr=array();
		while(strstr($util_str,"function(require, module, exports)")){
			$util_arr[]=substr($util_str,strrpos($util_str,"define(\""));
			$util_str=substr($util_str,0,strrpos($util_str,"define(\""));
		}
		foreach($util_arr as $tmp_util){
			$tmp_check=substr($tmp_util,0,round(strlen($tmp_util)/2));
			if(!strstr($tmp_check,"function(require, module, exports)")){continue;}
			$tmp_str=substr($tmp_util,strpos($tmp_util,"function(require, module, exports){")+35);
			$tmp_str=substr($tmp_str,0,strrpos($tmp_str,"});"));
			$tmp_name=substr($tmp_util,strpos($tmp_util,"define(\"")+8);
			$tmp_name=substr($tmp_name,0,strpos($tmp_name,"\", function(require"));
			$this->createDir(dirname($this->dir_path.$tmp_name));
			file_put_contents($this->dir_path.$tmp_name,$tmp_str);
		}
	}
	$this->logger("js文件分析成功");
}

private function config(){
	$file_name=$this->dir_path."app-config.json";
	$tmp_str=file_get_contents($file_name);
	$json=json_decode($tmp_str,true);
	if (array_key_exists("subContext",$json)){
		$json["openDataContext"]=$json["subContext"];
	}
	file_put_contents($this->dir_path."game.json",json_encode($json,JSON_PRETTY_PRINT));
	$this->logger("config文件分析成功");
}

private function readme(){
	$project_config=json_decode(file_get_contents("./template/project.config.json"),true);
	file_put_contents($this->dir_path."project.config.json",json_encode($project_config,JSON_PRETTY_PRINT));
	copy("./template/readme.md", $this->dir_path."readme.md");
	$this->logger("配置及说明文件生成成功");
}

}