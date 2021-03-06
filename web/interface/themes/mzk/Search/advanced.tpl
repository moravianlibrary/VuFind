<div id="bd">
  <form method="GET" action="{$url}/Search/Results" id="advSearchForm" name="searchForm" class="search">
    <div id="yui-main" class="content">
      <div class="yui-b first contentbox">
        <b class="btop"><b></b></b>
          <div class="advSearchContent">
            <div class="resulthead"><h3>{translate text='Advanced Search'}</h3></div>
            <div class="page">

              {if $editErr}
              {assign var=error value="advSearchError_$editErr"}
              <div class="error">{translate text=$error}</div>
              {/if}

              <div id="groupJoin" class="searchGroups">
                <div class="searchGroupDetails">
                  {translate text="search_match"} : 
                  <select name="join">
                    <option value="AND">{translate text="group_AND"}</option>
                    <option value="OR"{if $searchDetails}{if $searchDetails.0.join == 'OR'} selected="selected"{/if}{/if}>{translate text="group_OR"}</option>
                  </select>
                </div>
                <strong>{translate text="search_groups"}</strong>:
              </div>

              {* An empty div. This is the target for the javascript that builds this screen *}
              <div id="searchHolder"></div>

              <a href="#" class="add" onclick="addGroup(); return false;">{translate text="add_search_group"}</a>
              <br /><br />
              <input class="form-submit" type="submit" name="submit" value="{translate text="Find"}"><br><br>
             
			<div class="advanced-options">
			 {if $facetLists}
              <h3>{translate text='Limit To'}</h3><br>
              {foreach from=$facetLists item="facetList" key="label"}
              <table class="citation" width="100%" summary="{translate text='Limit To'}">
                <tr>
                {foreach from=$facetList item="list" key="label"}
                  <th width="{$columnWidth}%" align="right">{translate text=$label}: </th>
                {/foreach}
                </tr>
                <tr>
                {foreach from=$facetList item="list" key="label" name="facetList"}
                  <td>
                    <select name="filter[]" id="{$label|replace:' ':''}Filter" multiple="multiple" size="10">
                      {foreach from=$list item="value" key="display"}
                        {if $value.filter}
                          <option value="{$value.filter|escape}"{if $value.selected} selected="selected"{/if}>{$display|escape}</option>
                        {elseif !$smarty.foreach.facetList.last}
                          <option disabled="true">==================</option>
                        {/if}
                      {/foreach}
                    </select>
                  </td>
                {/foreach}
                </tr>
                <tr>
                {foreach from=$facetList item="list" key="label"}
                  <td>
                    <input type="button" class="form-submit" onclick="$('#{$label|replace:' ':''}Filter').val('');" value="{$label|cat:'_select_all'|translate}"></input>
                  </td>
                {/foreach}
                </tr>
              </table>
              {/foreach}
              {/if}
              {if $illustratedLimit}
              <table class="table-illustrated" summary="{translate text='Illustrated'}">
                <tr>
                  <th align="right">{translate text="Illustrated"}: </th>
                  <td>
                    {foreach from=$illustratedLimit item="current"}
                      <input type="radio" name="illustration" value="{$current.value|escape}"{if $current.selected} checked="checked"{/if}> {translate text=$current.text}<br>
                    {/foreach}
                  </td>
                </tr>
              </table>
              {/if}
              {if $limitList|@count gt 1}
                <div id="advSearchLimit">
                  <label for="limit">{translate text='Results per page'}:</label>
                  <select id="limit" name="limit">
                    {foreach from=$limitList item=limitData key=limitLabel}
                      {* If a previous limit was used, make that the default; otherwise, use the "default default" *}
                      {if $lastLimit}
                        <option value="{$limitData.desc|escape}"{if $limitData.desc == $lastLimit} selected="selected"{/if}>{$limitData.desc|escape}</option>
                      {else}
                        <option value="{$limitData.desc|escape}"{if $limitData.selected} selected="selected"{/if}>{$limitData.desc|escape}</option>
                      {/if}
                    {/foreach}
                  </select>
                </div>
              {/if}
              {if $lastSort}<input type="hidden" name="sort" value="{$lastSort|escape}" />{/if}
              {if $dateRangeLimit}
              {* Load the publication date slider UI widget *}
              {js filename="yui/slider-min.js"}
              {js filename="pubdate_slider.js"}
              <br/>
              <table class="table-release" summary="{translate text='adv_search_year'}">
                <tr>
                  <th valign="top" align="right">{translate text="adv_search_year"}:&nbsp;</th>
                  <td>
                    <input type="hidden" name="daterange[]" id="publishDateFacet" value="publishDateFacet"/>
                    <label for="publishDatefrom" class='yearboxlabel'>{translate text='date_from'}:</label>
                    <input class="text" type="text" size="4" maxlength="4" class="yearbox" name="publishDateFacetfrom" id="publishDatefrom" value="{$dateRangeLimit.0|escape}" />
                    <label for="publishDateto" class='yearboxlabel'>{translate text='date_to'}:</label>
                    <input class="text" type="text" size="4" maxlength="4" class="yearbox" name="publishDateFacetto" id="publishDateto" value="{$dateRangeLimit.1|escape}" />
                    <div id="publishDateSlider" class="yui-h-slider dateSlider" title="{translate text='Range slider'}" style="display:none;">
                      <div id="publishDateslider_min_thumb" class="yui-slider-thumb"><img src="{$path}/images/yui/left-thumb.png"></div>
                      <div id="publishDateslider_max_thumb" class="yui-slider-thumb"><img src="{$path}/images/yui/right-thumb.png"></div>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td>
                    <input type="button" class="form-submit" onclick="(function() {ldelim} $('#publishDatefrom').val(''); $('#publishDateto').val(''); {rdelim}());" value="{translate text='adv_search_year_no_limits'}"></input>
                  </td>
                </tr>
              </table>
              </div>
			  
              {/if}
              <input class="form-submit" type="submit" name="submit" value="{translate text="Find"}"><br>
            </div>
          </div>
        <b class="bbot"><b></b></b>
    </div>
  </div>

  <div class="yui-b">
  {if $searchFilters}
    <div class="filterList">
      <h3>{translate text="adv_search_filters"}<br/><span>({translate text="adv_search_select_all"} <input type="checkbox" checked="checked" onclick="filterAll(this);" />)</span></h3>
      {foreach from=$searchFilters item=data key=field}
      <div>
        <h4>{translate text=$field}</h4>
        <ul>
          {foreach from=$data item=value}
          <li><input type="checkbox" checked="checked" name="filter[]" value='{$value.field|escape}:"{$value.value|escape}"' /> {$value.display|escape}</li>
          {/foreach}
        </ul>
      </div>
      {/foreach}
    </div>
  {/if}
    <div class="sidegroup">
      <h4>{translate text="Search Tips"}</h4>
      <a href="{$url}/Help/Home?topic=search" onClick="window.open('{$url}/Help/Home?topic=advsearch', 'Help', 'width=625, height=510'); return false;">{translate text="Help with Advanced Search"}</a><br />
      <a href="{$url}/Help/Home?topic=search" onClick="window.open('{$url}/Help/Home?topic=search', 'Help', 'width=625, height=510'); return false;">{translate text="Help with Search Operators"}</a>
    </div>
  </div>
  </form>
