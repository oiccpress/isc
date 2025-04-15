<?php

namespace APP\plugins\importexport\isc\grid;

use APP\controllers\grid\issues\ExportableIssuesListGridHandler;
use APP\facades\Repo;
use PKP\controllers\grid\feature\selectableItems\SelectableItemsFeature;
use PKP\controllers\grid\GridColumn;

class ISCExportableIssuesListGridHandler extends ExportableIssuesListGridHandler {

    public $plugin;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
        parent::__construct();
    }

    /**
     * @copydoc GridHandler::loadData()
     */
    protected function loadData($request, $filter)
    {
        $journal = $request->getJournal();

        // Handle grid paging (deprecated style)

        $rangeInfo = $this->getGridRangeInfo($request, $this->getId());
        $collector = Repo::issue()->getCollector()
            ->filterByContextIds([$journal->getId()]);

        $totalCount = $collector->getCount();
        return new \PKP\core\VirtualArrayIterator($collector->getMany()->toArray(), $totalCount, 1, $totalCount);
    }

    /**
     * @copydoc GridHandler::initFeatures()
     */
    public function initFeatures($request, $args)
    {
        return [new SelectableItemsFeature()];
    }

    public function initialize($request, $args = null)
    {
     
        parent::initialize($request, $args);

        $issueGridCellProvider = new IssueGridCellProvider($this->plugin);

        // Issue identification
        $this->addColumn(
            new GridColumn(
                'iscStatus',
                'plugins.importexport.isc.iscStatus',
                null,
                null,
                $issueGridCellProvider
            )
        );
        
    }

}