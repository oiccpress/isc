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
		<li><a href="#xmlSetup-tab">{translate key="plugins.importexport.isc.export.xmlSetup"}</a></li>
	</ul>
	<div id="xmlSetup-tab">
		<form id="xmlSetupForm" class="pkp_form" action="{plugin_url path="xmlSettings"}" method="post">

			{csrf}

			{fbvFormSection title="plugins.importexport.isc.export.xmlSetup"}
				{fbvElement type="text" name="isc_username" id="isc_username" label="plugins.importexport.isc.export.xmlUsername" value=$isc_username size=$fbvStyles.size.SMALL}
				{fbvElement type="text" name="isc_password" id="isc_password" label="plugins.importexport.isc.export.xmlPassword" value=$isc_password size=$fbvStyles.size.SMALL}
			{/fbvFormSection}

			{fbvFormButtons hideCancel=true submitText="common.save"}
		</form>
	</div>
	<div id="exportIssues-tab">
		<script>
			$(function() {ldelim}
				// Attach the form handler.
				let form = $('#exportIssuesXmlForm').pkpHandler('$.pkp.controllers.form.FormHandler');
				form.find('button[type=submit]').click(function () {
					form.trigger('unregisterAllForms');
				});

				let form2 = $('#xmlSetupForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
				form2.find('button[type=submit]').click(function () {
					form2.trigger('unregisterAllForms');
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
					{fbvElement type="submit" label="plugins.importexport.isc.export.text" id="debugIssues" name="type" value="view" inline=true}
					<input type="button" value="{translate key="plugins.importexport.isc.export.toggleSelection"|escape}" class="pkp_button" onclick="toggleIssues()" />
				{/fbvFormSection}
			{/fbvFormArea}
		</form>
	</div>
</div>

{/block}
