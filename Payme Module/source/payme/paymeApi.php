<?php

class paymeApi {

	private $errorInfo ="";
	private $errorCod =0;
	private $request_id=0;
	private $responceType=0;
	private $result =true;
	private $inputArray;
	private $lastTransaction;
	private $statement;
	private $snpetProp;
	private $myModx;

	public function construct() { }
	
	public function setMyModx($i_myModx) {

		$this->myModx=$i_myModx;
	}
	
	public function getSnpetProp(){ 
	
		$res = $this->myModx->db->select("properties", $this->myModx->getFullTableName('site_snippets'),"upper(name)='PAYME'");  
		 
		if($this->myModx->db->getRecordCount($res)) {  
		 
			$properties = $this->myModx->db->getValue($res);	
			$this->snpetProp = $this->myModx->parseProperties($properties);
					
		} else break; 
	}
 
	public function parseRequest() {
		
		//file_put_contents( $_SERVER['DOCUMENT_ROOT']."/assets/snippets/payme/payme.log", ' Begin  parseRequest'.PHP_EOL, FILE_APPEND);

		if ( (!isset($this->inputArray)) || empty($this->inputArray) ) {

			$this->setErrorCod(-32700,"empty inputArray");

		} else {

			$parsingJsonError=false;

			switch (json_last_error()){

				case JSON_ERROR_NONE: break;
				default: $parsingJson=true; break;
			}

			if ($parsingJsonError) {

				$this->setErrorCod(-32700,"parsingJsonError");

			} else {

				// Request ID
				if (!empty($this->inputArray['id']) ) {

					$this->request_id = filter_var($this->inputArray['id'], FILTER_SANITIZE_NUMBER_INT);
				}
				
					 if ($_SERVER['REQUEST_METHOD']!='POST') $this->setErrorCod(-32300);
				else if(! isset($_SERVER['PHP_AUTH_USER']))  $this->setErrorCod(-32504,"логин пустой");
				else if(! isset($_SERVER['PHP_AUTH_PW']))	 $this->setErrorCod(-32504,"пароль пустой");
			} 
		}

		if ($this->result) {
			
			 $this->getSnpetProp();

			     if ($this->snpetProp['paymeTestMode']==1) $merchantKey=html_entity_decode($this->snpetProp['paymePasswordForTest']);
			else if ($this->snpetProp['paymeTestMode']==0) $merchantKey=html_entity_decode($this->snpetProp['paymePassword']); 

			if( $merchantKey != html_entity_decode($_SERVER['PHP_AUTH_PW']) ) {

				$this->setErrorCod(-32504,"неправильный  пароль");

			} else {

				if ( method_exists($this,"payme_".$this->inputArray['method'])) {

					$methodName="payme_".$this->inputArray['method'];
					$this->$methodName();

				} else {

					$this->setErrorCod(-32601, $this->inputArray['method'] );
				}
			}
		} 

		return $this->GenerateResponse();
	}

	public function getTransactionByOrderId($order_id) {

		$sql= "SELECT * FROM payme_transactions WHERE cms_order_id='".$order_id."' order by transaction_id ";
		$dbResult= $this->myModx->db->query($sql);
	
		while ($row = $this->myModx->db->getRow($dbResult)) {

			$this->lastTransaction=$row;
		} 
	}
	
