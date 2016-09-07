<?php
	abstract class __emarket_custom {
		
		/* Функция реализуют cписание захолдированных денежных средств покупателя при подтверждении заказа (изменение статуса заказа с "ожидает проверки" на "принят") */		

		public function customOnModifyProperty(iUmiEventPoint $event) {
			$entity = $event->getRef("entity");
			if($entity instanceof iUmiObject) {
				$allowedProperties = array("status_id", "payment_status_id", "delivery_status_id");
				$typeId = umiObjectTypesCollection::getInstance()->getBaseType('emarket', 'order');
				if(($entity->getTypeId() == $typeId) &&
					(in_array($event->getParam("property"), $allowedProperties) ) &&
					($event->getParam("newValue") != $event->getParam("oldValue")) ) {
					if ($event->getParam("property") == 'payment_status_id' && $event->getParam("newValue") == order::getStatusByCode('accepted', 'order_payment_status')) {
						self::addNewBonus($entity->getId());
					}
					if ($event->getParam("property") == 'status_id' &&
						$event->getParam("newValue") == order::getStatusByCode('accepted') &&
						order::getCodeByStatus($entity->payment_status_id) == 'validated') {
							$tinkoffPaymentId = $entity->getValue('payment_document_num');
							if ($tinkoffPaymentId) {								
								$paymentId = $entity->getValue('payment_id');
								$payment = payment::get($paymentId);
								if($payment instanceof payment){									
									objectProxyHelper::includeClass('emarket/classes/payment/api/', 'TinkoffMerchantAPI');								
									$tinkoff = new TinkoffMerchantAPI($payment->getValue('terminal_key'),$payment->getValue('password'),$payment->getValue('url'));
									
									$params = array('PaymentId' => $tinkoffPaymentId);
									$tinkoff->confirm($params);
								}
							}
					}
					if ($event->getParam("property") == 'status_id' &&
						$event->getParam("newValue") == order::getStatusByCode('canceled') &&
						order::getCodeByStatus($entity->payment_status_id) == 'validated') {
							$tinkoffPaymentId = $entity->getValue('payment_document_num');
							if ($tinkoffPaymentId) {								
								$paymentId = $entity->getValue('payment_id');
								$payment = payment::get($paymentId);
								if($payment instanceof payment){									
									objectProxyHelper::includeClass('emarket/classes/payment/api/', 'TinkoffMerchantAPI');								
									$tinkoff = new TinkoffMerchantAPI($payment->getValue('terminal_key'),$payment->getValue('password'),$payment->getValue('url'));
									
									$params = array('PaymentId' => $tinkoffPaymentId);
									$tinkoff->cancel($params);
								}
							}
					}						
				}
			}
		}

		/* Функция реализуют возврат захолдированных или уже списанных денежных средств покупателю при последующей отмене заказа (изменение статуса заказа с "ожидает проверки" на "отменен" либо с "принят" на "отменен") */
		
		public function customOnModifyObject(iUmiEventPoint $event) {
			static $modifiedCache = array();
			$object = $event->getRef("object");
			$typeId = umiObjectTypesCollection::getInstance()->getBaseType('emarket', 'order');
			if($object->getTypeId() != $typeId) return;
			if($event->getMode() == "before") {
				$data = getRequest("data");
				$id   = $object->getId();
				$newOrderStatus    = getArrayKey($data[$id], 'status_id');
				$newPaymentStatus  = getArrayKey($data[$id], 'payment_status_id');
				$newDeliveryStatus = getArrayKey($data[$id], 'delivery_status_id');
				switch(true) {
				   case ($newOrderStatus != $object->getValue("status_id") ) : {
					   $modifiedCache[$object->getId()] = "status_id";					   
					   break;
				   }
				   case ($newDeliveryStatus != $object->getValue("delivery_status_id")) : $modifiedCache[$object->getId()] = "delivery_status_id"; break;
				   case ($newPaymentStatus != $object->getValue("payment_status_id") ) : $modifiedCache[$object->getId()] = "payment_status_id"; break;				   
				}
			} else {
				if(isset($modifiedCache[$object->getId()])) {
					if ($modifiedCache[$object->getId()] == 'payment_status_id' && $object->getValue("payment_status_id") == order::getStatusByCode('accepted', 'order_payment_status')) {
						self::addNewBonus($object->getId());
					}
					if ($modifiedCache[$object->getId()] == 'status_id' &&
						$object->getValue('status_id') == order::getStatusByCode('accepted') &&
						order::getCodeByStatus($object->payment_status_id) == 'validated') {
							$tinkoffPaymentId = $object->getValue('payment_document_num');
							if ($tinkoffPaymentId) {								
								$paymentId = $object->getValue('payment_id');
								$payment = payment::get($paymentId);
								if($payment instanceof payment){									
									objectProxyHelper::includeClass('emarket/classes/payment/api/', 'TinkoffMerchantAPI');								
									$tinkoff = new TinkoffMerchantAPI($payment->getValue('terminal_key'),$payment->getValue('password'),$payment->getValue('url'));
									
									$params = array('PaymentId' => $tinkoffPaymentId);
									$tinkoff->confirm($params);

								}
							}
					}
					if ($modifiedCache[$object->getId()] == 'status_id' &&
						$object->getValue('status_id') == order::getStatusByCode('canceled') &&
						order::getCodeByStatus($object->payment_status_id) == 'validated') {
							$tinkoffPaymentId = $object->getValue('payment_document_num');
							if ($tinkoffPaymentId) {								
								$paymentId = $object->getValue('payment_id');
								$payment = payment::get($paymentId);
								if($payment instanceof payment){									
									objectProxyHelper::includeClass('emarket/classes/payment/api/', 'TinkoffMerchantAPI');								
									$tinkoff = new TinkoffMerchantAPI($payment->getValue('terminal_key'),$payment->getValue('password'),$payment->getValue('url'));
									
									$params = array('PaymentId' => $tinkoffPaymentId);
									$tinkoff->canceled($params);

								}
							}
					}
				}
			}		
		}

		/* Редирект покупателя на страницу об успешной оплаты из Тинькофф */
		/* Success URL будет иметь вид site.ru/emarket/successfulTinkoffPayment/ */

		public function successfulTinkoffPayment(){
			$urlPrefix = cmsController::getInstance()->getUrlPrefix() ? (cmsController::getInstance()->getUrlPrefix() . '/') : '';			
			$this->redirect($this->pre_lang .'/'. $urlPrefix . 'emarket/purchase/result/successful/');
		}

		/* Редирект покупателя на страницу о неудачной оплаты */
		/* Fail URL будет иметь вид site.ru/emarket/failedTinkoffPayment/ */
		
		public function failedTinkoffPayment(){
			$urlPrefix = cmsController::getInstance()->getUrlPrefix() ? (cmsController::getInstance()->getUrlPrefix() . '/') : '';			
			$this->redirect($this->pre_lang .'/'. $urlPrefix . 'emarket/purchase/result/');
		}

	};
?>
