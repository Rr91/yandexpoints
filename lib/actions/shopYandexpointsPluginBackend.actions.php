<?php

class shopYandexpointsPluginBackendActions extends waViewActions
{
	public function runAction(){
		$plugin = wa('shop')->getPlugin('yandexpoints');
		$plugin::getFullGrastinCache();
		$plugin->getGrastinPoints();
		$data = $plugin->searchChager();
		$data = $plugin->searchChager(22044175);
		$response = "";
		if($data['add']){
			$response .= "<div style='color:green;'>Добавлено : <br><ul>";
			foreach ($data['add'] as $key => $value) {
				$response.= "<li>".$value['name']."</li>";
			}
			$response.= "</ul></div>";
		}
		else $response .= "<div style='color:green;'>Добавлено : Нет<div>";
			
		if($data['update']){
			$response .= "<div style='color:#03c;'>Обновлено : <br><ul>";
			foreach ($data['update'] as $key => $value) {
				$response.= "<li>".$value['name']."</li>";
			}
			$response.= "</ul></div>";
		}
		else $response .= "<div style='color:green;'>Обновлено : Нет<div>";

		if($data['delete']){
			$response .= "<div style='color:red;'>Удалено : <br><ul>";
			foreach ($data['delete'] as $key => $value) {
				$response.= "<li>".$value['name']."</li>";
			}
			$response.= "</ul></div>";
		}
		else $response .= "<div style='color:green;'>Удалено : Нет<div>";

		$response.="<div style='color:red; margin-bottom:20px;'>Успешность выполнения запросов к яндексу здесь - <a target='blank' href='/webasyst/logs/?action=file&path=yandexpoints.log'>Лог</a></div>";
		echo json_encode(array('result' => $response));
		exit;	
	}

	public function defaultAction() {   
		$model = new shopYandexpointsModel();
		$date = shopYandexpointsPlugin::getFullGrastinCacheDate();
		$data = $model->getAll();
		foreach ($data as &$point) {
			$point["scheduleItems"] = json_decode($point["scheduleItems"], true);
			foreach ($point["scheduleItems"] as &$val) {
				$val['startDay'] = $this->dayRu($val['startDay']);
				$val['endDay'] = $this->dayRu($val['endDay']);
			}
			$point["region"] == 213 ? $point["region"] = "Москва" : $point["region"] = "Санкт-Петербург";
		}
		$this->view->assign('data', $data);
		$this->view->assign('date', $date);
		$this->setBackendLayout();

	}
	private function setBackendLayout() {
		$l = new shopBackendLayout();
        $l->assign('no_level2', true);
        $this->setLayout($l);
	}

	private function dayRu($day){
		$days = array(
			"MONDAY" => "Понедельник",
			"FRIDAY" => "Пятница",
			"SATURDAY" => "Суббота",
			"SUNDAY" => "Воскресение",
		);
		return isset($days[$day])?$days[$day]:$day;
	}

}