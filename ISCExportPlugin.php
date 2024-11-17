<?php

/**
 * @file plugins/importexport/isc/ISCExportPlugin.php
 *
 * Copyright (c) 2024 Invisible Dragon Ltd
 *
 * @class ISCExportPlugin
 *
 * @brief ISC export plugin
 */

namespace APP\plugins\importexport\isc;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\PubObjectsExportPlugin;
use APP\template\TemplateManager;
use PKP\core\PKPPageRouter;
use PKP\db\DAORegistry;
use PKP\filter\FilterDAO;
use PKP\notification\PKPNotification;
use PKP\plugins\ImportExportPlugin;

class ISCExportPlugin extends ImportExportPlugin
{
    /** @var Context the current context */
    private $_context;

    private $_file_parts = [];

    /**
     * @copydoc ImportExportPlugin::display()
     */
    public function display($args, $request)
    {
        $this->_context = $request->getContext();

        parent::display($args, $request);
        $templateManager = TemplateManager::getManager();
        $templateManager->assign([
            'pluginName' => self::class,
            'ftpLibraryMissing' => !class_exists('\League\Flysystem\Ftp\FtpAdapter')
        ]);

        switch ($route = array_shift($args)) {
            case 'settings':
                return $this->manage($args, $request);
            case 'export':
                $issueIds = $request->getUserVar('selectedIssues') ?? [];
                if (!count($issueIds)) {
                    $templateManager->assign('iscErrorMessage', __('plugins.importexport.isc.export.failure.noIssueSelected'));
                    break;
                }
                try {
                    // create zip file
                    $file = $this->_createFile($issueIds);
                    if ($request->getUserVar('type') == 'view') {
                        header('content-type: text/plain; charset=UTF-8');
                        echo $file;
                        exit();
                    }
                    $this->_download($file);
                } catch (\Exception $e) {
                    $templateManager->assign('iscErrorMessage', $e->getMessage());
                }
                break;
        }

        // set the issn and abbreviation template variables
        foreach (['onlineIssn', 'printIssn'] as $name) {
            if ($value = $this->_context->getSetting($name)) {
                $templateManager->assign('issn', $value);
                break;
            }
        }

        if ($value = $this->_context->getLocalizedSetting('abbreviation')) {
            $templateManager->assign('abbreviation', $value);
        }

        $templateManager->display($this->getTemplateResource('index.tpl'));
    }

    /**
     * Generates a filename for the exported file
     */
    private function _createFilename(): string
    {
        return $this->_context->getLocalizedSetting('acronym') . '_' . implode('_', $this->_file_parts) . '_isc_' . date('Y-m-d-H-i-s') . '.xml';
    }

    /**
     * Downloads a zip file with the selected issues
     *
     * @param string $path the path of the zip file
     */
    private function _download(string $path): void
    {
        header('content-type: text/xml');
        header('content-disposition: attachment; filename=' . $this->_createFilename());
        echo $path;
        exit(0);
    }
    

