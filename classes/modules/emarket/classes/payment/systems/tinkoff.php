<?php
	class tinkoffPayment extends payment {
				
		public function validate() { return true; }

		public static function getOrderId() {
			return (int) getRequest('OrderId');
		}

		public function process($template = null) {
			$this->order->order();
			
			objectProxyHelper::includeClass('emarket/classes/payment/api/', 'TinkoffMerchantAPI');
			$tinkoff = new TinkoffMerchantAPI($this->getValue('terminal_key'),$this->getValue('password'),$this->getValue('url'));
				
			$amount	= $this->order->getActualPrice() * 100;
			$orderId = $this->order->getId(); //системный номер заказа UMI
			//$orderId = $this->order->getValue('number'); //номер заказа, отображаемый в админке
			
			$customerId = $this->order->getCustomerId();
			$customer = umiObjectsCollection::getInstance()->getObject($customerId);
			$email = $customer->getValue('e-mail');

			if(!$email) {
				$email = $customer->getValue('email');
			}
			
			$params = array('OrderId' => $orderId, 'Amount' => $amount, 'DATA' => 'Email='.$email);
            $link = $tinkoff->init($params);
			
			$param = array();
			$param['formAction'] = $tinkoff->paymentUrl;			
					
			$this->order->setPaymentStatus('initialized');
			list($templateString) = def_module::loadTemplates("emarket/payment/tinkoff/".$template);
			return def_module::parseTemplate($templateString, $param);
		}

		public function poll() {

			$buffer = outputBuffer::current();
			$buffer->clear();
			$buffer->contentType('text/plain');
			
			if (!is_null(getRequest('TerminalKey')) && !is_null(getRequest('OrderId')) && !is_null(getRequest('Amount')) && !is_null(getRequest('PaymentId')) &&!is_null(getRequest('Token')) && !is_null(getRequest('Success'))) {
				if ($this->checkToken()){
						
					$orderId = getRequest('OrderId');
					$amount = (int) getRequest('Amount');
					$status = getRequest('Status');
					$paymentId = getRequest('PaymentId');					
					$orderActualPrice = $this->order->getActualPrice() * 100;
					$umiOrderId = $this->order->getId();
					//$umiOrderId = $this->order->getValue('number'); //номер заказа, отображаемый в админке

					if ((getRequest('TerminalKey') == $this->getValue('terminal_key')) && ($orderActualPrice == $amount) && ($umiOrderId == $orderId)) {
				 		$success = getRequest('Success');
						switch ($status){							
							case 'REJECTED':
								if ($success == 'true') {
									$this->order->setPaymentStatus('declined');
									$buffer->push('OK');
									break;
								} else {																		
									$buffer->push('OK');									
									break;
								}
							case 'REVERSED':
								if ($success == 'true') {
									$this->order->setPaymentStatus('declined');
									$buffer->push('OK');
									break;
								} else {
									$this->order->setOrderStatus('waiting');
									$buffer->push('OK');									
									break;
								}								
							case 'REFUNDED':
								if ($success == 'true') {
									$this->order->setPaymentStatus('declined');								
									$buffer->push('OK');
									break;
								} else {
									$this->order->setOrderStatus('accepted');
									$buffer->push('OK');									
									break;
								}								
							case 'AUTHORIZED':
								if ($success == 'true') {
									$this->order->setPaymentStatus('validated');
									$this->order->order();
									$this->order->payment_document_num = $paymentId;
									$buffer->push('OK');
									break;
								} else {									
									$buffer->push('OK');									
									break;
								}
							case 'CONFIRMED':
								if ($success == 'true') {
									$this->order->setPaymentStatus('accepted');
									$this->order->payment_document_num = $paymentId;
									$buffer->push('OK');
									break;
								} else {
									$this->order->setOrderStatus('waiting');
									$buffer->push('OK');									
									break;
								}
						}
					} else {
						$buffer->push('OrderIds, OrderPrices or TerminalKeys are different!');
					}
				} else {
					$buffer->push('Tokens are different!');
				}	
			}
			$buffer->end();
		}

		public function checkToken() {
			$query_array = array();
			if (getRequest('TerminalKey')) $query_array['TerminalKey'] = getRequest('TerminalKey');
			if (getRequest('OrderId')) $query_array['OrderId'] = getRequest('OrderId');
			if (getRequest('PaymentId')) $query_array['PaymentId'] = getRequest('PaymentId');			
			if (getRequest('Amount')) $query_array['Amount'] = getRequest('Amount');
			if (getRequest('Success')) $query_array['Success'] = getRequest('Success');
			if (getRequest('RebillId')) $query_array['RebillId'] = getRequest('RebillId');
			if (getRequest('CardId')) $query_array['CardId'] = getRequest('CardId');
			if (getRequest('Pan')) $query_array['Pan'] = getRequest('Pan');

			$query_array['ErrorCode'] = getRequest('ErrorCode');
			$query_array['Status'] = getRequest('Status');
			$query_array['Password'] = $this->getValue('password');
			
			ksort($query_array);
			$token = implode('', $query_array);
			$token = hash('sha256', $token);


				//Logging all the parametrs sent by Tinkoff in POST request
				$info = '$_REQUEST[\'url\'] method:';
				$info .= PHP_EOL;
				foreach ($_REQUEST as $key => $value){
					$info .= $key . ' = ' . $value;
					$info .= PHP_EOL;
				}
				//Adding to the log parametrs which read by getRequest() method that should have been sent by Tinkoff in POST request
				$info .= PHP_EOL;
				$info .= 'getRequest() method:';
				$info .= PHP_EOL;
				foreach ($query_array as $key => $value){
						$info .= $key . ' = ' . $value;
						$info .= PHP_EOL;
					}
				$file =  CURRENT_WORKING_DIR . '/tinkoffParamsFromPostRequest.txt';
				file_put_contents($file, $info);


			if(strcasecmp($token, getRequest('Token') ) == 0) {
				return true;
			}		
			
			return false;
			
		} 

	};
?>