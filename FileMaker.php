<?php
	//Class for saving find requests
	class FileMakerFind {
		public $query = array();
		public $sortOrder = array();
		public $layout;
		
		function AddRequest($field, $data, bool $include){
			if($include){
				array_push($this->query, array($field	=> $data));
			}else{
				array_push($this->query, array($field	=> $data, "omit"	=> "true"));
			}
		}
		
		function AddSortOrder($field, bool $descend){
			if($descend){
				array_push($this->sortOrder, array('fieldName'=>$field, 'sortOrder'=> "descend"));
			}else{
				array_push($this->sortOrder, array('fieldName'=>$field, 'sortOrder'=> "ascend"));
			}
		}
	}

	//Main FileMaker class for connecting/disconnecting to/from solution and performing commands
	class FileMaker {
		public 	$token;			//Session token
		private $host;			//Hostname or IP address of the server
		private $database;		//Database to connect to
		private $datasources;	//Array of ecternal datasources
		private $find;			//Variable for holding find request parameters
		private $sortOrder;		//Variable for storing the sort order
		
		//Set the host variable
		function SetHost($uri){
			$this->host = $uri;
		}
		
		//Connect to the solution as well as any eternal data sources
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
		
		//Close session with target solution
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
		
		//Add external datasources
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
		
		//Create a new find request. If one already exists clear it
		function NewFindRequest($layout){
			if(isset($this->find)){
				unset($this->find);
			}
			$this->find = new FileMakerFind;
			$this->find->layout = $layout;
		}
		
		//Add find criterion
		function AddFindParameter($field, $data, bool $include){
			$this->find->AddRequest($field, $data, $include);
		}
		
		//Add sort criteria
		function AddSortOrder($field, bool $descend){
			if(isset($this->find)){
				$this->find->AddSortOrder($field, $descend);
			}else{
				if(!isset($this->sortOrder)){
					$this->sortOrder = array();
				}
				if($descend){
					$this->sortOrder[] = array('fieldName' => $field, 'sortOrder' => "descend");
				}else{
					$this->sortOrder[] = array('fieldName' => $field, 'sortOrder' => "ascend");
				}
			}
		}
		
		//Perform the saved find
		function PerformFind(){
			if(!isset($this->find)){
				return false;
			}else{
				$curl = curl_init("https://" . $this->host . "/fmi/data/v1/databases/" . $this->database . "/layouts/" . $this->find->layout . "/_find");
				$header = array(
							"Content-Type: application/json",
							"Authorization: Bearer " . $this->token
							);
							
				$content = array('query' => $this->find->query, "sort" => $this->find->sortOrder);
				curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
				curl_setopt($curl, CURLOPT_POST, 1);
				curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
				curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($content));
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
				
				return json_decode(curl_exec($curl), true);
			}
		}
		
		//Edit record specified by $record_id
		function EditRecord($layout, $record_id, $field, $data){
			$curl = curl_init("https://" . $this->host . "/fmi/data/v1/databases/" . $this->database . "/layouts/" . $layout . "/records/" . $record_id);
			$header = array(
						"Content-Type: application/json",
						"Authorization: Bearer " . $this->token
						);
			
			$content = json_encode(array("fieldData" => array($field => $data)));
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PATCH");
			curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			$response = curl_exec($curl);
			$response = json_decode($response, true);
			
			return $response['messages'][0]['code'] == "0" ? true : false;
		}
		
		//Get records from $layout after running script $script
		function GetRecords($layout, $script, $scriptParam){
			if(!isset($layout)){
				return false;
			}else{
				$curl = curl_init("https://" . $this->host . "/fmi/data/v1/databases/" . $this->database . "/layouts/" . $layout . "/records?script.prerequest=" . $script . "&script.prerequest.param=" . $scriptParam);
				$header = array(
							"Content-Type: application/json",
							"Authorization: Bearer " . $this->token
							);
							
				curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
				curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
				
				return json_decode(curl_exec($curl), true);
			}
		}
		
		//Run a script on specified layout
		function PerformScript($layout, $script, $scriptParam){
			if(!isset($script)){
				return false;
			}else{
				$url = "https://" . $this->host . "/fmi/data/v1/databases/" . $this->database . "/layouts/" . $layout . "/records/1?script=" . $script . "&script.param=" . $scriptParam;
				var_dump($url);
				$curl = curl_init($url);
				$header = array(
							"Content-Type: application/json",
							"Authorization: Bearer " . $this->token
							);
				
				curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
				curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
				$response = curl_exec($curl);
				$response = json_decode($response, true);
				
				return $response['messages'][0]['code'];
			}
		}
	}
?>
