<?php


class shopYandexpointsModel extends waModel
{
	protected $table = "shop_yandex_points";

	public function savePointsFromGrastin($points)
	{
		if($points){
			$query = "DELETE FROM shop_yandex_points";
			$this->query($query);
		}
		foreach ($points as $point) {
			$data = array(
				"id" => $point["shopOutletCode"],
				"name" => $point["name"],
				"coord" => $point["coord"],
				"region" => $point["address"]["regionId"],
				"street" => $point["address"]["street"],
				"number_home" => $point["address"]["number"],
				"phones" => implode(",", $point["phones"]),
				"scheduleItems" => json_encode($point["workingSchedule"]['scheduleItems']),
				"cost" => $point["deliveryRules"][0]['cost'],
				"emails" => implode(",", $point["emails"]),
				"state" => 1
			);
			$this->insert($data, 1);
		}
	}

}