    /**
     * Creates a zip file with the given issues
     *
     * @return string the content of the file
     */
    private function _createFile(array $issueIds): string
    {
        $application = Application::get();
        $dispatcher = $application->getDispatcher();
        $request = Application::get()->getRequest();

        // start with header
        $output = [ '<?xml version="1.0" encoding="utf-8"?>', '<XML>' ];

        foreach ($issueIds as $issueId) {
            if (!($issue = Repo::issue()->get($issueId, $this->_context->getId()))) {
                throw new \Exception(__('plugins.importexport.isc.export.failure.loadingIssue', ['issueId' => $issueId]));
            }

            $this->_file_parts[] = 'Volume' . $issue->getVolume();
            $this->_file_parts[] = 'Issue' . $issue->getNumber();

            $output[] = '<JOURNAL>';
            $output[] = '<YEAR>' . $issue->getYear() . '</YEAR>';
            $output[] = '<VOL>' . $issue->getVolume() . '</VOL>';
            $output[] = '<NO>' . $issue->getNumber() . '</NO>';
            $output[] = '<MOSALSAL>0</MOSALSAL>';
            $output[] = '<PAGE_NO>0</PAGE_NO>';
            $output[] = '<ARTICLES>';

            // add submission XML
            $submissionCollector = Repo::submission()->getCollector();
            $submissions = $submissionCollector
                ->filterByContextIds([$this->_context->getId()])
                ->filterByIssueIds([$issueId])
                ->orderBy($submissionCollector::ORDERBY_SEQUENCE, $submissionCollector::ORDER_DIR_ASC)
                ->getMany();
            foreach ($submissions as $submission) {

                $publication = $submission->getCurrentPublication();
                
                $output[] = '<ARTICLE>';

                // Article basic details
                $output[] = '<LANGUAGE_ID>1</LANGUAGE_ID><TitleF>-</TitleF>';
                $output[] = '<TitleE>' . htmlspecialchars( $publication->getLocalizedFullTitle(null, 'html'), ENT_XML1, 'utf-8' ) . '</TitleE>';
                $output[] = '<URL>' . $request->url($this->_context->getPath(), 'article', 'view', [$submission->getId()]) . '</URL>';
                $output[] = '<DOI>' . $publication->getStoredPubId('doi') . '</DOI>';
                $output[] = '<DOR>' . $publication->getData('pub-id::other::dor') . '</DOR>';

                // Abstract
                $output[] = '<ABSTRACTS><ABSTRACT><LANGUAGE_ID>1</LANGUAGE_ID>';
                $abstract = $publication->getLocalizedData('abstract');
                $output[] = '<CONTENT>' . htmlspecialchars( strip_tags( $abstract ), ENT_XML1, 'utf-8' ) . '</CONTENT>';
				$output[] = '</ABSTRACT><ABSTRACT><LANGUAGE_ID>0</LANGUAGE_ID><CONTENT>-</CONTENT></ABSTRACT></ABSTRACTS>';

                // Pages
                $pages = $publication->getData('pages');
                if($pages) {
                    $pages = explode("-", $pages);
                    $output[] = '<PAGES><PAGE><FPAGE>' . trim($pages[0]) . '</FPAGE><TPAGE>' . trim($pages[1]) . '</TPAGE></PAGE></PAGES>';
                }

                $output[] = '<AUTHORS>';
                $authors = $publication->getData('authors');
                foreach($authors as $author) {
                    $element = [
                        '<AUTHOR>',
                        '<Name>-</Name>',
						'<MidName></MidName>',	
						'<Family>-</Family>',
						'<NameE>' . htmlspecialchars($author->getGivenName('en'), ENT_XML1, 'utf-8') . '</NameE>',
						'<MidNameE></MidNameE>',	
						'<FamilyE>' . htmlspecialchars($author->getFamilyName('en'), ENT_XML1, 'utf-8') . '</FamilyE>',
                        '<Organizations>',
						'<Organization>' . htmlspecialchars($author->getLocalizedAffiliation(), ENT_XML1, 'utf-8') . '</Organization>',
						'</Organizations>',
						'<Countries>',
						'<Country>' . htmlspecialchars($author->getCountryLocalized(), ENT_XML1, 'utf-8') . '</Country>',
						'</Countries>',
						'<EMAILS>',
						'<Email>' . htmlspecialchars($author->getEmail(), ENT_XML1, 'utf-8') . '</Email>',			
						'</EMAILS>',
                        '</AUTHOR>',
                    ];
                    $output[] = ' ' . implode("\n ", $element);
                }
                $output[] = '</AUTHORS>';

                $output[] = '<KEYWORDS>';
                $keywords = $publication->getLocalizedData('keywords');
                foreach($keywords as $keyword) {
                    $output[] = ' <KEYWORD><KeyText>' . htmlspecialchars($keyword, ENT_XML1, 'utf-8') . '</KeyText></KEYWORD>';
                }
                $output[] = '</KEYWORDS>';

                $output[] = '<REFRENCES><REFRENCE>';
                $citationDao = DAORegistry::getDAO('CitationDAO'); /** @var CitationDAO $citationDao */
                $parsedCitations = $citationDao->getByPublicationId($publication->getId());
                if($parsedCitations) {
                    $output[] = '<REF>' . htmlspecialchars( implode('#', $parsedCitations->toArray()), ENT_XML1, 'utf-8') . '</REF>';
                } else {
                    $output[] = '<REF>' . htmlspecialchars(str_replace("\n", "#", $publication->getData('citationsRaw')), ENT_XML1, 'utf-8') . '</REF>';
                }
                $output[] = '</REFRENCES></REFRENCE>';

                $output[] = '</ARTICLE>';

            }

            $output[] = '</ARTICLES>';
        }

        $output[] = '</XML>';

        return implode("\n", $output);
    }

    /**
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request)
    {
        return parent::manage($args, $request);
    }

    /**
     * @copydoc ImportExportPlugin::executeCLI()
     */
    public function executeCLI($scriptName, &$args)
    {
    }

    /**
     * @copydoc ImportExportPlugin::usage()
     */
    public function usage($scriptName)
    {
    }

    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null)
    {
        $isRegistered = parent::register($category, $path, $mainContextId);
        $this->addLocaleData();
        return $isRegistered;
    }

    /**
     * @copydoc Plugin::getName()
     */
    public function getName()
    {
        return 'isc';
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName()
    {
        return __('plugins.importexport.isc.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription()
    {
        return __('plugins.importexport.isc.description.short');
    }

}
