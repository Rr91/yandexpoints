<?php 
class shopYandexpointsPluginCronCli extends waCliController
{
    public function execute()
    {
	   $plugin = wa('shop')->getPlugin('yandexpoints');
	   $plugin::getFullGrastinCache();
	   $plugin->getGrastinPoints();
	   $data = $plugin->searchChager();
	   $data = $plugin->searchChager(22044175);
	   // mail("rr@easy-it.ru", "subject", "yp");
    }
}