	public function getTransactionDateByPaymeTrId($t_id) {

		$sql= "SELECT * FROM payme_transactions WHERE paycom_transaction_id='".$t_id."' order by transaction_id ";
		$dbResult= $this->myModx->db->query($sql); 

		while ($row = $this->myModx->db->getRow($dbResult)) {

			$this->lastTransaction=$row;
		}
	}
    // FIX state=1 and
	public function SaveOrder($amount, $cmsOrderId,$paycomTime,$paycomTimeDatetime,$paycomTransactionId ) {

		$res = $this->myModx->db->select("id", 'payme_transactions',  "cms_order_id='".$cmsOrderId."' and amount=".$amount." and state=1"  );  

		if($this->myModx->db->getRecordCount($res)) { 

		} else {
			  
			$fields = array('create_time'  => date('Y-m-d H:i:s'),  
							'amount' => $amount,  
							'state'  => 1,  
							'cms_order_id' =>(is_null( $cmsOrderId ) ? 0:$cmsOrderId), 
							'paycom_time' => $paycomTime, 
							'paycom_time_datetime' => $paycomTimeDatetime, 
							'paycom_transaction_id' => $paycomTransactionId 
							);
				
			$this->myModx->db->insert( $fields, 'payme_transactions'); 
				
		}
	}
	
	public function payme_getOrder($order_id) {
		
		$mod_table = $this->myModx->db->config['table_prefix']."manager_shopkeeper"; 
		
		$order = $this->myModx->db->getRow(
									$this->myModx->db->select(
									"id, short_txt, content, allowed, addit, price, currency, DATE_FORMAT(date,'%d.%m.%Y %k:%i') AS date, status, email, phone, payment, tracking_num,  userid", 
									$mod_table, 
									"id = $order_id", "", "")
									);
		return $order;							
	}

	public function payme_CheckPerformTransaction() {

		// Поиск транзакции по order_id
		$this->getTransactionByOrderId($this->inputArray['params']['account']['order_id']);
		
		// Поиск заказа по order_id
		$order = $this->payme_getOrder($this->inputArray['params']['account']['order_id'] );

		// Заказ не найден
		if (! $order ) {

			$this->setErrorCod(-31050,'order_id');

		// Заказ найден
		} else {

			// Транзакция статусс
			if (! $this->lastTransaction ) {

				// Проверка состояния заказа
				if ($order['status']!=1 ) {

					$this->setErrorCod(-31052, 'order_id');

				// Сверка суммы заказа
				} else  if ( abs( (round($order['price'] * 100)) - (int)$this->inputArray['params']['amount'])>=0.01) {

					$this->setErrorCod(-31001, 'order_id'); 

				// Allow true
				} else {

					$this->responceType=1;
				} 

			// Существует транзакция
			} else {

				$this->setErrorCod(-31051, 'order_id');
			}
		}
	}

	public function payme_CreateTransaction() {
		
		$this->getTransactionDateByPaymeTrId($this->inputArray['params']['id']);
		
		if ($this->lastTransaction) {
			$order = $this->payme_getOrder($this->lastTransaction['cms_order_id'] );
		}

		// Существует транзакция
		if ($this->lastTransaction) {
			
			$paycom_time_integer=$this->datetime2timestamp($this->lastTransaction['create_time'])*1000;
			$paycom_time_integer=$paycom_time_integer+43200000;

			// Проверка состояния заказа
			if ($order['status']!=1 ){ //order status 2 A

				$this->setErrorCod(-31052, 'order_id');
 
			// Проверка состояния транзакции
			} else if ($this->lastTransaction['state'] !=1){ //Transaction status W

				$this->setErrorCod(-31008, 'order_id');

			// Проверка времени создания транзакции
			} else if ($paycom_time_integer <= $this->timestamp2milliseconds(time())){

				// Отменит reason = 4 
				$fields = array('cancel_time' => date('Y-m-d H:i:s'), 'reason' => 4, 'state'  => -1 );  
				$result = $this->myModx->db->update( $fields, 'payme_transactions', "paycom_transaction_id = '".$this->inputArray['params']['id']."'" );   
				
				$mod_table = $this->myModx->db->config['table_prefix']."manager_shopkeeper";
				$update_arr = array( 'status' => 5 );
                $change_status = $this->myModx->db->update($update_arr, $mod_table, "id =".$order['id']);
                $this->myModx->invokeEvent('OnSHKChangeStatus',array('order_id'=>$order['id'],'status'=>5)); 
				$this->responceType=2;
 
			// Всё OK
			} else {

				$this->responceType=2;
			}

		// Транзакция нет
		} else {
			
			$this->getTransactionByOrderId($this->inputArray['params']['account']['order_id']);
			$order = $this->payme_getOrder($this->inputArray['params']['account']['order_id']); 
 
			// Заказ не найден
			if (! $order ) {

				$this->setErrorCod(-31050,'order_id');

			// Заказ найден
			} else {

				// Транзакция не найден
				if (! $this->lastTransaction ) {
 
				// Проверка состояния заказа 
				if ($order['status']!=1 )  { //order status 1 Q

					$this->setErrorCod(-31052, 'order_id');

				// Сверка суммы заказа 	
				} else  if ( abs( (round($order['price'] * 100)) - (int)$this->inputArray['params']['amount'])>=0.01) {

					$this->setErrorCod(-31001, 'order_id');

				// Запись транзакцию state=1
				} else {
					
					$this->SaveOrder(
									($order['price']*100), 
									$this->inputArray['params']['account']['order_id'],
									$this->inputArray['params']['time'],
									$this->timestamp2datetime($this->inputArray['params']['time'] ),
									$this->inputArray['params']['id'] 
									);
					
					$this->responceType=2;
					$this->getTransactionByOrderId($this->inputArray['params']['account']['order_id']);
				}
				// Существует транзакция
				} else {

				$this->setErrorCod(-31051, 'order_id');
				}
			} //
		}
	}

