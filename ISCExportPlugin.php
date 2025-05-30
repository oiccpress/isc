<?php

/**
 * @file plugins/importexport/isc/ISCExportPlugin.php
 *
 * Copyright (c) 2025 Invisible Dragon Ltd
 *
 * @class ISCExportPlugin
 *
 * @brief ISC export plugin
 */

namespace APP\plugins\importexport\isc;

use APP\core\Application;
use APP\core\Services;
use APP\facades\Repo;
use APP\issue\IssueGalleyDAO;
use APP\file\IssueFileManager;
use APP\file\PublicFileManager;
use APP\notification\NotificationManager;
use APP\plugins\importexport\isc\grid\ISCExportableIssuesListGridHandler;
use APP\template\TemplateManager;
use PKP\core\JSONMessage;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\plugins\ImportExportPlugin;
use ZipArchive;

class ISCExportPlugin extends ImportExportPlugin
{
    /** @var Context the current context */
    private $_context;

    private $_file_parts = [];

    private $_warnings = [];

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
            case 'fetchGrid':
                // This gets OJS to force a grid to be displayed when it's only
                // in the strange way export plugins are loaded on-demand only
                $handler = new ISCExportableIssuesListGridHandler($this);
                $handler->setDispatcher( $request->getDispatcher() );
                $handler->initialize($request, $args);
                return $handler->fetchGrid($args, $request);
                break;
            case 'xmlStatus':
                // Fetch XML status of an issue from the API and render it
                // for frontend to display nicely in a table
                $client = new IscService($this, $this->_context);
                $issue = Repo::issue()->get($_GET['issueId'], $this->_context->getId());
                if(!$issue) {
                    return new JSONMessage(false, 'Cannot get issue!');
                }
                $status = $client->getIndexingResult( $issue->getYear(), $issue->getVolume(), $issue->getNumber() );
                $templateManager->assign('indexingResult', $status);
                return $templateManager->fetchJson($this->getTemplateResource('indexingResult.tpl'));
                break;
            case 'xmlSettings':
                $this->updateSetting( $this->_context->getId(), 'isc_username', @$_POST['isc_username'] );
                $this->updateSetting( $this->_context->getId(), 'isc_password', @$_POST['isc_password'] );
                $this->updateSetting( $this->_context->getId(), 'isc_journalTitle', @$_POST['isc_journalTitle'] );
                $this->updateSetting( $this->_context->getId(), 'isc_issn', @$_POST['isc_issn'] );
                $notificationManager = new NotificationManager();
                $user = $request->getUser();
                $notificationManager->createTrivialNotification($user->getId());
                return new JSONMessage(true);
                break;
            case 'settings':
                return $this->manage($args, $request);
            case 'export':
                $issueIds = $request->getUserVar('selectedIssues') ?? [];
                if (!count($issueIds)) {
                    $templateManager->assign('iscErrorMessage', __('plugins.importexport.isc.export.failure.noIssueSelected'));
                    break;
                }

                if ($request->getUserVar('type') == 'sendToISC') {
                    foreach($issueIds as $issueId) {
                        $this->_createZip($request, $issueId);
                    }
                    $templateManager->assign('iscSuccessMessage', __('plugins.importexport.isc.export.sentToIsc'));
                } else {
                    try {
                        $file = $this->_createFile($issueIds);
                        if(!empty($this->_warnings)) {
                            echo '<pre><p>Problems producing XML:</p>';
                            var_dump($this->_warnings);
                            echo '</pre>';
                            exit;
                        }
                        if ($request->getUserVar('type') == 'view') {
                            header('content-type: text/plain; charset=UTF-8');
                            echo $file;
                            exit();
                        }
                        $this->_download($file);
                    } catch (\Exception $e) {
                        $templateManager->assign('iscErrorMessage', $e->getMessage());
                    }
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

        foreach([ 'isc_username', 'isc_password', 'isc_issn', 'isc_journalTitle' ] as $setting) {
            $value = $this->getSetting($this->_context->getId(), $setting) ?: '';
            $templateManager->assign($setting, $value);
        }

        if ($value = $this->_context->getLocalizedSetting('abbreviation')) {
            $templateManager->assign('abbreviation', $value);
        }

        $templateManager->assign('soapAvailable', class_exists('SoapClient'));

        $templateManager->display($this->getTemplateResource('index.tpl'));
    }

