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

        // Removed grid paging and just display all of them in 1 page -- this is as
        // paging URLs won't be displayed correctly

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
        // Removed grid paging and just display all of them in 1 page -- this is as
        // paging URLs won't be displayed correctly
        return [new SelectableItemsFeature()];
    }

    public function initialize($request, $args = null)
    {
     
        parent::initialize($request, $args);

        // Add in our new grid item for displaying status
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