	public function payme_CheckTransaction() {

		// Поиск транзакции по id
		$this->getTransactionDateByPaymeTrId($this->inputArray['params']['id']);

		// Существует транзакция
		if ($this->lastTransaction) {

			$this->responceType=2;

		// Транзакция нет
		} else {

			$this->setErrorCod(-31003);
		}
	}

	public function payme_PerformTransaction() {

		// Поиск транзакции по id
		$this->getTransactionDateByPaymeTrId($this->inputArray['params']['id']);

		// Существует транзакция
		if ($this->lastTransaction) {

			// Поиск заказа по order_id
			$order = $this->payme_getOrder($this->lastTransaction['cms_order_id']);
  
			// Проверка состояние транзакцие
			if ($this->lastTransaction['state'] ==1){

				$paycom_time_integer=$this->datetime2timestamp($this->lastTransaction['create_time']) *1000;
				$paycom_time_integer=$paycom_time_integer+43200000;

				// Проверка времени создания транзакции
				if ($paycom_time_integer <= $this->timestamp2milliseconds(time())){

					// Отменит reason = 4
					$fields = array('cancel_time' => date('Y-m-d H:i:s'), 'reason' => 4, 'state'  => -1 );  
					$result = $this->myModx->db->update( $fields, 'payme_transactions', "paycom_transaction_id = '".$this->inputArray['params']['id']."'" );   
					
					$mod_table = $this->myModx->db->config['table_prefix']."manager_shopkeeper";
					$update_arr = array( 'status' => 5 );
					$change_status = $this->myModx->db->update($update_arr, $mod_table, "id =".$order['id']);
					$this->myModx->invokeEvent('OnSHKChangeStatus',array('order_id'=>$order['id'],'status'=>5));

				// Всё Ok
				} else {
					// Оплата
					$fields = array('perform_time' => date('Y-m-d H:i:s'), 'state'  => 2 );  
					$result = $this->myModx->db->update( $fields, 'payme_transactions', "paycom_transaction_id = '".$this->inputArray['params']['id']."'" );   
					
					$mod_table = $this->myModx->db->config['table_prefix']."manager_shopkeeper";
					$update_arr = array( 'status' => 2 );
					$change_status = $this->myModx->db->update($update_arr, $mod_table, "id =".$order['id']);
					$this->myModx->invokeEvent('OnSHKChangeStatus',array('order_id'=>$order['id'],'status'=>2));
				}

				$this->responceType=2;
				$this->getTransactionDateByPaymeTrId($this->inputArray['params']['id']);

			// Cостояние не 1
			} else {

				// Проверка состояние транзакцие
				if ($this->lastTransaction['state'] ==2){ //Transaction status

					$this->responceType=2;

				// Cостояние не 2
				} else {

					$this->setErrorCod(-31008);
				}
			}
		// Транзакция нет
		} else {

			$this->setErrorCod(-31003);
		}
	}