    private function _createZip($request, $issueId) {

        $publicFileManager = new PublicFileManager();
        $filename = 'isc_' . $this->_context->getId() . '_' . $issueId . '.zip';
        $filepath = $publicFileManager->getContextFilesPath($this->_context->getId()) . '/' . $filename;
        $fileurl = $request->getBaseUrl() . '/' . $publicFileManager->getContextFilesPath($this->_context->getId()) . '/' . $filename;
        
        $zip = new ZipArchive();
        $zip->open( $filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE );

        // Add XML
        $file = $this->_createFile([ $issueId ]);
        $zip->addFromString( 'index.xml', $file );

        $i = 1;

        $submissionCollector = Repo::submission()->getCollector();
        $submissions = $submissionCollector
            ->filterByContextIds([$this->_context->getId()])
            ->filterByIssueIds([$issueId])
            ->orderBy($submissionCollector::ORDERBY_SEQUENCE, $submissionCollector::ORDER_DIR_ASC)
            ->getMany();
        foreach ($submissions as $article) {

            // add galleys
            $fileService = Services::get('file');
            foreach ($article->getGalleys() as $galley) {
                $submissionFile = Repo::submissionFile()->get($galley->getData('submissionFileId'));
                if (!$submissionFile) {
                    continue;
                }

                if($galley->getLabel() != 'PDF') continue;

                $filePath = $fileService->get($submissionFile->getData('fileId'))->path;
                if (!$zip->addFromString($i . '.pdf', $fileService->fs->read($filePath))) {
                    error_log("Unable add file {$filePath} to ISC ZIP");
                    throw new \Exception(__('plugins.importexport.isc.export.failure.creatingFile'));
                }
                $i += 1;
            }

        }

        $zip->close();

        // Send to ISC
        $issue = Repo::issue()->get($issueId);
        $client = new IscService($this, $this->_context);
        $client->submitIssue( $issue->getYear(), $issue->getVolume(), $issue->getNumber(), $i, $fileurl, $filename );
        
        // We use plugin setting storage as we can't add anything to the Journal
        // schema (as we're only loaded temporarily), so this workaround will need to do.
        $this->updateSetting( $this->_context->getId(), 'isc_submitted_' . $issue->getId(), 1 );

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

            $output[] = '<ISCJOURNAL>';
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
                    if(count($pages) === 1) {
                        $pages = [ $pages, $pages ];
                    }
                    $output[] = '<PAGES><PAGE><FPAGE>' . trim($pages[0]) . '</FPAGE><TPAGE>' . trim($pages[1]) . '</TPAGE></PAGE></PAGES>';
                } else {
                    $output[] = '<PAGES><PAGE><FPAGE></FPAGE><TPAGE></TPAGE></PAGE></PAGES>';
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
                    ];

                    $orgs = explode(" AND ", $author->getLocalizedAffiliation());
                    foreach($orgs as $org) {
                        $org = trim($org, '" ');
                        $element[] = '<Organization>' . htmlspecialchars($org, ENT_XML1, 'utf-8') . '</Organization>';
                    }

                    array_push($element,
						'</Organizations>',
						'<Countries>',
						'<Country>' . htmlspecialchars($author->getCountryLocalized(), ENT_XML1, 'utf-8') . '</Country>',
						'</Countries>',
						'<EMAILS>',
						'<Email>' . htmlspecialchars($author->getEmail(), ENT_XML1, 'utf-8') . '</Email>',			
						'</EMAILS>',
                        '</AUTHOR>',
                    );
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
                    $publicationsRaw = $parsedCitations->toArray();
                    $publicationsString = [];
                    foreach($publicationsRaw as $publication) {
                        if(is_string($publication)) {
                            $publicationsString[] = $publication;
                        } else {
                            $publicationsString[] = $publication->getRawCitation();
                        }
                    }
                    $output[] = '<REF>' . htmlspecialchars( implode('##', $publicationsString), ENT_XML1, 'utf-8') . '##</REF>';
                } else {
                    $publicationsRaw = $publication->getData('citationsRaw');
                    $publicationsString = [];
                    foreach($publicationsRaw as $publication) {
                        if(is_string($publication)) {
                            $publicationsString[] = $publication;
                        } else {
                            $publicationsString[] = $publication->getRawCitation();
                        }
                    }
                    $output[] = '<REF>' . htmlspecialchars(str_replace("\n", "##", $publicationsString), ENT_XML1, 'utf-8') . '##</REF>';
                }
                $output[] = '</REFRENCE></REFRENCES>';

                $output[] = '</ARTICLE>';

            }

            $output[] = '</ARTICLES></ISCJOURNAL>';
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
