<?php

class Shopware_Plugins_Frontend_ScnSubresourceIntegrity_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    private function getPluginJson()
    {
        $json = json_decode(file_get_contents(__DIR__ . '/plugin.json'), true);

        if ($json) {
            return $json;
        } else {
            throw new Exception('Cannot find plugin.json');
        }
    }

    public function getInfo()
    {
        $json = $this->getPluginJson();

        return array(
            'version' => $json['currentVersion'],
            'label' => $json['label']['de'],
            'copyright' => $json['copyright'],
            'license' => $json['license'],
            'author' => $json['author'],
            'description' => $json['description'],
            'support' => $json['support'],
            'link' => $json['link']
        );
    }

    public function getCapabilities()
    {
        return array(
            'install' => true,
            'update' => true,
            'enable' => true
        );
    }

    public function install()
    {
        $this->registerEvents();
        $this->createConfiguration();

        return array('success' => true, 'invalidateCache' => array('frontend', 'theme'));
    }

    public function uninstall()
    {
        return array('success' => true, 'invalidateCache' => array('frontend', 'theme'));
    }

    public function update()
    {
        return true;
    }

    private function createConfiguration()
    {
        $form = $this->Form();

        $form->setElement('checkbox', 'enableCss', array(
            'label' => 'Enable SRI for CSS-Resources',
            'value' => 1
        ));

        $form->setElement('checkbox', 'enableJs', array(
            'label' => 'Enable SRI for JS-Resources',
            'value' => 1
        ));

        $form->setElement('checkbox', 'activateCrossoriginAnonymous', array(
            'label' => 'Set crossorign anonymous on JS-Resources',
            'value' => 0
        ));
    }

    private function registerEvents()
    {
        // Priority 401 ensures that our plugin gets added after initialization of smarty
        $this->subscribeEvent('Enlight_Controller_Action_Init', 'onActionInit', 401);
        $this->subscribeEvent('Enlight_Controller_Action_PostDispatchSecure_Frontend', 'onPostDispatchFrontend');
    }

    public function onActionInit(Enlight_Event_EventArgs $args)
    {
        $subject = $args->getSubject();
        $view = $subject->View();
        $engine = $view->Engine();
        if (!isset($engine->smarty->registered_plugins['function']['sri'])) {
            $engine->registerPlugin('function', 'sri', array(get_class($this), 'smartyFunctionSri'));
        }
    }

    public function onPostDispatchFrontend(Enlight_Event_EventArgs $args)
    {
        $subject = $args->getSubject();
        $view = $subject->View();
        $view->addTemplateDir($this->Path() . 'Views');
    }

    public static function getAbsolutePath($relativePath)
    {
        return '/' . trim(Shopware()->DocPath(), '/') . '/' . trim($relativePath, '/');
    }

    public static function smartyFunctionSri($params, $smarty)
    {
        $algorithm = 'sha384';

        if (empty($params['file'])) {
            throw new Exception('assign: missing \'file\' parameter');
        }

        $filepath = self::getAbsolutePath($params['file']);

        $fileContents = file_get_contents($filepath);;
        if (!$fileContents) {
            throw new Exception('fs: file \'' . $filepath . '\' not found');
        }

        if (!empty($params['algorithm'])) {
            $algorithm = $params['algorithm'];
        }

        $hash = hash($algorithm, $fileContents, true);
        $sri = $algorithm . '-' . base64_encode($hash);

        if (!empty($params['assign'])) {
            $smarty->assign($params['assign'], $sri);
            return;
        }

        return $sri;
    }
}