	public function payme_CancelTransaction() {
		
		// Поиск транзакции по id
		$this->getTransactionDateByPaymeTrId($this->inputArray['params']['id']);

		// Существует транзакция
		if ($this->lastTransaction) {

			// Поиск заказа по order_id
			$order = $this->payme_getOrder($this->lastTransaction['cms_order_id']);
			$reasonCencel=filter_var($this->inputArray['params']['reason'], FILTER_SANITIZE_NUMBER_INT);

			// Проверка состояние транзакцие
			if ($this->lastTransaction['state'] == 1){ //Transaction status W

				// Отменит state = -1
				$fields = array('cancel_time' => date('Y-m-d H:i:s'), 'reason' => $reasonCencel, 'state'  => -1 );  
				$result = $this->myModx->db->update( $fields, 'payme_transactions', "paycom_transaction_id = '".$this->inputArray['params']['id']."'" );   
				
				$mod_table = $this->myModx->db->config['table_prefix']."manager_shopkeeper";
				$update_arr = array( 'status' => 5 );
				$change_status = $this->myModx->db->update($update_arr, $mod_table, "id =".$order['id']);
				$this->myModx->invokeEvent('OnSHKChangeStatus',array('order_id'=>$order['id'],'status'=>5));

			// Cостояние 2
			} else if ($this->lastTransaction['state'] == 2){ //Transaction status

				// Отменит state = -2
				$fields = array('cancel_time' => date('Y-m-d H:i:s'), 'reason' => $reasonCencel, 'state'  => -2 );  
				$result = $this->myModx->db->update( $fields, 'payme_transactions', "paycom_transaction_id = '".$this->inputArray['params']['id']."'" );   
				
				$mod_table = $this->myModx->db->config['table_prefix']."manager_shopkeeper";
				$update_arr = array( 'status' => 5 );
				$change_status = $this->myModx->db->update($update_arr, $mod_table, "id =".$order['id']);
				$this->myModx->invokeEvent('OnSHKChangeStatus',array('order_id'=>$order['id'],'status'=>5));

			// Cостояние
			} else {

				// Ничего не надо делать
			}

			$this->responceType=2;
			$this->getTransactionDateByPaymeTrId($this->inputArray['params']['id']);

		// Транзакция нет
		} else {

			$this->setErrorCod(-31003);
		}
	}

	public function payme_ChangePassword() {
 
		$propertiesString='';
		$res = $this->myModx->db->select("properties", $this->myModx->getFullTableName('site_snippets'),"upper(name)='PAYME'");  
		 
		if($this->myModx->db->getRecordCount($res)) {  
		 
			$propertiesString = $this->myModx->db->getValue($res);
					
		} else break;
		
		$propertiesJson = json_decode($propertiesString);
		
		    if ($propertiesJson->paymeTestMode[0]->value==1) 
			$propertiesJson->paymePasswordForTest[0]->value=$this->inputArray['params']['password'];
		else if ($propertiesJson->paymeTestMode[0]->value==0) 
			$propertiesJson->paymePassword[0]->value=$this->inputArray['params']['password'];
		
		$propertiesString = json_encode($propertiesJson,JSON_UNESCAPED_UNICODE);
		$fields = array('properties' => $propertiesString);  	
		$result = $this->myModx->db->update( $fields, $this->myModx->getFullTableName('site_snippets'), "upper(name)='PAYME'"); 
		$this->responceType=3;
	}

