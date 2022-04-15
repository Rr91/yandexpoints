<?php

class shopYandexpointsPlugin extends shopPlugin
{
	public static function sOs(){
		$model = new waModel();
		$query = "SELECT order_id, `datetime` FROM `shop_order_log` WHERE action_id = 'pvz_kogda-zaberut-zakaz-' ORDER BY `datetime` DESC LIMIT 1000";
		$rs = $model->query($query)->fetchAll();
		foreach ($rs as $key => $value) {
			echo "<a target='_blank' href='/webasyst/shop/?action=orders#/order/".$value['order_id']."/'>Эгт".$value['order_id']."</a> - <span>".$value['datetime']."</span><br/>";
		}
		// $plugin = wa('shop')->getPlugin('yandexpoints');
		// $plugin->getGrastinPoints();
		// $plugin->searchChager();
	}
	

	public function backendMenu()
	{
		return array('core_li' => '<li class="no-tab"><a href="?plugin=yandexpoints">ПВЗ яндекс</a></li>');
	}

	// получение точек службы доставки
	public function getGrastinPoints(){

		$settings = $this->getSettings();
		$grastin_token = $settings['grastin_token'];
		
		$prices['Москва'] = $settings['delivery_cost_by_yandex'];
		$prices['Санкт-Петербург'] = $settings['delivery_cost_by_yandex_piter'];
		
		$xml = "<File><API>".$grastin_token."</API><Method>selfpickup</Method></File>";
		$url = 'http://api.grastin.ru/api.php';
        
        $ch  = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, 1 );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, 'XMLPackage=' . urlencode( $xml ) );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_HEADER, 0 );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
        $xml_string = curl_exec( $ch );
        curl_close( $ch );	

		$xml = new simpleXmlIterator( $xml_string, null );
        $result = $this->xmlToArray( $xml, null );
		$points = $result["Selfpickup"];

		$job_city = array("Москва", "Санкт-Петербург");
		$regions_ids = array($job_city[0] => 213, $job_city[1]=>2);
		$phones_shop = array($job_city[0] => "+7 (499) 390-16-09", $job_city[1]=>"+7 (812) 309-50-37");
		$delivery_costs = array($job_city[0] => $prices[$job_city[0]], $job_city[1]=>$prices[$job_city[1]]);
		$yandex_points = array();

		foreach ($points as $value) {
			if(in_array($value['city'][0], $job_city)){
				$point = array();
				$point['name'] = $value['Name'][0];
				$point['type'] = "DEPOT";
				$point['coord'] = $value['longitude'][0].",".$value['latitude'][0];
				$point['isMain'] = false;
				$point['shopOutletCode'] = $value['id'][0];
				$point['visibility'] = "VISIBLE";
				$point['address']['regionId'] = $regions_ids[$value['city'][0]];
				$addr = $this->getAddress($value['address'][0]);
				$point['address']['street'] = $addr['street'];
				if($addr['number']) $point['address']['number'] = $addr['number'];
				$point['phones'][0] = str_replace("8(", "+7 (", str_replace("доб.", "#", $value['phone'][0]));
				$point['phones'][1] = $phones_shop[$value['city'][0]];
				$point['workingSchedule']['scheduleItems'] = $this->getScheduleItems($value['timetable'][0]);
				$point['deliveryRules'][0]['cost'] = $delivery_costs[$value['city'][0]];
				$point['deliveryRules'][0]['unspecifiedDeliveryInterval'] = true;
				$point['emails'][] = "info@zbat.ru";
				$yandex_points[] = $point;
			}
		}
		$model = new shopYandexpointsModel();
		$model->savePointsFromGrastin($yandex_points);

	}
	// получение текущих точек яндека
	public function getYandexPoints($campID = false){
		$settings = $this->getSettings();
		$id_app = $settings['yandex_app_id'];
		$token = $settings['yandex_token'];
		$campaignId = $campID?$campID:$settings['campaing_id'];
		 
		$url = "https://api.partner.market.yandex.ru/v2/campaigns/$campaignId/outlets.json";
		$authorization = 'OAuth oauth_token="'.$token.'", oauth_client_id="'.$id_app.'"';
		$header = array("Authorization: ".$authorization);

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl, CURLOPT_HEADER, 0);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
		$out = curl_exec($curl);
		$obj = json_decode($out, true);
		curl_close($curl);
		return $obj['outlets'];
	}
	// преобразование данных по точке к структуре приемлимой для yandex
	private function preparePointForYandex($point){
		$data = array();
		$data["shopOutletCode"] = $point['shopOutletCode'];
		$data["name"] = $point['name'];
		$data["type"] = "DEPOT";
		$data["visibility"] = "VISIBLE";
		$data["isMain"] = false;
		$data["coords"] = $point['coord'];
		$email = explode(",", $point['emails']);
		$data["emails"][0] = $email[0];
		$data["address"]["regionId"] = $point['region'];
		$data["address"]["street"] = $point['street'];
		$data["address"]["number"] = $point['number_home'];
		$phones = explode(",", $point['phones']);
		$data["phones"][0] = $phones[0];
		$data["workingSchedule"]["scheduleItems"] = json_decode($point["scheduleItems"], true);
		$data["deliveryRules"][0]['cost'] = intval($point['cost']);
		$data["deliveryRules"][0]['unspecifiedDeliveryInterval'] = true;
		return $data;
	}
	// запись в лог
	private function myLog($log, $loger_name, $result){
		$log_message = $loger_name." - ".$log." - Результат - ".$result;
		waLog::log($log_message, 'yandexpoints.log');
	}
	// обновление данных точки в яндекс
	public function updatePointOnYandex($point, $loger, $campID = false){
		$loger_name = $point['name'];
		$point_id = $point['id'];
		$json = json_encode($point);

		$settings = $this->getSettings();
		$id_app = $settings['yandex_app_id'];
		$token = $settings['yandex_token'];
		$campaignId = $campID?$campID:$settings['campaing_id'];
		 
		$url = "https://api.partner.market.yandex.ru/v2/campaigns/$campaignId/outlets/$point_id.json";
		$authorization = 'OAuth oauth_token="'.$token.'", oauth_client_id="'.$id_app.'"';
		$header = array("Authorization: ".$authorization, "Content-Type: application/json");

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
		$result = curl_exec($curl);
		curl_close($curl);
		$this->myLog($loger, $loger_name, $result);
	}
	// добавление точки в яндекс
	public function addNewPointOnYandex($point, $loger, $campID = false){
		$loger_name = $point['name']; 
		$point = $this->preparePointForYandex($point);
		// wa_dump($point);
		$json = json_encode($point);

		$settings = $this->getSettings();
		$id_app = $settings['yandex_app_id'];
		$token = $settings['yandex_token'];
		$campaignId = $campID?$campID:$settings['campaing_id'];
		 
		$url = "https://api.partner.market.yandex.ru/v2/campaigns/$campaignId/outlets.json";
		$authorization = 'OAuth oauth_token="'.$token.'", oauth_client_id="'.$id_app.'"';
		$header = array("Authorization: ".$authorization, "Content-Type: application/json");

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
		$result = curl_exec($curl);
		$this->myLog($loger, $loger_name, $result);
	}

	public function addPoints($add_points, $campID){
		foreach ($add_points as $key => $point) {
			$point['shopOutletCode'] = $key;
			$this->addNewPointOnYandex($point, "Добавление точки", $campID);
		}
	}

	public function deletePoints($delete_points, $campID = false){
		// wa_dump($delete_points);
		$settings = $this->getSettings();
		$id_app = $settings['yandex_app_id'];
		$token = $settings['yandex_token'];
		$campaignId = $campID?$campID:$settings['campaing_id'];

		$authorization = 'OAuth oauth_token="'.$token.'", oauth_client_id="'.$id_app.'"';
		$header = array("Authorization: ".$authorization, "Content-Type: application/json");

		foreach ($delete_points as $key => $point) {
			$loger_name = $point['name'];
			$point_id = $point['id'];
			$url = "https://api.partner.market.yandex.ru/v2/campaigns/$campaignId/outlets/$point_id.json";
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, $url);
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
			$result = curl_exec($curl);
			curl_close($curl);
			$this->myLog("Удаление точки", $loger_name, $result);
		}
	}



	public function searchChager($campID = false)
	{
		$upd_points = array();
		$ad_points = array();
		$clear_points = array();

		$job_regions = array(2, 213);
		$model = new shopYandexpointsModel();
		$new_points = $model->getAll("id", 1);
        $current_points = $this->getYandexPoints($campID);
		$delete_points = array();
		$loger = array();
		foreach ($current_points as $k=>&$cur_point) {
			// обрабатываем только москву и питер
			// добавляем точку в массив на удаление
				$delete_points[$k] = $cur_point; 
			if(in_array($cur_point["address"]["regionId"], $job_regions))
			{
				$loger = "";
				// если среди стрых точек находим с нужным id 
				if(isset($new_points[$cur_point["shopOutletCode"]])){
					$current_code = $cur_point["shopOutletCode"];
					$new_point = $new_points[$cur_point["shopOutletCode"]];
					// если точка есть и в новых то выкидываем ее из этого массива на удаление
					unset($delete_points[$k]);
					// 	сравниваем что изменилось, обновляем изменные данные.
					if($new_point['coord'] && $cur_point['coords'] != $new_point['coord']) {
						// проверяются координаты - если не равны и адрес меняем автоматом
						$cur_point['coords'] = $new_point['coord'];
						$cur_point['address']["street"] = $new_point['street'];
						$cur_point['address']["number"] = $new_point['number_home'];
						$loger .= "Обновлены координаты/n";
					}
					$new_phones = explode(",", $new_point['phones']);
					foreach ($cur_point['phones'] as $key_phone => &$phone) { 
						$phone = str_replace("доб. ", "#", $phone);
					}
					// phones
					$cur_phone_compare = str_replace(" ", "", $cur_point['phones'][0]); 
					$new_phone_compare = str_replace(" ", "", str_replace("-", "", $new_phones[0]));

					$cur_phone_compare1 = $cur_point['phones'][1]?str_replace(" ", "", $cur_point['phones'][1]):false; 
					$new_phone_compare1 = $new_phones[1]?str_replace(" ", "", str_replace("-", "", $new_phones[1])):false;

					if($cur_phone_compare != $new_phone_compare || $cur_phone_compare1 != $new_phone_compare1){
						$cur_point['phones'][0] = $new_phones[0];
						$cur_point['phones'][1] = $new_phones[1];
						$loger .= "Телефоны обновлены/n"; 
					}

					// emails
					$new_emails = explode(",", $new_point['emails']);
					if($cur_point['emails'][0] != $new_emails[0]){
						$cur_point['emails'][0] = $new_emails[0];
						$loger .= "Email обновлен/n"; 
					}
					// name
					if($cur_point['name'] != $new_point['name']){
						$cur_point['name'] = $new_point['name'];
						$loger .= "Имя точки обновлено/n"; 
					}
					// shopOutletCode
					if($cur_point['shopOutletCode'] != $current_code){
						$cur_point['shopOutletCode'] = $current_code;
						$loger .= "Код точки обновлен/n"; 
					}
					//cost
					if(intval($cur_point['deliveryRules'][0]['cost']) != intval($new_point['cost'])){
						$cur_point['deliveryRules'][0]['cost'] = intval($new_point['cost']);
						$loger .= "Стоимость доставки обновлена/n"; 
					}
					// время работы
					$new_scheduleItems = json_decode($new_point["scheduleItems"], true);
					foreach($cur_point['workingSchedule']['scheduleItems'] as $kp=>&$pt) {
						$upd_time = false;
						if($pt['startDay'] != $new_scheduleItems[$kp]['startDay']){ 
							$pt['startDay'] = $new_scheduleItems[$kp]['startDay'];
							$upd_time = true;
						}
						if($pt['endDay'] != $new_scheduleItems[$kp]['endDay']){
							$pt['endDay'] = $new_scheduleItems[$kp]['endDay'];
							$upd_time = true;
						}
						if($pt['startTime'] != $new_scheduleItems[$kp]['startTime']){
							$pt['startTime'] = $new_scheduleItems[$kp]['startTime'];
							$upd_time = true;
						}
						if($pt['endTime'] != $new_scheduleItems[$kp]['endTime']){
							$pt['endTime'] = $new_scheduleItems[$kp]['endTime'];
							$upd_time = true;
						}
						if($upd_time) $loger .= "Время работы обновлено/n"; 
					}
					if($loger){
						$upd_points[] = $cur_point;
						$this->updatePointOnYandex($cur_point, $loger, $campID);
					}
					else { $clear_points[] = $cur_point; }
					unset($new_points[$cur_point["shopOutletCode"]]);
				}
				
			}
		}
		$this->addPoints($new_points, $campID);
		$this->deletePoints($delete_points, $campID);
		return array("add" => $new_points, "update" => $upd_points, "delete" => $delete_points);
	}

	private function getScheduleItems($timetable){
		$timetable = mb_strtolower($timetable);	
		$timetable = str_replace(" часов", ":00", $timetable);
		$result = array();
		$result[0]['startDay'] = "MONDAY";
		
		if(strpos($timetable, "ежедневно") !== false){
			$result[0]['endDay'] = "SUNDAY";
			$hour = trim(str_replace("ежедневно с", "", $timetable));
			$hour_arr = array_map('trim', explode("до", $hour));
			$startTime = str_replace(".", ":", $hour_arr[0]);
			$endTime = str_replace(".", ":", $hour_arr[1]);
			if(strlen($startTime) <= 2){
				$startTime = $startTime.":00";
			}
			if(strlen($endTime) <= 2){
				$endTime = $endTime.":00";
			}
			$result[0]['startTime'] = $startTime;
			$result[0]['endTime'] =  $endTime;
		}
		elseif(strpos($timetable, "пн-сб") !== false){
			$result[0]['endDay'] = "SATURDAY";
			$timetable_arr = explode(",", $timetable);
			$hour = trim(str_replace("пн-сб с", "", $timetable_arr[0]));
			$hour_arr = array_map('trim', explode("до", $hour));
			$startTime = str_replace(".", ":", substr($hour_arr[0], 0, 5));
			$endTime = str_replace(".", ":", substr($hour_arr[1], 0, 5));
			if(strlen($startTime) <= 2){
				$startTime = $startTime.":00";
			}
			if(strlen($endTime) <= 2){
				$endTime = $endTime.":00";
			}
			$result[0]['startTime'] = $startTime;
			$result[0]['endTime'] = $endTime;
		}
		else{
			$result[0]['endDay'] = "FRIDAY";
			$timetable_arr = explode(",", $timetable);
			$hour = trim(str_replace("пн-пт с", "", $timetable_arr[0]));
			$hour_arr = array_map('trim', explode("до", $hour));
			$startTime = str_replace(".", ":", substr($hour_arr[0], 0, 5));
			$endTime = str_replace(".", ":", substr($hour_arr[1], 0, 5));
			if(strlen($startTime) <= 2){
				$startTime = $startTime.":00";
			}
			if(strlen($endTime) <= 2){
				$endTime = $endTime.":00";
			}
			$result[0]['startTime'] = $startTime;
			$result[0]['endTime'] = $endTime;

			if(strpos($timetable, "сб с") !== false){
				$result[1]['startDay'] = "SATURDAY";
				$result[1]['endDay'] = "SATURDAY";
				$hour = trim(str_replace("сб с", "", $timetable_arr[1]));
				$hour_arr = array_map('trim', explode("до", $hour));
				$startTime = str_replace(".", ":", substr($hour_arr[0], 0, 5));
				$endTime = str_replace(".", ":", substr($hour_arr[1], 0, 5));
				if(strlen($startTime) <= 2){
					$startTime = $startTime.":00";
				}
				if(strlen($endTime) <= 2){
					$endTime = $endTime.":00";
				}
				$result[1]['startTime'] = $startTime;
				$result[1]['endTime'] = $endTime;
			}
		}
		return $result;
	}
	
	private function getAddress($grastin_addr){
		$sep = "д.";
		$addr_arr = explode($sep, $grastin_addr);
		$result = array();
		$result['street'] = trim($addr_arr[0]);
		$number = trim($addr_arr[1]);
		$result['number'] = $number?$number:false;
		return $result;
	}

	private function xmlToArray( $xml, $namespaces = null ) {
	       //   waLog::dump($xml);
	        $a = array();
	        try {

	            $xml->rewind();
	            while ( $xml->valid() ) {
	                $key = $xml->key();
	                if ( ! isset( $a[ $key ] ) ) {
	                    $a[ $key ] = array();
	                    $i         = 0;
	                } else {
	                    $i = count( $a[ $key ] );
	                }
	                $simple = true;
	                foreach ( $xml->current()->attributes() as $k => $v ) {
	                    $a[ $key ][ $i ][ $k ] = (string) $v;
	                    $simple                = false;
	                }

	                if ( $namespaces ) {
	                    foreach ( $namespaces as $nid => $name ) {
	                        foreach ( $xml->current()->attributes( $name ) as $k => $v ) {
	                            $a[ $key ][ $i ][ $nid . ':' . $k ] = ( string ) $v;
	                            $simple                             = false;
	                        }
	                    }
	                }
	                if ( $xml->hasChildren() ) {
	                    if ( $simple ) {
	                        $a[ $key ][ $i ] = $this->xmlToArray( $xml->current(), $namespaces );
	                    } else {
	                        $a[ $key ][ $i ]['content'] = $this->xmlToArray( $xml->current(), $namespaces );
	                    }
	                } else {
	                    if ( $simple ) {
	                        $a[ $key ][ $i ] = strval( $xml->current() );
	                    } else {
	                        $a[ $key ][ $i ]['content'] = strval( $xml->current() );
	                    }
	                }
	                $i ++;
	                $xml->next();
	            }
	        } catch ( Exception $ex ) {
	            // waLog::log( $ex->getMessage() );
	            // waLog::dump( 'жопа' );
	        }
	        return $a;
	}

	public static function getFullGrastinCache()
	{
		$my_towns = array("МОСКВА", "ПОДОЛЬСК", "БАЛАШИХА", "ЛЮБЕРЦЫ", "ХИМКИ", "МЫТИЩИ", "ОДИНЦОВО", "САНКТ-ПЕТЕРБУРГ", "КОРОЛЕВ", "ЩЕЛКОВО");
		
		foreach ($my_towns as $to_city) {
			if(!$to_city){
				$file = "https://grastin.ru/cimgs/widget_pickuplist.xml";
				$file_name = '/home/p468983/www/zbat.ru/widget_pickuplist.xml';
			}
			else{
				$file = "https://grastin.ru/cimgs/v2/widget_pickuplist_".$to_city.".xml";
				$file_name = "/home/p468983/www/zbat.ru/widget_pickuplist_".$to_city.".xml";
			}

		    $ch = curl_init();
		    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		    curl_setopt($ch, CURLOPT_HEADER, false);
		    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		    curl_setopt($ch, CURLOPT_URL, $file);
		    curl_setopt($ch, CURLOPT_REFERER, $file);
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3000); // 3 sec.
		    curl_setopt($ch, CURLOPT_TIMEOUT, 10000); // 10 sec.
		    $data = curl_exec($ch);
		    curl_close($ch);

		    if($data){
				file_put_contents($file_name, $data);
		    }
		}

	}

	public static function getFullGrastinCacheDate(){
		$file_name = $_SERVER['DOCUMENT_ROOT'].'/widget_pickuplist_МОСКВА.xml';
		return filemtime($file_name);
	}
}
