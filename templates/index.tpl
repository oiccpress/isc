{**
 * templates/index.tpl
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * List of operations this plugin can perform
 *
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<script>
	// Attach the JS file tab handler.
	$(function() {ldelim}
		$('#exportTabs')
			.pkpHandler('$.pkp.controllers.TabHandler')
			.tabs('option', 'cache', true);
	{rdelim});
</script>

<div id="exportTabs">
	<ul>
		<li{if $porticoErrorMessage || $porticoSuccessMessage} class="ui-tabs-active"{/if}><a href="#exportIssues-tab">{translate key="plugins.importexport.isc.export.issues"}</a></li>
	</ul>
	<div id="exportIssues-tab">
		<script>
			$(function() {ldelim}
				// Attach the form handler.
				var form = $('#exportIssuesXmlForm').pkpHandler('$.pkp.controllers.form.FormHandler');
				form.find('button[type=submit]').click(function () {
					form.trigger('unregisterAllForms');
				});
			{rdelim});
			{literal}
			function toggleIssues() {
				var elements = document.querySelectorAll("#exportIssuesXmlForm input[type=checkbox]");
				for (var i = elements.length; i--; ) {
						elements[i].checked ^= true;
				}
			}
			{/literal}
		</script>
		<form id="exportIssuesXmlForm" class="pkp_form" action="{plugin_url path="export"}" method="post">
			{csrf}
			{fbvFormArea id="issuesXmlForm"}
				{if $iscErrorMessage}
					<p><span class="error">{$iscErrorMessage|escape}</strong></p>
				{/if}
				{if $iscSuccessMessage}
					<p><span class="pkp_form_success">{$iscSuccessMessage|escape}</strong></p>
				{/if}

				{capture assign=issuesListGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.issues.ExportableIssuesListGridHandler" op="fetchGrid" escape=false}{/capture}
				{load_url_in_div id="issuesListGridContainer" url=$issuesListGridUrl}

				{fbvFormSection}
					{fbvElement type="submit" label="plugins.importexport.native.exportIssues" id="exportIssues" name="type" value="download" inline=true}
					<input type="button" value="{translate key="plugins.importexport.portico.export.toggleSelection"|escape}" class="pkp_button" onclick="toggleIssues()" />
				{/fbvFormSection}
			{/fbvFormArea}
		</form>
	</div>
</div>

{/block}