	public function payme_GetStatement() {
		
		$responseArray = array();
		$transactions  = array();
		
		$sql = "SELECT * FROM payme_transactions as t WHERE 
						       t.paycom_time_datetime >= '".$this->timestamp2datetime($this->inputArray['params']['from']).
					   "' and  t.paycom_time_datetime <= '".$this->timestamp2datetime($this->inputArray['params']['to'])."'" ;
		
		$dbResult= $this->myModx->db->query($sql); 

		while ($row = $this->myModx->db->getRow($dbResult)) {

			array_push($transactions,array(

				"id"		   => $row["paycom_transaction_id"],
				"time"		   => $row['paycom_time']  ,
				"amount"	   => $row["amount"],
				"account"	   => array("cms_order_id" => $row["cms_order_id"]),
				"create_time"  => (is_null($row['create_time']) ? null: $this->datetime2timestamp( $row['create_time']) ) ,
				"perform_time" => (is_null($row['perform_time'])? null: $this->datetime2timestamp( $row['perform_time'])) ,
				"cancel_time"  => (is_null($row['cancel_time']) ? null: $this->datetime2timestamp( $row['cancel_time']) ) ,
				"transaction"  => $row["cms_order_id"],
				"state"		   => (int) $row['state'],
				"reason"	   => (is_null($row['reason'])?null:(int) $row['reason']) ,
				"receivers"	=> null
			)) ;
		}
		$responseArray['result'] = array( "transactions"=> $transactions );

		$this->responceType=4;
		$this->statement=$responseArray; 
	}
 
	public function GenerateResponse() {

		if ($this->errorCod==0) {

			if ($this->responceType==1) {

				$responseArray = array('result'=>array( 'allow' => true )); 

			} else if ($this->responceType==2) {

				$responseArray = array(); 
				$responseArray['id']	 = $this->request_id;
				$responseArray['result'] = array(

					"create_time"	=> $this->datetime2timestamp($this->lastTransaction['create_time']) *1000,
					"perform_time"  => $this->datetime2timestamp($this->lastTransaction['perform_time'])*1000,
					"cancel_time"   => $this->datetime2timestamp($this->lastTransaction['cancel_time']) *1000,
					"transaction"	=> $this->lastTransaction['cms_order_id'], //FIX $this->order_id,
					"state"			=> (int)$this->lastTransaction['state'],
					"reason"		=> ( $this->lastTransaction['reason'] ? (int)$this->lastTransaction['reason'] : null)
				);

			} else if ($this->responceType==3) {

				$responseArray = array('result'=>array( 'success' => true ));

			} else if ($this->responceType==4) {

				$responseArray=$this->statement;
			}

		} else {

			$responseArray['id']	= $this->request_id;
			$responseArray['error'] = array (

				'code'  =>(int)$this->errorCod,
				"data" 	=>$this->errorInfo,
				'message'=> array(

					"ru"=>$this->getGenerateErrorText($this->errorCod,"ru"),
					"uz"=>$this->getGenerateErrorText($this->errorCod,"uz"),
					"en"=>$this->getGenerateErrorText($this->errorCod,"en"),

				)
			);
		}

		return $responseArray;
	}

