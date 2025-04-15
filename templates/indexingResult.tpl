<div class="indexing-result">

    {if empty($indexingResult)}

        <p><span class="error">{translate key="plugins.importexport.isc.export.xmlEmptyStatus"}</strong></p>

    {else}

        <table class="table">

            <thead>
                <tr>
                    <th>{translate key="plugins.importexport.isc.export.pubYear"}</th>
                    <th>{translate key="plugins.importexport.isc.export.volume"}</th>
                    <th>{translate key="plugins.importexport.isc.export.issue"}</th>
                    <th>{translate key="plugins.importexport.isc.export.comment"}</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$indexingResult item=$item}
                <tr>
                    <td>{$item['PUBYEAR']}</td>
                    <td>{$item['VOLUME']}</td>
                    <td>{$item['ISSUE']}</td>
                    <td>{$item['GB_COMMENT']}</td>
                </tr>
                {/foreach}
            </tbody>

        </table>

    {/if}

</div>