</div>

{* Step 1: Define our search arrays so they are usuable in the javascript *}
<script language="JavaScript" type="text/javascript">
    var searchFields = new Array();
    {foreach from=$advSearchTypes item=searchDesc key=searchVal}
    searchFields["{$searchVal}"] = "{translate text=$searchDesc}";
    {/foreach}
    var searchJoins = new Array();
    searchJoins["AND"]  = "{translate text="search_AND"}";
    searchJoins["OR"]   = "{translate text="search_OR"}";
    searchJoins["NOT"]  = "{translate text="search_NOT"}";
    var addSearchString = "{translate text="add_search"}";
    var searchLabel     = "{translate text="adv_search_label"}";
    var searchFieldLabel = "{translate text="in"}";
    var deleteSearchGroupString = "{translate text="del_search"}";
    var searchMatch     = "{translate text="search_match"}";
    var searchFormId    = 'advSearchForm';
</script>
{* Step 2: Call the javascript to make use of the above *}
{js filename="advanced.js"}
{* Step 3: Build the page *}
<script language="JavaScript" type="text/javascript">
  {if $searchDetails}
    {foreach from=$searchDetails item=searchGroup}
      {foreach from=$searchGroup.group item=search name=groupLoop}
        {if $smarty.foreach.groupLoop.iteration == 1}
    var new_group = addGroup('{$search.lookfor|escape:"javascript"}', '{$search.field|escape:"javascript"}', '{$search.bool}');
        {else}
    addSearch(new_group, '{$search.lookfor|escape:"javascript"}', '{$search.field|escape:"javascript"}');
        {/if}
      {/foreach}
    {/foreach}
  {else}
    var new_group = addGroup('', 'Title');
    addSearch(new_group, '', 'Author');
    addSearch(new_group, '', 'year');
  {/if}
</script>