	public function getGenerateErrorText($codeOfError,$codOfLang){

		$listOfError=array ('-31001' => array(
										  "ru"=>'Неверная сумма.',
										  "uz"=>'Неверная сумма.',
										  "en"=>'Неверная сумма.'
										),
							'-31003' => array(
										  "ru"=>'Транзакция не найдена.',
										  "uz"=>'Транзакция не найдена.',
										  "en"=>'Транзакция не найдена.'
										),
							'-31008' => array(
										  "ru"=>'Невозможно выполнить операцию.',
										  "uz"=>'Невозможно выполнить операцию.',
										  "en"=>'Невозможно выполнить операцию.'
										),
							'-31050' => array(
										  "ru"=>'Заказ не найден.',
										  "uz"=>'Заказ не найден.',
										  "en"=>'Заказ не найден.'
										),
							'-31051' => array(
										  "ru"=>'Существует транзакция.',
										  "uz"=>'Существует транзакция.',
										  "en"=>'Существует транзакция.'
										),
							'-31052' => array(
											"ru"=>'Заказ уже оплачен.',
											"uz"=>'Заказ уже оплачен.',
											"en"=>'Заказ уже оплачен.'
										),
										
							'-32300' => array(
										  "ru"=>'Ошибка возникает если метод запроса не POST.',
										  "uz"=>'Ошибка возникает если метод запроса не POST.',
										  "en"=>'Ошибка возникает если метод запроса не POST.'
										),
							'-32600' => array(
										  "ru"=>'Отсутствуют обязательные поля в RPC-запросе или тип полей не соответствует спецификации',
										  "uz"=>'Отсутствуют обязательные поля в RPC-запросе или тип полей не соответствует спецификации',
										  "en"=>'Отсутствуют обязательные поля в RPC-запросе или тип полей не соответствует спецификации'
										),
							'-32700' => array(
										  "ru"=>'Ошибка парсинга JSON.',
										  "uz"=>'Ошибка парсинга JSON.',
										  "en"=>'Ошибка парсинга JSON.'
										),
							'-32600' => array(
										  "ru"=>'Отсутствуют обязательные поля в RPC-запросе или тип полей не соответствует спецификации.',
										  "uz"=>'Отсутствуют обязательные поля в RPC-запросе или тип полей не соответствует спецификации.',
										  "en"=>'Отсутствуют обязательные поля в RPC-запросе или тип полей не соответствует спецификации.'
										),
							'-32601' => array(
										  "ru"=>'Запрашиваемый метод не найден. В RPC-запросе имя запрашиваемого метода содержится в поле data.',
										  "uz"=>'Запрашиваемый метод не найден. В RPC-запросе имя запрашиваемого метода содержится в поле data.',
										  "en"=>'Запрашиваемый метод не найден. В RPC-запросе имя запрашиваемого метода содержится в поле data.'
										),
							'-32504' => array(
										  "ru"=>'Недостаточно привилегий для выполнения метода.',
										  "uz"=>'Недостаточно привилегий для выполнения метода.',
										  "en"=>'Недостаточно привилегий для выполнения метода.'
										),
							'-32400' => array(
										  "ru"=>'Системная (внутренняя ошибка). Ошибку следует использовать в случае системных сбоев: отказа базы данных, отказа файловой системы, неопределенного поведения и т.д.',
										  "uz"=>'Системная (внутренняя ошибка). Ошибку следует использовать в случае системных сбоев: отказа базы данных, отказа файловой системы, неопределенного поведения и т.д.',
										  "en"=>'Системная (внутренняя ошибка). Ошибку следует использовать в случае системных сбоев: отказа базы данных, отказа файловой системы, неопределенного поведения и т.д.'
										)
							);

		return $listOfError[$codeOfError][$codOfLang];
	}

	public function timestamp2datetime($timestamp){

		if (strlen((string)$timestamp) == 13) {
			$timestamp = $this->timestamp2seconds($timestamp);
		}

		return date('Y-m-d H:i:s', $timestamp);
	}

	public function timestamp2seconds($timestamp) {

		if (strlen((string)$timestamp) == 10) {
			return $timestamp;
		}

		return floor(1 * $timestamp / 1000);
	}

	public function timestamp2milliseconds($timestamp) {

		if (strlen((string)$timestamp) == 13) {
			return $timestamp;
		}

		return $timestamp * 1000;
	}

	public function datetime2timestamp($datetime) {

		if ($datetime) {

			return strtotime($datetime);
		}

		return $datetime;
	}

	public function setErrorCod($cod_,$info=null) {

		$this->errorCod=$cod_;

		if ($info!=null) $this->errorInfo=$info;

		if ($cod_!=0) {

			$this->result=false;
		}
	}

	public function getInputArray() {

		return $this->inputArray;
	}

	public function setInputArray($i_Array) {

		$this->inputArray = json_decode($i_Array, true); 
	}
}
