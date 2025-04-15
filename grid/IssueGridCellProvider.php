<?php

namespace APP\plugins\importexport\isc\grid;

use PKP\controllers\grid\GridCellProvider;
use PKP\controllers\grid\GridHandler;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use APP\issue\Issue;

class IssueGridCellProvider extends GridCellProvider
{

    public $plugin;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
        parent::__construct();
    }

    /**
     * Get cell actions associated with this row/column combination
     *
     * @param \PKP\controllers\grid\GridRow $row
     * @param GridColumn $column
     *
     * @return array an array of LinkAction instances
     */
    public function getCellActions($request, $row, $column, $position = GridHandler::GRID_ACTION_POSITION_DEFAULT)
    {
        if ($column->getId() == 'iscStatus') {
            $issue = $row->getData();
            assert(is_a($issue, 'Issue'));
            $router = $request->getRouter();
            return [
                new LinkAction(
                    'fetchStatus',
                    new AjaxModal(
                        $router->url($request, null, null, 'importexport', array('plugin', 'isc', 'xmlStatus'), ['issueId' => $issue->getId()]),
                        __('plugins.importexport.isc.iscStatus'),
                        'modal_edit',
                        true
                    ),
                    __('plugins.importexport.isc.fetchIscStatus')
                )
            ];
        }
        return [];
    }

    /**
     * Extracts variables for a given column from a data element
     * so that they may be assigned to template before rendering.
     *
     * @param \PKP\controllers\grid\GridRow $row
     * @param GridColumn $column
     *
     * @return array
     */
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        $issue = $row->getData(); /** @var Issue $issue */
        $columnId = $column->getId();
        assert(is_a($issue, 'Issue'));
        assert(!empty($columnId));

        switch ($columnId) {
            case 'iscStatus':
                // We use plugin setting storage as we can't add anything to the Journal
                // schema (as we're only loaded temporarily), so this workaround will need to do.
                $status = $this->plugin->getSetting( $issue->getJournalId(), 'isc_submitted_' . $issue->getId() );
                return ['label' => $status ? __('plugins.importexport.isc.export.submitted') : __('plugins.importexport.isc.export.notSubmitted')];
            default: assert(false);
                break;
        }
    }
}
