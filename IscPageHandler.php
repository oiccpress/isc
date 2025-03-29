<?php

namespace APP\plugins\importexport\isc;

use APP\plugins\importexport\isc\ISCExportPlugin;
use APP\template\TemplateManager;
use PKP\controllers\page\PageHandler;

class IscPageHandler extends PageHandler
{
    public ISCExportPlugin $plugin;

    public function __construct(ISCExportPlugin $plugin)
    {
        parent::__construct();

        $this->plugin = $plugin;
    }

    public function index($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);

        return $templateMgr->display(
            $this->plugin->getTemplateResource(
                'example.tpl'
            )
        );
    }
}
