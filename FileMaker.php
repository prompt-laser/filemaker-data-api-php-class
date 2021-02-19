<?php	
	class FileMakerFind {
		public $query = array();
		public $layout;
		
		function AddRequest($field, $data, bool $include){
			if($include){
				$this->query[] = array($field	=> $data);
			}else{
				$this->query[] = array($field	=> $data, "omit"	=> "true");
			}
		}
	}

	class FileMaker {
		public $token;
		private $host;
		private $database;
		private $datasources;
		private $find;
		
		function SetHost($uri){
			$this->host = $uri;
		}
		
		function Connect($database, $username, $password){
			$this->database = $database;
			$curl = curl_init("https://" . $this->host . "/fmi/data/v1/databases/" . $database . "/sessions");
			$header = array(
						'Authorization: Basic ' . base64_encode($username . ':' . $password),
						'Content-Type: application/json'
						);
			
			if(!isset($this->datasources)){
				$content = "{}";
			}else{
				
				$sources = array();
				foreach($this->datasources as $d){
					$sources[] = array(
								'database' =>	$d['database'],
								'username' =>	$d['username'],
								'password' =>	$d['password']
								);
				}
				$content = array('fmDataSource'	=> $sources);
			}
			
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			$response = curl_exec($curl);
			$response = json_decode($response, true);
			$this->token = isset($response['response']['token']) ? $response['response']['token'] : false;
		}
		
		function Close(){
			$curl = curl_init("https://" . $this->host . "/fmi/data/v1/databases/" . $this->database . "/sessions/" . $this->token);
			$header = array(
						'Content-Type: application/json'
						);
			$content = "{}";
			
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
			curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			$response = curl_exec($curl);
			$response = json_decode($response, true);
			
			return $response['messages'][0]['code'] == "0" ? true : false;
		}
		
		function AddDataSource($database, $username, $password){
			if(!isset($sources)){
				$this->sources = array();
			}
			
			$this->sources[] = array(
								'database' =>	$database,
								'username' =>	$username,
								'password' =>	$password
								);
		}
		
		function NewFindRequest($layout){
			if(isset($this->find)){
				unset($this->find);
			}
			$this->find = new FileMakerFind;
			$this->find->layout = $layout;
		}
		
		function AddFindParameter($field, $data, bool $include){
			$this->find->AddRequest($field, $data, $include);
		}
		
		function PerformFind(){
			if(!isset($this->find)){
				return false;
			}else{
				$curl = curl_init("https://" . $this->host . "/fmi/data/v1/databases/" . $this->database . "/layouts/" . $this->find->layout . "/_find");
				$header = array(
							"Content-Type: application/json",
							"Authorization: Bearer " . $this->token
							);
							
				$content = array('query' => $this->find->query);
				curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
				curl_setopt($curl, CURLOPT_POST, 1);
				curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
				curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($content));
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
				
				return json_decode(curl_exec($curl));
			}
		}				
	}
?>
